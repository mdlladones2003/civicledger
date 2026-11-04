<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Models\UserSectionAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'sections' => Section::query()
                ->with(['program:id,code,name', 'term:id,code'])
                ->orderBy('code')
                ->get(['id', 'program_id', 'term_id', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $studentRole = Role::firstOrCreate(['code' => 'student'], ['label' => 'Student']);
            $user->roles()->attach($studentRole);
            $user->sectionAssignments()->create([
                'section_id' => $validated['section_id'],
                'scope_role' => UserSectionAssignment::STUDENT,
            ]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
