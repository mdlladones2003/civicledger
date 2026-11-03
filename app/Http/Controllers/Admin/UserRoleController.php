<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /** F1.3 / F1.5: permission changes are auditable. */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $adminRoleId = Role::where('code', 'admin')->value('id');

        // Prevent locking yourself out of administration.
        abort_if(
            $user->is($request->user()) && $adminRoleId && ! in_array($adminRoleId, $data['role_ids'], true),
            422,
            'You cannot remove your own administrator role.',
        );

        $before = $user->roles()->pluck('code')->sort()->values()->all();
        $user->roles()->sync($data['role_ids']);
        $after = $user->roles()->pluck('code')->sort()->values()->all();

        $this->audit->log($request->user(), 'user.roles_changed', $user, ['roles' => $before], ['roles' => $after]);

        return back()->with('success', 'Roles updated.');
    }
}
