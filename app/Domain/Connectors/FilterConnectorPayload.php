<?php

namespace App\Domain\Connectors;

/**
 * Drops payload keys that are not opted-in for a School.
 *
 * 6.2 sync must pass the Tenant Connector; callers must not rely on
 * Connector::query()->first() under HTTP Auth.
 */
class FilterConnectorPayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(Connector $connector, string $schoolId, array $payload): array
    {
        if ($connector->enabled !== true) {
            return [];
        }

        $shares = $connector->fieldSharesFor($schoolId);

        $filtered = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (($shares[$key] ?? false) === true) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
