<?php

namespace App\Domain\Identity;

use RuntimeException;

/**
 * Raised when an IdP subject (external_id) is already linked to a different User.
 */
class ExternalIdConflictException extends RuntimeException
{
    public const CODE = 'external_id_conflict';

    public function __construct(string $message = 'This external_id is already linked to another User.')
    {
        parent::__construct($message);
    }
}
