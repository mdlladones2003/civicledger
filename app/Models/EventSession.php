<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const QR_MODE_CHECK_IN = 'check_in';

    public const QR_MODE_CHECK_OUT = 'check_out';

    protected $fillable = ['event_id', 'starts_at', 'ends_at', 'status', 'qr_challenge_enabled', 'qr_challenge_mode'];

    protected $attributes = ['qr_challenge_enabled' => true];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'qr_challenge_enabled' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hasEnded(?Event $event = null): bool
    {
        $event ??= $this->event;

        return $this->ends_at->lessThanOrEqualTo(now())
            || $event->ends_at->lessThanOrEqualTo(now())
            || in_array($event->status, [Event::STATUS_CANCELLED, Event::STATUS_COMPLETED], true);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(QrChallenge::class, 'session_id');
    }
}
