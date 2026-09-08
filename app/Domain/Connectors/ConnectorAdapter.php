<?php

namespace App\Domain\Connectors;

use App\Domain\Tenancy\School;

interface ConnectorAdapter
{
    public function type(): ConnectorType;

    /**
     * @param  list<array<string, mixed>>  $inboundPupils
     * @return list<array<string, mixed>>
     */
    public function pull(Connector $connector, School $school, array $inboundPupils): array;
}
