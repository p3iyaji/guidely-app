<?php

namespace App\Domain\Connectors;

/**
 * Drops payload keys that are not opted-in for a School.
 *
 * 6.2 sync must call this; 6.1 proves the filter without a sync job.
 */
class FilterConnectorPayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(string $schoolId, array $payload): array
    {
        $connector = Connector::query()->first();

        if ($connector === null || $connector->enabled !== true) {
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
