<?php

namespace App\Domain\Connectors\Import;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Evidence\EvidenceLifecycle;
use App\Domain\Evidence\EvidenceRecord;
use App\Domain\Evidence\EvidenceSource;
use App\Domain\Evidence\EvidenceType;
use App\Domain\Ontology\ProvisionTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Tenancy\CurrentTenant;
use App\Domain\Tenancy\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Parse and upsert Pupils (and Pilot Intervention Evidence) from an Import Template CSV
 * with partial-success reporting.
 */
class PupilCsvImporter
{
    /**
     * Evidence columns accepted for Pilot Intervention import.
     *
     * @var list<string>
     */
    private const EVIDENCE_FIELD_HEADERS = [
        'evidence_type',
        'evidence_provision_code',
        'evidence_occurred_at',
        'evidence_date',
        'evidence_external_id',
        'evidence_body',
        'evidence_notes',
        'evidence_provision',
        'evidence_provision_label',
    ];

    /**
     * Free-text Provision columns — values are always rejected (Ontology code required).
     *
     * @var list<string>
     */
    private const FREE_TEXT_PROVISION_HEADERS = [
        'evidence_provision',
        'evidence_provision_label',
    ];

    /**
     * Headers that indicate Evidence import intent (including unsupported aliases).
     *
     * @var list<string>
     */
    private const EVIDENCE_HEADER_MARKERS = [
        'evidence',
        'evidence_body',
        'evidence_date',
        'evidence_type',
        'evidence_notes',
        'historical_evidence',
        'evidence_provision_code',
        'evidence_occurred_at',
        'evidence_external_id',
        'evidence_provision',
        'evidence_provision_label',
    ];

    /**
     * @var list<string>
     */
    private const KNOWN_HEADERS = [
        'pupil_identifier',
        'mis_key',
        'given_name',
        'family_name',
        'date_of_birth',
        'school_name',
        'school_id',
        'year_group',
        'sen_status',
        'send_status',
        'notes',
    ];

    /**
     * Pupil attribute columns that imply a Pupil upsert (not Evidence-only lookup).
     *
     * @var list<string>
     */
    private const PUPIL_UPSERT_SIGNAL_HEADERS = [
        'given_name',
        'family_name',
        'date_of_birth',
        'year_group',
        'sen_status',
        'send_status',
        'notes',
    ];

    /**
     * Optional fields that must not be wiped on upsert when the CSV cell is blank.
     *
     * @var list<string>
     */
    private const PRESERVE_ON_BLANK_UPDATE = [
        'date_of_birth',
        'notes',
    ];

    public function __construct(private AuditWriter $audit) {}

    /**
     * @return array{
     *     committed: list<array{row: int, action: string, pupil: Pupil}>,
     *     errors: list<array{row: int, message: string}>,
     *     summary: array{committed_count: int, error_count: int}
     * }
     */
    public function import(UploadedFile $file, User $user, Request $request): array
    {
        $parsed = $this->parseCsv($file);

        $committed = [];
        $errors = [];

        foreach ($parsed['rows'] as $rowNumber => $cells) {
            try {
                $result = $this->processRow(
                    $rowNumber,
                    $cells,
                    $parsed['headers'],
                    $parsed['evidence_headers'],
                    $user,
                    $request,
                );
            } catch (Throwable) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Import failed unexpectedly.',
                ];

                continue;
            }

            if (isset($result['committed'])) {
                $committed[] = $result['committed'];
            }

