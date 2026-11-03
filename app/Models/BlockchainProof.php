<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainProof extends Model
{
    // Proof states (separate from approval + validity: Design Brief §3)
    public const QUEUED = 'queued';

    public const SUBMITTED = 'submitted';

    public const CONFIRMED = 'confirmed';

    public const FAILED = 'failed';

    // Validity states
    public const CURRENT = 'current';

    public const SUPERSEDED = 'superseded';

    public const REVOKED = 'revoked';

    protected $fillable = [
        'service_record_version_id', 'certificate_id', 'opaque_record_key', 'commitment',
        'chain_id', 'contract_address', 'tx_hash', 'proof_status', 'validity_status',
        'confirmed_at', 'last_error',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'chain_id' => 'integer'];
    }

    public function serviceRecordVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceRecordVersion::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function recordType(): string
    {
        return $this->certificate_id ? 'certificate' : 'service_record';
    }

    public function recordVersion(): int
    {
        return $this->certificate_id ? 1 : (int) $this->serviceRecordVersion->version;
    }
}
