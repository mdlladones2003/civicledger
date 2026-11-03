<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditLogger
{
    /** Validates actor and target because entity_type/entity_id cannot carry an FK (Schema §5). */
    public function log(?User $actor, string $action, Model $entity, ?array $before = null, ?array $after = null): AuditLog
    {
        if (! $entity->exists) {
            throw new InvalidArgumentException('Audit target must be persisted before logging.');
        }

        if ($actor && ! $actor->exists) {
            throw new InvalidArgumentException('Audit actor must be a persisted user.');
        }

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'before_json' => $before,
            'after_json' => $after,
        ]);
    }
}
