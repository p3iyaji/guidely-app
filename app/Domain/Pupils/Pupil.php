<?php

namespace App\Domain\Pupils;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\School;
use Database\Factories\PupilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id',
    'given_name',
    'family_name',
    'mis_key',
    'date_of_birth',
    'year_group',
    'send_status',
    'primary_need_term_id',
    'primary_need_notes',
    'secondary_need_term_id',
    'secondary_need_notes',
])]
class Pupil extends Model
{
    /** @use HasFactory<PupilFactory> */
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'send_status' => 'neither',
        'documentation_status' => 'not-started',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'send_status' => SendStatus::class,
            'documentation_status' => DocumentationStatus::class,
        ];
    }

    protected static function newFactory(): PupilFactory
    {
        return PupilFactory::new();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function primaryNeedTerm(): BelongsTo
    {
        return $this->belongsTo(NeedTerm::class, 'primary_need_term_id');
    }

    public function secondaryNeedTerm(): BelongsTo
    {
        return $this->belongsTo(NeedTerm::class, 'secondary_need_term_id');
    }
}
