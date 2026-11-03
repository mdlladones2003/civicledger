<?php

namespace App\Services\Chain;

/**
 * TODO (Day 2): real EVM JSON-RPC client for the Solidity ProofRegistry.
 *
 *  - submit():        sign + send registerProof(recordKey, commitment, recordType, version) with the server key
 *                     (key from env/secret store, never the browser); return tx hash.
 *  - receipt():       eth_getTransactionReceipt + eth_blockNumber => confirmations; check chainId + contract.
 *  - commitmentFor(): eth_call on the registry getter.
 *  - On RPC failure try CIVICLEDGER_RPC_FALLBACK_URL, then throw ChainUnavailableException.
 *
 * Until implemented every call reports "unavailable", which surfaces as Pending/Unavailable, never Verified.
 */
class EvmChainClient implements ChainClient
{
    public function submit(string $recordKey, string $commitment, string $recordType, int $version): string
    {
        throw new ChainUnavailableException('EvmChainClient::submit is not implemented yet.');
    }

    public function receipt(string $txHash): ?ChainReceipt
    {
        throw new ChainUnavailableException('EvmChainClient::receipt is not implemented yet.');
    }

    public function commitmentFor(string $recordKey): ?string
    {
        throw new ChainUnavailableException('EvmChainClient::commitmentFor is not implemented yet.');
    }
}
