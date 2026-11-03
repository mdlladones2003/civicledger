<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->with(['roles:id,code,label', 'sectionAssignments.section:id,code,program_id,term_id'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('middle_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (User $account): User {
                if ($account->roles->contains('code', 'admin')) {
                    $account->makeHidden('email');
                }

                return $account;
            });

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => Role::orderBy('code')->get(['id', 'code', 'label']),
            'sections' => Section::with(['program:id,code', 'term:id,code'])->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        abort_if(isset($data['status']) && $data['status'] !== 'active' && $user->is($request->user()), 422, 'You cannot suspend your own account.');

        $before = $user->only(['first_name', 'middle_name', 'last_name', 'status']);
        $user->forceFill($data)->save();
        $this->audit->log($request->user(), 'user.updated', $user, $before, $user->only(['first_name', 'middle_name', 'last_name', 'status']));

        return back()->with('success', 'User updated.');
    }
}
