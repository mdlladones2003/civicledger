<?php

namespace App\Services;

use App\Exceptions\ScanRejectedException;
use App\Models\Attendance;
use App\Models\AttendanceAdjustment;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private QrChallengeService $qr,
        private ServiceRecordService $records,
        private AuditLogger $audit,
    ) {}

    /**
     * First valid scan = check-in, second = check-out. Transactional; UNIQUE(session_id, student_id) blocks duplicates.
     *
     * @return array{action:string,attendance:Attendance}
     *
     * @throws ScanRejectedException
     */
    public function scan(User $student, string $token): array
    {
        $session = $this->qr->verify($token);

        return DB::transaction(function () use ($student, $session) {
            $registered = Registration::query()
                ->where('event_id', $session->event_id)
                ->where('student_id', $student->id)
                ->where('status', Registration::REGISTERED)
                ->exists();

            if (! $registered) {
                throw ScanRejectedException::notRegistered();
            }

            $attendance = Attendance::query()
                ->where('session_id', $session->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                try {
                    $attendance = Attendance::create([
                        'session_id' => $session->id,
                        'student_id' => $student->id,
                        'check_in_at' => now(),
                        'source' => Attendance::SOURCE_QR,
                        'status' => Attendance::STATUS_CHECKED_IN,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw ScanRejectedException::duplicate($session->event->title);
                }

                return ['action' => 'check_in', 'attendance' => $attendance];
            }

            if ($attendance->status !== Attendance::STATUS_CHECKED_IN || $attendance->check_out_at !== null) {
                return [
                    'action' => $attendance->check_out_at !== null ? 'already_checked_out' : 'already_checked_in',
                    'attendance' => $attendance,
                ];
            }

            if ($attendance->check_in_at->diffInSeconds(now(), true) < (int) config('civicledger.qr.min_checkout_seconds')) {
                throw ScanRejectedException::tooSoon();
            }

            $attendance->update(['check_out_at' => now(), 'status' => Attendance::STATUS_COMPLETED]);
            $this->records->openClaim($attendance);

            return ['action' => 'check_out', 'attendance' => $attendance];
        });
    }

    /** F3.3: staff manual fallback. Reason is mandatory and logged. */
    public function manualEntry(User $actor, EventSession $session, User $student, CarbonInterface $in, ?CarbonInterface $out, string $reason): Attendance
    {
        return DB::transaction(function () use ($actor, $session, $student, $in, $out, $reason) {
            $registered = Registration::query()
                ->where('event_id', $session->event_id)
                ->where('student_id', $student->id)
                ->where('status', Registration::REGISTERED)
                ->exists();

            if (! $registered) {
                throw ValidationException::withMessages(['student_id' => 'Student is not registered for this event.']);
            }

            if (Attendance::where('session_id', $session->id)->where('student_id', $student->id)->exists()) {
                throw ValidationException::withMessages(['student_id' => 'Attendance already exists for this student. Use an adjustment instead.']);
            }

            $attendance = Attendance::create([
                'session_id' => $session->id,
                'student_id' => $student->id,
                'check_in_at' => $in,
                'check_out_at' => $out,
                'source' => Attendance::SOURCE_MANUAL,
                'status' => $out ? Attendance::STATUS_COMPLETED : Attendance::STATUS_CHECKED_IN,
            ]);

            AttendanceAdjustment::create([
                'attendance_id' => $attendance->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'old_json' => null,
                'new_json' => $this->snapshot($attendance),
            ]);

            $this->audit->log($actor, 'attendance.manual_created', $attendance, null, $this->snapshot($attendance) + ['reason' => $reason]);

            if ($out) {
                $this->records->openClaim($attendance);
            }

            return $attendance;
        });
    }

    /** Corrections keep the earlier values in attendance_adjustments and reopen any approved claim. */
    public function adjust(User $actor, Attendance $attendance, CarbonInterface $in, ?CarbonInterface $out, string $reason): Attendance
    {
        return DB::transaction(function () use ($actor, $attendance, $in, $out, $reason) {
            $attendance = Attendance::whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $old = $this->snapshot($attendance);

            $attendance->update([
                'check_in_at' => $in,
                'check_out_at' => $out,
                'status' => $out ? Attendance::STATUS_COMPLETED : Attendance::STATUS_CHECKED_IN,
            ]);

            $new = $this->snapshot($attendance);

            AttendanceAdjustment::create([
                'attendance_id' => $attendance->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'old_json' => $old,
                'new_json' => $new,
            ]);

            $this->audit->log($actor, 'attendance.adjusted', $attendance, $old, $new + ['reason' => $reason]);

            if ($out) {
                $this->records->openClaim($attendance);
                $this->records->reopenForCorrection($attendance, $actor, $reason);
            }

            return $attendance;
        });
    }

    private function snapshot(Attendance $a): array
    {
        return [
            'check_in_at' => $a->check_in_at?->utc()->toIso8601String(),
            'check_out_at' => $a->check_out_at?->utc()->toIso8601String(),
            'status' => $a->status,
            'source' => $a->source,
        ];
    }
}
