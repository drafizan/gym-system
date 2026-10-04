<?php

use App\Enums\MembershipStatus;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DailySalesReportController;
use App\Http\Controllers\DoorAccessHistoryController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberMembershipController;
use App\Http\Controllers\MembershipPackageController;
use App\Http\Controllers\PersonalTrainingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RfidCardController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Models\MemberMembership;
use App\Models\User;
use App\Services\AccessSyncManager;
use App\Support\Audit;
use App\Support\DailySalesReport;
use App\Support\DashboardMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', function (Request $request, DashboardMetrics $metrics, DailySalesReport $report) {
        return view('dashboard', [
            'dashboard' => $metrics->generate(),
            'report' => $report->generate($request->user(), ['date' => now()->toDateString()]),
        ]);
    })->name('dashboard');

    Route::get('/password', [UserManagementController::class, 'password'])->name('password.edit');
    Route::put('/password', [UserManagementController::class, 'updatePassword'])->name('password.update');
    Route::get('/search', GlobalSearchController::class)->name('search.index');

    Route::middleware('permission:members.manage')->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
        Route::get('/members/suspended', [MemberController::class, 'suspended'])->name('members.suspended');
        Route::get('/members/photo-capture', [MemberController::class, 'photoCapture'])->name('members.photo-capture');
        Route::get('/members/expiring-soon', [MemberController::class, 'expiringSoon'])->name('members.expiring-soon');
        Route::get('/members/expired', [MemberController::class, 'expired'])->name('members.expired');
        Route::get('/members/export', [MemberController::class, 'export'])->name('members.export');
        Route::post('/members/import', [MemberController::class, 'import'])->name('members.import');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::patch('/members/{member}/suspend', [MemberController::class, 'suspend'])->name('members.suspend');
        Route::patch('/members/{member}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');
    });

    Route::middleware('permission:memberships.manage')->group(function () {
        Route::get('/membership-packages', [MembershipPackageController::class, 'index'])->name('membership-packages.index');
        Route::get('/membership-packages/create', [MembershipPackageController::class, 'create'])->name('membership-packages.create');
        Route::post('/membership-packages', [MembershipPackageController::class, 'store'])->name('membership-packages.store');
        Route::get('/membership-packages/{membershipPackage}/edit', [MembershipPackageController::class, 'edit'])->name('membership-packages.edit');
        Route::put('/membership-packages/{membershipPackage}', [MembershipPackageController::class, 'update'])->name('membership-packages.update');
        Route::delete('/membership-packages/{membershipPackage}', [MembershipPackageController::class, 'destroy'])->name('membership-packages.destroy');

        Route::get('/members/{member}/memberships/create', [MemberMembershipController::class, 'create'])->name('member-memberships.create');
        Route::post('/members/{member}/memberships', [MemberMembershipController::class, 'store'])->name('member-memberships.store');
        Route::get('/members/{member}/memberships/{memberMembership}/renew', [MemberMembershipController::class, 'renew'])->name('member-memberships.renew');
        Route::post('/members/{member}/memberships/{memberMembership}/renew', [MemberMembershipController::class, 'storeRenewal'])->name('member-memberships.store-renewal');
        Route::patch('/members/{member}/memberships/{memberMembership}/suspend', [MemberMembershipController::class, 'suspend'])->name('member-memberships.suspend');
        Route::get('/memberships/expiring-soon', [MemberMembershipController::class, 'expiringSoon'])->name('memberships.expiring-soon');
        Route::get('/memberships/expired', [MemberMembershipController::class, 'expired'])->name('memberships.expired');
    });

    Route::middleware('permission:sales.manage')->group(function () {
        Route::get('/sales/pos', [SaleController::class, 'create'])->name('sales.pos');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::post('/sales/end-of-day', [SaleController::class, 'endOfDay'])->name('sales.end-of-day');
        Route::get('/sales/history', [SaleController::class, 'history'])->name('sales.history');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    });

    Route::middleware('permission:products.manage')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
        Route::get('/products/price-changes', [ProductController::class, 'priceChanges'])->name('products.price-changes');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('/product-categories', [ProductController::class, 'categories'])->name('product-categories.index');
        Route::get('/product-categories/create', [ProductController::class, 'createCategory'])->name('product-categories.create');
        Route::post('/product-categories', [ProductController::class, 'storeCategory'])->name('product-categories.store');
        Route::get('/product-categories/{productCategory}/edit', [ProductController::class, 'editCategory'])->name('product-categories.edit');
        Route::put('/product-categories/{productCategory}', [ProductController::class, 'updateCategory'])->name('product-categories.update');
        Route::delete('/product-categories/{productCategory}', [ProductController::class, 'destroyCategory'])->name('product-categories.destroy');
    });

    Route::middleware('permission:pt.manage')->group(function () {
        Route::get('/pt/trainers', [PersonalTrainingController::class, 'trainers'])->name('pt.trainers.index');
        Route::get('/pt/trainers/create', [PersonalTrainingController::class, 'createTrainer'])->name('pt.trainers.create');
        Route::post('/pt/trainers', [PersonalTrainingController::class, 'storeTrainer'])->name('pt.trainers.store');
        Route::get('/pt/trainers/{trainer}/edit', [PersonalTrainingController::class, 'editTrainer'])->name('pt.trainers.edit');
        Route::put('/pt/trainers/{trainer}', [PersonalTrainingController::class, 'updateTrainer'])->name('pt.trainers.update');

        Route::get('/pt/packages', [PersonalTrainingController::class, 'packages'])->name('pt.packages.index');
        Route::get('/pt/packages/create', [PersonalTrainingController::class, 'createPackage'])->name('pt.packages.create');
        Route::post('/pt/packages', [PersonalTrainingController::class, 'storePackage'])->name('pt.packages.store');
        Route::get('/pt/packages/{package}/edit', [PersonalTrainingController::class, 'editPackage'])->name('pt.packages.edit');
        Route::put('/pt/packages/{package}', [PersonalTrainingController::class, 'updatePackage'])->name('pt.packages.update');

        Route::get('/pt/member-packages/create', [PersonalTrainingController::class, 'createMemberPackage'])->name('pt.member-packages.create');
        Route::post('/pt/member-packages', [PersonalTrainingController::class, 'storeMemberPackage'])->name('pt.member-packages.store');
        Route::get('/pt/schedule', [PersonalTrainingController::class, 'schedule'])->name('pt.schedule.index');
        Route::get('/pt/schedule/create', [PersonalTrainingController::class, 'createSchedule'])->name('pt.schedule.create');
        Route::post('/pt/schedule', [PersonalTrainingController::class, 'storeSchedule'])->name('pt.schedule.store');
        Route::patch('/pt/schedule/{session}/complete', [PersonalTrainingController::class, 'completeScheduledSession'])->name('pt.schedule.complete');
        Route::patch('/pt/schedule/{session}/cancel', [PersonalTrainingController::class, 'cancelScheduledSession'])->name('pt.schedule.cancel');
        Route::get('/pt/sessions', [PersonalTrainingController::class, 'sessions'])->name('pt.sessions.index');
        Route::get('/pt/sessions/create', [PersonalTrainingController::class, 'createSession'])->name('pt.sessions.create');
        Route::post('/pt/sessions', [PersonalTrainingController::class, 'storeSession'])->name('pt.sessions.store');
        Route::get('/pt/commission-report', [PersonalTrainingController::class, 'commissionReport'])->name('pt.reports.commission');
    });

    Route::middleware('permission:access.manage')->group(function () {
        Route::post('/access/sync-now', function (Request $request, AccessSyncManager $manager): RedirectResponse {
            $synced = $manager->syncPending(500);

            Audit::record($request, 'access', 'manual_sync', null, null, null, [
                'processed' => $synced,
            ]);

            return back()->with('success', $synced.' door access sync record(s) processed.');
        })->name('access.sync-now');

        Route::get('/rfid-cards', [RfidCardController::class, 'index'])->name('rfid-cards.index');
        Route::get('/rfid-cards/history', [RfidCardController::class, 'history'])->name('rfid-cards.history');
        Route::get('/door-access/history', DoorAccessHistoryController::class)->name('door-access.history');
        Route::get('/members/{member}/rfid-cards', [RfidCardController::class, 'memberHistory'])->name('members.rfid-cards.history');
        Route::get('/members/{member}/rfid-cards/create', [RfidCardController::class, 'create'])->name('members.rfid-cards.create');
        Route::post('/members/{member}/rfid-cards', [RfidCardController::class, 'store'])->name('members.rfid-cards.store');
        Route::get('/rfid-cards/{rfidCard}/replace', [RfidCardController::class, 'replace'])->name('rfid-cards.replace');
        Route::post('/rfid-cards/{rfidCard}/replace', [RfidCardController::class, 'storeReplacement'])->name('rfid-cards.store-replacement');
        Route::patch('/rfid-cards/{rfidCard}/deactivate', [RfidCardController::class, 'deactivate'])->name('rfid-cards.deactivate');
        Route::patch('/rfid-cards/{rfidCard}/block', [RfidCardController::class, 'block'])->name('rfid-cards.block');
    });

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/daily-sales', [DailySalesReportController::class, 'index'])->name('reports.daily-sales');
        Route::get('/reports/daily-sales/export', [DailySalesReportController::class, 'export'])->name('reports.daily-sales.export');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/roles', [UserManagementController::class, 'roles'])->name('users.roles');
        Route::get('/users/permissions', [UserManagementController::class, 'permissions'])->name('users.permissions');
        Route::get('/users/password-resets', [UserManagementController::class, 'passwordResets'])->name('users.password-resets');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('/users/{user}/reactivate', [UserManagementController::class, 'reactivate'])->name('users.reactivate');
    });

    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit-trail', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit-trail/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
    });

    Route::middleware('permission:backup.manage')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/backups/{backupLog}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('/backups/{backupLog}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/backup/folders', [SettingsController::class, 'browseBackupFolders'])->name('settings.backup.folders');
        Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::put('/settings/door-access', [SettingsController::class, 'updateDoors'])->name('settings.doors.update');
        Route::put('/settings/backup', [SettingsController::class, 'updateBackup'])->name('settings.backup.update');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        $doorUnits = AccessControllerSetting::query()
            ->whereIn('name', ['1st Floor Door', '2nd Floor Door', 'Door Access Unit 1', 'Door Access Unit 2'])
            ->orderBy('id')
            ->get();
        $lastSyncAt = $doorUnits
            ->filter(fn (AccessControllerSetting $unit): bool => $unit->last_sync_at !== null)
            ->sortByDesc('last_sync_at')
            ->first()
            ?->last_sync_at;

        return view('auth.login', [
            'loginStatus' => [
                'door_units' => $doorUnits->count(),
                'door_statuses' => $doorUnits
                    ->map(fn (AccessControllerSetting $unit): array => [
                        'label' => $unit->statusLabel(),
                        'online' => $unit->isOnline(),
                    ])
                    ->values()
                    ->all(),
                'active_access' => MemberMembership::query()
                    ->where('status', MembershipStatus::Active->value)
                    ->whereDate('end_date', '>=', now()->toDateString())
                    ->whereHas('package', fn ($query) => $query->where('access_allowed', true))
                    ->distinct('member_id')
                    ->count('member_id'),
                'last_sync' => $lastSyncAt?->format('H:i') ?? '-',
                'pending_syncs' => AccessSyncLog::query()->where('status', 'pending')->count(),
            ],
        ]);
    })->name('login');

    Route::post('/login', function (Request $request): RedirectResponse {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $username = Str::lower($validated['username']);
        $throttleKey = $username.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            Audit::record($request, 'auth', 'login_blocked', User::class, null, null, [
                'username' => $username,
                'reason' => 'rate_limited',
                'retry_after_seconds' => RateLimiter::availableIn($throttleKey),
            ]);

            throw ValidationException::withMessages([
                'username' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $credentials = [
            'username' => $username,
            'password' => $validated['password'],
        ];

        $user = User::query()->where('username', $username)->first();

        if (! $user?->is_active) {
            RateLimiter::hit($throttleKey, 60);
            Audit::record($request, 'auth', 'login_failed', User::class, $user?->id, null, [
                'username' => $username,
                'reason' => $user ? 'inactive_user' : 'unknown_user',
            ]);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            Audit::record($request, 'auth', 'login_failed', User::class, $user->id, null, [
                'username' => $username,
                'reason' => 'invalid_password',
            ]);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        Audit::record($request, 'auth', 'login', User::class, $request->user()->id);

        return redirect()->intended(route('dashboard'));
    })->name('login.store');
});

Route::post('/logout', function (Request $request): RedirectResponse {
    Audit::record($request, 'auth', 'logout', User::class, $request->user()->id);

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware(['auth', 'active'])->name('logout');

Route::post('/deployment/update', function (Request $request): RedirectResponse {
    abort_unless($request->user()?->username === 'admin', 403);

    $validated = $request->validate([
        'deployment_key' => ['required', 'string', 'max:255'],
    ]);

    $throttleKey = 'deployment-update|'.$request->user()->id.'|'.$request->ip();

    if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
        return back()->with('error', 'Too many update attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.');
    }

    $secret = (string) config('gym.deployment.secret');

    if (! config('gym.deployment.enabled') || $secret === '') {
        return back()->with('error', 'Deployment updates are not configured yet.');
    }

    if (! hash_equals($secret, $validated['deployment_key'])) {
        RateLimiter::hit($throttleKey, 300);

        return back()->with('error', 'Deployment key is invalid.');
    }

    $scriptPath = (string) config('gym.deployment.script_path');

    if (! is_file($scriptPath) || ! is_executable($scriptPath)) {
        return back()->with('error', 'Deployment script is not available.');
    }

    $process = new Process([$scriptPath], base_path());
    $process->setTimeout((int) config('gym.deployment.timeout', 300));

    try {
        $process->run();
    } catch (ProcessTimedOutException) {
        return back()->with('error', 'Deployment timed out before it finished.');
    }

    $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
    $output = Str::limit($output, 4000);

    if (! $process->isSuccessful()) {
        RateLimiter::hit($throttleKey, 300);

        return back()
            ->with('error', 'Deployment failed. Please review the output.')
            ->with('deployment_output', $output);
    }

    RateLimiter::clear($throttleKey);

    return back()
        ->with('success', 'Deployment completed successfully.')
        ->with('deployment_output', $output);
})->middleware(['auth', 'active'])->name('deployment.update');
