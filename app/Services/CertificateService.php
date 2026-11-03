<?php

namespace App\Services;

use App\Enums\VerificationState;
use App\Jobs\AnchorProofJob;
use App\Models\BlockchainProof;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Models\Section;
use App\Models\ServiceRecord;
use App\Models\Term;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function __construct(private ProofService $proofs, private AuditLogger $audit) {}

    /** Approved minutes only; pending/rejected never count (F6.3). Events without a section have no term and are excluded. */
    public function eligibleMinutes(User $student, Term $term): int
    {
        return (int) ServiceRecord::query()
            ->where('student_id', $student->id)
            ->where('status', ServiceRecord::APPROVED)
            ->whereHas('attendance.session.event.section', fn ($q) => $q->where('term_id', $term->id))
            ->sum('approved_minutes');
    }

    /** Students inside the signer's scope who currently qualify and have no active certificate. */
    public function eligibleCandidates(User $signer): Collection
    {
        $min = (int) config('civicledger.certificate.min_minutes');
        $out = collect();

        Section::with('term')
            ->whereIn('id', $signer->assignedSectionIds(UserSectionAssignment::SIGNER))
            ->get()
            ->each(function (Section $section) use (&$out, $min) {
                UserSectionAssignment::with('user:id,first_name,middle_name,last_name')
                    ->where('section_id', $section->id)
                    ->where('scope_role', UserSectionAssignment::STUDENT)
                    ->get()
                    ->each(function ($assignment) use (&$out, $section, $min) {
                        $key = $assignment->user_id.'-'.$section->term_id;

                        if ($out->has($key)) {
                            return;
                        }

                        $minutes = $this->eligibleMinutes($assignment->user, $section->term);
                        $active = Certificate::where('student_id', $assignment->user_id)
                            ->where('term_id', $section->term_id)
                            ->where('status', Certificate::ISSUED)
                            ->exists();

                        if ($minutes >= $min && ! $active) {
                            $out->put($key, [
                                'student_id' => $assignment->user_id,
                                'student_name' => $assignment->user->full_name,
                                'term_id' => $section->term_id,
                                'term_code' => $section->term->code,
                                'approved_minutes' => $minutes,
                            ]);
                        }
                    });
            });

        return $out->values();
    }

    public function issue(User $signer, User $student, Term $term, ?Certificate $supersedes = null): Certificate
    {
        return DB::transaction(function () use ($signer, $student, $term, $supersedes) {
            $minutes = $this->eligibleMinutes($student, $term);

            if ($minutes < (int) config('civicledger.certificate.min_minutes')) {
                throw ValidationException::withMessages(['student_id' => 'Not eligible: no qualifying approved hours for this term.']);
            }

            $active = Certificate::where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->where('status', Certificate::ISSUED)
                ->lockForUpdate()
                ->first();

            if ($active && (! $supersedes || $supersedes->id !== $active->id)) {
                throw ValidationException::withMessages(['student_id' => 'An active certificate already exists. Supersede it instead.']);
            }

            if ($supersedes && (! $active || $supersedes->student_id !== $student->id || $supersedes->term_id !== $term->id)) {
                throw ValidationException::withMessages(['supersedes_certificate_id' => 'The certificate to supersede is not an active certificate for this student and term.']);
            }

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'term_id' => $term->id,
                'issued_by' => $signer->id,
                'public_token' => Str::random(48), // CSPRNG, unguessable (F5.2)
                'certificate_number' => 'CL-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
                'approved_minutes' => $minutes,
                'status' => Certificate::ISSUED,
                'issued_at' => now(),
                'supersedes_certificate_id' => $supersedes?->id,
            ]);

            $this->event($certificate, $signer, 'issued', null);

            if ($supersedes) {
                $supersedes->update(['status' => Certificate::SUPERSEDED]);
                BlockchainProof::where('certificate_id', $supersedes->id)->update(['validity_status' => BlockchainProof::SUPERSEDED]);
                $this->event($supersedes, $signer, 'superseded', 'Superseded by '.$certificate->certificate_number);
                $this->audit->log($signer, 'certificate.superseded', $supersedes, ['status' => Certificate::ISSUED], ['status' => Certificate::SUPERSEDED]);
            }

            $proof = $this->proofs->queueForCertificate($certificate);
            $this->audit->log($signer, 'certificate.issued', $certificate, null, ['approved_minutes' => $minutes, 'number' => $certificate->certificate_number]);

            AnchorProofJob::dispatch($proof->id)->afterCommit();

            return $certificate;
        });
    }

    public function revoke(User $actor, Certificate $certificate, string $reason): Certificate
    {
        return DB::transaction(function () use ($actor, $certificate, $reason) {
            $certificate = Certificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();

            if ($certificate->status !== Certificate::ISSUED) {
                throw ValidationException::withMessages(['certificate' => 'Only an active certificate can be revoked.']);
            }

            $certificate->update(['status' => Certificate::REVOKED, 'revoked_at' => now()]);
            BlockchainProof::where('certificate_id', $certificate->id)->update(['validity_status' => BlockchainProof::REVOKED]);

            $this->event($certificate, $actor, 'revoked', $reason);
            $this->audit->log($actor, 'certificate.revoked', $certificate, ['status' => Certificate::ISSUED], ['status' => Certificate::REVOKED, 'reason' => $reason]);

            return $certificate;
        });
    }

    /** A revoked or superseded certificate can never come back as valid. */
    public function verificationState(Certificate $certificate): VerificationState
    {
        return match ($certificate->status) {
            Certificate::REVOKED => VerificationState::Revoked,
            Certificate::SUPERSEDED => VerificationState::Superseded,
            default => $this->proofs->verify($certificate->proof),
        };
    }

    private function event(Certificate $certificate, User $actor, string $type, ?string $reason): void
    {
        CertificateEvent::create([
            'certificate_id' => $certificate->id,
            'actor_id' => $actor->id,
            'event_type' => $type,
            'reason' => $reason,
        ]);
    }
}
