<?php

namespace App\Domain\Sre;

use App\Domain\Ontology\SreDimension;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\OverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Professional-judgement Override for a Pupil+dimension.
 *
 * Domain\Sre is the sole writer (forceFill). Append-only — does not mutate
 * Evidence or prior Determination rows.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class Override extends Model
{
    /** @use HasFactory<OverrideFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => SreDimension::class,
        ];
    }

    protected static function newFactory(): OverrideFactory
    {
        return OverrideFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function determination(): BelongsTo
    {
        return $this->belongsTo(Determination::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
