<?php

namespace App\Services;

use App\Exceptions\ScanRejectedException;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\QrChallenge;
use Illuminate\Support\Facades\Crypt;
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
        abort_unless($session->status === EventSession::STATUS_OPEN, 409, 'This session is not open for attendance.');

        $ttl = (int) config('civicledger.qr.ttl_seconds');
        $expires = now()->addSeconds($ttl);
        $nonce = Str::random(40);

        QrChallenge::create([
            'session_id' => $session->id,
            'nonce_hash' => $this->hash($nonce),
            'expires_at' => $expires,
        ]);

        return [
            'token' => Crypt::encryptString(json_encode(['s' => $session->id, 'n' => $nonce, 'e' => $expires->getTimestamp()])),
            'expires_at' => $expires->toIso8601String(),
            'ttl' => $ttl,
        ];
    }

    /** @throws ScanRejectedException */
    public function verify(string $token): EventSession
    {
        return $this->validatedChallenge($token)['session'];
    }

    /** @throws ScanRejectedException */
    public function remainingSeconds(string $token): int
    {
        $challenge = $this->validatedChallenge($token);

        return max(0, $challenge['expires_at'] - now()->getTimestamp());
    }

    /**
     * @return array{session: EventSession, expires_at: int}
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

        if (! is_array($payload) || ! isset($payload['s'], $payload['n'], $payload['e'])) {
            throw ScanRejectedException::invalid();
        }

        // Server clock only (TRD §5).
        if ((int) $payload['e'] < now()->getTimestamp()) {
            throw ScanRejectedException::expired();
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

        $session = EventSession::with('event')->find($payload['s']);

        if (! $session
            || $session->status !== EventSession::STATUS_OPEN
            || $session->event->status !== Event::STATUS_PUBLISHED) {
            throw ScanRejectedException::sessionClosed();
        }

        return [
            'session' => $session,
            'expires_at' => (int) $payload['e'],
        ];
    }

    /** Housekeeping; scheduled daily in routes/console.php. */
    public function prune(): int
    {
        return QrChallenge::where('expires_at', '<', now()->subDay())->delete();
    }

    private function hash(string $nonce): string
    {
        return hash_hmac('sha256', $nonce, (string) config('app.key'));
    }
}
