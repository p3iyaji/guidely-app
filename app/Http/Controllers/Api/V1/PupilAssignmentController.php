<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Pupils\Pupil;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyPupilAssignmentRequest;
use App\Http\Requests\Api\V1\StorePupilAssignmentRequest;
use App\Http\Resources\Api\V1\PupilResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response;

class PupilAssignmentController extends Controller
{
    public function __construct(private AuditWriter $audit) {}

    public function store(StorePupilAssignmentRequest $request, Pupil $pupil): PupilResource
    {
        $validated = $request->validated();

        /** @var User $assignee */
        $assignee = User::query()->findOrFail($validated['user_id']);

        $attributes = [];

        if (array_key_exists('class_label', $validated)) {
            $attributes['class_label'] = $validated['class_label'];
        }

        if (array_key_exists('cohort_label', $validated)) {
            $attributes['cohort_label'] = $validated['cohort_label'];
        }

        try {
            $pupil->assignTo($assignee, $attributes);
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert won the unique (pupil_id, user_id) race — merge as upsert.
            $pupil->assignTo($assignee, $attributes);
        }

        $pivot = $pupil->assignedUsers()->whereKey($assignee->id)->first()?->pivot;

        $this->audit->record(
            AuditEventType::PupilAssignmentUpdated,
            $request,
            $request->user(),
            resourceType: 'pupil',
            resourceId: $pupil->id,
            metadata: [
                'action' => 'assigned',
                'assigned_user_id' => $assignee->id,
                'class_label' => $pivot?->class_label,
                'cohort_label' => $pivot?->cohort_label,
                'source' => $pivot?->source,
            ],
        );

        $pupil->load(['primaryNeedTerm', 'secondaryNeedTerm']);

        return new PupilResource($pupil);
    }

    public function destroy(DestroyPupilAssignmentRequest $request, Pupil $pupil, User $user): Response
    {
        if ($pupil->isAssignedTo($user)) {
            $pupil->unassign($user);

            $this->audit->record(
                AuditEventType::PupilAssignmentUpdated,
                $request,
                $request->user(),
                resourceType: 'pupil',
                resourceId: $pupil->id,
                metadata: [
                    'action' => 'unassigned',
                    'assigned_user_id' => $user->id,
                ],
            );
        }

        return response()->noContent();
    }
}
