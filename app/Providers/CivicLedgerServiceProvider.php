<?php

namespace App\Providers;

use App\Services\Chain\ChainClient;
use App\Services\Chain\EvmChainClient;
use App\Services\Chain\FakeChainClient;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/** Register in bootstrap/providers.php. */
class CivicLedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChainClient::class, function () {
            $driver = config('civicledger.proof.driver');

            if ($driver === 'fake' && $this->app->isProduction()) {
                throw new RuntimeException('The fake chain client must not be used in production.');
            }

            return $driver === 'evm' ? new EvmChainClient : new FakeChainClient;
        });
    }
}
