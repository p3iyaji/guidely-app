<?php

namespace App\Domain\Evidence;

use App\Domain\Ontology\SettingTerm;
use App\Domain\Pupils\Pupil;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\EvidenceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pupil_id',
    'author_id',
    'occurred_at',
    'setting_term_id',
    'body',
])]
class EvidenceRecord extends Model
{
    /** @use HasFactory<EvidenceRecordFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'observation',
        'lifecycle' => 'submitted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'lifecycle' => EvidenceLifecycle::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EvidenceRecordFactory
    {
        return EvidenceRecordFactory::new();
    }

    public function pupil(): BelongsTo
    {
        return $this->belongsTo(Pupil::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function settingTerm(): BelongsTo
    {
        return $this->belongsTo(SettingTerm::class, 'setting_term_id');
    }
}
