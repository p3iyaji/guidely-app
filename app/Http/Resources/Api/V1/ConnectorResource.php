<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Connectors\Connector;
use App\Domain\Connectors\ConnectorType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Connector
 */
class ConnectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof ConnectorType
            ? $this->type->value
            : ConnectorType::PilotStub->value;

        return [
            'id' => $this->id,
            'type' => $type,
            'enabled' => (bool) $this->enabled,
            'has_secret' => $this->resource instanceof Connector
                ? $this->resource->hasSecret()
                : false,
            'field_shares' => $this->resource instanceof Connector
                ? $this->resource->normalizedFieldShares()
                : [],
        ];
    }
}
