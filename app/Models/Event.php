<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'coordinator_id', 'program_id', 'section_id', 'title', 'description',
        'venue', 'component', 'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** F1.2: events in the coordinator's assigned sections (or program-wide events in assigned programs). */
    public function scopeCoordinatedBy(Builder $query, User $user): Builder
    {
        $sections = $user->assignedSectionIds(UserSectionAssignment::COORDINATOR);
        $programs = $user->assignedProgramIds(UserSectionAssignment::COORDINATOR);

        return $query->where(fn (Builder $w) => $w
            ->whereIn('section_id', $sections)
            ->orWhere(fn (Builder $x) => $x->whereNull('section_id')->whereIn('program_id', $programs)));
    }

    /** F2.3: published events a student is eligible to register for. */
    public function scopeEligibleFor(Builder $query, User $user): Builder
    {
        $sections = $user->assignedSectionIds(UserSectionAssignment::STUDENT);
        $programs = $user->assignedProgramIds(UserSectionAssignment::STUDENT);

        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('ends_at', '>=', now())
            ->where(fn (Builder $w) => $w
                ->whereIn('section_id', $sections)
                ->orWhere(fn (Builder $x) => $x->whereNull('section_id')->whereIn('program_id', $programs)));
    }

    public function scopeVisibleToStudent(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $events) => $events
            ->eligibleFor($user)
            ->orWhere(fn (Builder $past) => $past
                ->where('status', '!=', self::STATUS_DRAFT)
                ->where('ends_at', '<', now())));
    }
}
