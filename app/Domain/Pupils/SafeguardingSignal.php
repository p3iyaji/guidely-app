<?php

namespace App\Domain\Pupils;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\SafeguardingSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current presence/severity category for a Pupil. Not a safeguarding case record.
 */
#[Fillable([
    'tenant_id',
])]
class SafeguardingSignal extends Model
{
    /** @use HasFactory<SafeguardingSignalFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'severity' => SafeguardingSeverity::class,
        ];
    }

    protected static function newFactory(): SafeguardingSignalFactory
    {
        return SafeguardingSignalFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }
}
