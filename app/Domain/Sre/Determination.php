<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\OntologyVersion;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\DeterminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Minimal Determination citation stub — Ontology version immutability only (story 4.4).
 * SRE evaluator write path is story 4.6.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
    'ontology_version_id',
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
}
