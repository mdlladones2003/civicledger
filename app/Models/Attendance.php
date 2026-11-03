<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_QR = 'qr';

    public const SOURCE_MANUAL = 'manual';

    protected $table = 'attendance';

    protected $fillable = ['session_id', 'student_id', 'check_in_at', 'check_out_at', 'source', 'status'];

    protected function casts(): array
    {
        return ['check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function serviceRecord(): HasOne
    {
        return $this->hasOne(ServiceRecord::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }

    /** Server-derived duration; never trusted from the client (TRD §4). */
    public function durationMinutes(): int
    {
        if (! $this->check_out_at) {
            return 0;
        }

        $seconds = max(0, $this->check_out_at->getTimestamp() - $this->check_in_at->getTimestamp());
        $minutes = $seconds / 60;

        return (int) match (config('civicledger.minutes.rounding')) {
            'ceil' => ceil($minutes),
            'round' => round($minutes),
            default => floor($minutes),
        };
    }
}
