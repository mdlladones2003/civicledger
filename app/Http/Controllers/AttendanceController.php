<?php

namespace App\Http\Controllers;

use App\Exceptions\ScanRejectedException;
use App\Http\Requests\ManualAttendanceRequest;
use App\Http\Requests\ScanAttendanceRequest;
use App\Models\Attendance;
use App\Models\EventSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\QrChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** S05: mobile scanner page. */
    public function scan()
    {
        return Inertia::render('Attendance/Scan');
    }

    public function store(ScanAttendanceRequest $request)
    {
        try {
            $result = $this->attendance->scan($request->user(), $request->validated('token'));
        } catch (ScanRejectedException $e) {
            return back()->withErrors(['token' => $e->getMessage()])->with('scan_reason', $e->reason);
        }

        return back()->with('scan', [
            'action' => $result['action'],
            'at' => now()->toIso8601String(),
        ]);
    }

    public function challenge(ScanAttendanceRequest $request, QrChallengeService $qr): JsonResponse
    {
        try {
            $remainingSeconds = $qr->remainingSeconds($request->validated('token'));
        } catch (ScanRejectedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['remaining_seconds' => $remainingSeconds])
            ->header('Cache-Control', 'no-store');
    }

    /** F3.3: manual fallback by authorized staff, reason required. */
    public function manual(ManualAttendanceRequest $request, EventSession $session)
    {
        $session->load('event');
        Gate::authorize('manageAttendance', $session->event);

        $data = $request->validated();

        $this->attendance->manualEntry(
            $request->user(),
            $session,
            User::findOrFail($data['student_id']),
            Carbon::parse($data['check_in_at'])->utc(),
            isset($data['check_out_at']) ? Carbon::parse($data['check_out_at'])->utc() : null,
            $data['reason'],
        );

        return back()->with('success', 'Manual attendance recorded.');
    }

    /** Correction preserving the old values + rationale (F3.5). */
    public function adjust(Request $request, Attendance $attendance)
    {
        $attendance->load('session.event');
        Gate::authorize('manageAttendance', $attendance->session->event);

        $data = $request->validate([
            'check_in_at' => ['required', 'date'],
            'check_out_at' => ['nullable', 'date', 'after_or_equal:check_in_at'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $this->attendance->adjust(
            $request->user(),
            $attendance,
            Carbon::parse($data['check_in_at'])->utc(),
            isset($data['check_out_at']) ? Carbon::parse($data['check_out_at'])->utc() : null,
            $data['reason'],
        );

        return back()->with('success', 'Attendance corrected; any approved claim is back in review.');
    }
}
