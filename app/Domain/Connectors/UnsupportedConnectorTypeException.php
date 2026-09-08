<?php

namespace App\Domain\Connectors;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UnsupportedConnectorTypeException extends RuntimeException
{
    public function __construct(public readonly ?ConnectorType $type = null)
    {
        $value = $type instanceof ConnectorType ? $type->value : 'unknown';

        parent::__construct("Connector type [{$value}] is not registered.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'type' => ['The selected type is invalid.'],
            ],
        ], 422);
    }
}
