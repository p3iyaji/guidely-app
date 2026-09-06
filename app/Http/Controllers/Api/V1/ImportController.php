<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Connectors\Import\PupilCsvImporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportPupilsRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImportController extends Controller
{
    public function __construct(private PupilCsvImporter $importer) {}

    public function template(): BinaryFileResponse
    {
        $this->authorize('import-pupils');

        $path = resource_path('pilot/import-template-placeholder.csv');

        if (! is_file($path)) {
            throw new NotFoundHttpException;
        }

        return response()->download(
            $path,
            'guidely-import-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function uploadPupils(ImportPupilsRequest $request): JsonResponse
    {
        $result = $this->importer->import(
            $request->file('file'),
            $request->user(),
            $request,
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
