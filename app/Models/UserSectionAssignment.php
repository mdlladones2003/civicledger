<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSectionAssignment extends Model
{
    public const STUDENT = 'student';

    public const COORDINATOR = 'coordinator';

    public const SIGNER = 'signer';

    public const SCOPE_ROLES = [self::STUDENT, self::COORDINATOR, self::SIGNER];

    protected $fillable = ['user_id', 'section_id', 'scope_role'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
