<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EventSession;
use App\Models\Registration;
use App\Services\QrChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SessionQrController extends Controller
{
    public function show(EventSession $session)
    {
        $session->load('event:id,title,status');
        Gate::authorize('manage', $session->event);

        return Inertia::render('Sessions/LiveQr', [
            'session' => $session,
            'ttl' => (int) config('civicledger.qr.ttl_seconds'),
            'roster' => $this->roster($session),
        ]);
    }

    /** Poll roughly every (ttl - 5) seconds from the live screen; each call issues a fresh rotating challenge. */
    public function challenge(EventSession $session, QrChallengeService $qr): JsonResponse
    {
        $session->load('event');
        Gate::authorize('manage', $session->event);

        return response()->json($qr->issue($session))->header('Cache-Control', 'no-store');
    }

    public function rosterJson(EventSession $session): JsonResponse
    {
        $session->load('event');
        Gate::authorize('manage', $session->event);

        return response()->json($this->roster($session));
    }

    private function roster(EventSession $session): array
    {
        $attendance = Attendance::where('session_id', $session->id)->get()->keyBy('student_id');

        return Registration::where('event_id', $session->event_id)
            ->where('status', Registration::REGISTERED)
            ->with('student:id,first_name,middle_name,last_name')
            ->get()
            ->map(fn (Registration $r) => [
                'student_id' => $r->student_id,
                'name' => $r->student->full_name,
                'status' => $attendance[$r->student_id]->status ?? 'not_arrived',
                'check_in_at' => $attendance[$r->student_id]->check_in_at ?? null,
                'check_out_at' => $attendance[$r->student_id]->check_out_at ?? null,
                'source' => $attendance[$r->student_id]->source ?? null,
                'attendance_id' => $attendance[$r->student_id]->id ?? null,
            ])
            ->values()
            ->all();
    }
}
