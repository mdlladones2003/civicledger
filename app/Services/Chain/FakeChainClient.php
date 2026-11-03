<?php

namespace App\Services\Chain;

use Illuminate\Support\Facades\Cache;

/**
 * Local/demo stand-in for the ProofRegistry contract, backed by the cache store.
 * Lets the whole flow (queue -> confirm -> verify -> tamper -> Mismatch) run without an RPC.
 * Blocked in production by CivicLedgerServiceProvider.
 */
class FakeChainClient implements ChainClient
{
    public function submit(string $recordKey, string $commitment, string $recordType, int $version): string
    {
        $tx = '0x'.hash('sha256', $recordKey.'|'.$commitment.'|'.$version);

        Cache::forever("fakechain:record:{$recordKey}", $commitment);
        Cache::forever("fakechain:tx:{$tx}", true);

        return $tx;
    }

    public function receipt(string $txHash): ?ChainReceipt
    {
        if (! Cache::has("fakechain:tx:{$txHash}")) {
            return null;
        }

        return new ChainReceipt(
            success: true,
            confirmations: max(12, (int) config('civicledger.proof.confirmations')),
            chainId: (int) config('civicledger.proof.chain_id'),
            contractAddress: (string) config('civicledger.proof.contract_address'),
        );
    }

    public function commitmentFor(string $recordKey): ?string
    {
        return Cache::get("fakechain:record:{$recordKey}");
    }
}
