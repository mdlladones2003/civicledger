<?php

return [
    'qr' => [
        'ttl_seconds' => (int) env('CIVICLEDGER_QR_TTL', 30),
        // Ignore a second scan from the same student within this window (accidental double-scan).
        'min_checkout_seconds' => (int) env('CIVICLEDGER_MIN_CHECKOUT_SECONDS', 30),
    ],

    // School decision pending (PRD §9): rounding/break rules. floor | round | ceil
    'minutes' => [
        'rounding' => env('CIVICLEDGER_ROUNDING', 'floor'),
    ],

    // School decision pending (PRD §9): certificate eligibility.
    'certificate' => [
        'min_minutes' => (int) env('CIVICLEDGER_CERT_MIN_MINUTES', 1),
    ],

    'proof' => [
        // Secret used to derive per-record commitment keys. Keep OUTSIDE the database. Never APP_KEY.
        'secret' => env('CIVICLEDGER_PROOF_SECRET'),
        'driver' => env('CIVICLEDGER_CHAIN_DRIVER', 'fake'), // fake | evm
        'chain_id' => (int) env('CIVICLEDGER_CHAIN_ID', 11155111), // pin at kickoff (Sepolia proposed)
        'contract_address' => env('CIVICLEDGER_CONTRACT_ADDRESS', '0x0000000000000000000000000000000000000000'),
        'confirmations' => (int) env('CIVICLEDGER_CONFIRMATIONS', 3),
        'rpc_url' => env('CIVICLEDGER_RPC_URL'),
        'rpc_fallback_url' => env('CIVICLEDGER_RPC_FALLBACK_URL'),
    ],
];
