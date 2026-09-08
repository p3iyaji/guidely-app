<?php

namespace App\Domain\Connectors;

enum ConnectorField: string
{
    case GivenName = 'given_name';
    case FamilyName = 'family_name';
    case MisKey = 'mis_key';
    case DateOfBirth = 'date_of_birth';
    case YearGroup = 'year_group';
    case SendStatus = 'send_status';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::GivenName => 'Given name',
            self::FamilyName => 'Family name',
            self::MisKey => 'MIS key',
            self::DateOfBirth => 'Date of birth',
            self::YearGroup => 'Year group',
            self::SendStatus => 'SEND status',
        };
    }

    /**
     * Opt-in defaults — every allowlisted field starts unshared.
     *
     * @return array<string, bool>
     */
    public static function defaultMap(): array
    {
        return array_fill_keys(self::values(), false);
    }

    /**
     * Laravel's boolean rule accepts the string "0"; `(bool) "0"` is true in PHP.
     */
    public static function isShared(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
    }
}
