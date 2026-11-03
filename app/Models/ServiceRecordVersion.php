<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRecordVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_record_id', 'version', 'canonical_payload_json', 'reason'];

    protected function casts(): array
    {
        return ['canonical_payload_json' => 'array'];
    }

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(BlockchainProof::class);
    }
}
