<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Ontology\RuleLibraryVersion;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DeterminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minimal Determination citation stub — Ontology + Rule Library version immutability (stories 4.4–4.5).
 * SRE evaluator write path is story 4.6.
 *
 * Citation FKs (`ontology_version_id`, `rule_library_version_id`) are intentionally not fillable;
 * set them via factory / forceFill so casual mass-assignment cannot rewrite history.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class Determination extends Model
{
    /** @use HasFactory<DeterminationFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): DeterminationFactory
    {
        return DeterminationFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function ontologyVersion(): BelongsTo
    {
        return $this->belongsTo(OntologyVersion::class);
    }

    public function ruleLibraryVersion(): BelongsTo
    {
        return $this->belongsTo(RuleLibraryVersion::class);
    }
}
