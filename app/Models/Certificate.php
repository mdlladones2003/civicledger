<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Certificate extends Model
{
    public const ISSUED = 'issued';

    public const REVOKED = 'revoked';

    public const SUPERSEDED = 'superseded';

    protected $fillable = [
        'student_id', 'term_id', 'issued_by', 'public_token', 'certificate_number',
        'approved_minutes', 'status', 'issued_at', 'revoked_at', 'supersedes_certificate_id',
    ];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_certificate_id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_certificate_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CertificateEvent::class);
    }

    public function proof(): HasOne
    {
        return $this->hasOne(BlockchainProof::class);
    }

    public function publicUrl(): string
    {
        return route('verify.show', $this->public_token);
    }
}
