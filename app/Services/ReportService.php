<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BlockchainProof;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ServiceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** F6: role-scoped summaries. Official totals count approved hours only (F6.3). */
class ReportService
{
    public function studentSummary(User $student): array
    {
        $byStatus = ServiceRecord::query()
            ->where('student_id', $student->id)
            ->select('status', DB::raw('count(*) as claims'), DB::raw('coalesce(sum(approved_minutes),0) as minutes'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'official_minutes' => (int) ($byStatus[ServiceRecord::APPROVED]->minutes ?? 0),
            'claims' => [
                'pending' => (int) ($byStatus[ServiceRecord::PENDING]->claims ?? 0),
                'approved' => (int) ($byStatus[ServiceRecord::APPROVED]->claims ?? 0),
                'rejected' => (int) ($byStatus[ServiceRecord::REJECTED]->claims ?? 0),
                'revision_requested' => (int) ($byStatus[ServiceRecord::REVISION_REQUESTED]->claims ?? 0),
            ],
            'registered_events' => Registration::where('student_id', $student->id)->where('status', Registration::REGISTERED)->count(),
            'active_certificates' => Certificate::where('student_id', $student->id)->where('status', Certificate::ISSUED)->count(),
        ];
    }

    public function coordinatorSummary(User $coordinator): array
    {
        $scopedEvents = fn ($q) => $q->coordinatedBy($coordinator);

        $records = ServiceRecord::query()->whereHas('attendance.session.event', $scopedEvents);

        $hoursByStatus = (clone $records)
            ->select('status', DB::raw('count(*) as claims'), DB::raw('coalesce(sum(approved_minutes),0) as minutes'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'events_by_status' => Event::coordinatedBy($coordinator)
                ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'completed_attendance' => Attendance::whereHas('session.event', $scopedEvents)
                ->where('status', Attendance::STATUS_COMPLETED)->count(),
            'claims_by_status' => $hoursByStatus->map(fn ($r) => (int) $r->claims),
            'official_minutes' => (int) ($hoursByStatus[ServiceRecord::APPROVED]->minutes ?? 0),
            'proofs_by_status' => BlockchainProof::query()
                ->whereHas('serviceRecordVersion.serviceRecord.attendance.session.event', $scopedEvents)
                ->select('proof_status', DB::raw('count(*) as total'))->groupBy('proof_status')->pluck('total', 'proof_status'),
        ];
    }

    /** Per-event rows for the basic accomplishment summary (F6.4). */
    public function coordinatorEventRows(User $coordinator)
    {
        return Event::coordinatedBy($coordinator)
            ->withCount(['registrations as registered' => fn ($q) => $q->where('status', Registration::REGISTERED)])
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get(['id', 'title', 'status', 'starts_at'])
            ->map(function (Event $e) {
                $records = ServiceRecord::query()->whereHas('attendance.session', fn ($q) => $q->where('event_id', $e->id));

                return [
                    'id' => $e->id,
                    'title' => $e->title,
                    'status' => $e->status,
                    'starts_at' => $e->starts_at,
                    'registered' => $e->registered,
                    'approved_claims' => (clone $records)->where('status', ServiceRecord::APPROVED)->count(),
                    'approved_minutes' => (int) (clone $records)->where('status', ServiceRecord::APPROVED)->sum('approved_minutes'),
                ];
            });
    }
}