            if (isset($result['error'])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $result['error'],
                ];
            }

            foreach ($result['errors'] ?? [] as $message) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $message,
                ];
            }
        }

        return [
            'committed' => $committed,
            'errors' => $errors,
            'summary' => [
                'committed_count' => count($committed),
                'error_count' => count($errors),
            ],
        ];
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     evidence_headers: list<string>,
     *     rows: array<int, list<string|null>>
     * }
     */
    public function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        try {
            $lineNumber = 0;
            $headerRow = fgetcsv($handle);
            $lineNumber++;

            if ($headerRow === false || $headerRow === [null] || $this->rowIsEmpty($headerRow)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty or missing a header row.',
                ]);
            }

            if ($this->isSepPreamble($headerRow)) {
                $headerRow = fgetcsv($handle);
                $lineNumber++;

                if ($headerRow === false || $headerRow === [null] || $this->rowIsEmpty($headerRow)) {
                    throw ValidationException::withMessages([
                        'file' => 'The CSV file is empty or missing a header row.',
                    ]);
                }
            }

            $headers = array_map(
                fn ($header): string => $this->normalizeHeaderCell((string) $header),
                $headerRow,
            );

            if ($headers === [] || $this->rowIsEmpty($headers)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty or missing a header row.',
                ]);
            }

            $nonEmptyHeaders = array_values(array_filter(
                $headers,
                fn (string $header): bool => $header !== '',
            ));

            if (count($nonEmptyHeaders) !== count(array_unique($nonEmptyHeaders))) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file has duplicate column headers.',
                ]);
            }

            $evidenceHeaders = array_values(array_filter(
                $headers,
                fn (string $header): bool => $this->isEvidenceHeader($header),
            ));

            $rows = [];
            $rowNumber = $lineNumber;

            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->rowIsEmpty($cells)) {
                    continue;
                }

                $rows[$rowNumber] = $cells;
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file has no data rows.',
                ]);
            }

            return [
                'headers' => $headers,
                'evidence_headers' => $evidenceHeaders,
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $evidenceHeaders
     * @param  list<string|null>  $cells
     * @return array{committed?: array<string, mixed>, error?: string, errors?: list<string>}
     */
    private function processRow(
        int $rowNumber,
        array $cells,
        array $headers,
        array $evidenceHeaders,
        User $user,
        Request $request,
    ): array {
        $associative = $this->associateRow($headers, $cells);
        $hasEvidenceValues = $evidenceHeaders !== []
            && $this->rowHasEvidenceValues($associative, $evidenceHeaders);
        $hasPupilUpsertFields = $this->rowHasPupilUpsertFields($associative);

        $committed = null;
        $pupil = null;
        $rowErrors = [];
        $needsSre = false;
        $sreReason = 'import';

        if ($hasPupilUpsertFields || ! $hasEvidenceValues) {
            $pupilResult = $this->processPupilUpsert($rowNumber, $associative, $user, $request);

            if (isset($pupilResult['error'])) {
                return ['error' => $pupilResult['error']];
            }

            $committed = $pupilResult['committed'];
            $pupil = $committed['pupil'];
            $needsSre = true;
            $sreReason = 'import';
        }

        if ($hasEvidenceValues) {
            if ($pupil === null) {
                $resolved = $this->resolveExistingPupilForEvidence($associative, $user);

                if (isset($resolved['error'])) {
                    return ['error' => $resolved['error']];
                }

                $pupil = $resolved['pupil'];
            }

            $evidenceResult = $this->processEvidenceIntervention(
                $associative,
                $evidenceHeaders,
                $pupil,
                $user,
                $request,
            );

            if (isset($evidenceResult['error'])) {
                $rowErrors[] = $evidenceResult['error'];
            } else {
                $needsSre = true;
                $sreReason = 'evidence_imported';

                if ($committed === null) {
                    $committed = [
                        'row' => $rowNumber,
                        'action' => $evidenceResult['action'],
                        'evidence_action' => $evidenceResult['action'],
                        'pupil' => $pupil,
                    ];
                } else {
                    $committed['evidence_action'] = $evidenceResult['action'];
                }
            }
        }

        if ($needsSre && $pupil !== null) {
            $this->enqueueSreReevaluation($pupil, $sreReason);
        }

        $result = [];

        if ($committed !== null) {
            $result['committed'] = $committed;
        }

        if ($rowErrors !== []) {
            $result['errors'] = $rowErrors;
        }

        if ($result === []) {
            return ['error' => 'Import failed unexpectedly.'];
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $associative
     * @return array{committed?: array<string, mixed>, error?: string}
     */
    private function processPupilUpsert(
        int $rowNumber,
        array $associative,
        User $user,
        Request $request,
    ): array {
        $payload = $this->mapRowPayload($associative);

        if (isset($payload['error'])) {
            return ['error' => $payload['error']];
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $payload['attributes'];

        $school = $this->resolveSchool($associative, $user);

        if (isset($school['error'])) {
            return ['error' => $school['error']];
        }

        /** @var School $resolvedSchool */
        $resolvedSchool = $school['school'];
        $attributes['school_id'] = $resolvedSchool->id;

        $validator = Validator::make($attributes, $this->rowRules(), [
            'mis_key.max' => 'MIS key must not exceed 255 characters.',
            'given_name.required' => 'Given name is required.',
            'family_name.required' => 'Family name is required.',
            'year_group.required' => 'Year group is required.',
            'send_status.required' => 'SEND status is required.',
            'send_status.in' => 'SEND status must be SEN Support, EHCP, or neither.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.before' => 'Date of birth must be before today.',
        ]);

        if ($validator->fails()) {
            return ['error' => $validator->errors()->first()];
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        try {
            $committed = $this->upsertPupil($validated, $user, $request, $rowNumber);
        } catch (UniqueConstraintViolationException) {
            return ['error' => 'A Pupil with this MIS key already exists in this School.'];
        }

        if (isset($committed['error'])) {
            return ['error' => $committed['error']];
        }

        return ['committed' => $committed];
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $evidenceHeaders
     * @return array{action?: string, error?: string}
     */
    private function processEvidenceIntervention(
        array $row,
        array $evidenceHeaders,
        Pupil $pupil,
        User $user,
        Request $request,
    ): array {
        foreach ($evidenceHeaders as $header) {
            if (($row[$header] ?? null) === null) {
                continue;
            }

            if (in_array($header, self::FREE_TEXT_PROVISION_HEADERS, true)) {
                return ['error' => 'Provision must use an Ontology term code, not a free-text label.'];
            }

            if (! in_array($header, self::EVIDENCE_FIELD_HEADERS, true)) {
                return ['error' => 'Unsupported Evidence column: '.$header.'.'];
            }
        }

        $typeRaw = $this->nullableTrimmed($row['evidence_type'] ?? null);
        $type = $typeRaw === null
            ? EvidenceType::Intervention->value
            : Str::of($typeRaw)->lower()->replace(['-', ' '], '_')->toString();

        if ($type === '') {
            $type = EvidenceType::Intervention->value;
        }

        if ($type !== EvidenceType::Intervention->value) {
            return ['error' => 'Only Intervention Evidence import is supported in Pilot.'];
        }

        $provisionCode = $this->nullableTrimmed($row['evidence_provision_code'] ?? null);
        $occurredAt = $this->nullableTrimmed($row['evidence_occurred_at'] ?? $row['evidence_date'] ?? null);
        $externalId = $this->nullableTrimmed($row['evidence_external_id'] ?? null);
        $body = $this->nullableTrimmed($row['evidence_body'] ?? $row['evidence_notes'] ?? null);

        if ($provisionCode === null) {
            return ['error' => 'Evidence Provision code is required.'];
        }

        if ($occurredAt === null) {
            return ['error' => 'Evidence occurred_at is required.'];
        }

        $provision = ProvisionTerm::query()
            ->fromPublishedStub()
            ->where('code', Str::upper($provisionCode))
            ->first();

        if ($provision === null) {
            $provision = ProvisionTerm::query()
                ->fromPublishedStub()
                ->where('code', $provisionCode)
                ->first();
        }

        if ($provision === null) {
            return ['error' => 'Evidence Provision code must match an active published Ontology term.'];
        }

        $validator = Validator::make([
            'occurred_at' => $occurredAt,
            'external_id' => $externalId,
            'body' => $body,
        ], [
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ], [
            'occurred_at.required' => 'Evidence occurred_at is required.',
            'occurred_at.date' => 'Evidence occurred_at must be a valid date.',
            'occurred_at.before_or_equal' => 'Evidence occurred_at cannot be in the future.',
        ]);

        if ($validator->fails()) {
            return ['error' => $validator->errors()->first()];
        }

        /** @var array{occurred_at: string, external_id?: ?string, body?: ?string} $validated */
        $validated = $validator->validated();
        $resolvedExternalId = $this->nullableTrimmed($validated['external_id'] ?? null);
        $resolvedBody = $this->nullableTrimmed($validated['body'] ?? null);

        try {
            $result = $this->upsertImportedIntervention(
                $pupil,
                $user,
                $request,
                $provision,
                $validated['occurred_at'],
                $resolvedExternalId,
                $resolvedBody,
            );
        } catch (UniqueConstraintViolationException) {
            return ['error' => 'An Evidence Record with this external_id already exists in your organisation.'];
        } catch (Throwable) {
            return ['error' => 'Evidence import failed unexpectedly.'];
        }

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        return ['action' => $result['action']];
    }

    /**
     * @return array{action?: string, error?: string}
     */
    private function upsertImportedIntervention(
        Pupil $pupil,
        User $user,
        Request $request,
        ProvisionTerm $provision,
        string $occurredAt,
        ?string $externalId,
        ?string $body,
    ): array {
        return DB::transaction(function () use ($pupil, $user, $request, $provision, $occurredAt, $externalId, $body): array {
            $existing = null;

            if (is_string($externalId) && $externalId !== '') {
                $existing = EvidenceRecord::query()
                    ->where('tenant_id', $pupil->tenant_id)
                    ->where('external_id', $externalId)
                    ->first();
            }

            if ($existing !== null && $existing->pupil_id !== $pupil->id) {
                return ['error' => 'Evidence external_id already belongs to a different Pupil.'];
            }

            if ($existing !== null) {
                $existing->fill([
                    'author_id' => $user->id,
                    'occurred_at' => $occurredAt,
                    'provision_term_id' => $provision->id,
                    'body' => $body,
                    'source' => EvidenceSource::Import,
                    'external_id' => $externalId,
                ]);
                $existing->forceFill([
                    'type' => EvidenceType::Intervention,
                    'lifecycle' => EvidenceLifecycle::Submitted,
                ])->save();

                $existing->load(['provisionTerm', 'pupil']);

                $this->audit->record(
                    AuditEventType::EvidenceInterventionUpdated,
                    $request,
                    $user,
                    resourceType: 'evidence_record',
                    resourceId: $existing->id,
                    metadata: [
                        'source' => EvidenceSource::Import->value,
                        'client_type' => 'import',
                        'pupil_id' => $existing->pupil_id,
                        'type' => $existing->type->value,
                        'lifecycle' => $existing->lifecycle->value,
                        'provision_term_id' => $existing->provision_term_id,
                        'provision_term_code' => $provision->code,
                        'external_id' => $existing->external_id,
                        'occurred_at' => $existing->occurred_at?->utc()->toIso8601String(),
                    ],
                );

                return ['action' => 'evidence_updated'];
            }

            $record = new EvidenceRecord([
                'pupil_id' => $pupil->id,
                'author_id' => $user->id,
                'occurred_at' => $occurredAt,
                'provision_term_id' => $provision->id,
                'body' => $body,
                'source' => EvidenceSource::Import,
                'external_id' => $externalId,
            ]);
            $record->forceFill([
                'type' => EvidenceType::Intervention,
                'lifecycle' => EvidenceLifecycle::Submitted,
            ])->save();

            $record->load(['provisionTerm', 'pupil']);

            $this->audit->record(
                AuditEventType::EvidenceInterventionCreated,
                $request,
                $user,
                resourceType: 'evidence_record',
                resourceId: $record->id,
                metadata: [
                    'source' => EvidenceSource::Import->value,
                    'client_type' => 'import',
                    'pupil_id' => $record->pupil_id,
                    'type' => $record->type->value,
                    'lifecycle' => $record->lifecycle->value,
                    'provision_term_id' => $record->provision_term_id,
                    'provision_term_code' => $provision->code,
                    'external_id' => $record->external_id,
                    'occurred_at' => $record->occurred_at?->utc()->toIso8601String(),
                ],
            );

            return ['action' => 'evidence_created'];
        });
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array{pupil?: Pupil, error?: string}
     */
    private function resolveExistingPupilForEvidence(array $row, User $user): array
    {
        $school = $this->resolveSchool($row, $user);

        if (isset($school['error'])) {
            return ['error' => $school['error']];
        }

        /** @var School $resolvedSchool */
        $resolvedSchool = $school['school'];
        $misKey = $row['pupil_identifier'] ?? $row['mis_key'] ?? null;

        if ($misKey === null || $misKey === '') {
            return ['error' => 'Pupil MIS key is required to import Evidence.'];
        }

        $pupil = Pupil::query()
            ->where('school_id', $resolvedSchool->id)
            ->where('mis_key', $misKey)
            ->first();

        if ($pupil === null) {
            return ['error' => 'No Pupil matches this MIS key in the selected School.'];
        }

        return ['pupil' => $pupil];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function upsertPupil(array $validated, User $user, Request $request, int $rowNumber): array
    {
        $misKey = $validated['mis_key'] ?? null;
        $schoolId = $validated['school_id'];
        $existing = null;

        if (is_string($misKey) && $misKey !== '') {
            $match = Pupil::withTrashed()
                ->where('school_id', $schoolId)
                ->where('mis_key', $misKey)
                ->first();

            if ($match !== null && $match->trashed()) {
                return ['error' => 'A left Pupil already uses this MIS key in this School.'];
            }

            $existing = $match;
        }

        if ($existing !== null) {
            $existing->update($this->payloadForUpdate($validated));
            $existing->refresh()->load(['primaryNeedTerm', 'secondaryNeedTerm']);

            $this->audit->record(
                AuditEventType::PupilUpdated,
                $request,
                $user,
                resourceType: 'pupil',
                resourceId: $existing->id,
                metadata: ['source' => 'import'],
            );

            return [
                'row' => $rowNumber,
                'action' => 'updated',
                'pupil' => $existing,
            ];
        }

        $pupil = Pupil::query()->create($validated);
        $pupil->load(['primaryNeedTerm', 'secondaryNeedTerm']);

        $this->audit->record(
            AuditEventType::PupilCreated,
            $request,
            $user,
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: ['source' => 'import'],
        );

        return [
            'row' => $rowNumber,
            'action' => 'created',
            'pupil' => $pupil,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadForUpdate(array $validated): array
    {
        foreach (self::PRESERVE_ON_BLANK_UPDATE as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($validated[$field] === null || $validated[$field] === '') {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    private function enqueueSreReevaluation(Pupil $pupil, string $reason): void
    {
        $jobClass = 'App\\Jobs\\SreReevaluatePupil';

        if (! class_exists($jobClass)) {
            return;
        }

        try {
            dispatch(new $jobClass($pupil->tenant_id, $pupil->id, $reason));
        } catch (Throwable $e) {
            Log::warning('import.sre_dispatch_failed', [
                'tenant_id' => $pupil->tenant_id,
                'pupil_id' => $pupil->id,
                'reason' => $reason,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = Str::of($value)->trim()->toString();

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $cells
     * @return array<string, string|null>
     */
    private function associateRow(array $headers, array $cells): array
    {
        $associative = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = $cells[$index] ?? null;
            $associative[$header] = is_string($value)
                ? Str::of($value)->trim()->toString()
                : null;

            if ($associative[$header] === '') {
                $associative[$header] = null;
            }
        }

        return $associative;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array{attributes?: array<string, mixed>, error?: string}
     */
    private function mapRowPayload(array $row): array
    {
        $sendStatusRaw = $row['send_status'] ?? $row['sen_status'] ?? null;
        $sendStatus = $this->normalizeSendStatus($sendStatusRaw);

        if ($sendStatusRaw !== null && $sendStatus === null) {
            return ['error' => 'SEND status must be SEN Support, EHCP, or neither.'];
        }

        $misKey = $row['pupil_identifier'] ?? $row['mis_key'] ?? null;

        return [
            'attributes' => [
                'given_name' => $row['given_name'] ?? null,
                'family_name' => $row['family_name'] ?? null,
                'mis_key' => $misKey,
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'year_group' => $row['year_group'] ?? null,
                'send_status' => $sendStatus,
                'notes' => $row['notes'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array{school?: School, error?: string}
     */
    private function resolveSchool(array $row, User $user): array
    {
        $tenantId = CurrentTenant::id();
        $schoolId = $row['school_id'] ?? null;
        $schoolName = $row['school_name'] ?? null;

        if ($schoolId !== null && $schoolName !== null) {
            return ['error' => 'Provide either school_id or school_name, not both.'];
        }

        if ($schoolId === null && $schoolName === null) {
            return ['error' => 'School is required (school_name or school_id).'];
        }

        if ($schoolId !== null) {
            $school = School::query()
                ->whereKey($schoolId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if ($school === null) {
                return ['error' => 'The selected School must be an active School in your organisation.'];
            }

            if (! $user->canAccessSchool($school)) {
                return ['error' => 'You do not have access to the selected School.'];
            }

            return ['school' => $school];
        }

        $matches = School::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('name', $schoolName)
            ->get();

        if ($matches->isEmpty()) {
            return ['error' => 'No active School matches this school_name.'];
        }

        if ($matches->count() > 1) {
            return ['error' => 'Multiple Schools match this school_name.'];
        }

        /** @var School $school */
        $school = $matches->first();

        if (! $user->canAccessSchool($school)) {
            return ['error' => 'You do not have access to the selected School.'];
        }

        return ['school' => $school];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowRules(): array
    {
        return [
            'school_id' => ['required', 'ulid'],
            'given_name' => ['required', 'string', 'max:255'],
            'family_name' => ['required', 'string', 'max:255'],
            'mis_key' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'year_group' => ['required', 'string', 'max:50'],
            'send_status' => ['required', 'string', Rule::in(SendStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function normalizeHeaderCell(string $header): string
    {
        $withoutBom = preg_replace('/^\xEF\xBB\xBF|\x{FEFF}/u', '', $header) ?? $header;

        return Str::of($withoutBom)->trim()->lower()->toString();
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function isSepPreamble(array $cells): bool
    {
        $first = isset($cells[0]) ? $this->normalizeHeaderCell((string) $cells[0]) : '';

        return Str::startsWith($first, 'sep=');
    }

    private function normalizeSendStatus(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        return match ($normalized) {
            'sen_support', 'sensupport', 'sen' => SendStatus::SenSupport->value,
            'ehcp' => SendStatus::Ehcp->value,
            'neither', 'none', 'n_a', 'na' => SendStatus::Neither->value,
            default => null,
        };
    }

    private function isEvidenceHeader(string $header): bool
    {
        if (in_array($header, self::KNOWN_HEADERS, true)) {
            return false;
        }

        if (in_array($header, self::EVIDENCE_HEADER_MARKERS, true)) {
            return true;
        }

        return Str::startsWith($header, 'evidence_') || Str::startsWith($header, 'evidence ');
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function rowHasPupilUpsertFields(array $row): bool
    {
        foreach (self::PUPIL_UPSERT_SIGNAL_HEADERS as $header) {
            if (($row[$header] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $evidenceHeaders
     */
    private function rowHasEvidenceValues(array $row, array $evidenceHeaders): bool
    {
        foreach ($evidenceHeaders as $header) {
            if (($row[$header] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (is_string($cell) && trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
