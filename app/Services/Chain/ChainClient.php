<?php

namespace App\Services\Chain;

interface ChainClient
{
    /**
     * Register opaque key + commitment in ProofRegistry. Returns the transaction hash.
     *
     * @throws ChainUnavailableException
     */
    public function submit(string $recordKey, string $commitment, string $recordType, int $version): string;

    /**
     * Receipt for a transaction, or null while it is still pending.
     *
     * @throws ChainUnavailableException
     */
    public function receipt(string $txHash): ?ChainReceipt;

    /**
     * Confirmed on-chain commitment for a record key, or null if the key is unknown.
     *
     * @throws ChainUnavailableException
     */
    public function commitmentFor(string $recordKey): ?string;
}
