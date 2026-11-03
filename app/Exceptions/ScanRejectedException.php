<?php

namespace App\Exceptions;

use RuntimeException;

/** Understandable reasons for QR failures (Design Brief §4). */
class ScanRejectedException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
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
        return new self('session_closed', 'Attendance for this session is not open.');
    }

    public static function notRegistered(): self
    {
        return new self('not_registered', 'You are not registered for this event. Register first, or ask a coordinator.');
    }

    public static function duplicate(): self
    {
        return new self('duplicate', 'Your attendance for this session is already recorded.');
    }

    public static function tooSoon(): self
    {
        return new self('too_soon', 'You just checked in. Wait a moment before scanning to check out.');
    }
}
