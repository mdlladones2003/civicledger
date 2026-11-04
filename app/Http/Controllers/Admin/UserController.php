<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Models\UserSectionAssignment;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $search = $request->string('q')->squish()->toString();

        $users = User::query()
            ->with('roles:id,code,label')
            ->when($search, fn (Builder $query, string $term) => $this->applySearch($query, $term))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $account): User => $this->withoutAdministratorEmail($account));

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load(['roles:id,code,label', 'sectionAssignments:id,user_id,section_id,scope_role']);

        return Inertia::render('Admin/UserDetail', [
            'user' => $this->withoutAdministratorEmail($user),
            'roles' => Role::orderBy('code')->get(['id', 'code', 'label']),
            'sections' => Section::with(['program:id,code', 'term:id,code'])->orderBy('code')->get(),
            'scopeRoles' => UserSectionAssignment::SCOPE_ROLES,
            'isCurrentUser' => $user->is($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['coordinator', 'signer'])],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $role = Role::query()->where('code', $validated['role'])->firstOrFail();
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->forceFill([
                'status' => 'active',
                'must_change_password' => true,
            ])->save();
            $user->roles()->attach($role);

            $this->audit->log($request->user(), 'user.created', $user, null, [
                'email' => $user->email,
                'role' => $role->code,
                'must_change_password' => true,
            ]);
        });

        return redirect()->route('admin.users.index')->with('success', 'Staff account created.');
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

    /** Every word must match. Administrator emails are hidden on this screen, so they are not searchable either. */
    private function applySearch(Builder $query, string $search): void
    {
        foreach (explode(' ', $search) as $word) {
            $query->where(fn (Builder $match) => $match
                ->where('first_name', 'like', "%{$word}%")
                ->orWhere('middle_name', 'like', "%{$word}%")
                ->orWhere('last_name', 'like', "%{$word}%")
                ->orWhere(fn (Builder $email) => $email
                    ->where('email', 'like', "%{$word}%")
                    ->whereDoesntHave('roles', fn (Builder $role) => $role->where('code', 'admin'))));
        }
    }

    private function withoutAdministratorEmail(User $account): User
    {
        return $account->roles->contains('code', 'admin') ? $account->makeHidden('email') : $account;
    }
}
