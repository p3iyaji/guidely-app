<?php

namespace App\Domain\Reviews;

use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\ReviewCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review Cycle keyed to a Pupil (type, due date, optional EHCP-link).
 *
 * Domain\Reviews is the sole writer (forceFill). Not mass-assignable from capture APIs.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class ReviewCycle extends Model
{
    /** @use HasFactory<ReviewCycleFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'ehcp_linked' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReviewCycleType::class,
            'status' => ReviewCycleStatus::class,
            'due_on' => 'date',
            'ehcp_linked' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ReviewCycleFactory
    {
        return ReviewCycleFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('status'), ReviewCycleStatus::Open);
    }

    #[Scope]
    protected function dueOnOrBefore(Builder $query, string $date): Builder
    {
        return $query->whereDate($query->getModel()->qualifyColumn('due_on'), '<=', $date);
    }
}
