<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'status' => $user->status ?? 'active',
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Account/Profile', [
            'user' => [
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
            'canDeleteAccount' => $this->canDeleteAccount($user),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->canDeleteAccount($request->user()), 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $email = $user->email;

        DB::transaction(function () use ($user, $email): void {
            $this->audit->log(
                $user,
                'account.deleted',
                $user,
                ['status' => $user->status],
                ['status' => 'suspended', 'personal_data_removed' => true],
            );

            $user->roles()->detach();
            $user->sectionAssignments()->delete();
            $user->forceFill([
                'first_name' => 'Deleted',
                'middle_name' => null,
                'last_name' => 'Account',
                'email' => "deleted+{$user->getKey()}@civicledger.invalid",
                'email_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
                'status' => 'suspended',
            ])->save();

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
            }
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function canDeleteAccount(User $user): bool
    {
        return ! $user->hasPermission('users.manage')
            && ! $user->hasPermission('events.manage')
            && ! $user->hasPermission('certificates.issue');
    }
}
