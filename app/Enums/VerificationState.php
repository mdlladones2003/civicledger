<?php

namespace App\Enums;

/** PRD F4.3 / App Flow §4. "Verified" is only returned after a successful on-chain check. */
enum VerificationState: string
{
    case Verified = 'verified';
    case Pending = 'pending';
    case Mismatch = 'mismatch';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
    case Revoked = 'revoked';
    case Superseded = 'superseded';
}
