<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Models\UserSectionAssignment;

class EventPolicy
{
    /** Coordinator creates drafts only inside an assigned section/program (F1.2, F2.1). */
    public function create(User $user): bool
    {
        return $user->hasPermission('events.manage')
            && $user->assignedSectionIds(UserSectionAssignment::COORDINATOR)->isNotEmpty();
    }

    public function manage(User $user, Event $event): bool
    {
        return $user->hasPermission('events.manage')
            && Event::coordinatedBy($user)->whereKey($event->id)->exists();
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->hasPermission('users.manage') || $user->hasPermission('certificates.issue')) {
            return true;
        }

        if ($this->manage($user, $event)) {
            return true;
        }

        if ($event->status === Event::STATUS_DRAFT) {
            return false;
        }

        return Event::visibleToStudent($user)->whereKey($event->id)->exists()
            || Registration::where('event_id', $event->id)->where('student_id', $user->id)->exists();
    }

    public function register(User $user, Event $event): bool
    {
        return Event::eligibleFor($user)->whereKey($event->id)->exists();
    }

    public function manageAttendance(User $user, Event $event): bool
    {
        return $user->hasPermission('attendance.manual') && $this->manage($user, $event);
    }
}
