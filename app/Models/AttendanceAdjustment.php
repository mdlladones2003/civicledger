<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['attendance_id', 'actor_id', 'reason', 'old_json', 'new_json'];

    protected function casts(): array
    {
        return ['old_json' => 'array', 'new_json' => 'array'];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
