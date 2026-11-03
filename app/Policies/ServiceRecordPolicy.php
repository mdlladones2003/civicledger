<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\ServiceRecord;
use App\Models\User;

class ServiceRecordPolicy
{
    /** F1.1: students see only their own; reviewers see records inside their scope. */
    public function view(User $user, ServiceRecord $record): bool
    {
        return $record->student_id === $user->id || $this->inReviewScope($user, $record);
    }

    /** F1.5: approve / reject / request revision. Never your own claim. */
    public function review(User $user, ServiceRecord $record): bool
    {
        return $record->student_id !== $user->id && $this->inReviewScope($user, $record);
    }

    private function inReviewScope(User $user, ServiceRecord $record): bool
    {
        if (! $user->hasPermission('hours.review')) {
            return false;
        }

        $record->loadMissing('attendance.session');

        return Event::coordinatedBy($user)->whereKey($record->attendance->session->event_id)->exists();
    }
}
