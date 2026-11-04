<?php

namespace App\Services;

use App\Exceptions\ScanRejectedException;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\QrChallenge;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * TRD §5: short-lived, server-signed challenge = opaque session ref + random nonce + expiry.
 * Crypt::encryptString is authenticated encryption, so the payload is both opaque and tamper-proof.
 */
class QrChallengeService
{
    /** @return array{token:string,expires_at:string,ttl:int} */
    public function issue(EventSession $session): array
    {
        return DB::transaction(function () use ($session): array {
            $session = EventSession::query()
                ->with('event')
                ->lockForUpdate()
                ->findOrFail($session->id);

            abort_unless(
                ! $session->hasEnded(),
                409,
                'This event or attendance session has ended. QR challenges are unavailable.',
            );
            abort_unless(
                $session->status === EventSession::STATUS_OPEN
                    && $session->event->status === Event::STATUS_PUBLISHED,
                409,
                'Attendance is not open. Ask the coordinator to open this session.',
            );
            abort_unless(
                $session->qr_challenge_enabled,
                409,
                'QR attendance is temporarily stopped by the coordinator. Wait until a new code is shown.',
            );

            $now = now();
            $ttl = (int) config('civicledger.qr.ttl_seconds');
            $expiresAt = min(
                $now->getTimestamp() + $ttl,
                $session->ends_at->getTimestamp(),
                $session->event->ends_at->getTimestamp(),
            );
            $ttl = $expiresAt - $now->getTimestamp();
            abort_unless(
                $ttl > 0,
                409,
                'This event or attendance session has ended. QR challenges are unavailable.',
            );
            $expires = $now->copy()->setTimestamp($expiresAt);
            $nonce = Str::random(40);

            QrChallenge::create([
                'session_id' => $session->id,
                'nonce_hash' => $this->hash($nonce),
                'expires_at' => $expires,
            ]);

            return [
                'token' => Crypt::encryptString(json_encode([
                    's' => $session->id,
                    'n' => $nonce,
                    'e' => $expires->getTimestamp(),
                    'm' => $session->qr_challenge_mode,
                ])),
                'expires_at' => $expires->toIso8601String(),
                'ttl' => $ttl,
            ];
        });
    }

    /** @throws ScanRejectedException */
    public function verify(string $token): EventSession
    {
        return $this->validatedChallenge($token)['session'];
    }

    /** @throws ScanRejectedException */
    public function mode(string $token): string
    {
        return $this->validatedChallenge($token)['mode'];
    }

    /** @throws ScanRejectedException */
    public function remainingSeconds(string $token): int
    {
        $challenge = $this->validatedChallenge($token);

        return max(0, $challenge['expires_at'] - now()->getTimestamp());
    }

    /**
     * @return array{session: EventSession, expires_at: int, mode: string}
     *
     * @throws ScanRejectedException
     */
    private function validatedChallenge(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ScanRejectedException::invalid();
        }

        if (! is_array($payload) || ! isset($payload['s'], $payload['n'], $payload['e'], $payload['m'])) {
            throw ScanRejectedException::invalid();
        }

        if (! in_array($payload['m'], [EventSession::QR_MODE_CHECK_IN, EventSession::QR_MODE_CHECK_OUT], true)) {
            throw ScanRejectedException::invalid();
        }

        // Server clock only (TRD §5).
        if ((int) $payload['e'] < now()->getTimestamp()) {
            throw ScanRejectedException::expired();
        }

        $session = EventSession::with('event')->find($payload['s']);

        if (! $session) {
            throw ScanRejectedException::sessionClosed();
        }

        if ($payload['m'] !== $session->qr_challenge_mode) {
            throw ScanRejectedException::wrongChallengeMode();
        }

        if ($session->hasEnded()) {
            throw ScanRejectedException::sessionEnded();
        }

        if (! $session->qr_challenge_enabled) {
            throw ScanRejectedException::challengePaused();
        }

        if ($session->status !== EventSession::STATUS_OPEN || $session->event->status !== Event::STATUS_PUBLISHED) {
            throw ScanRejectedException::sessionClosed();
        }

        $challenge = QrChallenge::query()
            ->where('session_id', $payload['s'])
            ->where('nonce_hash', $this->hash((string) $payload['n']))
            ->first();

        if (! $challenge) {
            throw ScanRejectedException::invalid();
        }

        if ($challenge->expires_at->isPast()) {
            throw ScanRejectedException::expired();
        }

        return [
            'session' => $session,
            'expires_at' => (int) $payload['e'],
            'mode' => (string) $payload['m'],
        ];
    }

    /** Housekeeping; schedule daily. */
    public function prune(): int
    {
        return QrChallenge::where('expires_at', '<', now()->subDay())->delete();
    }

    private function hash(string $nonce): string
    {
        return hash_hmac('sha256', $nonce, (string) config('app.key'));
    }
}
