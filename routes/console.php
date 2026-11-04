<?php

use App\Services\QrChallengeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn (QrChallengeService $challenges) => $challenges->prune())
    ->name('prune-qr-challenges')
    ->daily();
