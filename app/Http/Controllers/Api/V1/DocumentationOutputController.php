<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Outputs\BuildDocumentationOutput;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentationOutputRequest;
use App\Http\Resources\Api\V1\DocumentationOutputResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentationOutputController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private BuildDocumentationOutput $build,
    ) {}

    /**
     * List Documentation Outputs for Pupils in the user's accessible Schools.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DocumentationOutput::class);

        /** @var User $user */
        $user = $request->user();

        $query = DocumentationOutput::query()
            ->with(['pupil', 'confirmer', 'reviewCycle'])
            ->whereHas('pupil', function ($pupils) use ($user): void {
                if ($user->seesAllTenantSchools()) {
                    return;
                }

                $pupils->whereIn('school_id', $user->schools()->allRelatedIds());
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return DocumentationOutputResource::collection($query->get());
    }

    public function show(DocumentationOutput $documentationOutput): DocumentationOutputResource
    {
        $this->authorize('view', $documentationOutput);

        $documentationOutput->load(['pupil', 'confirmer', 'reviewCycle']);

        return new DocumentationOutputResource($documentationOutput);
    }

    public function store(
        StoreDocumentationOutputRequest $request,
        ?Pupil $pupil = null,
        ?ReviewCycle $reviewCycle = null,
    ): JsonResponse {
        $resolvedPupil = $pupil instanceof Pupil ? $pupil : $request->pupil();
        $resolvedCycle = $reviewCycle instanceof ReviewCycle ? $reviewCycle : $request->reviewCycle();

        if ($resolvedPupil === null) {
            throw ValidationException::withMessages([
                'pupil_id' => 'A Pupil is required.',
            ]);
        }

        if ($resolvedCycle === null) {
            throw ValidationException::withMessages([
                'review_cycle_id' => 'A Review Cycle is required.',
            ]);
        }

        $this->authorize('create', [DocumentationOutput::class, $resolvedPupil]);

        if ($resolvedCycle->pupil_id !== $resolvedPupil->id) {
            throw ValidationException::withMessages([
                'review_cycle_id' => 'The Review Cycle must belong to the selected Pupil.',
            ]);
        }

        $output = DB::transaction(function () use ($request, $resolvedPupil, $resolvedCycle): DocumentationOutput {
            ReviewCycle::query()
                ->whereKey($resolvedCycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nextVersion = (int) DocumentationOutput::query()
                ->where('pupil_id', $resolvedPupil->id)
                ->where('review_cycle_id', $resolvedCycle->id)
                ->where('type', $request->type())
                ->lockForUpdate()
                ->max('version');

            $confirmedAt = now();
            $output = new DocumentationOutput([
                'tenant_id' => $resolvedPupil->tenant_id,
                'pupil_id' => $resolvedPupil->id,
            ]);
            $output->forceFill([
                'review_cycle_id' => $resolvedCycle->id,
                'type' => $request->type(),
                'version' => $nextVersion + 1,
                'confirmer_user_id' => $request->user()?->id,
                'disclaimer_text' => DocumentationOutput::DISCLAIMER_TEXT,
                'confirmed_at' => $confirmedAt,
                'pack_ready_at' => $confirmedAt,
                'payload' => $this->build->handle($resolvedPupil, $resolvedCycle, $request->type()),
            ])->save();

            $this->audit->record(
                AuditEventType::DocumentationOutputGenerated,
                $request,
                $request->user(),
                resourceType: 'documentation_output',
                resourceId: $output->id,
                metadata: $this->outputAuditMetadata($output, $resolvedPupil->id, $resolvedCycle->id),
            );

            return $output;
        });

        $output->load(['pupil', 'confirmer', 'reviewCycle']);

        return (new DocumentationOutputResource($output))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function outputAuditMetadata(
        DocumentationOutput $output,
        string $pupilId,
        string $reviewCycleId,
    ): array {
        return [
            'type' => $output->type instanceof DocumentationOutputType
                ? $output->type->value
                : (string) $output->type,
            'version' => $output->version,
            'pupil_id' => $pupilId,
            'review_cycle_id' => $reviewCycleId,
            'confirmer_user_id' => $output->confirmer_user_id,
        ];
    }
}
