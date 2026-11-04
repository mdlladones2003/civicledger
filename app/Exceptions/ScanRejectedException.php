<?php

namespace App\Exceptions;

use RuntimeException;

/** Understandable reasons for QR failures (Design Brief §4). */
class ScanRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?string $result = null,
        public readonly ?string $eventTitle = null,
    ) {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('invalid', 'This QR code is not valid. Ask the coordinator to show the code again and rescan.');
    }

    public static function expired(): self
    {
        return new self('expired', 'This QR code has expired. Scan the code currently shown on the coordinator\'s screen.');
    }

    public static function sessionClosed(): self
    {
        return new self('session_closed', 'Attendance is not open. Ask the coordinator to open this session.');
    }

    public static function challengePaused(): self
    {
        return new self('session_closed', 'QR attendance is temporarily stopped by the coordinator. Wait until a new code is shown.');
    }

    public static function sessionEnded(): self
    {
        return new self('session_closed', 'This event or attendance session has ended. Check-in and check-out are no longer available.');
    }

    public static function notRegistered(): self
    {
        return new self('not_registered', 'You are not registered for this event. Register first, or ask a coordinator.');
    }

    public static function duplicate(?string $eventTitle = null): self
    {
        return new self('duplicate', 'Your attendance for this session is already recorded.', 'already_checked_in', $eventTitle);
    }

    public static function tooSoon(): self
    {
        return new self('too_soon', 'You just checked in. Wait a moment before scanning to check out.');
    }

    public static function wrongChallengeMode(): self
    {
        return new self('wrong_challenge_mode', 'This QR code is for check-in only. Use the current check-out QR code to leave.');
    }

    public static function checkoutWithoutCheckIn(): self
    {
        return new self('checkout_without_check_in', 'You must check in before using the check-out QR code.');
    }
}
