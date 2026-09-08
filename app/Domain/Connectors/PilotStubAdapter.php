<?php

namespace App\Domain\Connectors;

use App\Domain\Tenancy\School;

class PilotStubAdapter implements ConnectorAdapter
{
    public function type(): ConnectorType
    {
        return ConnectorType::PilotStub;
    }

    /**
     * @param  list<array<string, mixed>>  $inboundPupils
     * @return list<array<string, mixed>>
     */
    public function pull(Connector $connector, School $school, array $inboundPupils): array
    {
        return $inboundPupils;
    }
}
