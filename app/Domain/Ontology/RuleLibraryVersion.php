<?php

namespace App\Domain\Ontology;

use Database\Factories\RuleLibraryVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'label',
    'status',
    'published_at',
])]
class RuleLibraryVersion extends Model
{
    /** @use HasFactory<RuleLibraryVersionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RuleLibraryVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): RuleLibraryVersionFactory
    {
        return RuleLibraryVersionFactory::new();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', RuleLibraryVersionStatus::Published);
    }
}
