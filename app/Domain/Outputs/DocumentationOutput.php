<?php

namespace App\Domain\Outputs;

use App\Domain\Pupils\Pupil;
use App\Domain\Reviews\ReviewCycle;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\DocumentationOutputFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confirmed Documentation Output bound to a Pupil and Review Cycle.
 *
 * Domain\Outputs is the sole writer (forceFill). Not mass-assignable from capture APIs.
 */
#[Fillable([
    'tenant_id',
    'pupil_id',
])]
class DocumentationOutput extends Model
{
    /** @use HasFactory<DocumentationOutputFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * FR-35 disclaimer snapshotted onto every generated artefact.
     */
    public const DISCLAIMER_TEXT = 'Documentation evaluations support professional judgement. They are not diagnoses, funding decisions, or statutory determinations.';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentationOutputType::class,
            'version' => 'integer',
            'payload' => 'array',
            'confirmed_at' => 'datetime',
            'pack_ready_at' => 'datetime',
            'byte_size' => 'integer',
        ];
    }

    protected static function newFactory(): DocumentationOutputFactory
    {
        return DocumentationOutputFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function reviewCycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmer_user_id');
    }
}
