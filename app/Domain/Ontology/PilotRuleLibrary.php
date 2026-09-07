<?php

namespace App\Domain\Ontology;

use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Single published Pilot Rule Library version bundling FR-30 + FR-31 coverage.
 *
 * Partial / Pilot stub — never claims full SEND coverage. Uncovered remains valid.
 */
final class PilotRuleLibrary
{
    public const VERSION_CODE = 'pilot-rule-library-stub-v1';

    public const VERSION_LABEL = 'Pilot Rule Library stub v1 (partial coverage)';

    /**
     * Ensure the single published Pilot RuleLibraryVersion exists and return it.
     */
    public static function ensurePublishedVersion(): RuleLibraryVersion
    {
        try {
            $version = RuleLibraryVersion::query()->firstOrCreate(
                ['code' => self::VERSION_CODE],
                [
                    'label' => self::VERSION_LABEL,
                    'status' => RuleLibraryVersionStatus::Published,
                    'published_at' => now(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $version = RuleLibraryVersion::query()
                ->where('code', self::VERSION_CODE)
                ->firstOrFail();
        }

        if ($version->status !== RuleLibraryVersionStatus::Published) {
            $version->forceFill([
                'status' => RuleLibraryVersionStatus::Published,
                'published_at' => $version->published_at ?? now(),
            ])->save();
        }

        if ($version->label !== self::VERSION_LABEL) {
            $version->forceFill(['label' => self::VERSION_LABEL])->save();
        }

        return $version->fresh() ?? $version;
    }
}
