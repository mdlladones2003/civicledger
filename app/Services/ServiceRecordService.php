<?php

namespace App\Services;

use App\Jobs\AnchorProofJob;
use App\Models\Attendance;
use App\Models\BlockchainProof;
use App\Models\ServiceRecord;
use App\Models\ServiceRecordVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRecordService
{
    public function __construct(private ProofService $proofs, private AuditLogger $audit) {}

    /** Created when attendance completes. Pending claims never count toward totals. */
    public function openClaim(Attendance $attendance): ServiceRecord
    {
        return ServiceRecord::firstOrCreate(
            ['attendance_id' => $attendance->id],
            ['student_id' => $attendance->student_id, 'status' => ServiceRecord::PENDING, 'approved_minutes' => 0, 'current_version' => 0],
        );
    }

    /** F3.5: a corrected attendance row sends an approved claim back to review; old versions/proofs are kept. */
    public function reopenForCorrection(Attendance $attendance, User $actor, string $reason): void
    {
        $record = ServiceRecord::where('attendance_id', $attendance->id)->lockForUpdate()->first();

        if (! $record || $record->status !== ServiceRecord::APPROVED) {
            return;
        }

        $before = ['status' => $record->status, 'approved_minutes' => $record->approved_minutes];
        $record->update(['status' => ServiceRecord::PENDING]);

        BlockchainProof::whereIn('service_record_version_id', $record->versions()->pluck('id'))
            ->update(['validity_status' => BlockchainProof::SUPERSEDED]);

        $this->audit->log($actor, 'service_record.reopened', $record, $before, ['status' => ServiceRecord::PENDING, 'reason' => $reason]);
    }

    public function approve(ServiceRecord $record, User $actor, ?string $note = null): ServiceRecord
    {
        $this->guardSelfApproval($record, $actor);

        return DB::transaction(function () use ($record, $actor, $note) {
            $record = ServiceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $record->load('attendance');

            if ($record->status === ServiceRecord::APPROVED) {
                return $record; // idempotent
            }

            $attendance = $record->attendance;

            if ($attendance->status !== Attendance::STATUS_COMPLETED || ! $attendance->check_out_at) {
                throw ValidationException::withMessages(['record' => 'Attendance is not complete (missing check-out).']);
            }

            $before = ['status' => $record->status, 'approved_minutes' => $record->approved_minutes];
            $version = $record->current_version + 1;

            $record->forceFill([
                'approved_minutes' => $attendance->durationMinutes(), // server-derived
                'status' => ServiceRecord::APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'current_version' => $version,
            ])->save();

            $record->versions()
                ->each(fn (ServiceRecordVersion $v) => $v->proof()->update(['validity_status' => BlockchainProof::SUPERSEDED]));

            $versionRow = ServiceRecordVersion::create([
                'service_record_id' => $record->id,
                'version' => $version,
                'canonical_payload_json' => CanonicalPayload::forServiceRecord($record, $version),
                'reason' => $note,
            ]);

            $proof = $this->proofs->queueForVersion($versionRow);

            $this->audit->log($actor, 'service_record.approved', $record, $before, [
                'status' => ServiceRecord::APPROVED,
                'approved_minutes' => $record->approved_minutes,
                'version' => $version,
                'note' => $note,
            ]);

            AnchorProofJob::dispatch($proof->id)->afterCommit();

            return $record;
        });
    }

    public function reject(ServiceRecord $record, User $actor, string $reason): ServiceRecord
    {
        return $this->decide($record, $actor, ServiceRecord::REJECTED, 'service_record.rejected', $reason);
    }

    public function requestRevision(ServiceRecord $record, User $actor, string $reason): ServiceRecord
    {
        return $this->decide($record, $actor, ServiceRecord::REVISION_REQUESTED, 'service_record.revision_requested', $reason);
    }

    private function decide(ServiceRecord $record, User $actor, string $status, string $action, string $reason): ServiceRecord
    {
        $this->guardSelfApproval($record, $actor);

        return DB::transaction(function () use ($record, $actor, $status, $action, $reason) {
            $record = ServiceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($record->status === ServiceRecord::APPROVED) {
                throw ValidationException::withMessages(['record' => 'Approved records cannot be rejected; submit a correction instead.']);
            }

            $before = ['status' => $record->status];
            $record->update(['status' => $status]);
            $this->audit->log($actor, $action, $record, $before, ['status' => $status, 'reason' => $reason]);

            return $record;
        });
    }

    /** F1.5 defense in depth; the policy enforces the same rule at the HTTP layer. */
    private function guardSelfApproval(ServiceRecord $record, User $actor): void
    {
        if ($record->student_id === $actor->id) {
            throw new AuthorizationException('You cannot review your own service claim.');
        }
    }
}
