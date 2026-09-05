<?php

namespace App\Domain\Audit;

use RuntimeException;

/**
 * Thrown when product code attempts to update or delete an append-only Audit Event.
 */
class AuditEventImmutableException extends RuntimeException
{
    public static function cannotUpdate(): self
    {
        return new self('Audit events are append-only and cannot be updated.');
    }

    public static function cannotDelete(): self
    {
        return new self('Audit events are append-only and cannot be deleted.');
    }
}
