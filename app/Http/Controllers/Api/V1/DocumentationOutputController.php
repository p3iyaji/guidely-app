<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Domain\Outputs\BuildDocumentationOutput;
use App\Domain\Outputs\DocumentationOutput;
use App\Domain\Outputs\DocumentationOutputType;
use App\Domain\Outputs\ExportDocumentationOutput;
use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\FeatureFlagKey;
use App\Domain\Tenancy\FeatureFlagResolver;
use App\Exceptions\FeatureNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentationOutputRequest;
use App\Http\Resources\Api\V1\DocumentationOutputResource;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentationOutputController extends Controller
{
    public function __construct(
        private AuditWriter $audit,
        private BuildDocumentationOutput $build,
        private ExportDocumentationOutput $export,
        private FeatureFlagResolver $flags,
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

        if ($request->type()->isAdvanced() && ! $this->flags->isEnabled(FeatureFlagKey::AdvancedDocumentationPacks)) {
            throw new FeatureNotAvailableException(FeatureFlagKey::AdvancedDocumentationPacks);
        }

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
                'purpose' => $request->type()->isAdvanced() ? $request->purpose() : null,
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

    public function download(Request $request, DocumentationOutput $documentationOutput): BinaryFileResponse
    {
        $this->authorize('view', $documentationOutput);

        if ($documentationOutput->confirmed_at === null) {
            abort(403);
        }

        $output = DB::transaction(function () use ($documentationOutput): DocumentationOutput {
            $locked = DocumentationOutput::query()
                ->whereKey($documentationOutput->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->storedBundleIsAvailable($locked)) {
                return $locked;
            }

            return $this->materialiseBundle($locked);
        });

        $tempPath = $this->writeDecryptedTempFile($output);

        $this->audit->record(
            AuditEventType::DocumentationOutputDownloaded,
            $request,
            $request->user(),
            resourceType: 'documentation_output',
            resourceId: $output->id,
            metadata: [
                'type' => $output->type instanceof DocumentationOutputType
                    ? $output->type->value
                    : (string) $output->type,
                'version' => $output->version,
                'pupil_id' => $output->pupil_id,
                'review_cycle_id' => $output->review_cycle_id,
                'checksum' => $output->checksum,
            ],
        );

        return $this->downloadResponse($output, $tempPath);
    }

    /**
     * @return array<string, bool|int|float|string|null>
     */
    private function outputAuditMetadata(
        DocumentationOutput $output,
        string $pupilId,
        string $reviewCycleId,
    ): array {
        $metadata = [
            'type' => $output->type instanceof DocumentationOutputType
                ? $output->type->value
                : (string) $output->type,
            'version' => $output->version,
            'pupil_id' => $pupilId,
            'review_cycle_id' => $reviewCycleId,
            'confirmer_user_id' => $output->confirmer_user_id,
        ];

        if (is_string($output->purpose) && $output->purpose !== '') {
            $metadata['purpose'] = $output->purpose;
        }

        return $metadata;
    }

    private function storedBundleIsAvailable(DocumentationOutput $output): bool
    {
        if (! filled($output->file_path) || ! filled($output->checksum)) {
            return false;
        }

        $disk = $this->bundleDisk($output);
        $path = (string) $output->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $encrypted = Storage::disk($disk)->get($path);

        return is_string($encrypted) && $encrypted !== '';
    }

    private function materialiseBundle(DocumentationOutput $output): DocumentationOutput
    {
        $bundle = $this->export->handle($output);
        $output->forceFill([
            'file_disk' => $bundle['file_disk'],
            'file_path' => $bundle['file_path'],
            'checksum' => $bundle['checksum'],
            'byte_size' => $bundle['byte_size'],
        ])->save();

        return $output;
    }

    private function writeDecryptedTempFile(DocumentationOutput $output): string
    {
        $disk = $this->bundleDisk($output);
        $path = (string) $output->file_path;
        $encrypted = Storage::disk($disk)->get($path);

        if (! is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('Documentation Output bundle is missing from encrypted storage.');
        }

        try {
            $plaintext = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            throw new RuntimeException('Unable to decrypt the Documentation Output bundle.');
        }

        if (hash('sha256', $plaintext) !== $output->checksum) {
            throw new RuntimeException('Documentation Output bundle checksum does not match.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'guidely-doc-dl-');

        if ($tempPath === false) {
            throw new RuntimeException('Unable to create a temporary file for the Documentation Output download.');
        }

        if (file_put_contents($tempPath, $plaintext) === false) {
            @unlink($tempPath);

            throw new RuntimeException('Unable to write the Documentation Output download.');
        }

        return $tempPath;
    }

    private function downloadResponse(DocumentationOutput $output, string $tempPath): BinaryFileResponse
    {
        $type = $output->type instanceof DocumentationOutputType
            ? $output->type->value
            : (string) $output->type;
        $filename = str_replace('_', '-', $type).'-v'.$output->version.'.zip';

        $response = response()->download(
            $tempPath,
            $filename,
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend(true);

        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->removeCacheControlDirective('public');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function bundleDisk(DocumentationOutput $output): string
    {
        return is_string($output->file_disk) && $output->file_disk !== ''
            ? $output->file_disk
            : 'local';
    }
}
