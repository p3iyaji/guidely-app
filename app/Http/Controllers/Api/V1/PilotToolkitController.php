<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PilotToolkitController extends Controller
{
    public function importTemplate(): BinaryFileResponse
    {
        $this->authorize('view-pilot-toolkit');

        $path = resource_path('pilot/import-template-placeholder.csv');

        if (! is_file($path)) {
            throw new NotFoundHttpException;
        }

        return response()->download(
            $path,
            'guidely-import-template-placeholder.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function disclaimers(): JsonResponse
    {
        $this->authorize('view-pilot-toolkit');

        return response()->json([
            'data' => [
                'title' => 'Pilot disclaimer pack',
                'items' => [
                    [
                        'id' => 'data-minimisation',
                        'heading' => 'Data minimisation',
                        'body' => 'Only collect and process pupil and staff data that is necessary for Pilot SEND documentation workflows. Do not upload full Evidence bodies into success-metrics exports.',
                    ],
                    [
                        'id' => 'purpose-limitation',
                        'heading' => 'Purpose limitation',
                        'body' => 'Pilot data may be used only to evaluate GuidelyEdu for your Tenant. Secondary marketing or product-analytics use outside the agreed Pilot scope is not permitted.',
                    ],
                    [
                        'id' => 'uk-residency',
                        'heading' => 'UK residency',
                        'body' => 'Primary database, object storage, and backups for this Pilot must remain in the United Kingdom or a UK-adequate jurisdiction.',
                    ],
                    [
                        'id' => 'import-template',
                        'heading' => 'Import Template',
                        'body' => 'SENCO and Tenant Admin can download the Import Template and upload Pupil CSV via Import (partial success by row). This is not a live MIS Connector.',
                    ],
                ],
            ],
        ]);
    }

    public function successMetrics(): JsonResponse
    {
        $this->authorize('view-pilot-toolkit');

        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'exported_at' => now()->toIso8601String(),
                'metrics' => [
                    [
                        'key' => 'time_to_prepare_review_cycle_hours',
                        'label' => 'Time to prepare Review Cycle (hours)',
                        'value' => null,
                        'note' => 'Placeholder until Pilot kickoff columns are agreed.',
                    ],
                    [
                        'key' => 'staff_accounts_provisioned',
                        'label' => 'Staff accounts provisioned',
                        'value' => null,
                        'note' => 'Placeholder column for Pilot kickoff.',
                    ],
                    [
                        'key' => 'schools_onboarded',
                        'label' => 'Schools onboarded',
                        'value' => null,
                        'note' => 'Placeholder column for Pilot kickoff.',
                    ],
                ],
            ],
        ]);
    }
}
