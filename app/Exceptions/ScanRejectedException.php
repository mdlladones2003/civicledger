<?php

namespace App\Exceptions;

use RuntimeException;

/** Understandable reasons for QR failures; copy follows the Design Brief §7 catalogue. */
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
        return new self('invalid', 'This isn\'t a CivicLedger attendance code. Scan the code shown by your coordinator.');
    }

    public static function expired(): self
    {
        return new self('expired', 'This code has expired. Ask the coordinator to show the current code.');
    }

    public static function sessionClosed(): self
    {
        return new self('session_closed', 'Attendance for this session is not open.');
    }

    public static function notRegistered(): self
    {
        return new self('not_registered', 'You need to register for this event before scanning.');
    }

    public static function duplicate(?string $eventTitle = null): self
    {
        return new self('duplicate', 'Your attendance for this session is already recorded.', 'already_checked_in', $eventTitle);
    }

    public static function tooSoon(): self
    {
        return new self('too_soon', 'You just checked in. Wait a moment before scanning to check out.');
    }
}
