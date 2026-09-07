<?php

namespace App\Domain\Ontology;

/**
 * Single published Pilot Ontology version that owns all FR-23 domains (+ Setting).
 */
final class PilotOntology
{
    public const VERSION_CODE = 'pilot-ontology-stub-v1';

    public const VERSION_LABEL = 'Pilot Ontology stub v1';

    /**
     * Legacy per-domain stub codes retired by story 4.4 — consolidated onto {@see VERSION_CODE}.
     *
     * @var list<string>
     */
    public const LEGACY_VERSION_CODES = [
        'pilot-need-stub-v1',
        'pilot-setting-stub-v1',
        'pilot-provision-stub-v1',
    ];

    /**
     * Ensure the single published Pilot OntologyVersion exists and return it.
     */
    public static function ensurePublishedVersion(): OntologyVersion
    {
        $version = OntologyVersion::query()->firstOrCreate(
            ['code' => self::VERSION_CODE],
            [
                'label' => self::VERSION_LABEL,
                'status' => OntologyVersionStatus::Published,
                'published_at' => now(),
            ],
        );

        if ($version->status !== OntologyVersionStatus::Published) {
            $version->forceFill([
                'status' => OntologyVersionStatus::Published,
                'published_at' => $version->published_at ?? now(),
            ])->save();
        }

        if ($version->label !== self::VERSION_LABEL) {
            $version->forceFill(['label' => self::VERSION_LABEL])->save();
        }

        return $version;
    }
}
