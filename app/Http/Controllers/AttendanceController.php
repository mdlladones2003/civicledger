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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** S05: mobile scanner page. */
    public function scan()
    {
        return Inertia::render('Attendance/Scan');
    }

    public function store(ScanAttendanceRequest $request): Response
    {
        try {
            $result = $this->attendance->scan($request->user(), $request->validated('token'));
        } catch (ScanRejectedException $e) {
            if ($request->expectsJson()) {
                if ($e->reason === 'duplicate') {
                    return response()->json([
                        'result' => $e->result,
                        'at' => now()->utc()->toISOString(),
                        'event' => ['title' => $e->eventTitle],
                    ]);
                }

                return $this->scanErrorResponse($e);
            }

            // The check-in stands and nothing changed, so the page reports it like any other repeat scan (F3.2-A2).
            if ($e->reason === 'too_soon') {
                return back()->with('scan', [
                    'action' => 'already_checked_in',
                    'at' => $e->checkedInAt->toIso8601String(),
                ]);
            }

            return back()->withErrors(['token' => $e->getMessage()]);
        }

        $attendance = $result['attendance'];
        $at = in_array($result['action'], ['check_out', 'already_checked_out'], true)
            ? $attendance->check_out_at
            : $attendance->check_in_at;

        if ($request->expectsJson()) {
            $attendance->loadMissing('session.event');

            return response()->json([
                'result' => $result['action'] === 'check_in' ? 'checked_in' : ($result['action'] === 'check_out' ? 'checked_out' : $result['action']),
                'at' => $at->utc()->toISOString(),
                'event' => ['title' => $attendance->session->event->title],
            ]);
        }

        return back()->with('scan', [
            'action' => $result['action'],
            'at' => $at->toIso8601String(),
        ]);
    }

    private function scanErrorResponse(ScanRejectedException $exception): JsonResponse
    {
        [$code, $status, $retryable] = match ($exception->reason) {
            'expired' => ['qr_expired', 410, true],
            'invalid' => ['qr_invalid', 400, false],
            'not_registered' => ['not_registered', 403, false],
            'session_closed' => ['session_closed', 409, true],
            'too_soon' => ['invalid_state', 409, true],
            default => ['invalid_state', 409, false],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $exception->getMessage(),
                'retryable' => $retryable,
                'request_id' => (string) Str::ulid(),
            ],
        ], $status);
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
