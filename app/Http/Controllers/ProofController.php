<?php

namespace App\Http\Controllers;

use App\Jobs\AnchorProofJob;
use App\Models\BlockchainProof;
use App\Services\ProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** S12: coordinators inspect proof status for records inside their scope. Approval, proof and validity stay separate. */
class ProofController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Proofs/Index', [
            'proofs' => BlockchainProof::query()
                ->whereHas('serviceRecordVersion.serviceRecord.attendance.session.event', fn ($q) => $q->coordinatedBy($user))
                ->with('serviceRecordVersion.serviceRecord:id,student_id,status,approved_minutes')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(BlockchainProof $proof, ProofService $proofs)
    {
        $proof->load('serviceRecordVersion.serviceRecord');
        abort_unless($proof->serviceRecordVersion, 404);
        Gate::authorize('view', $proof->serviceRecordVersion->serviceRecord);

        return Inertia::render('Proofs/Show', [
            'proof' => $proof->makeHidden('last_error'),
            'verification' => $proofs->verify($proof)->value,
        ]);
    }

    public function retry(BlockchainProof $proof)
    {
        $proof->load('serviceRecordVersion.serviceRecord');
        abort_unless($proof->serviceRecordVersion, 404);
        Gate::authorize('review', $proof->serviceRecordVersion->serviceRecord);
        abort_unless($proof->proof_status === BlockchainProof::FAILED, 422, 'Only a failed proof can be retried.');

        $proof->update([
            'proof_status' => $proof->tx_hash ? BlockchainProof::SUBMITTED : BlockchainProof::QUEUED,
            'last_error' => null,
        ]);

        AnchorProofJob::dispatch($proof->id);

        return back()->with('success', 'Retry queued.');
    }
}
