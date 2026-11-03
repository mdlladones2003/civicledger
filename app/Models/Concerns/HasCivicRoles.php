<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Models\Section;
use App\Models\UserSectionAssignment;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Add `use HasCivicRoles;` to App\Models\User (F1 accounts, roles, scopes).
 */
trait HasCivicRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(UserSectionAssignment::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }

    /** @return array<int,string> */
    public function permissionCodes(): array
    {
        return once(fn () => DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->where('user_roles.user_id', $this->getKey())
            ->distinct()
            ->pluck('permissions.code')
            ->all());
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissionCodes(), true);
    }

    /** @return Collection<int,int> */
    public function assignedSectionIds(string $scopeRole): Collection
    {
        return $this->sectionAssignments()
            ->where('scope_role', $scopeRole)
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id);
    }

    /** @return Collection<int,int> */
    public function assignedProgramIds(string $scopeRole): Collection
    {
        return Section::query()
            ->whereIn('id', $this->assignedSectionIds($scopeRole))
            ->pluck('program_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** Signer scope: shares a section in the given term with the student. */
    public function signerCoversStudent(self $student, int $termId): bool
    {
        return Section::query()
            ->where('term_id', $termId)
            ->whereIn('id', $this->assignedSectionIds(UserSectionAssignment::SIGNER))
            ->whereIn('id', $student->assignedSectionIds(UserSectionAssignment::STUDENT))
            ->exists();
    }
}
