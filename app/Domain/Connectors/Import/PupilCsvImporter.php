<?php

namespace App\Domain\Connectors\Import;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SendStatus;
use App\Domain\Sre\EnqueueSreReevaluation;
use App\Domain\Tenancy\School;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Orchestrate Import Template CSV upserts for Pupils (and Pilot Intervention Evidence).
 */
class PupilCsvImporter
{
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

    public function __construct(
        private AuditWriter $audit,
        private ImportCsvParser $parser,
        private ImportSchoolResolver $schools,
        private ImportedInterventionEvidenceUpserter $evidence,
        private EnqueueSreReevaluation $enqueueSreReevaluation,
    ) {}

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
        return $this->parser->parse($file);
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
        $associative = $this->parser->associateRow($headers, $cells);
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
                $resolved = $this->evidence->resolveExistingPupil($associative, $user);

                if (isset($resolved['error'])) {
                    return ['error' => $resolved['error']];
                }

                $pupil = $resolved['pupil'];
            }

            $evidenceResult = $this->evidence->process(
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
            $this->enqueueSreReevaluation->handle($pupil, $sreReason, 'import');
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

        $school = $this->schools->resolve($associative, $user);

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
}
