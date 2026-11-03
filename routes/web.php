<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SectionAssignmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\HourReviewController;
use App\Http\Controllers\MyHoursController;
use App\Http\Controllers\ProofController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionQrController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\RequirePermission;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (): Response {
    return Inertia::render('Public/Home');
})->name('home');

Route::get('/design-system', function (): Response {
    return Inertia::render('DesignSystem');
})->name('design-system');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', EnsureActiveUser::class])->group(function () {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');
});

Route::get('/verify/{token}', [PublicVerificationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('verify.show');

Route::get('/api/verify/{token}', [PublicVerificationController::class, 'api'])
    ->middleware('throttle:30,1')
    ->name('api.verify');

Route::middleware(['auth', EnsureActiveUser::class, EnsurePasswordChanged::class])->group(function () {
    Route::get('/me', [AccountController::class, 'me'])->name('me');
    Route::get('/profile', [AccountController::class, 'show'])->name('profile.show');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Student
    Route::get('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::post('/attendance/scan/challenge', [AttendanceController::class, 'challenge'])
        ->middleware('throttle:30,1')
        ->name('attendance.scan.challenge');
    Route::post('/attendance/scan', [AttendanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('attendance.scan.store');
    Route::get('/my/hours', [MyHoursController::class, 'index'])->name('my.hours');
    Route::get('/me/hours', [MyHoursController::class, 'index'])->name('me.hours');
    Route::post('/events/{event}/register', [RegistrationController::class, 'store'])->name('events.register');
    Route::delete('/events/{event}/register', [RegistrationController::class, 'destroy'])->name('events.unregister');

    // Students see own certificates; signers see candidates + issued list.
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');

    // Events (coordinators manage, students browse)
    Route::get('/events', [EventController::class, 'index'])->name('events.index');

    Route::middleware(RequirePermission::class.':events.manage')->group(function () {
        // Declared before /events/{event} so "create" is not treated as an id.
        Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('events.publish');
        Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::post('/events/{event}/complete', [EventController::class, 'complete'])->name('events.complete');

        Route::post('/events/{event}/sessions', [EventSessionController::class, 'store'])->name('events.sessions.store');
        Route::post('/sessions/{session}/open', [EventSessionController::class, 'open'])->name('sessions.open');
        Route::post('/sessions/{session}/close', [EventSessionController::class, 'close'])->name('sessions.close');

        // Live QR + roster (S10)
        Route::get('/sessions/{session}/qr', [SessionQrController::class, 'show'])->name('sessions.qr');
        Route::get('/sessions/{session}/qr/challenge', [SessionQrController::class, 'challenge'])
            ->middleware('throttle:12,1')
            ->name('sessions.qr.challenge');
        Route::get('/sessions/{session}/roster', [SessionQrController::class, 'rosterJson'])->name('sessions.roster');
        Route::get('/sessions/{session}/live', [SessionQrController::class, 'rosterJson'])->name('sessions.live');
    });

    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');

    // Attendance corrections (permission + policy)
    Route::middleware(RequirePermission::class.':attendance.manual')->group(function () {
        Route::post('/sessions/{session}/attendance/manual', [AttendanceController::class, 'manual'])->name('sessions.attendance.manual');
        Route::patch('/attendance/{attendance}/adjust', [AttendanceController::class, 'adjust'])->name('attendance.adjust');
    });

    // Hour review + proofs
    Route::middleware(RequirePermission::class.':hours.review')->group(function () {
        Route::get('/hours/review', [HourReviewController::class, 'index'])->name('hours.review.index');
        Route::get('/hours/review/{serviceRecord}', [HourReviewController::class, 'show'])->name('hours.review.show');
        Route::post('/hours/review/{serviceRecord}/approve', [HourReviewController::class, 'approve'])->name('hours.approve');
        Route::post('/hours/review/{serviceRecord}/reject', [HourReviewController::class, 'reject'])->name('hours.reject');
        Route::post('/hours/review/{serviceRecord}/revise', [HourReviewController::class, 'revise'])->name('hours.revise');

        Route::get('/proofs', [ProofController::class, 'index'])->name('proofs.index');
        Route::get('/proofs/{proof}', [ProofController::class, 'show'])->name('proofs.show');
        Route::post('/proofs/{proof}/retry', [ProofController::class, 'retry'])->name('proofs.retry');
    });

    // Certificates: issue / revoke
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->middleware(RequirePermission::class.':certificates.issue')
        ->name('certificates.store');
    Route::post('/certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])
        ->middleware(RequirePermission::class.':certificates.revoke')
        ->name('certificates.revoke');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware(RequirePermission::class.':reports.view')
        ->name('reports.index');

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware(RequirePermission::class.':users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles');
            Route::post('/users/{user}/assignments', [SectionAssignmentController::class, 'store'])->name('users.assignments.store');
            Route::delete('/assignments/{assignment}', [SectionAssignmentController::class, 'destroy'])->name('assignments.destroy');
        });

        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware(RequirePermission::class.':audit.view')
            ->name('audit.index');
    });
});
