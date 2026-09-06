<?php

namespace App\Domain\Pupils;

use App\Domain\Ontology\NeedTerm;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\School;
use App\Models\User;
use Database\Factories\PupilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'school_id',
    'given_name',
    'family_name',
    'mis_key',
    'date_of_birth',
    'year_group',
    'send_status',
    'notes',
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

    protected static function booted(): void
    {
        static::updating(function (Pupil $pupil): void {
            if (! $pupil->isDirty('school_id')) {
                return;
            }

            $school = School::query()->find($pupil->school_id);

            $assigneeIds = $pupil->assignedUsers()
                ->get()
                ->reject(fn (User $assignee): bool => $school !== null && $assignee->canAccessSchool($school))
                ->modelKeys();

            if ($assigneeIds !== []) {
                $pupil->assignedUsers()->detach($assigneeIds);
            }
        });

        static::deleting(function (Pupil $pupil): void {
            $pupil->assignedUsers()->detach();
        });
    }

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

    /**
     * Teachers / Support Staff assigned to this Pupil (separate from school_user).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['class_label', 'cohort_label', 'source'])
            ->withTimestamps();
    }

    /**
     * Assign a same-School Teacher or Support Staff (SENCO/Admin ownership is enforced at the API).
     *
     * Partial updates merge into the existing pivot: omitted keys keep prior values.
     * `source` defaults to senco only on first create; existing connector (or other) source is preserved
     * unless an explicit valid AssignmentSource value is provided.
     *
     * @param  array{class_label?: ?string, cohort_label?: ?string, source?: string|AssignmentSource}  $attributes
     */
    public function assignTo(User $user, array $attributes = []): void
    {
        $existing = $this->assignedUsers()->whereKey($user->id)->first();
        $pivot = $existing?->pivot;

        $classLabel = array_key_exists('class_label', $attributes)
            ? $attributes['class_label']
            : $pivot?->class_label;

        $cohortLabel = array_key_exists('cohort_label', $attributes)
            ? $attributes['cohort_label']
            : $pivot?->cohort_label;

        if (array_key_exists('source', $attributes)) {
            $source = $this->resolveAssignmentSource($attributes['source'])->value;
        } elseif ($pivot?->source !== null) {
            $source = $this->resolveAssignmentSource($pivot->source)->value;
        } else {
            $source = AssignmentSource::Senco->value;
        }

        $this->assignedUsers()->syncWithoutDetaching([
            $user->id => [
                'class_label' => $classLabel,
                'cohort_label' => $cohortLabel,
                'source' => $source,
            ],
        ]);
    }

    public function unassign(User $user): void
    {
        $this->assignedUsers()->detach($user->id);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignedUsers()->whereKey($user->id)->exists();
    }

    private function resolveAssignmentSource(string|AssignmentSource $source): AssignmentSource
    {
        if ($source instanceof AssignmentSource) {
            return $source;
        }

        $resolved = AssignmentSource::tryFrom($source);

        if ($resolved === null) {
            throw new InvalidArgumentException("Invalid assignment source [{$source}].");
        }

        return $resolved;
    }
}
