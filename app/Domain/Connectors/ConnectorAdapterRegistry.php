<?php

namespace App\Domain\Connectors;

use InvalidArgumentException;

class ConnectorAdapterRegistry
{
    /**
     * @var array<string, ConnectorAdapter>
     */
    private array $adapters = [];

    /**
     * @param  iterable<ConnectorAdapter>  $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $type = $adapter->type()->value;

            if (isset($this->adapters[$type])) {
                throw new InvalidArgumentException("Duplicate Connector adapter for type [{$type}].");
            }

            $this->adapters[$type] = $adapter;
        }
    }

    public function for(ConnectorType $type): ConnectorAdapter
    {
        $adapter = $this->adapters[$type->value] ?? null;

        if ($adapter === null) {
            throw new UnsupportedConnectorTypeException($type);
        }

        return $adapter;
    }
}
