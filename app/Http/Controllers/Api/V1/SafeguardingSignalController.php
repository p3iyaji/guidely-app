<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Domain\Pupils\SafeguardingSeverity;
use App\Domain\Pupils\SafeguardingSignal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertSafeguardingSignalRequest;
use App\Http\Resources\Api\V1\SafeguardingSignalResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SafeguardingSignalController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    /**
     * List current minimised context signals for Pupils in accessible active Schools.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SafeguardingSignal::class);

        /** @var User $user */
        $user = $request->user();

        $query = SafeguardingSignal::query()
            ->with('pupil:id,school_id,given_name,family_name')
            ->whereHas('pupil', function ($pupils) use ($user): void {
                $pupils->whereHas('school', function ($schools): void {
                    $schools->active();
                });

                if ($user->seesAllTenantSchools()) {
                    return;
                }

                $pupils->whereIn('school_id', $user->schools()->allRelatedIds());
            })
            ->orderBy('id');

        return SafeguardingSignalResource::collection($query->get());
    }

    /**
     * Create or replace the current context signal for a Pupil.
     */
    public function upsert(UpsertSafeguardingSignalRequest $request, Pupil $pupil): JsonResponse
    {
        $present = (bool) $request->validated('present');
        $severity = $present
            ? SafeguardingSeverity::from((string) $request->validated('severity'))
            : null;

        $attributes = [
            'tenant_id' => $pupil->tenant_id,
            'pupil_id' => $pupil->id,
            'present' => $present,
            'severity' => $severity,
        ];

        $signal = SafeguardingSignal::query()->where('pupil_id', $pupil->id)->first()
            ?? new SafeguardingSignal;

        try {
            $signal->forceFill($attributes)->save();
        } catch (UniqueConstraintViolationException) {
            $signal = SafeguardingSignal::query()->where('pupil_id', $pupil->id)->firstOrFail();
            $signal->forceFill($attributes)->save();
        }

        $signal->load('pupil:id,school_id,given_name,family_name');

        $this->audit->record(
            AuditEventType::SafeguardingSignalUpserted,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: [
                'present' => $present,
                'severity' => $severity?->value,
            ],
        );

        return (new SafeguardingSignalResource($signal))
            ->response()
            ->setStatusCode(200);
    }
}
