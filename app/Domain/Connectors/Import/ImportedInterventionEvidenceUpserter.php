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
use App\Domain\Tenancy\School;
use App\Domain\Tenancy\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Validate and upsert Pilot Intervention Evidence rows from Import Template columns.
 */
class ImportedInterventionEvidenceUpserter
{
    /**
     * Evidence columns accepted for Pilot Intervention import.
     *
     * @var list<string>
     */
    public const EVIDENCE_FIELD_HEADERS = [
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
    public const FREE_TEXT_PROVISION_HEADERS = [
        'evidence_provision',
        'evidence_provision_label',
    ];

    public function __construct(
        private AuditWriter $audit,
        private ImportSchoolResolver $schools,
    ) {}

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $evidenceHeaders
     * @return array{action?: string, error?: string}
     */
    public function process(
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

        $tenant = Tenant::query()->find($pupil->tenant_id);

        if ($tenant === null) {
            return ['error' => 'Pupil Tenant could not be resolved for Evidence Provision mapping.'];
        }

        $provision = ProvisionTerm::query()
            ->forTenant($tenant)
            ->where('code', Str::upper($provisionCode))
            ->first();

        if ($provision === null) {
            $provision = ProvisionTerm::query()
                ->forTenant($tenant)
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
            $result = $this->upsert(
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
     * @param  array<string, string|null>  $row
     * @return array{pupil?: Pupil, error?: string}
     */
    public function resolveExistingPupil(array $row, User $user): array
    {
        $school = $this->schools->resolve($row, $user);

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
     * @return array{action?: string, error?: string}
     */
    private function upsert(
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

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = Str::of($value)->trim()->toString();

        return $trimmed === '' ? null : $trimmed;
    }
}
