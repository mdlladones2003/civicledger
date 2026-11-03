<?php

namespace App\Services;

use App\Enums\VerificationState;
use App\Models\BlockchainProof;
use App\Models\Certificate;
use App\Models\ServiceRecordVersion;
use App\Services\Chain\ChainClient;
use App\Services\Chain\ChainUnavailableException;
use RuntimeException;

class ProofService
{
    public function __construct(private ChainClient $chain) {}

    public function queueForVersion(ServiceRecordVersion $version): BlockchainProof
    {
        $version->loadMissing('serviceRecord');
        $payload = CanonicalPayload::forServiceRecord($version->serviceRecord, $version->version);

        return $this->create(['service_record_version_id' => $version->id], $payload);
    }

    public function queueForCertificate(Certificate $certificate): BlockchainProof
    {
        return $this->create(['certificate_id' => $certificate->id], CanonicalPayload::forCertificate($certificate));
    }

    /** Idempotent: a proof that already has a tx hash is not resubmitted. */
    public function submit(BlockchainProof $proof): void
    {
        if ($proof->tx_hash) {
            return;
        }

        $tx = $this->chain->submit(
            $proof->opaque_record_key,
            $proof->commitment,
            $proof->recordType(),
            $proof->recordVersion(),
        );

        $proof->update(['tx_hash' => $tx, 'proof_status' => BlockchainProof::SUBMITTED, 'last_error' => null]);
    }

    /** Submitted is never treated as confirmed: needs receipt, right chain + contract, enough confirmations. */
    public function reconcile(BlockchainProof $proof): bool
    {
        if ($proof->proof_status === BlockchainProof::CONFIRMED) {
            return true;
        }

        if (! $proof->tx_hash) {
            return false;
        }

        $receipt = $this->chain->receipt($proof->tx_hash);

        if ($receipt === null) {
            return false;
        }

        if (! $receipt->success
            || $receipt->chainId !== (int) $proof->chain_id
            || strcasecmp($receipt->contractAddress, $proof->contract_address) !== 0) {
            $proof->update(['proof_status' => BlockchainProof::FAILED, 'last_error' => 'Receipt failed or wrong chain/contract.']);

            return false;
        }

        if ($receipt->confirmations < (int) config('civicledger.proof.confirmations')) {
            return false;
        }

        $proof->update(['proof_status' => BlockchainProof::CONFIRMED, 'confirmed_at' => now(), 'last_error' => null]);

        return true;
    }

    /** Recompute from the authoritative record and compare with the confirmed on-chain value. */
    public function verify(?BlockchainProof $proof): VerificationState
    {
        if ($proof === null) {
            return VerificationState::NotFound;
        }

        if ($proof->validity_status === BlockchainProof::REVOKED) {
            return VerificationState::Revoked;
        }

        if ($proof->validity_status === BlockchainProof::SUPERSEDED) {
            return VerificationState::Superseded;
        }

        if ($proof->proof_status === BlockchainProof::FAILED) {
            return VerificationState::Unavailable;
        }

        if ($proof->proof_status !== BlockchainProof::CONFIRMED) {
            return VerificationState::Pending;
        }

        try {
            $onChain = $this->chain->commitmentFor($proof->opaque_record_key);
        } catch (ChainUnavailableException) {
            return VerificationState::Unavailable; // never "verified" when the chain cannot be checked
        }

        if ($onChain === null) {
            return VerificationState::NotFound;
        }

        $payload = $this->livePayload($proof);

        if ($payload === null) {
            return VerificationState::Mismatch;
        }

        $live = $this->commitment($payload, $proof->opaque_record_key);

        return hash_equals(strtolower($onChain), strtolower($live))
            ? VerificationState::Verified
            : VerificationState::Mismatch;
    }

    private function create(array $subject, array $payload): BlockchainProof
    {
        $key = '0x'.bin2hex(random_bytes(32));

        return BlockchainProof::create($subject + [
            'opaque_record_key' => $key,
            'commitment' => $this->commitment($payload, $key),
            'chain_id' => (int) config('civicledger.proof.chain_id'),
            'contract_address' => (string) config('civicledger.proof.contract_address'),
            'proof_status' => BlockchainProof::QUEUED,
            'validity_status' => BlockchainProof::CURRENT,
        ]);
    }

    /** Keyed construction (TRD §6): HMAC-SHA256 with a key derived per record from a secret held outside the DB. */
    private function commitment(array $payload, string $recordKey): string
    {
        $secret = (string) config('civicledger.proof.secret');

        if ($secret === '') {
            throw new RuntimeException('CIVICLEDGER_PROOF_SECRET is not configured.');
        }

        $derived = hash_hmac('sha256', $recordKey, $secret, true);

        return '0x'.hash_hmac('sha256', CanonicalPayload::json($payload), $derived);
    }

    private function livePayload(BlockchainProof $proof): ?array
    {
        if ($proof->service_record_version_id) {
            $version = $proof->serviceRecordVersion;
            $record = $version?->serviceRecord;

            return $record ? CanonicalPayload::forServiceRecord($record, $version->version) : null;
        }

        $certificate = $proof->certificate;

        return $certificate ? CanonicalPayload::forCertificate($certificate) : null;
    }
}
