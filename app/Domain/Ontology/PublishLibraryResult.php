<?php

namespace App\Domain\Ontology;

final readonly class PublishLibraryResult
{
    public function __construct(
        public bool $ontologyPublished,
        public bool $ruleLibraryPublished,
        public bool $ontologyPinChanged,
        public bool $ruleLibraryPinChanged,
        public int $queuedCount,
    ) {}
}
