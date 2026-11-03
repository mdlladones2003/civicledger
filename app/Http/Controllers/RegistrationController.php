<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RegistrationController extends Controller
{
    /** F2.3: once per event. Idempotent: UNIQUE(event_id, student_id) backs it. */
    public function store(Request $request, Event $event)
    {
        Gate::authorize('register', $event);

        try {
            Registration::updateOrCreate(
                ['event_id' => $event->id, 'student_id' => $request->user()->id],
                ['status' => Registration::REGISTERED],
            );
        } catch (UniqueConstraintViolationException) {
            // concurrent double-submit: already registered
        }

        return back()->with('success', 'You are registered.');
    }

    public function destroy(Request $request, Event $event)
    {
        $registration = Registration::where('event_id', $event->id)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $hasAttendance = Attendance::where('student_id', $request->user()->id)
            ->whereHas('session', fn ($q) => $q->where('event_id', $event->id))
            ->exists();

        abort_if($hasAttendance, 422, 'You cannot cancel after attendance has been recorded.');

        $registration->update(['status' => Registration::CANCELLED]);

        return back()->with('success', 'Registration cancelled.');
    }
}
