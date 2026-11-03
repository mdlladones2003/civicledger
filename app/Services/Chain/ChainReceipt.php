<?php

namespace App\Services\Chain;

final class ChainReceipt
{
    public function __construct(
        public readonly bool $success,
        public readonly int $confirmations,
        public readonly int $chainId,
        public readonly string $contractAddress,
    ) {}
}
