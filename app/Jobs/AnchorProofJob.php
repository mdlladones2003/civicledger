<?php

namespace App\Jobs;

use App\Models\BlockchainProof;
use App\Services\ProofService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Submits then reconciles one proof. Idempotent, so retries are safe. Dispatch with ->afterCommit(). */
class AnchorProofJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public int $proofId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300, 600];
    }

    public function handle(ProofService $proofs): void
    {
        $proof = BlockchainProof::find($this->proofId);

        if (! $proof || $proof->proof_status === BlockchainProof::CONFIRMED) {
            return;
        }

        $proofs->submit($proof);

        if (! $proofs->reconcile($proof->refresh())) {
            if ($proof->refresh()->proof_status === BlockchainProof::FAILED) {
                return;
            }

            $this->release(20); // not confirmed yet; local approved data is unaffected
        }
    }

    public function failed(Throwable $e): void
    {
        BlockchainProof::whereKey($this->proofId)
            ->where('proof_status', '!=', BlockchainProof::CONFIRMED)
            ->update(['proof_status' => BlockchainProof::FAILED, 'last_error' => Str::limit($e->getMessage(), 500)]);
    }
}
