<?php

namespace App\Domain\Evidence;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\EvidenceRecordVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'evidence_record_id',
    'version',
    'snapshot',
    'superseded_at',
    'superseded_by',
])]
class EvidenceRecordVersion extends Model
{
    /** @use HasFactory<EvidenceRecordVersionFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EvidenceRecordVersionFactory
    {
        return EvidenceRecordVersionFactory::new();
    }

    public function evidenceRecord(): BelongsTo
    {
        return $this->belongsTo(EvidenceRecord::class);
    }

    public function supersededByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by');
    }
}
