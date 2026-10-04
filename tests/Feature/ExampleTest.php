<?php

use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\RfidCardStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPackage;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PtMemberPackage;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\PtTrainer;
use App\Models\RfidCard;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\BackupManager;
use App\Support\DahuaBridgeHeartbeat;
use App\Support\DailySalesReport;
use App\Support\DashboardMetrics;
use App\Support\MembershipPeriod;
use App\Support\ProductCatalogManager;
use App\Support\RfidCardManager;
use App\Support\SalesManager;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function roleWithPermissions(array $permissionNames): Role
{
    $role = Role::query()->create([
        'name' => 'test-role-'.str()->random(8),
        'label' => 'Test Role',
    ]);

    $permissions = collect($permissionNames)->map(fn (string $name) => Permission::query()->create([
        'name' => $name,
        'label' => str($name)->headline()->toString(),
    ]));

    $role->permissions()->sync($permissions->pluck('id'));

    return $role;
}

function rfidRequestFor(User $user): Request
{
    $request = Request::create('/test/rfid-cards', 'POST');
    $request->setUserResolver(fn () => $user);

    return $request;
}

function staffRequestFor(User $user, string $path = '/test'): Request
{
    $request = Request::create($path, 'POST');
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('guests are redirected to login from the dashboard', function () {
    $this->get('/')
        ->assertRedirect(route('login'));
});

test('the login page returns a successful response', function () {
    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'host' => '192.168.100.11',
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_at' => now()->setTime(21, 0),
        'last_sync_status' => 'success',
    ]);
    AccessControllerSetting::query()->create([
        'name' => '2nd Floor Door',
        'host' => '192.168.100.12',
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_status' => 'failed',
    ]);
    AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'pending',
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to MACS')
        ->assertSee('1st Floor Door Online')
        ->assertSee('2nd Floor Door Offline')
        ->assertSee('21:00')
        ->assertSee('Pending syncs')
        ->assertSee('1')
        ->assertSee('status-success', false)
        ->assertSee('status-danger', false)
        ->assertSee('Contact support to reset password')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');
});

test('configured door devices remain offline until sync succeeds', function () {
    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'host' => '192.168.100.11',
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_status' => null,
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('1st Floor Door Offline')
        ->assertSee('status-danger', false);
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create([
        'cashier_id' => $user->id,
        'receipt_no' => 'INV-'.now()->format('Ymd').'-TEST',
        'completed_at' => now(),
        'total' => 35,
    ]);
    $sale->items()->create([
        'description' => 'Dashboard test sale',
        'quantity' => 1,
        'unit_price' => 35,
        'total' => 35,
    ]);
    $sale->payments()->create([
        'payment_method' => 'cash',
        'amount' => 35,
        'received_by' => $user->id,
        'paid_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Daily Sales Report')
        ->assertSee('Total Revenue')
        ->assertSee('Sales Breakdown')
        ->assertSee('Weekly Sales Trend')
        ->assertSee('Door Access Status')
        ->assertSee('Door access is not configured yet.')
        ->assertSee('Sales Details')
        ->assertSee($sale->receipt_no)
        ->assertSeeInOrder(['Sales Details', 'Door Access Status']);
});

test('dashboard page shows configured door controller status and latest sync', function () {
    $user = User::factory()->create();

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'host' => '192.168.100.11',
        'port' => 37777,
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_at' => now()->setTime(21, 0),
        'last_sync_status' => 'success',
    ]);

    AccessControllerSetting::query()->create([
        'name' => '2nd Floor Door',
        'host' => '192.168.100.12',
        'port' => 37777,
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_at' => now()->setTime(20, 45),
        'last_sync_status' => 'failed',
    ]);

    AccessSyncLog::query()->create([
        'action' => 'UPDATE_CARD',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Door Access Status')
        ->assertSee('1st Floor Door Online')
        ->assertSee('2nd Floor Door Offline')
        ->assertSee('192.168.100.11:37777')
        ->assertSee('192.168.100.12:37777')
        ->assertSee('Pending Syncs')
        ->assertSee('Awaiting device update');
});

test('branch name is hidden until multibranch is enabled', function () {
    $user = User::factory()->create();

    config([
        'gym.multibranch_enabled' => false,
        'gym.branch_name' => 'Main Branch',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertDontSee('Main Branch');
});

test('outlet filter is hidden until multibranch is enabled', function () {
    $user = User::factory()->create();

    config([
        'gym.multibranch_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertDontSee('Outlet')
        ->assertDontSee('All Outlets');
});

test('users can sign in and out', function () {
    RateLimiter::clear('admin|127.0.0.1');

    $user = User::factory()->create([
        'username' => 'admin',
        'email' => 'admin@gorillamutanz.test',
        'password' => 'Abcd@1234',
    ]);

    $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'Abcd@1234',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'login')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'logout')->exists())->toBeTrue();
});

test('login security policy uses short encrypted browser sessions', function () {
    expect(config('session.lifetime'))->toBe(30)
        ->and(config('session.expire_on_close'))->toBeTrue()
        ->and(config('session.encrypt'))->toBeTrue();
});

test('failed login attempts are recorded in audit trail without passwords', function () {
    RateLimiter::clear('admin|127.0.0.1');

    $user = User::factory()->create([
        'username' => 'admin',
        'password' => 'Abcd@1234',
    ]);

    $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('username');

    $log = AuditLog::query()
        ->where('module', 'auth')
        ->where('action', 'login_failed')
        ->firstOrFail();

    expect($log->record_id)->toBe($user->id)
        ->and($log->new_values)->toMatchArray([
            'username' => 'admin',
            'reason' => 'invalid_password',
        ])
        ->and(json_encode($log->new_values))->not->toContain('wrong-password');
});

test('settings page manages door access ip addresses', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['settings.manage'])->id,
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.11',
        'port' => 37777,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'existing-password',
            'card_number_format' => 'decimal',
            'bridge_url' => 'http://127.0.0.1:8787/dahua',
            'bridge_token' => 'existing-token',
        ],
    ]);
    AccessControllerSetting::query()->create([
        'name' => '2nd Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.12',
        'port' => 37777,
        'is_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Dahua Door Access Configuration')
        ->assertSee('Dahua SDK port is 37777')
        ->assertSee('Card Number Format')
        ->assertSee('Local SDK Bridge URL')
        ->assertSee('192.168.100.11')
        ->assertSee('192.168.100.12');

    $this->actingAs($user)
        ->put(route('settings.doors.update'), [
            'doors' => [
                [
                    'name' => '1st Floor Door',
                    'host' => '192.168.100.21',
                    'port' => 37777,
                    'username' => 'admin',
                    'password' => '',
                    'card_number_format' => 'decimal',
                    'bridge_url' => 'http://127.0.0.1:8787/dahua',
                    'bridge_token' => '',
                    'is_enabled' => '1',
                ],
                [
                    'name' => '2nd Floor Door',
                    'host' => '192.168.100.22',
                    'port' => null,
                    'username' => 'admin',
                    'password' => 'new-device-password',
                    'card_number_format' => 'hex',
                    'bridge_url' => 'http://127.0.0.1:8788/dahua',
                    'bridge_token' => 'new-bridge-token',
                    'is_enabled' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    $firstDoorCredentials = AccessControllerSetting::query()
        ->where('name', '1st Floor Door')
        ->firstOrFail()
        ->encrypted_credentials;
    $secondDoor = AccessControllerSetting::query()
        ->where('name', '2nd Floor Door')
        ->firstOrFail();

    expect(AccessControllerSetting::query()->where('name', '1st Floor Door')->value('host'))->toBe('192.168.100.21')
        ->and(AccessControllerSetting::query()->where('name', '1st Floor Door')->value('port'))->toBe(37777)
        ->and($firstDoorCredentials['password'])->toBe('existing-password')
        ->and($firstDoorCredentials['bridge_token'])->toBe('existing-token')
        ->and($secondDoor->host)->toBe('192.168.100.22')
        ->and($secondDoor->port)->toBe(37777)
        ->and($secondDoor->driver)->toBe('dahua_standalone')
        ->and($secondDoor->encrypted_credentials['password'])->toBe('new-device-password')
        ->and($secondDoor->encrypted_credentials['card_number_format'])->toBe('hex')
        ->and(AuditLog::query()->where('module', 'settings')->where('action', 'door_access_updated')->exists())->toBeTrue();
});

test('settings page manages backup and restore configuration', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['settings.manage'])->id,
    ]);

    $backupPath = storage_path('framework/testing/settings-backups');

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSeeText('Backup & Restore Configuration')
        ->assertSee('Windows / PostgreSQL Installer')
        ->assertSee('Backup Save Location');

    $this->actingAs($user)
        ->put(route('settings.backup.update'), [
            'backup_path' => $backupPath,
            'backup_retention_count' => 12,
            'backup_schedule' => 'manual',
            'backup_os' => 'linux',
            'pg_dump_binary' => 'pg_dump',
            'psql_binary' => 'psql',
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->put(route('settings.backup.update'), [
            'backup_path' => public_path('unsafe-backups'),
            'backup_retention_count' => 12,
            'backup_schedule' => 'manual',
            'backup_os' => 'linux',
            'pg_dump_binary' => 'pg_dump',
            'psql_binary' => 'psql',
            'current_password' => 'password',
        ])
        ->assertSessionHasErrors('backup_path');

    $this->actingAs($user)
        ->put(route('settings.backup.update'), [
            'backup_path' => $backupPath,
            'backup_retention_count' => 12,
            'backup_schedule' => 'manual',
            'backup_os' => 'linux',
            'pg_dump_binary' => 'pg_dump',
            'psql_binary' => 'psql',
            'current_password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(SystemSettings::class);

    expect($settings->get('backup_path'))->toBe($backupPath)
        ->and($settings->get('backup_retention_count'))->toBe(12)
        ->and($settings->get('backup_schedule'))->toBe('manual')
        ->and($settings->get('backup_os'))->toBe('linux')
        ->and($settings->get('backup.pg_dump_binary'))->toBe('pg_dump')
        ->and($settings->get('backup.psql_binary'))->toBe('psql')
        ->and(app(BackupManager::class)->backupPath())->toBe($backupPath)
        ->and(AuditLog::query()->where('module', 'settings')->where('action', 'backup_updated')->exists())->toBeTrue();
});

test('settings backup folder picker is permission protected and returns folders', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['settings.manage'])->id,
    ]);
    $blockedUser = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $folder = storage_path('framework/testing/folder-picker');
    File::ensureDirectoryExists($folder);
    app(SystemSettings::class)->set('backup_path', $folder);

    $this->actingAs($blockedUser)
        ->getJson(route('settings.backup.folders'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('settings.backup.folders', ['path' => $folder]))
        ->assertOk()
        ->assertJsonStructure([
            'current',
            'parent',
            'writable',
            'roots' => [['label', 'path']],
            'directories',
        ]);
});

test('username sign in is normalized for security', function () {
    RateLimiter::clear('admin|127.0.0.1');

    $user = User::factory()->create([
        'username' => 'admin',
        'password' => 'Abcd@1234',
    ]);

    $this->post(route('login.store'), [
        'username' => 'ADMIN',
        'password' => 'Abcd@1234',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('login attempts are rate limited', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => 'Abcd@1234',
    ]);

    RateLimiter::clear('admin|127.0.0.1');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');
    }

    $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'Abcd@1234',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('inactive users cannot sign in', function () {
    RateLimiter::clear('inactive|127.0.0.1');

    User::factory()->create([
        'username' => 'inactive',
        'password' => 'Abcd@1234',
        'is_active' => false,
    ]);

    $this->post(route('login.store'), [
        'username' => 'inactive',
        'password' => 'Abcd@1234',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('permission middleware protects user management', function () {
    $manager = User::factory()->create([
        'role_id' => roleWithPermissions(['dashboard.view'])->id,
    ]);

    $this->actingAs($manager)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('main protected module routes reject users without matching permissions', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['dashboard.view'])->id,
    ]);

    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create();
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['product_category_id' => $category->id]);
    $sale = Sale::factory()->create();
    $auditLog = AuditLog::query()->create(['module' => 'auth', 'action' => 'login']);
    $backupLog = BackupLog::query()->create([
        'filename' => 'backup-test.zip',
        'path' => storage_path('app/backups/backup-test.zip'),
        'disk' => 'local',
        'status' => 'completed',
        'backup_type' => 'manual',
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $trainer = PtTrainer::query()->create([
        'name' => 'Permission Trainer',
        'commission_per_session' => 30,
        'status' => RecordStatus::Active->value,
    ]);
    $ptPackage = PtPackage::query()->create([
        'name' => 'Permission PT Package',
        'sessions_count' => 3,
        'price' => 270,
        'commission_per_session' => 30,
        'status' => RecordStatus::Active->value,
    ]);
    $rfidCard = RfidCard::factory()->create(['member_id' => $member->id]);
    $managedUser = User::factory()->create();

    $protectedRoutes = [
        route('members.index'),
        route('members.create'),
        route('members.show', $member),
        route('members.edit', $member),
        route('membership-packages.index'),
        route('membership-packages.create'),
        route('membership-packages.edit', $package),
        route('member-memberships.create', $member),
        route('sales.pos'),
        route('sales.history'),
        route('sales.receipt', $sale),
        route('products.index'),
        route('products.create'),
        route('products.edit', $product),
        route('product-categories.index'),
        route('product-categories.create'),
        route('product-categories.edit', $category),
        route('pt.trainers.index'),
        route('pt.trainers.create'),
        route('pt.trainers.edit', $trainer),
        route('pt.packages.index'),
        route('pt.packages.create'),
        route('pt.packages.edit', $ptPackage),
        route('pt.member-packages.create'),
        route('pt.schedule.index'),
        route('pt.schedule.create'),
        route('pt.sessions.index'),
        route('pt.sessions.create'),
        route('pt.reports.commission'),
        route('rfid-cards.index'),
        route('rfid-cards.history'),
        route('members.rfid-cards.history', $member),
        route('members.rfid-cards.create', $member),
        route('rfid-cards.replace', $rfidCard),
        route('reports.daily-sales'),
        route('reports.daily-sales.export'),
        route('users.index'),
        route('users.create'),
        route('users.edit', $managedUser),
        route('audit.index'),
        route('audit.show', $auditLog),
        route('backups.index'),
        route('backups.download', $backupLog),
        route('settings.index'),
        route('settings.backup.folders'),
    ];

    foreach ($protectedRoutes as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
});

test('main crud screens render for authorized staff', function () {
    $staff = User::factory()->create([
        'role_id' => roleWithPermissions([
            'members.manage',
            'memberships.manage',
            'access.manage',
            'sales.manage',
            'products.manage',
            'pt.manage',
            'reports.view',
            'users.manage',
            'audit.view',
            'backup.manage',
            'settings.manage',
        ])->id,
    ]);

    $member = Member::factory()->create(['full_name' => 'CRUD Screen Member']);
    $membershipPackage = MembershipPackage::factory()->create(['name' => 'CRUD Monthly']);
    $memberMembership = MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $membershipPackage->id,
        'status' => MembershipStatus::Active->value,
    ]);
    $category = ProductCategory::factory()->create(['name' => 'CRUD Category']);
    $product = Product::factory()->create(['product_category_id' => $category->id, 'name' => 'CRUD Product']);
    $trainer = PtTrainer::query()->create([
        'name' => 'CRUD Trainer',
        'commission_per_session' => 30,
        'status' => RecordStatus::Active->value,
    ]);
    $ptPackage = PtPackage::query()->create([
        'name' => 'CRUD PT Package',
        'sessions_count' => 3,
        'price' => 270,
        'commission_per_session' => 30,
        'validity_days' => 90,
        'status' => RecordStatus::Active->value,
    ]);
    $managedUser = User::factory()->create(['name' => 'CRUD Managed User']);
    $auditLog = AuditLog::query()->create([
        'module' => 'members',
        'action' => 'created',
        'user_id' => $staff->id,
    ]);

    $screenRoutes = [
        route('members.index'),
        route('members.create'),
        route('members.show', $member),
        route('members.edit', $member),
        route('members.expiring-soon'),
        route('members.expired'),
        route('membership-packages.index'),
        route('membership-packages.create'),
        route('membership-packages.edit', $membershipPackage),
        route('member-memberships.create', $member),
        route('member-memberships.renew', [$member, $memberMembership]),
        route('rfid-cards.index'),
        route('rfid-cards.history'),
        route('members.rfid-cards.history', $member),
        route('members.rfid-cards.create', $member),
        route('sales.pos'),
        route('sales.history'),
        route('products.index'),
        route('products.create'),
        route('products.edit', $product),
        route('products.low-stock'),
        route('products.price-changes'),
        route('product-categories.index'),
        route('product-categories.create'),
        route('product-categories.edit', $category),
        route('pt.trainers.index'),
        route('pt.trainers.create'),
        route('pt.trainers.edit', $trainer),
        route('pt.packages.index'),
        route('pt.packages.create'),
        route('pt.packages.edit', $ptPackage),
        route('pt.member-packages.create'),
        route('pt.schedule.index'),
        route('pt.schedule.create'),
        route('pt.sessions.index'),
        route('pt.sessions.create'),
        route('pt.reports.commission'),
        route('reports.daily-sales'),
        route('users.index'),
        route('users.create'),
        route('users.edit', $managedUser),
        route('audit.index'),
        route('audit.show', $auditLog),
        route('backups.index'),
        route('settings.index'),
    ];

    foreach ($screenRoutes as $url) {
        $this->actingAs($staff)->get($url)->assertOk();
    }
});

test('users with permission can manage users and deactivate accounts', function () {
    $administrator = User::factory()->create([
        'role_id' => roleWithPermissions(['users.manage'])->id,
    ]);
    $cashierRole = roleWithPermissions(['sales.manage']);

    $this->actingAs($administrator)
        ->post(route('users.store'), [
            'name' => 'Cashier One',
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'role_id' => $cashierRole->id,
            'password' => 'Abcd@1234',
            'password_confirmation' => 'Abcd@1234',
        ])
        ->assertRedirect(route('users.index'));

    $cashier = User::query()->where('username', 'cashier1')->firstOrFail();

    expect($cashier->is_active)->toBeTrue()
        ->and($cashier->role_id)->toBe($cashierRole->id)
        ->and(AuditLog::query()->where('action', 'created')->exists())->toBeTrue();

    $this->actingAs($administrator)
        ->patch(route('users.deactivate', $cashier))
        ->assertRedirect();

    expect($cashier->fresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'deactivated')->exists())->toBeTrue();
});

test('users can change their password', function () {
    $user = User::factory()->create([
        'password' => 'Abcd@1234',
    ]);

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'Abcd@1234',
            'password' => 'Newpass@1234',
            'password_confirmation' => 'Newpass@1234',
        ])
        ->assertRedirect();

    expect(Hash::check('Newpass@1234', $user->fresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'password_changed')->exists())->toBeTrue();
});

test('deployment update requires configuration', function () {
    $user = User::factory()->create([
        'username' => 'admin',
    ]);

    config([
        'gym.deployment.enabled' => false,
        'gym.deployment.secret' => null,
    ]);

    $this->actingAs($user)
        ->post(route('deployment.update'), [
            'deployment_key' => 'anything',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Deployment updates are not configured yet.');
});

test('deployment update rejects invalid key', function () {
    $user = User::factory()->create([
        'username' => 'admin',
    ]);

    config([
        'gym.deployment.enabled' => true,
        'gym.deployment.secret' => 'correct-key',
        'gym.deployment.script_path' => base_path('scripts/deploy.sh'),
    ]);

    $this->actingAs($user)
        ->post(route('deployment.update'), [
            'deployment_key' => 'wrong-key',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Deployment key is invalid.');
});

test('deployment update is restricted to admin user', function () {
    $user = User::factory()->create([
        'username' => 'manager',
    ]);

    config([
        'gym.deployment.enabled' => true,
        'gym.deployment.secret' => 'correct-key',
    ]);

    $this->actingAs($user)
        ->post(route('deployment.update'), [
            'deployment_key' => 'correct-key',
        ])
        ->assertForbidden();
});

test('audit trail is permission protected and can be viewed', function () {
    $viewer = User::factory()->create([
        'role_id' => roleWithPermissions(['audit.view'])->id,
    ]);
    $log = AuditLog::query()->create([
        'user_id' => $viewer->id,
        'module' => 'auth',
        'action' => 'login',
        'record_type' => User::class,
        'record_id' => $viewer->id,
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAs($viewer)
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Audit Trail')
        ->assertSee('Login');

    $this->actingAs($viewer)
        ->get(route('audit.show', $log))
        ->assertOk()
        ->assertSee('Audit Detail')
        ->assertSee('127.0.0.1');
});

test('audit trail filters by module and blocks unauthorized users', function () {
    $viewer = User::factory()->create([
        'role_id' => roleWithPermissions(['audit.view'])->id,
    ]);
    $unauthorized = User::factory()->create([
        'role_id' => roleWithPermissions(['dashboard.view'])->id,
    ]);

    AuditLog::query()->create(['module' => 'auth', 'action' => 'login']);
    AuditLog::query()->create(['module' => 'users', 'action' => 'created']);

    $this->actingAs($viewer)
        ->get(route('audit.index', ['module' => 'users']))
        ->assertOk()
        ->assertSee('Created')
        ->assertDontSee('Auth</td>', false);

    $this->actingAs($unauthorized)
        ->get(route('audit.index'))
        ->assertForbidden();
});

test('audit payload excludes sensitive values recursively', function () {
    $user = User::factory()->create();

    $request = request();
    $request->setUserResolver(fn () => $user);

    Audit::record($request, 'settings', 'updated', null, null, [
        'password' => 'hidden',
        'safe' => 'visible',
        'nested' => [
            'secret' => 'hidden',
            'name' => 'visible',
        ],
    ], [
        'deployment_key' => 'hidden',
        'encrypted_credentials' => 'hidden',
        'safe' => 'visible',
    ]);

    $log = AuditLog::query()->where('module', 'settings')->firstOrFail();

    expect($log->old_values)->toBe([
        'safe' => 'visible',
        'nested' => [
            'name' => 'visible',
        ],
    ])->and($log->new_values)->toBe([
        'safe' => 'visible',
    ]);
});

test('audit logs do not expose edit or delete routes', function () {
    $viewer = User::factory()->create([
        'role_id' => roleWithPermissions(['audit.view'])->id,
    ]);
    $log = AuditLog::query()->create([
        'module' => 'auth',
        'action' => 'login',
    ]);

    $this->actingAs($viewer)
        ->put('/audit-trail/'.$log->id)
        ->assertMethodNotAllowed();

    $this->actingAs($viewer)
        ->delete('/audit-trail/'.$log->id)
        ->assertMethodNotAllowed();
});

test('critical workflows write audit records across core modules', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['settings.manage', 'members.manage', 'access.manage', 'products.manage', 'sales.manage'])->id,
    ]);
    $member = Member::factory()->create(['rfid_card_number' => null]);
    $category = ProductCategory::factory()->create(['status' => RecordStatus::Active->value]);
    $product = Product::factory()->create([
        'product_category_id' => $category->id,
        'selling_price' => 12,
        'stock_quantity' => 5,
        'status' => RecordStatus::Active->value,
    ]);

    $this->actingAs($user)->put(route('settings.general.update'), [
        'gym_name' => 'Gorilla Mutantz Gym',
        'company_name' => 'Gorilla Mutantz Gym Sdn Bhd',
        'gym_address' => 'Audit address',
        'gym_contact_number' => '+601128520309',
        'receipt_footer' => 'Audit receipt footer',
        'expiring_soon_days' => 7,
        'payment_methods' => ['cash', 'qr', 'debit_credit_card'],
    ])->assertRedirect();

    $this->actingAs($user)
        ->patch(route('members.suspend', $member))
        ->assertRedirect();

    app(RfidCardManager::class)->assign(rfidRequestFor($user), $member, 'AUDIT-RFID-001');

    app(ProductCatalogManager::class)->updateProduct(staffRequestFor($user), $product, [
        'selling_price' => 13,
    ]);

    app(SalesManager::class)->complete(staffRequestFor($user), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'payment_method' => 'cash',
    ]);

    $modules = AuditLog::query()
        ->whereIn('module', ['settings', 'members', 'rfid_cards', 'products', 'sales'])
        ->pluck('module')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($modules)->toBe(['members', 'products', 'rfid_cards', 'sales', 'settings']);
});

test('member registration is protected by permission', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['dashboard.view'])->id,
    ]);

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertForbidden();
});

test('users with permission can register members with automatic member number', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $package = MembershipPackage::factory()->create([
        'name' => 'Monthly',
        'duration_days' => 30,
        'price' => 150,
    ]);
    $activeReferrer = Member::factory()->create([
        'full_name' => 'Active Referrer',
        'status' => 'active',
        'phone' => '+60116667777',
    ]);
    $suspendedReferrer = Member::factory()->create([
        'full_name' => 'Suspended Referrer',
        'status' => 'suspended',
        'phone' => '+60118889999',
    ]);

    $this->actingAs($user)
        ->get(route('members.create'))
        ->assertOk()
        ->assertSee('Member Registration')
        ->assertSee('Personal Information')
        ->assertSee('Membership Information')
        ->assertSee('Monthly')
        ->assertSee('name="membership_package_id"', false)
        ->assertSee('data-price="150.00"', false)
        ->assertSee('data-duration-days="30"', false)
        ->assertSee('data-membership-amount', false)
        ->assertDontSee('Pending module')
        ->assertSee('Active Referrer')
        ->assertSee('data-filterable-combobox', false)
        ->assertSee('data-combobox-search', false)
        ->assertSee('data-filter="active referrer', false)
        ->assertDontSee('Suspended Referrer')
        ->assertSee('Photo')
        ->assertSee('RFID Card');

    $this->actingAs($user)
        ->post(route('members.store'), [
            'full_name' => 'Ahmad Rizal',
            'ic_passport_no' => '900101-10-1234',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '+60123456789',
            'email' => 'ahmad@example.com',
            'rfid_card_number' => 'RFID-10001',
            'emergency_contact_name' => 'Siti Aminah',
            'emergency_contact_phone' => '+60129876543',
            'referred_by_member_id' => $activeReferrer->id,
            'membership_package_id' => $package->id,
            'membership_start_date' => '2026-06-19',
            'membership_amount' => 150,
            'membership_payment_method' => 'cash',
            'membership_payment_status' => 'paid',
        ])
        ->assertRedirect();

    $member = Member::query()->where('full_name', 'Ahmad Rizal')->firstOrFail();

    expect(str_starts_with($member->member_no, 'GMG'.now()->format('y')))->toBeTrue()
        ->and($member->full_name)->toBe('Ahmad Rizal')
        ->and($member->rfid_card_number)->toBe('RFID-10001')
        ->and($member->referred_by_member_id)->toBe($activeReferrer->id)
        ->and($member->created_by)->toBe($user->id)
        ->and(MemberMembership::query()->where('member_id', $member->id)->where('membership_package_id', $package->id)->exists())->toBeFalse()
        ->and(Sale::query()->where('member_id', $member->id)->where('sale_type', SaleType::MembershipSale->value)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'created')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('members.store'), [
            'full_name' => 'Rejected Referral',
            'phone' => '+60125550000',
            'referred_by_member_id' => $suspendedReferrer->id,
        ])
        ->assertSessionHasErrors('referred_by_member_id');
});

test('member edit shows existing membership information but cannot change validity without POS', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $package = MembershipPackage::factory()->create([
        'name' => 'Monthly',
        'duration_days' => 30,
        'price' => 150,
    ]);
    $member = Member::factory()->create([
        'full_name' => 'Existing Member',
        'phone' => '+601128520309',
    ]);
    $membership = MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'start_date' => '2026-06-19',
        'end_date' => '2026-07-18',
        'payment_status' => 'paid',
        'amount' => 150,
    ]);

    $this->actingAs($user)
        ->get(route('members.edit', $member))
        ->assertOk()
        ->assertSee('Monthly')
        ->assertSee('2026-06-19')
        ->assertSee('2026-07-18')
        ->assertSee('150.00');

    $this->actingAs($user)
        ->put(route('members.update', $member), [
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'membership_start_date' => '2026-06-19',
            'membership_end_date' => '2026-07-17',
        ])
        ->assertRedirect(route('members.show', $member));

    expect($membership->fresh()->end_date->format('Y-m-d'))->toBe('2026-07-18')
        ->and(AuditLog::query()->where('module', 'memberships')->where('action', 'expiry_updated')->exists())->toBeFalse();
});

test('member edit card number changes queue rfid sync with membership validity', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $member = Member::factory()->create([
        'full_name' => 'Card Sync Member',
        'phone' => '+601128520309',
    ]);
    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'start_date' => '2026-06-19',
        'end_date' => '2026-07-18',
        'status' => MembershipStatus::Active->value,
    ]);

    $this->actingAs($user)
        ->put(route('members.update', $member), [
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'rfid_card_number' => '0002694641',
        ])
        ->assertRedirect(route('members.show', $member));

    $card = RfidCard::query()->where('member_id', $member->id)->firstOrFail();
    $syncLog = AccessSyncLog::query()->where('rfid_card_id', $card->id)->firstOrFail();

    expect($member->fresh()->rfid_card_number)->toBe('0002694641')
        ->and($card->card_number)->toBe('0002694641')
        ->and($syncLog->action)->toBe('ADD_CARD')
        ->and($syncLog->status)->toBe('pending')
        ->and($syncLog->payload['card_number'])->toBe('0002694641')
        ->and($syncLog->payload['start_date'])->toBe('2026-06-19')
        ->and($syncLog->payload['end_date'])->toBe('2026-07-18');
});

test('members can be searched by name phone ic and member number', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $member = Member::factory()->create([
        'member_no' => 'GMG26060001',
        'full_name' => 'Nur Hidayah',
        'phone' => '+60115550123',
        'ic_passport_no' => 'P1234567',
    ]);

    foreach (['Nur', '+60115550123', 'P1234567', 'GMG26060001'] as $search) {
        $this->actingAs($user)
            ->get(route('members.index', ['search' => $search]))
            ->assertOk()
            ->assertSee($member->full_name);
    }
});

test('members can be filtered by operational status and rfid/photo gaps', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $package = MembershipPackage::factory()->create();
    $activeMember = Member::factory()->create([
        'full_name' => 'Active Filter Member',
        'phone' => '+60111110001',
        'rfid_card_number' => 'RFID-ACTIVE',
        'photo_path' => 'members/photos/active.jpg',
    ]);
    MemberMembership::factory()->create([
        'member_id' => $activeMember->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->addMonth()->toDateString(),
    ]);
    $expiredMember = Member::factory()->create([
        'full_name' => 'Expired Filter Member',
        'phone' => '+60111110002',
        'photo_path' => null,
    ]);
    MemberMembership::factory()->create([
        'member_id' => $expiredMember->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->subDay()->toDateString(),
    ]);
    $noRfidMember = Member::factory()->create([
        'full_name' => 'No RFID Filter Member',
        'phone' => '+60111110003',
        'rfid_card_number' => null,
    ]);

    $this->actingAs($user)
        ->get(route('members.index', ['filter' => 'expired']))
        ->assertOk()
        ->assertSee('Import Members CSV')
        ->assertSee('Expired Filter Member')
        ->assertDontSee('Active Filter Member');

    $this->actingAs($user)
        ->get(route('members.index', ['filter' => 'missing_photo']))
        ->assertOk()
        ->assertSee('Expired Filter Member')
        ->assertDontSee('Active Filter Member');

    $this->actingAs($user)
        ->get(route('members.index', ['filter' => 'no_rfid']))
        ->assertOk()
        ->assertSee('No RFID Filter Member')
        ->assertDontSee('Active Filter Member');
});

test('members can be exported and imported by csv', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    Member::factory()->create([
        'member_no' => 'GMG26069999',
        'full_name' => 'Export Member',
        'phone' => '+60119998888',
    ]);

    $response = $this->actingAs($user)
        ->get(route('members.export'))
        ->assertOk()
        ->assertSee('Export Member');

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $csv = UploadedFile::fake()->createWithContent('members.csv', implode("\n", [
        'full_name,phone,email,gender,rfid_card_number',
        'Imported Member,+60118887777,imported@example.com,male,RFID-IMPORT',
        'Invalid Member,12345,invalid@example.com,male,RFID-BAD',
    ]));

    $this->actingAs($user)
        ->post(route('members.import'), [
            'members_csv' => $csv,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Member import completed. Created 1, skipped 1.');

    expect(Member::query()->where('full_name', 'Imported Member')->exists())->toBeTrue()
        ->and(Member::query()->where('full_name', 'Invalid Member')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'imported')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'exported')->exists())->toBeTrue();
});

test('member phone numbers must use valid malaysian mobile format', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);

    $this->actingAs($user)
        ->post(route('members.store'), [
            'full_name' => 'Invalid Phone',
            'phone' => '12345',
            'emergency_contact_phone' => 'not-a-phone',
        ])
        ->assertSessionHasErrors(['phone', 'emergency_contact_phone']);

    $this->actingAs($user)
        ->post(route('members.store'), [
            'full_name' => 'Valid Phone',
            'phone' => '011-2852 0309',
            'emergency_contact_phone' => '+6065252503',
        ])
        ->assertRedirect();

    expect(Member::query()->where('full_name', 'Valid Phone')->exists())->toBeTrue();
});

test('member profile uses crm style overview sections', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage', 'memberships.manage'])->id,
    ]);
    $referrer = Member::factory()->create([
        'full_name' => 'Referral Source',
        'status' => 'active',
    ]);
    $member = Member::factory()->create([
        'full_name' => 'Profile Member',
        'referred_by_member_id' => $referrer->id,
    ]);
    $package = MembershipPackage::factory()->create([
        'name' => 'Monthly',
    ]);
    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertSee('profile-hero', false)
        ->assertSee('Contact')
        ->assertSee('Membership &amp; Access', false)
        ->assertSee('Referral')
        ->assertSee('Referral Source')
        ->assertSee('Monthly');
});

test('member submenu pages show suspended and missing photo worklists', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    Member::factory()->create([
        'full_name' => 'Active With Photo',
        'status' => 'active',
        'photo_path' => 'members/photos/active.jpg',
    ]);
    Member::factory()->create([
        'full_name' => 'Suspended Member',
        'status' => 'suspended',
        'photo_path' => 'members/photos/suspended.jpg',
    ]);
    Member::factory()->create([
        'full_name' => 'Missing Photo',
        'status' => 'active',
        'photo_path' => null,
    ]);
    $expiringMember = Member::factory()->create([
        'full_name' => 'Expiring Member',
        'status' => 'active',
        'photo_path' => 'members/photos/expiring.jpg',
    ]);
    $package = MembershipPackage::factory()->create();
    MemberMembership::factory()->create([
        'member_id' => $expiringMember->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->addDays(3)->toDateString(),
    ]);
    $expiredMember = Member::factory()->create([
        'full_name' => 'Expired Member',
        'status' => 'active',
        'photo_path' => 'members/photos/expired.jpg',
    ]);
    MemberMembership::factory()->create([
        'member_id' => $expiredMember->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('members.suspended'))
        ->assertOk()
        ->assertSee('Suspended Members')
        ->assertSee('Suspended Member')
        ->assertDontSee('Active With Photo');

    $this->actingAs($user)
        ->get(route('members.photo-capture'))
        ->assertOk()
        ->assertSee('Photo Capture')
        ->assertSee('Missing Photo')
        ->assertDontSee('Active With Photo');

    $this->actingAs($user)
        ->get(route('members.expiring-soon'))
        ->assertOk()
        ->assertSee('Expiring Soon')
        ->assertSee('Member No.')
        ->assertSee('Expiring Member')
        ->assertSee('Expiring')
        ->assertDontSee('Package');

    $this->actingAs($user)
        ->get(route('members.expired'))
        ->assertOk()
        ->assertSee('Expired Members')
        ->assertSee('Member No.')
        ->assertSee('Expired Member')
        ->assertSee('sale-type-pill danger', false)
        ->assertSee('Expired')
        ->assertSee('Active With Photo')
        ->assertDontSee('Suspended Member</td>', false)
        ->assertSee('Missing Photo</td>', false);
});

test('sidebar opens only the active module group', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage', 'memberships.manage'])->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('members.expired'))
        ->assertOk()
        ->assertSee('Expired Members');

    $html = $response->getContent();

    expect($html)->toContain('<span class="menu-text">Members</span>')
        ->and($html)->toContain('href="'.route('members.expiring-soon').'" class="submenu-link')
        ->and($html)->toContain('href="'.route('members.expired').'" class="submenu-link active"')
        ->and($html)->toContain('<span class="menu-text">Memberships</span>')
        ->and($html)->not->toContain('href="'.route('memberships.expiring-soon').'" class="submenu-link')
        ->and($html)->not->toContain('href="'.route('memberships.expired').'" class="submenu-link')
        ->and($html)->not->toContain('<div class="menu-group is-open" data-menu-group>
                            <button type="button" class="menu-link menu-trigger" data-menu-trigger>
                                <span class="menu-icon"><svg class="menu-icon-svg" viewBox="0 0 24 24" aria-hidden="true">
    <rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M7 8h10"></path><path d="M7 12h10"></path><path d="M7 16h6"></path>
</svg>
</span>
                                <span class="menu-text">Memberships</span>');
});

test('members can be updated with audit logging', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage', 'audit.view'])->id,
    ]);
    $member = Member::factory()->create([
        'full_name' => 'Old Name',
        'phone' => '+60111111111',
    ]);

    $this->actingAs($user)
        ->put(route('members.update', $member), [
            'full_name' => 'New Name',
            'phone' => '+60122222222',
            'gender' => 'female',
        ])
        ->assertRedirect(route('members.show', $member));

    expect($member->fresh()->full_name)->toBe('New Name')
        ->and($member->fresh()->updated_by)->toBe($user->id)
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'updated')->exists())->toBeTrue();

    $log = AuditLog::query()->where('module', 'members')->where('action', 'updated')->firstOrFail();

    $this->actingAs($user)
        ->get(route('audit.show', $log))
        ->assertOk()
        ->assertSee('Change Summary')
        ->assertSee('Full Name')
        ->assertSee('Old Name')
        ->assertSee('New Name');
});

test('members can be suspended and reactivated with audit logging', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);
    $member = Member::factory()->create([
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->patch(route('members.suspend', $member))
        ->assertRedirect();

    expect($member->fresh()->status)->toBe('suspended')
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'suspended')->exists())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('members.reactivate', $member))
        ->assertRedirect();

    expect($member->fresh()->status)->toBe('active')
        ->and(AuditLog::query()->where('module', 'members')->where('action', 'reactivated')->exists())->toBeTrue();
});

test('users with permission can manage membership packages', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['memberships.manage'])->id,
    ]);

    $this->actingAs($user)
        ->post(route('membership-packages.store'), [
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 150,
            'access_allowed' => '1',
            'status' => 'active',
        ])
        ->assertRedirect(route('membership-packages.index'));

    $package = MembershipPackage::query()->firstOrFail();

    expect($package->duration_days)->toBe(30)
        ->and((float) $package->price)->toBe(150.0)
        ->and($package->access_allowed)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'package_created')->exists())->toBeTrue();

    $this->actingAs($user)
        ->put(route('membership-packages.update', $package), [
            'name' => 'Monthly Plus',
            'duration_days' => 31,
            'price' => 180,
            'status' => 'active',
        ])
        ->assertRedirect(route('membership-packages.index'));

    expect($package->fresh()->name)->toBe('Monthly Plus')
        ->and($package->fresh()->access_allowed)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'package_updated')->exists())->toBeTrue();
});

test('membership packages can be deleted only when unused', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['memberships.manage'])->id,
    ]);
    $unusedPackage = MembershipPackage::factory()->create([
        'name' => 'Unused Package',
    ]);
    $usedPackage = MembershipPackage::factory()->create([
        'name' => 'Used Package',
    ]);
    MemberMembership::factory()->create([
        'membership_package_id' => $usedPackage->id,
    ]);

    $this->actingAs($user)
        ->delete(route('membership-packages.destroy', $unusedPackage))
        ->assertRedirect(route('membership-packages.index'));

    $this->assertDatabaseMissing('membership_packages', [
        'id' => $unusedPackage->id,
    ]);
    expect(AuditLog::query()->where('action', 'package_deleted')->exists())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('membership-packages.destroy', $usedPackage))
        ->assertRedirect(route('membership-packages.index'))
        ->assertSessionHas('error', 'Package cannot be deleted because membership or sales records are still using it.');

    $this->assertDatabaseHas('membership_packages', [
        'id' => $usedPackage->id,
    ]);
});

test('membership packages cannot be deleted when used by sales history', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['memberships.manage'])->id,
    ]);
    $package = MembershipPackage::factory()->create();
    $sale = Sale::factory()->create();
    SaleItem::query()->create([
        'sale_id' => $sale->id,
        'membership_package_id' => $package->id,
        'description' => 'Membership package sale',
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
    ]);

    $this->actingAs($user)
        ->delete(route('membership-packages.destroy', $package))
        ->assertRedirect(route('membership-packages.index'))
        ->assertSessionHas('error', 'Package cannot be deleted because membership or sales records are still using it.');

    $this->assertDatabaseHas('membership_packages', [
        'id' => $package->id,
    ]);
});

test('memberships can be assigned and renewed using expiry rules', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['memberships.manage'])->id,
    ]);
    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create([
        'name' => 'Monthly',
        'duration_days' => 30,
        'price' => 150,
    ]);

    $this->actingAs($user)
        ->post(route('member-memberships.store', $member), [
            'membership_package_id' => $package->id,
            'start_date' => '2026-06-18',
            'payment_status' => 'paid',
            'amount' => 150,
        ])
        ->assertRedirect(route('members.show', $member));

    $membership = MemberMembership::query()->firstOrFail();

    expect($membership->end_date->format('Y-m-d'))->toBe('2026-07-17')
        ->and($membership->status)->toBe('active')
        ->and(AccessSyncLog::query()->where('action', 'ENABLE_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'assigned')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('member-memberships.store-renewal', [$member, $membership]), [
            'membership_package_id' => $package->id,
            'start_date' => '2026-06-20',
            'payment_status' => 'paid',
            'amount' => 150,
        ])
        ->assertRedirect(route('members.show', $member));

    $renewedBeforeExpiry = MemberMembership::query()->latest('id')->firstOrFail();

    expect($renewedBeforeExpiry->end_date->format('Y-m-d'))->toBe('2026-08-16')
        ->and(AuditLog::query()->where('action', 'renewed')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('member-memberships.store-renewal', [$member, $membership]), [
            'membership_package_id' => $package->id,
            'start_date' => '2026-08-01',
            'payment_status' => 'paid',
            'amount' => 150,
        ])
        ->assertRedirect(route('members.show', $member));

    $renewedAfterExpiry = MemberMembership::query()->latest('id')->firstOrFail();

    expect($renewedAfterExpiry->end_date->format('Y-m-d'))->toBe('2026-08-30');
});

test('membership period helper calculates inclusive expiry dates', function () {
    expect(MembershipPeriod::endDateFromStart(now()->parse('2026-06-19'), 30)->toDateString())->toBe('2026-07-18')
        ->and(MembershipPeriod::endDateFromStart(now()->parse('2026-06-19'), 1)->toDateString())->toBe('2026-06-19')
        ->and(MembershipPeriod::endDateAfterExistingExpiry(now()->parse('2026-07-18'), 30)->toDateString())->toBe('2026-08-17');
});

test('expired membership command marks memberships expired and queues access disable', function () {
    $membership = MemberMembership::factory()->create([
        'status' => 'active',
        'end_date' => now()->subDay()->format('Y-m-d'),
    ]);

    $this->artisan('memberships:detect-expired')
        ->expectsOutput('1 expired membership(s) detected.')
        ->expectsOutput('1 access disable sync record(s) queued.')
        ->assertExitCode(0);

    expect($membership->fresh()->status)->toBe('expired')
        ->and(AccessSyncLog::query()->where('action', 'DISABLE_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'memberships')->where('action', 'expired')->exists())->toBeTrue();
});

test('expired membership command can immediately sync queued access disables', function () {
    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'fake',
        'is_enabled' => true,
    ]);

    $membership = MemberMembership::factory()->create([
        'status' => 'active',
        'end_date' => now()->subDay()->format('Y-m-d'),
    ]);

    $this->artisan('memberships:detect-expired --sync --sync-limit=25')
        ->expectsOutput('1 expired membership(s) detected.')
        ->expectsOutput('1 access disable sync record(s) queued.')
        ->expectsOutput('1 access sync record(s) processed.')
        ->assertExitCode(0);

    $syncLog = AccessSyncLog::query()
        ->where('member_membership_id', $membership->id)
        ->where('action', 'DISABLE_CARD')
        ->firstOrFail();

    expect($syncLog->status)->toBe('success')
        ->and(AccessControllerSetting::query()->firstOrFail()->last_sync_status)->toBe('success');
});

test('expired old membership does not disable access when member has another active access membership', function () {
    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create([
        'access_allowed' => true,
    ]);

    $expiredMembership = MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->subDay()->format('Y-m-d'),
    ]);

    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'status' => 'active',
        'end_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $this->artisan('memberships:detect-expired')
        ->expectsOutput('1 expired membership(s) detected.')
        ->expectsOutput('0 access disable sync record(s) queued.')
        ->assertExitCode(0);

    expect($expiredMembership->fresh()->status)->toBe('expired')
        ->and(AccessSyncLog::query()->where('action', 'DISABLE_CARD')->exists())->toBeFalse();
});

test('rfid cards can be assigned with sync and audit logging', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create();

    $card = app(RfidCardManager::class)->assign(rfidRequestFor($user), $member, ' RFID-90001 ', 'First counter issue');

    expect($card->card_number)->toBe('RFID-90001')
        ->and($card->status)->toBe(RfidCardStatus::Active->value)
        ->and($card->assigned_by)->toBe($user->id)
        ->and($member->fresh()->rfid_card_number)->toBe('RFID-90001')
        ->and(AccessSyncLog::query()->where('rfid_card_id', $card->id)->where('action', 'ADD_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'rfid_cards')->where('action', 'assigned')->exists())->toBeTrue();
});

test('rfid cards prevent duplicate active numbers and multiple active cards per member', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create();
    $otherMember = Member::factory()->create();
    $manager = app(RfidCardManager::class);

    $manager->assign(rfidRequestFor($user), $member, 'RFID-90002');

    expect(fn () => $manager->assign(rfidRequestFor($user), $otherMember, 'RFID-90002'))
        ->toThrow(ValidationException::class);

    expect(fn () => $manager->assign(rfidRequestFor($user), $member, 'RFID-90003'))
        ->toThrow(ValidationException::class);
});

test('rfid card numbers can be updated while keeping the card active', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create();
    $manager = app(RfidCardManager::class);
    $oldCard = $manager->assign(rfidRequestFor($user), $member, 'RFID-90004');

    $updatedCard = $manager->replace(rfidRequestFor($user), $oldCard, 'RFID-90005', 'Card number updated');

    expect($updatedCard->id)->toBe($oldCard->id)
        ->and($updatedCard->status)->toBe(RfidCardStatus::Active->value)
        ->and($updatedCard->member_id)->toBe($member->id)
        ->and($oldCard->fresh()->card_number)->toBe('RFID-90005')
        ->and($oldCard->fresh()->replaced_by_id)->toBeNull()
        ->and($member->fresh()->rfid_card_number)->toBe('RFID-90005')
        ->and(AccessSyncLog::query()->where('rfid_card_id', $oldCard->id)->where('action', 'UPDATE_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'rfid_cards')->where('action', 'updated')->exists())->toBeTrue();
});

test('rfid cards can be deactivated or blocked with access disable sync', function () {
    $user = User::factory()->create();
    $manager = app(RfidCardManager::class);
    $deactivatedCard = $manager->assign(rfidRequestFor($user), Member::factory()->create(), 'RFID-90006');
    $blockedCard = $manager->assign(rfidRequestFor($user), Member::factory()->create(), 'RFID-90007');

    $manager->deactivate(rfidRequestFor($user), $deactivatedCard, 'Manual removal');
    $manager->block(rfidRequestFor($user), $blockedCard, 'Security hold');

    expect($deactivatedCard->fresh()->status)->toBe(RfidCardStatus::Inactive->value)
        ->and($blockedCard->fresh()->status)->toBe(RfidCardStatus::Blocked->value)
        ->and(AccessSyncLog::query()->where('rfid_card_id', $deactivatedCard->id)->where('action', 'DISABLE_CARD')->exists())->toBeTrue()
        ->and(AccessSyncLog::query()->where('rfid_card_id', $blockedCard->id)->where('action', 'DISABLE_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'rfid_cards')->where('action', 'deactivated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'rfid_cards')->where('action', 'blocked')->exists())->toBeTrue();
});

test('rfid card screens support assign replace deactivate block and history', function () {
    $staff = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage', 'access.manage'])->id,
    ]);
    $member = Member::factory()->create([
        'full_name' => 'RFID Screen Member',
        'rfid_card_number' => null,
    ]);
    $otherMember = Member::factory()->create([
        'full_name' => 'Blocked Card Member',
        'rfid_card_number' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('members.rfid-cards.create', $member))
        ->assertOk()
        ->assertSee('Assign RFID Card');

    $this->actingAs($staff)
        ->post(route('members.rfid-cards.store', $member), [
            'card_number' => 'RFID-UI-001',
            'remarks' => 'Issued at counter',
        ])
        ->assertRedirect(route('members.show', $member));

    $activeCard = $member->fresh()->activeRfidCard;
    expect($activeCard?->card_number)->toBe('RFID-UI-001');

    $this->actingAs($staff)
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertSee('RFID Card Control')
        ->assertSee('RFID-UI-001');

    $this->actingAs($staff)
        ->get(route('rfid-cards.replace', $activeCard))
        ->assertOk()
        ->assertSee('Update RFID Card');

    $this->actingAs($staff)
        ->post(route('rfid-cards.store-replacement', $activeCard), [
            'card_number' => 'RFID-UI-002',
            'remarks' => 'Lost card replacement',
        ])
        ->assertRedirect(route('members.show', $member));

    $updatedCard = $member->fresh()->activeRfidCard;

    expect($activeCard->fresh()->status)->toBe(RfidCardStatus::Active->value)
        ->and($activeCard->fresh()->card_number)->toBe('RFID-UI-002')
        ->and($updatedCard?->id)->toBe($activeCard->id);

    $this->actingAs($staff)
        ->get(route('members.rfid-cards.history', $member))
        ->assertOk()
        ->assertSee('RFID-UI-002')
        ->assertDontSee('RFID-UI-001');

    $this->actingAs($staff)
        ->patch(route('rfid-cards.deactivate', $updatedCard), [
            'remarks' => 'Member returned card',
        ])
        ->assertRedirect();

    $blockedCard = app(RfidCardManager::class)->assign(rfidRequestFor($staff), $otherMember, 'RFID-UI-003');

    $this->actingAs($staff)
        ->patch(route('rfid-cards.block', $blockedCard), [
            'remarks' => 'Security hold',
        ])
        ->assertRedirect();

    $this->actingAs($staff)
        ->get(route('rfid-cards.history', ['search' => 'RFID-UI']))
        ->assertOk()
        ->assertSee('RFID-UI-002')
        ->assertSee('RFID-UI-003');

    expect($updatedCard->fresh()->status)->toBe(RfidCardStatus::Inactive->value)
        ->and($blockedCard->fresh()->status)->toBe(RfidCardStatus::Blocked->value)
        ->and($member->fresh()->rfid_card_number)->toBeNull();
});

test('access controller settings store credentials encrypted', function () {
    $setting = AccessControllerSetting::query()->create([
        'name' => 'Main Door',
        'driver' => 'fake',
        'host' => '192.168.1.20',
        'port' => 37777,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'secret-password',
        ],
    ]);

    expect($setting->fresh()->encrypted_credentials)->toBe([
        'username' => 'admin',
        'password' => 'secret-password',
    ]);

    $raw = DB::table('access_controller_settings')
        ->where('id', $setting->id)
        ->value('encrypted_credentials');

    expect($raw)->not->toContain('secret-password');
});

test('access sync command processes pending records for all enabled floor doors', function () {
    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'fake',
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'secret-password',
        ],
    ]);
    AccessControllerSetting::query()->create([
        'name' => '2nd Floor Door',
        'driver' => 'fake',
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'secret-password',
        ],
    ]);

    $log = AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'pending',
        'payload' => [
            'card_number' => 'RFID-91001',
        ],
    ]);

    $this->artisan('access:sync-pending')
        ->expectsOutput('1 access sync record(s) processed.')
        ->assertExitCode(0);

    $settings = AccessControllerSetting::query()->orderBy('id')->get();
    $response = json_decode($log->fresh()->response, true);

    expect($log->fresh()->status)->toBe('success')
        ->and($response['controllers'])->toHaveKeys(['1st Floor Door', '2nd Floor Door'])
        ->and($settings[0]->fresh()->last_sync_status)->toBe('success')
        ->and($settings[0]->fresh()->last_sync_at)->not->toBeNull()
        ->and($settings[1]->fresh()->last_sync_status)->toBe('success')
        ->and($settings[1]->fresh()->last_sync_at)->not->toBeNull();
});

test('header sync door access button processes pending records', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['access.manage'])->id,
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'fake',
        'is_enabled' => true,
    ]);

    $log = AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'pending',
        'payload' => [
            'card_number' => 'RFID-HEADER-001',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('access.sync-now'), false)
        ->assertSee('Sync Door Access Now');

    $this->actingAs($user)
        ->post(route('access.sync-now'))
        ->assertRedirect()
        ->assertSessionHas('success', '1 door access sync record(s) processed.');

    expect($log->fresh()->status)->toBe('success')
        ->and(AuditLog::query()->where('module', 'access')->where('action', 'manual_sync')->exists())->toBeTrue();
});

test('manual door access sync retries failed records after bridge outage', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['access.manage'])->id,
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'fake',
        'is_enabled' => true,
    ]);

    $log = AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'failed',
        'payload' => [
            'card_number' => 'RFID-RETRY-001',
        ],
        'error' => 'Dahua SDK bridge is unavailable for sync.',
    ]);

    $this->actingAs($user)
        ->post(route('access.sync-now'))
        ->assertRedirect()
        ->assertSessionHas('success', '1 door access sync record(s) processed.');

    expect($log->fresh()->status)->toBe('success')
        ->and($log->fresh()->error)->toBeNull();
});

test('dahua bridge heartbeat reports healthy local bridge without restart', function () {
    config()->set('gym.access.dahua_bridge_health_url', 'http://127.0.0.1:8787/health');

    Http::fake([
        'http://127.0.0.1:8787/health' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('access:bridge-heartbeat --status')
        ->expectsOutputToContain('Dahua bridge is healthy.')
        ->expectsOutputToContain('Health URL: http://127.0.0.1:8787/health')
        ->assertExitCode(0);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:8787/health');
});

test('dahua standalone sync sends manual aligned payload to local sdk bridge', function () {
    Http::fake([
        'http://127.0.0.1:8787/health' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:8787/dahua/sync-card' => Http::response(['ok' => true], 200),
    ]);

    $member = Member::factory()->create([
        'member_no' => 'GMG26060001',
        'full_name' => 'Dahua Manual Member',
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.11',
        'port' => null,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'device-password',
            'card_number_format' => 'decimal',
            'bridge_url' => 'http://127.0.0.1:8787/dahua',
            'bridge_token' => 'bridge-token',
        ],
    ]);

    $log = AccessSyncLog::query()->create([
        'member_id' => $member->id,
        'action' => 'ADD_CARD',
        'status' => 'pending',
        'payload' => [
            'member_no' => $member->member_no,
            'card_number' => 'CARD-00012345',
            'card_status' => 'active',
            'start_date' => '2026-07-24',
            'end_date' => '2026-08-22',
            'membership_status' => 'active',
        ],
    ]);

    $this->artisan('access:sync-pending')
        ->expectsOutput('1 access sync record(s) processed.')
        ->assertExitCode(0);

    expect($log->fresh()->error)->toBeNull();

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8787/dahua/sync-card'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $payload['door']['host'] === '192.168.100.11'
            && $payload['door']['port'] === 37777
            && $payload['door']['username'] === 'admin'
            && $payload['door']['password'] === 'device-password'
            && $payload['user']['id'] === 26060001
            && $payload['card']['number'] === '00012345'
            && $payload['card']['format'] === 'decimal'
            && $payload['validity']['end_date'] === '2026-08-22'
            && $payload['manual_reference']['offline_card_capacity'] === 30000;
    });

    $response = json_decode($log->fresh()->response, true);

    expect($log->fresh()->status)->toBe('success')
        ->and($response['controllers']['1st Floor Door']['response'])->not->toHaveKey('password')
        ->and(AccessControllerSetting::query()->firstOrFail()->last_sync_status)->toBe('success');
});

test('dahua standalone door command sends lock, unlock, and time sync payloads to local sdk bridge', function () {
    Http::fake([
        'http://127.0.0.1:8787/health' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:8787/dahua/door-command' => Http::response(['ok' => true], 200),
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.11',
        'port' => 37777,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'device-password',
            'card_number_format' => 'decimal',
            'bridge_url' => 'http://127.0.0.1:8787/dahua',
            'bridge_token' => 'bridge-token',
        ],
    ]);

    $this->artisan('access:door-command unlock "1st Floor Door" --seconds=4')
        ->expectsOutputToContain('Dahua bridge accepted unlock command.')
        ->assertExitCode(0);

    $this->artisan('access:door-command lock "1st Floor Door"')
        ->expectsOutputToContain('Dahua bridge accepted lock command.')
        ->assertExitCode(0);

    $this->artisan('access:door-command sync-time "1st Floor Door"')
        ->expectsOutputToContain('Dahua bridge accepted sync-time command.')
        ->assertExitCode(0);

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8787/dahua/door-command'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $payload['command'] === 'unlock'
            && $payload['door']['name'] === '1st Floor Door'
            && $payload['door']['host'] === '192.168.100.11'
            && $payload['door']['port'] === 37777
            && $payload['door']['username'] === 'admin'
            && $payload['door']['password'] === 'device-password'
            && $payload['options']['unlock_seconds'] === 4;
    });

    Http::assertSent(function ($request): bool {
        return $request->url() === 'http://127.0.0.1:8787/dahua/door-command'
            && $request->data()['command'] === 'lock';
    });

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8787/dahua/door-command'
            && $payload['command'] === 'sync-time'
            && filled($payload['options']['device_time'] ?? null)
            && $payload['options']['timezone'] === config('app.timezone');
    });
});

test('access card list verification confirms controller local authorized cards', function () {
    Http::fake([
        'http://127.0.0.1:8787/health' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:8787/dahua/card-list' => Http::response([
            'ok' => true,
            'cards' => [
                [
                    'record_no' => 7,
                    'card_number' => '00291E41',
                    'card_number_decimal' => '0002694641',
                    'user_id' => '26060001',
                    'status_label' => 'Normal',
                    'is_valid' => true,
                ],
            ],
            'count' => 1,
        ], 200),
    ]);

    $member = Member::factory()->create([
        'member_no' => 'GMG26060001',
        'status' => RecordStatus::Active->value,
    ]);
    $package = MembershipPackage::factory()->create([
        'access_allowed' => true,
    ]);
    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'status' => MembershipStatus::Active->value,
        'end_date' => now()->addMonth()->toDateString(),
    ]);
    RfidCard::factory()->create([
        'member_id' => $member->id,
        'card_number' => '0002694641',
        'status' => RfidCardStatus::Active->value,
    ]);

    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.11',
        'port' => 37777,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'device-password',
            'card_number_format' => 'decimal',
            'bridge_url' => 'http://127.0.0.1:8787/dahua',
            'bridge_token' => 'bridge-token',
        ],
    ]);

    $this->artisan('access:verify-card-list "1st Floor Door"')
        ->expectsOutput('Door: 1st Floor Door')
        ->expectsOutput('Expected authorized cards in MACS: 1')
        ->expectsOutput('Cards stored locally on controller: 1')
        ->expectsOutputToContain('Controller local card list matches MACS authorized cards.')
        ->assertExitCode(0);

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8787/dahua/card-list'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $payload['door']['host'] === '192.168.100.11'
            && $payload['door']['port'] === 37777
            && $payload['door']['username'] === 'admin'
            && $payload['door']['password'] === 'device-password'
            && $payload['options']['limit'] === 500;
    });
});

test('door event history reads access card events from local dahua bridge', function () {
    Http::fake([
        'http://127.0.0.1:8787/health' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:8787/dahua/access-history' => Http::response([
            'ok' => true,
            'records' => [
                [
                    'record_no' => 102,
                    'door_name' => '1st Floor Door',
                    'door_index' => 0,
                    'reader_id' => '1',
                    'time' => '2026-08-05 10:21:34',
                    'card_number' => '00291EB8',
                    'card_number_decimal' => '0002694840',
                    'user_id' => '26060001',
                    'success' => true,
                    'method' => 1,
                    'method_label' => 'Card',
                    'card_type' => 0,
                    'error_code' => 0,
                    'error_label' => 'No error',
                ],
                [
                    'record_no' => 101,
                    'door_name' => '1st Floor Door',
                    'door_index' => 0,
                    'reader_id' => '1',
                    'time' => '2026-08-05 10:18:02',
                    'card_number' => '00291E05',
                    'card_number_decimal' => '0002694661',
                    'user_id' => '',
                    'success' => false,
                    'method' => 1,
                    'method_label' => 'Card',
                    'card_type' => 0,
                    'error_code' => 0x12,
                    'error_label' => 'No door permission',
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['access.manage'])->id,
    ]);

    $door = AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'driver' => 'dahua_standalone',
        'host' => '192.168.100.11',
        'port' => 37777,
        'is_enabled' => true,
        'encrypted_credentials' => [
            'username' => 'admin',
            'password' => 'device-password',
            'card_number_format' => 'decimal',
            'bridge_url' => 'http://127.0.0.1:8787/dahua',
            'bridge_token' => 'bridge-token',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('door-access.history', [
            'door_id' => $door->id,
            'card_number' => 'CARD-0002694840',
            'date_from' => '2026-08-05',
            'date_to' => '2026-08-05',
            'limit' => 25,
        ]))
        ->assertOk()
        ->assertSee('Door Event History')
        ->assertSee('0002694840')
        ->assertSee('Device: 00291EB8')
        ->assertSee('Allowed')
        ->assertSee('0002694661')
        ->assertSee('Rejected')
        ->assertSee('No door permission')
        ->assertDontSee('device-password');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'http://127.0.0.1:8787/dahua/access-history'
            && $request->hasHeader('Authorization', 'Bearer bridge-token')
            && $payload['door']['host'] === '192.168.100.11'
            && $payload['door']['port'] === 37777
            && $payload['door']['username'] === 'admin'
            && $payload['door']['password'] === 'device-password'
            && $payload['filters']['card_number'] === '0002694840'
            && $payload['filters']['date_from'] === '2026-08-05'
            && $payload['filters']['date_to'] === '2026-08-05'
            && $payload['filters']['limit'] === 25;
    });
});

test('access sync command skips pending records when controller is disabled', function () {
    AccessControllerSetting::query()->create([
        'name' => 'Main Door',
        'driver' => 'fake',
        'is_enabled' => false,
    ]);

    $log = AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'pending',
    ]);

    $this->artisan('access:sync-pending')
        ->expectsOutput('0 access sync record(s) processed.')
        ->assertExitCode(0);

    expect($log->fresh()->status)->toBe('pending');
});

test('product catalog backend can create and update categories and products', function () {
    $user = User::factory()->create();
    $manager = app(ProductCatalogManager::class);

    $category = $manager->createCategory(staffRequestFor($user), [
        'name' => 'Supplements',
        'description' => 'Protein and support items.',
    ]);

    $product = $manager->createProduct(staffRequestFor($user), [
        'product_category_id' => $category->id,
        'sku' => 'SUP-ISO-001',
        'name' => 'Isotonic Drink',
        'selling_price' => 5.50,
        'stock_quantity' => 10,
        'reorder_level' => 3,
    ]);

    $updated = $manager->updateProduct(staffRequestFor($user), $product, [
        'selling_price' => 6.00,
        'stock_quantity' => 2,
    ]);

    expect($category->isActive())->toBeTrue()
        ->and($updated->isLowStock())->toBeTrue()
        ->and($updated->priceHistories()->count())->toBe(1)
        ->and(AuditLog::query()->where('module', 'products')->where('action', 'category_created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'products')->where('action', 'created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'products')->where('action', 'updated')->exists())->toBeTrue();
});

test('inactive products cannot be selected for sale', function () {
    $product = Product::factory()->create([
        'sku' => 'SNK-INACTIVE',
        'status' => 'inactive',
    ]);

    expect(fn () => app(ProductCatalogManager::class)->findSellableProductBySku($product->sku))
        ->toThrow(ValidationException::class);
});

test('product search supports sku name and description', function () {
    Product::factory()->create([
        'sku' => 'DRK-WATER-500',
        'name' => 'Mineral Water',
        'description' => 'Cold bottle',
    ]);

    foreach (['DRK-WATER', 'Mineral', 'Cold bottle'] as $search) {
        expect(Product::query()->search($search)->exists())->toBeTrue();
    }
});

test('product management screens create update and show low stock and price changes', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['products.manage'])->id,
    ]);
    $category = ProductCategory::factory()->create(['name' => 'Drinks']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Product List');

    $response = $this->actingAs($user)->post(route('products.store'), [
        'product_category_id' => $category->id,
        'sku' => 'DRK-TEST-001',
        'name' => 'Test Drink',
        'description' => 'Cold drink',
        'selling_price' => 5,
        'stock_quantity' => 3,
        'reorder_level' => 5,
        'status' => RecordStatus::Active->value,
    ]);

    $product = Product::query()->where('sku', 'DRK-TEST-001')->firstOrFail();

    $response->assertRedirect(route('products.edit', $product));

    $this->actingAs($user)->put(route('products.update', $product), [
        'product_category_id' => $category->id,
        'sku' => 'DRK-TEST-001',
        'name' => 'Test Drink Updated',
        'description' => 'Cold drink',
        'selling_price' => 6,
        'stock_quantity' => 3,
        'reorder_level' => 5,
        'status' => RecordStatus::Active->value,
    ])->assertRedirect(route('products.index'));

    $this->actingAs($user)
        ->get(route('products.low-stock'))
        ->assertOk()
        ->assertSee('Test Drink Updated');

    $this->actingAs($user)
        ->get(route('products.price-changes'))
        ->assertOk()
        ->assertSee('Test Drink Updated')
        ->assertSee('RM 6.00');
});

test('product category screens create and update categories', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['products.manage'])->id,
    ]);

    $this->actingAs($user)
        ->get(route('product-categories.index'))
        ->assertOk()
        ->assertSee('Product Categories');

    $this->actingAs($user)->post(route('product-categories.store'), [
        'name' => 'Accessories',
        'description' => 'Gym goods',
        'status' => RecordStatus::Active->value,
    ])->assertRedirect(route('product-categories.index'));

    $category = ProductCategory::query()->where('name', 'Accessories')->firstOrFail();

    $this->actingAs($user)->put(route('product-categories.update', $category), [
        'name' => 'Accessories Updated',
        'description' => 'Gym goods',
        'status' => RecordStatus::Inactive->value,
    ])->assertRedirect(route('product-categories.index'));

    $this->assertDatabaseHas('product_categories', [
        'name' => 'Accessories Updated',
        'status' => RecordStatus::Inactive->value,
    ]);
});

test('product categories can be deleted only when no products are assigned', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['products.manage'])->id,
    ]);
    $emptyCategory = ProductCategory::factory()->create([
        'name' => 'Empty Category',
    ]);
    $usedCategory = ProductCategory::factory()->create([
        'name' => 'Used Category',
    ]);
    Product::factory()->create([
        'product_category_id' => $usedCategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('product-categories.destroy', $emptyCategory))
        ->assertRedirect(route('product-categories.index'));

    $this->assertDatabaseMissing('product_categories', [
        'id' => $emptyCategory->id,
    ]);
    expect(AuditLog::query()->where('action', 'category_deleted')->exists())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('product-categories.destroy', $usedCategory))
        ->assertRedirect(route('product-categories.index'))
        ->assertSessionHas('error', 'Category cannot be deleted because products are still assigned to it.');

    $this->assertDatabaseHas('product_categories', [
        'id' => $usedCategory->id,
    ]);
});

test('sales backend completes product sales with receipt payment and stock deduction', function () {
    $cashier = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Protein Shake 330ml',
        'selling_price' => 12,
        'stock_quantity' => 10,
    ]);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        'payment_method' => 'cash',
    ]);

    expect($sale->receipt_no)->toMatch('/^INV-\d{8}-0001$/')
        ->and((float) $sale->subtotal)->toBe(24.0)
        ->and((float) $sale->total)->toBe(24.0)
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->payments)->toHaveCount(1)
        ->and($product->fresh()->stock_quantity)->toBe(8)
        ->and(AuditLog::query()->where('module', 'sales')->where('action', 'completed')->exists())->toBeTrue();
});

test('personal training products require an active membership', function () {
    $cashier = User::factory()->create();
    $category = ProductCategory::factory()->create([
        'name' => 'Services',
        'status' => RecordStatus::Active->value,
    ]);
    $ptProduct = Product::factory()->create([
        'product_category_id' => $category->id,
        'sku' => 'SRV-PT-3',
        'name' => 'PT Session 3 Pack',
        'selling_price' => 270,
        'stock_quantity' => 9999,
    ]);
    $package = MembershipPackage::factory()->create();
    $expiredMember = Member::factory()->create();
    $activeMember = Member::factory()->create();

    MemberMembership::factory()->create([
        'member_id' => $expiredMember->id,
        'membership_package_id' => $package->id,
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => MembershipStatus::Active->value,
    ]);
    MemberMembership::factory()->create([
        'member_id' => $activeMember->id,
        'membership_package_id' => $package->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(28)->toDateString(),
        'status' => MembershipStatus::Active->value,
    ]);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::PtSession->value,
        'product_items' => [['product_id' => $ptProduct->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::PtSession->value,
        'member_id' => $expiredMember->id,
        'product_items' => [['product_id' => $ptProduct->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'member_id' => $activeMember->id,
        'product_items' => [['product_id' => $ptProduct->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::PtSession->value,
        'member_id' => $activeMember->id,
        'product_items' => [['product_id' => $ptProduct->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]);

    expect((float) $sale->total)->toBe(270.0)
        ->and($sale->member_id)->toBe($activeMember->id)
        ->and(PtMemberPackage::query()->where('member_id', $activeMember->id)->where('total_sessions', 3)->exists())->toBeTrue();
});

test('pos page syncs active personal training packages into saleable products', function () {
    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['sales.manage'])->id,
    ]);

    PtPackage::query()->create([
        'name' => 'PT Session 6 Pack',
        'sessions_count' => 6,
        'price' => 510,
        'commission_per_session' => 30,
        'validity_days' => 90,
        'status' => RecordStatus::Active->value,
    ]);

    expect(Product::query()->where('sku', 'SRV-PT-6')->exists())->toBeFalse();

    $this->actingAs($cashier)
        ->get(route('sales.pos'))
        ->assertOk()
        ->assertSee('PT Session')
        ->assertSee('PT Session 6 Pack');

    $product = Product::query()->with('category')->where('sku', 'SRV-PT-6')->firstOrFail();

    expect($product->name)->toBe('PT Session 6 Pack')
        ->and((float) $product->selling_price)->toBe(510.0)
        ->and($product->category?->name)->toBe('Services');
});

test('personal training management screens create packages trainers and sessions', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['pt.manage', 'sales.manage'])->id,
    ]);
    $member = Member::factory()->create();
    $membershipPackage = MembershipPackage::factory()->create();

    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $membershipPackage->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(29)->toDateString(),
        'status' => MembershipStatus::Active->value,
    ]);

    $this->actingAs($user)->post(route('pt.trainers.store'), [
        'name' => 'Coach Adam',
        'phone' => '+60120000000',
        'email' => 'coach@example.test',
        'specialization' => 'Strength',
        'commission_per_session' => 30,
        'joined_at' => now()->toDateString(),
        'status' => 'active',
    ])->assertRedirect(route('pt.trainers.index'));

    $this->actingAs($user)->post(route('pt.packages.store'), [
        'name' => 'PT Session 3 Pack',
        'sessions_count' => 3,
        'price' => 270,
        'commission_per_session' => 30,
        'validity_days' => 90,
        'status' => 'active',
    ])->assertRedirect(route('pt.packages.index'));

    $trainer = PtTrainer::query()->where('name', 'Coach Adam')->firstOrFail();
    $package = PtPackage::query()->where('name', 'PT Session 3 Pack')->firstOrFail();

    $checkout = $this->actingAs($user)->post(route('pt.member-packages.store'), [
        'member_id' => $member->id,
        'pt_package_id' => $package->id,
        'purchased_at' => now()->toDateString(),
        'payment_method' => 'cash',
    ])->assertRedirect();
    parse_str(parse_url($checkout->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->post(route('sales.store'), ['registration_checkout_token' => $query['registration_checkout'], 'payment_method' => 'cash'])->assertRedirect();

    $memberPackage = PtMemberPackage::query()->where('member_id', $member->id)->firstOrFail();

    $this->actingAs($user)->post(route('pt.sessions.store'), [
        'pt_member_package_id' => $memberPackage->id,
        'trainer_id' => $trainer->id,
        'session_date' => now()->toDateString(),
        'duration_minutes' => 60,
        'status' => 'completed',
    ])->assertRedirect(route('pt.sessions.index'));

    expect($memberPackage->fresh()->used_sessions)->toBe(1)
        ->and(PtSession::query()->where('trainer_id', $trainer->id)->where('commission_amount', 30)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('pt.reports.commission', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Coach Adam')
        ->assertSee('RM 30.00');
});

test('personal training schedule books sessions and completes them later', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['pt.manage'])->id,
    ]);
    $member = Member::factory()->create();
    $secondMember = Member::factory()->create();
    $membershipPackage = MembershipPackage::factory()->create();
    $ptPackage = PtPackage::query()->create([
        'name' => 'PT Session 3 Pack',
        'sessions_count' => 3,
        'price' => 270,
        'commission_per_session' => 30,
        'status' => 'active',
    ]);
    $trainer = PtTrainer::query()->create([
        'name' => 'Coach Schedule',
        'commission_per_session' => 30,
        'status' => 'active',
    ]);

    MemberMembership::factory()->create([
        'member_id' => $member->id,
        'membership_package_id' => $membershipPackage->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(29)->toDateString(),
        'status' => MembershipStatus::Active->value,
    ]);

    MemberMembership::factory()->create([
        'member_id' => $secondMember->id,
        'membership_package_id' => $membershipPackage->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(29)->toDateString(),
        'status' => MembershipStatus::Active->value,
    ]);

    $memberPackage = PtMemberPackage::query()->create([
        'member_id' => $member->id,
        'pt_package_id' => $ptPackage->id,
        'total_sessions' => 3,
        'used_sessions' => 0,
        'price' => 270,
        'purchased_at' => now()->toDateString(),
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $secondMemberPackage = PtMemberPackage::query()->create([
        'member_id' => $secondMember->id,
        'pt_package_id' => $ptPackage->id,
        'total_sessions' => 3,
        'used_sessions' => 1,
        'price' => 270,
        'purchased_at' => now()->toDateString(),
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)->post(route('pt.schedule.store'), [
        'pt_member_package_ids' => [$memberPackage->id, $secondMemberPackage->id],
        'trainer_id' => $trainer->id,
        'session_date' => '2026-07-08',
        'start_time' => '10:00',
        'duration_minutes' => 60,
    ])->assertRedirect(route('pt.schedule.index', ['date' => '2026-07-08']));

    $session = PtSession::query()
        ->where('pt_member_package_id', $memberPackage->id)
        ->where('status', 'scheduled')
        ->firstOrFail();

    expect($memberPackage->fresh()->used_sessions)->toBe(0)
        ->and($secondMemberPackage->fresh()->used_sessions)->toBe(1)
        ->and($session->scheduled_start_at->format('H:i'))->toBe('10:00')
        ->and(PtSession::query()->where('status', 'scheduled')->count())->toBe(2);

    $this->actingAs($user)->post(route('pt.schedule.store'), [
        'pt_member_package_id' => $memberPackage->id,
        'trainer_id' => $trainer->id,
        'session_date' => '2026-07-08',
        'start_time' => '10:30',
        'duration_minutes' => 60,
    ])->assertSessionHasErrors('start_time');

    $this->actingAs($user)
        ->patch(route('pt.schedule.complete', $session))
        ->assertSessionHas('success', 'Scheduled PT session completed.');

    expect($session->fresh()->status)->toBe('completed')
        ->and((float) $session->fresh()->commission_amount)->toBe(30.0)
        ->and($memberPackage->fresh()->used_sessions)->toBe(1);
});

test('pos page submits multiple product rows in one sale', function () {
    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['sales.manage'])->id,
    ]);
    $shake = Product::factory()->create([
        'name' => 'Protein Shake 330ml',
        'selling_price' => 12,
        'stock_quantity' => 10,
    ]);
    $water = Product::factory()->create([
        'name' => 'Mineral Water 500ml',
        'selling_price' => 2,
        'stock_quantity' => 20,
    ]);

    $response = $this->actingAs($cashier)->post(route('sales.store'), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [
            ['product_id' => $shake->id, 'quantity' => 2],
            ['product_id' => $water->id, 'quantity' => 3, 'discount' => 1],
        ],
        'discount' => 2,
        'payment_method' => 'cash',
    ]);

    $sale = Sale::query()->with('items')->firstOrFail();

    $response->assertRedirect(route('sales.receipt', $sale));

    expect($sale->items)->toHaveCount(2)
        ->and((float) $sale->subtotal)->toBe(30.0)
        ->and((float) $sale->discount)->toBe(3.0)
        ->and((float) $sale->total)->toBe(27.0)
        ->and($shake->fresh()->stock_quantity)->toBe(8)
        ->and($water->fresh()->stock_quantity)->toBe(17);
});

test('receipt screen uses configured gym profile and footer details', function () {
    app(SystemSettings::class)->setMany([
        'gym_name' => 'Gorilla Mutantz Gym HQ',
        'company_name' => 'Gorilla Mutantz Gym Sdn Bhd',
        'gym_address' => '1st Floor, Test Street',
        'gym_contact_number' => '+601128520309',
        'receipt_footer' => 'Train hard. See you again.',
    ]);

    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['sales.manage'])->id,
    ]);
    $product = Product::factory()->create([
        'name' => 'Mineral Water 600ml',
        'selling_price' => 1.50,
        'stock_quantity' => 10,
    ]);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]);

    $this->actingAs($cashier)
        ->get(route('sales.receipt', $sale))
        ->assertOk()
        ->assertSee('Gorilla Mutantz Gym HQ')
        ->assertSee('1st Floor, Test Street')
        ->assertSee('+601128520309')
        ->assertSee('Train hard. See you again.');
});

test('global header search finds members cards and receipts', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['dashboard.view', 'members.manage', 'sales.manage'])->id,
    ]);
    $member = Member::factory()->create([
        'full_name' => 'MOHAMAD DRAFIZAN BIN DRAHMAN',
        'member_no' => 'GMG26069999',
        'phone' => '+60119998888',
        'rfid_card_number' => 'CARD-SEARCH-001',
    ]);
    $sale = Sale::factory()->create([
        'member_id' => $member->id,
        'receipt_no' => 'INV-SEARCH-001',
        'total' => 88,
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('search.index', ['q' => 'mohamad drafizan']))
        ->assertOk()
        ->assertSee('MOHAMAD DRAFIZAN BIN DRAHMAN')
        ->assertSee('CARD-SEARCH-001');

    $this->actingAs($user)
        ->get(route('search.index', ['q' => 'card-search-001']))
        ->assertOk()
        ->assertSee('MOHAMAD DRAFIZAN BIN DRAHMAN')
        ->assertSee('CARD-SEARCH-001');

    $this->actingAs($user)
        ->get(route('search.index', ['q' => 'INV-SEARCH-001']))
        ->assertOk()
        ->assertSee('INV-SEARCH-001')
        ->assertSee('RM 88.00')
        ->assertSee(route('sales.receipt', $sale), false);
});

test('pos page rejects duplicate product rows in one sale', function () {
    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['sales.manage'])->id,
    ]);
    $product = Product::factory()->create([
        'selling_price' => 6,
        'stock_quantity' => 10,
    ]);

    $this->actingAs($cashier)->post(route('sales.store'), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        'payment_method' => 'cash',
    ])->assertSessionHasErrors('product_items');

    expect(Sale::query()->count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('product line discount cannot exceed product unit price', function () {
    $cashier = User::factory()->create();
    $product = Product::factory()->create([
        'selling_price' => 6,
        'stock_quantity' => 10,
    ]);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [
            ['product_id' => $product->id, 'quantity' => 3, 'discount' => 7],
        ],
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    expect(Sale::query()->count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

test('sales backend preserves receipt number sequence per day', function () {
    $cashier = User::factory()->create();
    $product = Product::factory()->create([
        'selling_price' => 5,
        'stock_quantity' => 10,
    ]);
    $manager = app(SalesManager::class);

    $first = $manager->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);
    $second = $manager->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    expect($first->receipt_no)->toEndWith('-0001')
        ->and($second->receipt_no)->toEndWith('-0002');
});

test('sales backend blocks inactive or insufficient stock products', function () {
    $cashier = User::factory()->create();
    $inactiveProduct = Product::factory()->create([
        'status' => 'inactive',
        'stock_quantity' => 10,
    ]);
    $lowStockProduct = Product::factory()->create([
        'status' => 'active',
        'stock_quantity' => 1,
    ]);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $inactiveProduct->id, 'quantity' => 1]],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $lowStockProduct->id, 'quantity' => 2]],
    ]))->toThrow(ValidationException::class);
});

test('sales backend creates memberships for membership sales and queues access sync', function () {
    $cashier = User::factory()->create();
    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create([
        'name' => 'Monthly',
        'duration_days' => 30,
        'price' => 150,
    ]);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::MembershipSale->value,
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'start_date' => '2026-06-19',
        'payment_method' => 'cash',
    ]);

    $membership = MemberMembership::query()->firstOrFail();

    expect($sale->items)->toHaveCount(1)
        ->and($membership->end_date->format('Y-m-d'))->toBe('2026-07-18')
        ->and((float) $membership->amount)->toBe(150.0)
        ->and(AccessSyncLog::query()->where('member_membership_id', $membership->id)->where('action', 'ENABLE_CARD')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'memberships')->where('action', 'assigned')->exists())->toBeTrue();
});

test('only managers and administrators can void completed sales and restore product stock', function () {
    $cashierRole = Role::query()->create([
        'name' => 'cashier',
        'label' => 'Cashier',
    ]);
    $managerRole = Role::query()->create([
        'name' => 'manager',
        'label' => 'Manager',
    ]);
    $cashier = User::factory()->create(['role_id' => $cashierRole->id]);
    $manager = User::factory()->create(['role_id' => $managerRole->id]);
    $product = Product::factory()->create([
        'selling_price' => 8,
        'stock_quantity' => 5,
    ]);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    expect(fn () => app(SalesManager::class)->void(staffRequestFor($cashier), $sale, 'Wrong item'))
        ->toThrow(ValidationException::class);

    $voided = app(SalesManager::class)->void(staffRequestFor($manager), $sale, 'Wrong item');

    expect($voided->status)->toBe(SaleStatus::Voided->value)
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and(AuditLog::query()->where('module', 'sales')->where('action', 'voided')->exists())->toBeTrue();
});

test('daily sales report backend totals match completed POS transactions', function () {
    $managerRole = Role::query()->create([
        'name' => 'manager',
        'label' => 'Manager',
    ]);
    $manager = User::factory()->create(['role_id' => $managerRole->id]);
    $cashier = User::factory()->create();
    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create([
        'duration_days' => 30,
        'price' => 150,
    ]);
    $walkInPackage = MembershipPackage::factory()->create([
        'name' => 'Walk-in Citizen',
        'duration_days' => 1,
        'price' => 11,
        'is_walk_in' => true,
    ]);
    $product = Product::factory()->create([
        'selling_price' => 12,
        'stock_quantity' => 10,
    ]);
    $ptProduct = Product::factory()->create([
        'sku' => 'SRV-PT-3',
        'name' => 'PT Session 3 Pack',
        'selling_price' => 270,
        'stock_quantity' => 9999,
    ]);
    $sales = app(SalesManager::class);

    $sales->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::MembershipSale->value,
        'member_id' => $member->id,
        'membership_package_id' => $package->id,
        'start_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);
    $sales->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => 'qr',
    ]);
    $sales->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::PtSession->value,
        'member_id' => $member->id,
        'product_items' => [['product_id' => $ptProduct->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]);
    $sales->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::WalkInSale->value,
        'member_id' => $member->id,
        'membership_package_id' => $walkInPackage->id,
        'start_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);

    $report = app(DailySalesReport::class)->generate($manager, [
        'date' => now()->toDateString(),
    ]);

    expect($report['summary'])->toMatchArray([
        'total_revenue' => 455.0,
        'membership_sales' => 161.0,
        'product_sales' => 24.0,
        'pt_sales' => 270.0,
        'walk_in_sales' => 11.0,
        'other_sales' => 0.0,
        'transaction_count' => 4,
    ])->and($report['payment_breakdown'])->toBe([
        'cash' => 431.0,
        'qr' => 24.0,
    ])->and($report['transactions'])->toHaveCount(4)
        ->and(collect($report['transactions'])->where('category', 'pt')->first()['type_label'])->toBe('PT Sales')
        ->and(collect($report['transactions'])->firstWhere('type', SaleType::WalkInSale->value)['category'])->toBe('membership');
});

test('daily sales report backend filters by cashier payment method and sale type', function () {
    $managerRole = Role::query()->create([
        'name' => 'manager',
        'label' => 'Manager',
    ]);
    $manager = User::factory()->create(['role_id' => $managerRole->id]);
    $cashierOne = User::factory()->create();
    $cashierTwo = User::factory()->create();
    $productOne = Product::factory()->create([
        'selling_price' => 10,
        'stock_quantity' => 10,
    ]);
    $productTwo = Product::factory()->create([
        'selling_price' => 20,
        'stock_quantity' => 10,
    ]);

    app(SalesManager::class)->complete(staffRequestFor($cashierOne), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $productOne->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]);
    app(SalesManager::class)->complete(staffRequestFor($cashierTwo), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $productTwo->id, 'quantity' => 1]],
        'payment_method' => 'qr',
    ]);

    $report = app(DailySalesReport::class)->generate($manager, [
        'date' => now()->toDateString(),
        'cashier_id' => $cashierTwo->id,
        'payment_method' => 'qr',
        'sale_type' => SaleType::ProductSale->value,
    ]);

    expect($report['summary']['total_revenue'])->toBe(20.0)
        ->and($report['summary']['transaction_count'])->toBe(1)
        ->and($report['transactions'][0]['received_by'])->toBe($cashierTwo->name);
});

test('daily sales report exports xlsx using current filters', function () {
    app(SystemSettings::class)->set('company_name', 'Gorilla Mutantz Gym Report Sdn Bhd');

    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['reports.view'])->id,
    ]);
    $product = Product::factory()->create([
        'name' => 'Mineral Water 600ml',
        'selling_price' => 1.50,
        'stock_quantity' => 10,
    ]);

    app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($cashier)->get(route('reports.daily-sales.export', [
        'date_from' => now()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition');

    $path = tempnam(sys_get_temp_dir(), 'daily-sales-export-test-');
    file_put_contents($path, $response->getContent());

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);

    expect($sheetXml)->toContain('Gorilla Mutantz Gym Report Sdn Bhd')
        ->and($sheetXml)->toContain('Daily Financial Summary')
        ->and($sheetXml)->toContain('Daily Transaction Breakdown')
        ->and($sheetXml)->toContain('Mineral Water 600ml');
});

test('cashier daily sales report is restricted to own transactions', function () {
    $cashierRole = Role::query()->create([
        'name' => 'cashier',
        'label' => 'Cashier',
    ]);
    $cashierOne = User::factory()->create(['role_id' => $cashierRole->id]);
    $cashierTwo = User::factory()->create(['role_id' => $cashierRole->id]);
    $productOne = Product::factory()->create([
        'selling_price' => 9,
        'stock_quantity' => 10,
    ]);
    $productTwo = Product::factory()->create([
        'selling_price' => 21,
        'stock_quantity' => 10,
    ]);

    app(SalesManager::class)->complete(staffRequestFor($cashierOne), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $productOne->id, 'quantity' => 1]],
    ]);
    app(SalesManager::class)->complete(staffRequestFor($cashierTwo), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $productTwo->id, 'quantity' => 1]],
    ]);

    $report = app(DailySalesReport::class)->generate($cashierOne, [
        'date' => now()->toDateString(),
        'cashier_id' => $cashierTwo->id,
    ]);

    expect($report['summary']['total_revenue'])->toBe(9.0)
        ->and($report['summary']['transaction_count'])->toBe(1)
        ->and($report['filters']['cashier_id'])->toBe($cashierOne->id);
});

test('dashboard backend calculates member and sales kpis from live records', function () {
    $cashier = User::factory()->create();
    $activeMember = Member::factory()->create(['status' => RecordStatus::Active->value]);
    $expiredMember = Member::factory()->create(['status' => RecordStatus::Active->value]);
    Member::factory()->create(['status' => RecordStatus::Suspended->value]);
    $package = MembershipPackage::factory()->create([
        'duration_days' => 30,
        'price' => 100,
    ]);
    $product = Product::factory()->create([
        'selling_price' => 25,
        'stock_quantity' => 10,
    ]);

    MemberMembership::factory()->create([
        'member_id' => $activeMember->id,
        'membership_package_id' => $package->id,
        'status' => MembershipStatus::Active->value,
        'end_date' => now()->addDays(3)->toDateString(),
    ]);
    MemberMembership::factory()->create([
        'member_id' => $expiredMember->id,
        'membership_package_id' => $package->id,
        'status' => MembershipStatus::Active->value,
        'end_date' => now()->subDay()->toDateString(),
    ]);

    app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::MembershipSale->value,
        'member_id' => $activeMember->id,
        'membership_package_id' => $package->id,
        'payment_method' => 'cash',
    ]);
    app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 2]],
        'payment_method' => 'cash',
    ]);

    $dashboard = app(DashboardMetrics::class)->generate();

    expect($dashboard['kpis']['total_members'])->toBe(3)
        ->and($dashboard['kpis']['active_members'])->toBe(2)
        ->and($dashboard['kpis']['expired_members'])->toBe(1)
        ->and($dashboard['kpis']['expiring_soon'])->toBe(1)
        ->and((float) $dashboard['kpis']['todays_sales'])->toBe(150.0)
        ->and((float) $dashboard['kpis']['membership_sales'])->toBe(100.0)
        ->and((float) $dashboard['kpis']['product_sales'])->toBe(50.0)
        ->and((float) $dashboard['kpis']['monthly_revenue'])->toBe(150.0);
});

test('dashboard backend returns membership overview chart activity and system status', function () {
    $user = User::factory()->create();
    $package = MembershipPackage::factory()->create();
    MemberMembership::factory()->create([
        'membership_package_id' => $package->id,
        'status' => MembershipStatus::Suspended->value,
    ]);
    MemberMembership::factory()->create([
        'membership_package_id' => $package->id,
        'status' => MembershipStatus::Cancelled->value,
    ]);
    AccessControllerSetting::query()->create([
        'name' => '1st Floor Door',
        'host' => '192.168.0.11',
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_at' => now(),
        'last_sync_status' => 'success',
    ]);
    AccessControllerSetting::query()->create([
        'name' => '2nd Floor Door',
        'host' => '192.168.0.12',
        'driver' => 'fake',
        'is_enabled' => true,
        'last_sync_at' => now(),
        'last_sync_status' => 'success',
    ]);
    AccessSyncLog::query()->create([
        'action' => 'ADD_CARD',
        'status' => 'pending',
    ]);
    AuditLog::query()->create([
        'user_id' => $user->id,
        'module' => 'sales',
        'action' => 'completed',
    ]);

    $dashboard = app(DashboardMetrics::class)->generate();

    expect($dashboard['membership_status_overview'][MembershipStatus::Suspended->value])->toBe(1)
        ->and($dashboard['membership_status_overview'][MembershipStatus::Cancelled->value])->toBe(1)
        ->and($dashboard['weekly_sales_chart'])->toHaveCount(7)
        ->and($dashboard['recent_activity'][0]['module'])->toBe('sales')
        ->and($dashboard['system_status']['database'])->toBe('online')
        ->and($dashboard['system_status']['backup_path'])->toBeString()
        ->and($dashboard['system_status']['controller'])->toBe('configured')
        ->and($dashboard['system_status']['controller_units'])->toBe(2)
        ->and($dashboard['system_status']['controller_names'])->toContain('1st Floor Door')
        ->and($dashboard['system_status']['controller_names'])->toContain('2nd Floor Door')
        ->and($dashboard['system_status']['controller_statuses'])->toHaveCount(2)
        ->and($dashboard['system_status']['controller_statuses'][0]['label'])->toBe('1st Floor Door Online')
        ->and($dashboard['system_status']['controller_statuses'][0]['host'])->toBe('192.168.0.11')
        ->and($dashboard['system_status']['controller_statuses'][0]['port'])->toBe(80)
        ->and($dashboard['system_status']['last_access_sync_status'])->toBe('success')
        ->and($dashboard['system_status']['pending_access_syncs'])->toBe(1);
});

test('dashboard page stays under the local network load target', function () {
    $user = User::factory()->create();
    $timestamp = now();

    Sale::query()->insert(collect(range(1, 120))->map(function (int $number) use ($user, $timestamp): array {
        $saleDate = $timestamp->copy()->subDays($number % 7);

        return [
            'receipt_no' => 'INV-'.$saleDate->format('Ymd').'-PERF-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'cashier_id' => $user->id,
            'sale_type' => match ($number % 4) {
                0 => SaleType::MembershipSale->value,
                1 => SaleType::ProductSale->value,
                2 => SaleType::PtSession->value,
                default => SaleType::WalkInSale->value,
            },
            'status' => SaleStatus::Completed->value,
            'subtotal' => 25,
            'discount' => 0,
            'total' => 25,
            'completed_at' => $saleDate->toDateTimeString(),
            'created_at' => $saleDate->toDateTimeString(),
            'updated_at' => $saleDate->toDateTimeString(),
        ];
    })->all());

    $startedAt = microtime(true);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(microtime(true) - $startedAt)->toBeLessThan(3.0);
});

test('system settings backend returns defaults and validates updates', function () {
    $settings = app(SystemSettings::class);

    expect($settings->get('gym_name'))->toBe('Gorilla Mutantz Gym Sdn Bhd')
        ->and($settings->get('company_name'))->toBe('Gorilla Mutantz Gym Sdn Bhd')
        ->and($settings->integer('expiring_soon_days'))->toBe(7)
        ->and($settings->paymentMethods())->toContain('cash');

    $settings->setMany([
        'gym_name' => 'Gorilla Mutantz HQ',
        'company_name' => 'Gorilla Mutantz Gym Holdings Sdn Bhd',
        'expiring_soon_days' => 14,
        'payment_methods' => ['cash', 'qr'],
    ]);

    expect($settings->get('gym_name'))->toBe('Gorilla Mutantz HQ')
        ->and($settings->get('company_name'))->toBe('Gorilla Mutantz Gym Holdings Sdn Bhd')
        ->and($settings->integer('expiring_soon_days'))->toBe(14)
        ->and($settings->paymentMethods())->toBe(['cash', 'qr']);

    expect(fn () => $settings->set('expiring_soon_days', 0))
        ->toThrow(ValidationException::class);

    expect(fn () => $settings->set('payment_methods', ['bitcoin']))
        ->toThrow(ValidationException::class);
});

test('settings page updates gym profile operations and payment methods', function () {
    $admin = User::factory()->create([
        'role_id' => roleWithPermissions(['settings.manage'])->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Gym Profile &amp; Operations', false)
        ->assertSee('QR Pay');

    $this->actingAs($admin)
        ->put(route('settings.general.update'), [
            'gym_name' => 'Gorilla Mutantz Gym HQ',
            'company_name' => 'Gorilla Mutantz Gym Sdn Bhd',
            'gym_contact_number' => '+601128520309',
            'gym_address' => '1st Floor, Test Street',
            'receipt_footer' => 'Train hard. See you again.',
            'expiring_soon_days' => 10,
            'payment_methods' => ['cash', 'debit_credit_card'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(SystemSettings::class);

    expect($settings->get('gym_name'))->toBe('Gorilla Mutantz Gym HQ')
        ->and($settings->get('gym_address'))->toBe('1st Floor, Test Street')
        ->and($settings->integer('expiring_soon_days'))->toBe(10)
        ->and($settings->paymentMethods())->toBe(['cash', 'debit_credit_card'])
        ->and(AuditLog::query()->where('module', 'settings')->where('action', 'general_updated')->exists())->toBeTrue();
});

test('system settings backend encrypts sensitive values at rest', function () {
    $settings = app(SystemSettings::class);

    $settings->set('access_controller.credentials', [
        'username' => 'admin',
        'password' => 'door-secret',
    ], true);

    expect($settings->get('access_controller.credentials'))->toBe([
        'username' => 'admin',
        'password' => 'door-secret',
    ]);

    $raw = DB::table('settings')
        ->where('key', 'access_controller.credentials')
        ->value('value');

    expect($raw)->not->toContain('door-secret');
});

test('system settings seed default values', function () {
    $this->seed();

    expect(Setting::query()->where('key', 'gym_name')->exists())->toBeTrue()
        ->and(Setting::query()->where('key', 'payment_methods')->exists())->toBeTrue()
        ->and(app(SystemSettings::class)->get('gym_contact_number'))->toBe('+601128520309');
});

test('sales backend respects enabled payment methods from settings', function () {
    app(SystemSettings::class)->set('payment_methods', ['cash']);

    $cashier = User::factory()->create();
    $product = Product::factory()->create([
        'selling_price' => 10,
        'stock_quantity' => 10,
    ]);

    expect(fn () => app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'qr',
    ]))->toThrow(ValidationException::class);

    $sale = app(SalesManager::class)->complete(staffRequestFor($cashier), [
        'sale_type' => SaleType::ProductSale->value,
        'product_items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payment_method' => 'cash',
    ]);

    expect($sale->payments->first()->payment_method)->toBe('cash');
});

test('pos end of day closing creates a daily backup', function () {
    app(SystemSettings::class)->set('backup_path', storage_path('framework/testing/end-of-day-backups'));

    $cashier = User::factory()->create([
        'role_id' => roleWithPermissions(['sales.manage'])->id,
    ]);

    $this->actingAs($cashier)
        ->post(route('sales.end-of-day'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $backup = BackupLog::query()->where('backup_type', 'end_of_day')->firstOrFail();

    expect($backup->status)->toBe('completed')
        ->and($backup->isDownloadable())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'end_of_day_started')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'end_of_day_completed')->exists())->toBeTrue();
});

test('backup page creates downloadable local backup and audit records', function () {
    app(SystemSettings::class)->set('backup_path', storage_path('framework/testing/backups'));

    $admin = User::factory()->create([
        'role_id' => roleWithPermissions(['backup.manage'])->id,
    ]);

    $this->actingAs($admin)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Backup History');

    $this->actingAs($admin)
        ->post(route('backups.store'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $backup = BackupLog::query()->where('status', 'completed')->firstOrFail();

    expect($backup->filename)->toStartWith('backup_')
        ->and($backup->filename)->toEndWith('.zip')
        ->and($backup->isDownloadable())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'started')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'completed')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('backups.download', $backup))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('administrator can restore a completed backup after confirmation', function () {
    config(['database.default' => 'sqlite']);
    app(SystemSettings::class)->set('backup_path', storage_path('framework/testing/backups-restore'));

    $photoPath = storage_path('app/public/members/photos/restore-test.txt');
    File::ensureDirectoryExists(dirname($photoPath));
    File::put($photoPath, 'before restore');

    $admin = User::factory()->create([
        'role_id' => roleWithPermissions(['backup.manage'])->id,
    ]);

    $backup = app(BackupManager::class)->run('manual', $admin);
    File::put($photoPath, 'after backup change');

    $this->actingAs($admin)
        ->post(route('backups.restore', $backup), [
            'restore_confirmation' => 'WRONG',
            'current_password' => 'password',
        ])
        ->assertSessionHasErrors('restore_confirmation');

    $this->actingAs($admin)
        ->post(route('backups.restore', $backup), [
            'restore_confirmation' => 'RESTORE',
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($admin)
        ->post(route('backups.restore', $backup), [
            'restore_confirmation' => ' restore ',
            'current_password' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(File::get($photoPath))->toBe('before restore')
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'restore_started')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('module', 'backup')->where('action', 'restore_completed')->exists())->toBeTrue();
});

test('backup manager records failed backup attempts', function () {
    $blockedPath = storage_path('framework/testing/backup-blocked');
    File::ensureDirectoryExists(dirname($blockedPath));
    File::put($blockedPath, 'not a directory');
    app(SystemSettings::class)->set('backup_path', $blockedPath.'/child');

    $log = app(BackupManager::class)->run('manual');

    expect($log->status)->toBe('failed')
        ->and($log->error_message)->not->toBeEmpty()
        ->and($log->isDownloadable())->toBeFalse();

    File::delete($blockedPath);
});

test('backup routes require backup permission', function () {
    $user = User::factory()->create([
        'role_id' => roleWithPermissions(['members.manage'])->id,
    ]);

    $this->actingAs($user)
        ->get(route('backups.index'))
        ->assertForbidden();
});

test('users and roles submenu destinations show configured access and password reset links', function () {
    $role = roleWithPermissions(['users.manage']);
    $admin = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin)->get(route('users.index'))
        ->assertOk()
        ->assertDontSee('href="'.route('users.roles').'"', false)
        ->assertSee(route('users.permissions'), false)
        ->assertSee(route('users.password-resets'), false);

    $this->get(route('users.index'))->assertOk()
        ->assertSeeInOrder(['System Users', 'Configured Roles'])
        ->assertSee($role->label)->assertSee($role->permissions()->first()->label);
    $this->get(route('users.roles'))->assertRedirect(route('users.index').'#configured-roles');
    $this->get(route('users.permissions'))->assertOk()->assertSee('users.manage')->assertSee($role->label);
    $this->get(route('users.password-resets'))->assertOk()
        ->assertSee($admin->username)
        ->assertSee(route('users.edit', $admin).'#password', false);
    $this->get(route('users.edit', $admin))->assertOk()->assertSee('id="password"', false);
});

test('users and roles submenu destinations enforce user management permission', function () {
    foreach (['users.roles', 'users.permissions', 'users.password-resets'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    $staff = User::factory()->create(['role_id' => roleWithPermissions(['dashboard.view'])->id]);
    $this->actingAs($staff);
    foreach (['users.roles', 'users.permissions', 'users.password-resets'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

test('registration checkout carries membership details to POS and charges the fee exactly once', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['members.manage', 'sales.manage'])->id]);
    $package = MembershipPackage::factory()->create(['name' => 'Monthly Checkout', 'price' => 150, 'duration_days' => 30]);
    MembershipPackage::factory()->create(['name' => 'Registration Fee', 'price' => 65, 'access_allowed' => false]);

    $this->actingAs($staff)->get(route('members.create'))->assertOk()
        ->assertSee('POS Summary')->assertSee('Save &amp; Continue to POS', false)->assertSee('RM 65.00');
    $response = $this->post(route('members.store'), [
        'registration_checkout' => 1,
        'full_name' => 'Checkout Member', 'phone' => '+60123456789',
        'membership_package_id' => $package->id,
        'membership_start_date' => '2026-10-05', 'membership_end_date' => '2026-11-10',
        'membership_amount' => 130, 'membership_payment_method' => 'qr',
    ])->assertRedirect();
    $member = Member::query()->where('full_name', 'Checkout Member')->firstOrFail();
    $url = $response->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $token = $query['registration_checkout'];
    $draft = session('registration_checkouts.'.$token);
    expect($member->memberships()->count())->toBe(0)->and(Sale::query()->count())->toBe(0);
    $this->get($url)->assertOk()->assertSee('Checkout Member')->assertSee('RM 65.00')
        ->assertSee('value="130"', false)->assertSee('value="qr" selected', false);

    $this->post(route('sales.store'), ['registration_checkout_token' => $token])->assertSessionHasErrors('payment_method');
    expect(Sale::query()->count())->toBe(0);
    $this->post(route('sales.store'), [
        'registration_checkout_token' => $token, 'payment_method' => 'qr', 'payment_reference' => 'QR-001',
        'membership_amount' => 1, 'sale_type' => 'membership_renewal', 'registration_fee' => 0,
    ])->assertRedirect();
    $sale = Sale::query()->firstOrFail();
    expect((float) $sale->total)->toBe(195.0)
        ->and($sale->items()->count())->toBe(2)
        ->and($sale->payments()->first()->reference_no)->toBe('QR-001')
        ->and($member->memberships()->count())->toBe(1)
        ->and($member->latestMembership()->first()->payment_status)->toBe('paid')
        ->and($member->latestMembership()->first()->end_date->toDateString())->toBe('2026-11-10');
    $this->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'qr'])
        ->assertRedirect(route('sales.receipt', $sale));
    // A retry with the original draft still resolves to the same database sale.
    $this->withSession(['registration_checkouts' => [$token => $draft]])
        ->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'qr'])
        ->assertRedirect(route('sales.receipt', $sale));
    expect(Sale::query()->count())->toBe(1)->and($member->memberships()->count())->toBe(1);

    $this->post(route('sales.store'), [
        'sale_type' => 'membership_renewal', 'member_id' => $member->id,
        'membership_package_id' => $package->id, 'payment_method' => 'cash', 'registration_fee' => 65,
    ])->assertRedirect();
    $renewal = Sale::query()->latest('id')->first();
    expect((float) $renewal->total)->toBe(150.0)
        ->and($renewal->items()->where('description', 'Registration Fee')->count())->toBe(0);
});

test('registration checkout rejects missing packages expired drafts and staff without POS permission', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['members.manage', 'sales.manage'])->id]);
    $this->actingAs($staff)->post(route('members.store'), [
        'registration_checkout' => 1, 'full_name' => 'Missing Package', 'phone' => '+60123456789',
    ])->assertSessionHasErrors('membership_package_id');
    expect(Member::query()->count())->toBe(0);
    $token = (string) str()->uuid();
    $this->get(route('sales.pos', ['registration_checkout' => $token]))->assertNotFound();
    $this->post(route('sales.store'), ['registration_checkout_token' => $token])->assertNotFound();
    $limitedRole = Role::query()->create(['name' => 'registration-only', 'label' => 'Registration Only']);
    $limitedRole->permissions()->attach(Permission::query()->where('name', 'members.manage')->firstOrFail());
    $limited = User::factory()->create(['role_id' => $limitedRole->id]);
    $this->actingAs($limited)->post(route('members.store'), [
        'registration_checkout' => 1, 'full_name' => 'No POS Access', 'phone' => '+60123456789',
    ])->assertForbidden();
});

test('automated tests cannot launch the native dahua bridge', function () {
    Http::fake(['*' => Http::response(['ok' => false], 503)]);
    $heartbeat = app(DahuaBridgeHeartbeat::class);
    $start = new ReflectionMethod($heartbeat, 'startBridge');
    $result = $start->invoke($heartbeat);
    expect($result['started'])->toBeFalse()
        ->and($result['message'])->toContain('disabled during automated tests');
});

test('editing a member prefills renewal POS without a registration fee', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['members.manage', 'sales.manage'])->id]);
    $member = Member::factory()->create();
    $package = MembershipPackage::factory()->create(['price' => 150, 'duration_days' => 30]);
    $membership = MemberMembership::factory()->create([
        'member_id' => $member->id, 'membership_package_id' => $package->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->addDays(10)->toDateString(),
    ]);
    $this->actingAs($staff)->get(route('members.edit', $member))->assertOk()
        ->assertSee('POS Summary')->assertSee('Save &amp; Continue to POS', false)
        ->assertDontSee('Registration Fee (one time)');
    $response = $this->put(route('members.update', $member), [
        'registration_checkout' => 1, 'full_name' => 'Updated Checkout Member', 'phone' => '+60123456789',
        'membership_package_id' => $package->id, 'membership_start_date' => now()->toDateString(),
        'membership_end_date' => now()->addDays(40)->toDateString(),
        'membership_amount' => 140, 'membership_payment_method' => 'cash',
    ])->assertRedirect();
    expect($membership->fresh()->end_date->toDateString())->toBe(now()->addDays(10)->toDateString());
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $token = $query['registration_checkout'];
    $draft = session('registration_checkouts.'.$token);
    expect($draft['sale_type'])->toBe('membership_renewal')->and($draft['registration_fee'])->toBe(0);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('No registration fee.');
    $this->post(route('sales.store'), ['registration_checkout_token' => $token])->assertSessionHasErrors('payment_method');
    expect($member->memberships()->count())->toBe(1)
        ->and($membership->fresh()->end_date->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and(Sale::query()->count())->toBe(0);
    $this->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'cash'])->assertRedirect();
    $sale = Sale::query()->firstOrFail();
    expect((float) $sale->total)->toBe(140.0)->and($sale->items()->count())->toBe(1)
        ->and($sale->items()->where('description', 'Registration Fee')->count())->toBe(0)
        ->and($member->latestMembership()->first()->end_date->toDateString())->toBe(now()->addDays(40)->toDateString());
    $this->withSession(['registration_checkouts' => [$token => $draft]])
        ->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'cash'])
        ->assertRedirect(route('sales.receipt', $sale));
    expect(Sale::query()->count())->toBe(1)->and($member->memberships()->count())->toBe(2);
});

test('cashiers gain personal training access without losing existing permissions', function () {
    $role = roleWithPermissions(['dashboard.view', 'members.manage', 'sales.manage']);
    $role->update(['name' => 'cashier', 'label' => 'Cashier']);
    $migration = require database_path('migrations/2026_10_04_000001_grant_cashiers_personal_training_access.php');
    $migration->up();
    $migration->up();
    $cashier = User::factory()->create(['role_id' => $role->id]);
    expect($cashier->hasPermission('pt.manage'))->toBeTrue()
        ->and($cashier->hasPermission('sales.manage'))->toBeTrue()
        ->and($cashier->hasPermission('users.manage'))->toBeFalse()
        ->and($role->permissions()->count())->toBe(4);
    $this->actingAs($cashier);
    foreach (['pt.trainers.index', 'pt.member-packages.create', 'pt.schedule.index', 'pt.sessions.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('cashier personal training menu only shows operational links', function () {
    $role = roleWithPermissions(['pt.manage']);
    $role->update(['name' => 'cashier']);
    $cashier = User::factory()->create(['role_id' => $role->id]);
    $response = $this->actingAs($cashier)->get(route('pt.schedule.index'))->assertOk();
    foreach (['pt.trainers.index', 'pt.packages.index', 'pt.reports.commission'] as $route) {
        $response->assertDontSee('href="'.route($route).'"', false);
    }
    foreach (['pt.member-packages.create', 'pt.schedule.create', 'pt.sessions.index'] as $route) {
        $response->assertSee('href="'.route($route).'"', false);
    }
    $response->assertSee('Manage Personal Training');
    $role->update(['name' => 'manager']);
    $this->actingAs($cashier->fresh())->get(route('pt.schedule.index'))->assertOk()
        ->assertSee('href="'.route('pt.trainers.index').'"', false)
        ->assertSee('href="'.route('pt.packages.index').'"', false)
        ->assertSee('href="'.route('pt.reports.commission').'"', false);
});

test('PT assignment prepares POS and creates sessions only after payment', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['pt.manage', 'sales.manage'])->id]);
    $member = Member::factory()->create(['status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $member->id, 'status' => 'active', 'start_date' => now()->subDays(1), 'end_date' => now()->addDays(30)]);
    $package = PtPackage::query()->create(['name' => 'PT Checkout 5', 'sessions_count' => 5, 'price' => 500, 'commission_per_session' => 30, 'validity_days' => 90, 'status' => 'active']);
    $this->actingAs($staff)->get(route('pt.member-packages.create'))->assertOk()->assertSee('POS Summary')->assertSee('Continue to POS');
    $response = $this->post(route('pt.member-packages.store'), [
        'member_id' => $member->id, 'pt_package_id' => $package->id, 'purchased_at' => '2026-10-04',
        'price' => 450, 'notes' => 'Five session pack', 'payment_method' => 'qr',
    ])->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $token = $query['registration_checkout'];
    $draft = session('registration_checkouts.'.$token);
    expect(PtMemberPackage::query()->count())->toBe(0)->and(Sale::query()->count())->toBe(0);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('PT package checkout')->assertSee('value="450"', false);
    $this->post(route('sales.store'), ['registration_checkout_token' => $token])->assertSessionHasErrors('payment_method');
    expect(PtMemberPackage::query()->count())->toBe(0);
    $this->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'qr'])->assertRedirect();
    $balance = PtMemberPackage::query()->firstOrFail();
    $sale = Sale::query()->firstOrFail();
    expect($balance->pt_package_id)->toBe($package->id)->and($balance->total_sessions)->toBe(5)
        ->and($balance->notes)->toBe('Five session pack')->and($balance->purchased_at->toDateString())->toBe('2026-10-04')
        ->and((float) $sale->total)->toBe(450.0)->and($sale->items()->count())->toBe(1);
    $this->withSession(['registration_checkouts' => [$token => $draft]])
        ->post(route('sales.store'), ['registration_checkout_token' => $token, 'payment_method' => 'qr'])
        ->assertRedirect(route('sales.receipt', $sale));
    expect(PtMemberPackage::query()->count())->toBe(1)->and(Sale::query()->count())->toBe(1);
});

test('settings system update is administrator only and respects installation configuration', function () {
    $role = roleWithPermissions(['settings.manage']);
    $role->update(['name' => 'administrator']);
    $admin = User::factory()->create(['role_id' => $role->id]);
    $this->get(route('settings.system-update'))->assertStatus(405);
    $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Update System');
    config()->set('gym.deployment.enabled', false);
    $this->post(route('settings.system-update'))->assertRedirect(route('settings.index'))->assertSessionHas('error');
    $role->update(['name' => 'manager']);
    $this->actingAs($admin->fresh())->get(route('settings.index'))->assertOk()->assertDontSee('Update System');
    $this->post(route('settings.system-update'))->assertForbidden();
});

test('PT check-in consumes one session and duplicate submission does not consume another', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['pt.manage'])->id]);
    $member = Member::factory()->create(['status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $member->id, 'status' => 'active', 'start_date' => now()->subDay(), 'end_date' => now()->addDays(30)]);
    $package = PtPackage::query()->create(['name' => 'Check-in Pack', 'sessions_count' => 5, 'price' => 500, 'commission_per_session' => 30, 'status' => 'active']);
    $balance = PtMemberPackage::query()->create(['member_id' => $member->id, 'pt_package_id' => $package->id, 'total_sessions' => 5, 'used_sessions' => 4, 'price' => 500, 'purchased_at' => today(), 'status' => 'active']);
    $trainer = PtTrainer::query()->create(['name' => 'Check-in Coach', 'status' => 'active', 'commission_per_session' => 30]);
    $payload = ['check_in' => 1, 'check_in_token' => (string) str()->uuid(), 'pt_member_package_ids' => [$balance->id], 'trainer_id' => $trainer->id, 'session_date' => today()->toDateString(), 'start_time' => '09:00', 'duration_minutes' => 60];
    $this->actingAs($staff)->get(route('pt.schedule.create'))->assertOk()->assertSee('PT Session Check-in');
    $this->post(route('pt.schedule.store'), $payload)->assertRedirect();
    expect($balance->fresh()->used_sessions)->toBe(5)->and($balance->fresh()->remainingSessions())->toBe(0)
        ->and($balance->fresh()->status)->toBe('completed')->and(PtSession::query()->first()->status)->toBe('completed');
    $this->post(route('pt.schedule.store'), $payload)->assertRedirect();
    expect($balance->fresh()->used_sessions)->toBe(5)->and(PtSession::query()->count())->toBe(1);
    $payload['check_in_token'] = (string) str()->uuid();
    $this->post(route('pt.schedule.store'), $payload)->assertSessionHasErrors();
    expect(PtSession::query()->count())->toBe(1);
});

test('PT assignment only lists active members with currently valid memberships', function () {
    $staff = User::factory()->create(['role_id' => roleWithPermissions(['pt.manage'])->id]);
    $eligible = Member::factory()->create(['full_name' => 'Eligible PT Member', 'status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $eligible->id, 'status' => 'active', 'start_date' => today(), 'end_date' => today()]);
    $excluded = [];
    foreach (['expired', 'future', 'suspended', 'no-membership', 'inactive-member'] as $case) {
        $member = Member::factory()->create(['full_name' => 'Excluded '.$case, 'status' => $case === 'inactive-member' ? 'suspended' : 'active']);
        $excluded[] = $member;
        if ($case !== 'no-membership') {
            MemberMembership::factory()->create([
                'member_id' => $member->id, 'status' => $case === 'suspended' ? 'suspended' : 'active',
                'start_date' => $case === 'future' ? today()->addDay() : today()->subDays(30),
                'end_date' => $case === 'expired' ? today()->subDay() : today()->addDays(30),
            ]);
        }
    }
    $response = $this->actingAs($staff)->get(route('pt.member-packages.create'))->assertOk()->assertSee($eligible->full_name);
    foreach ($excluded as $member) {
        $response->assertDontSee($member->full_name);
    }
});

it('does not label members active without a paid current membership', function () {
    $member = Member::factory()->create(['status' => 'active']);
    expect($member->displayStatus())->toBe('expired');

    $membership = MemberMembership::factory()->create([
        'member_id' => $member->id,
        'status' => 'active',
        'start_date' => today()->addDay(),
        'end_date' => today()->addDays(60),
        'payment_status' => 'paid',
    ]);
    expect($member->fresh()->displayStatus())->toBe('not_started');

    $membership->update(['start_date' => today(), 'payment_status' => 'unpaid']);
    expect($member->fresh()->displayStatus())->toBe('pending_payment');

    $membership->update(['payment_status' => 'paid']);
    expect($member->fresh()->displayStatus())->toBe('active');

    $membership->update(['start_date' => today()->subDays(30), 'end_date' => today()->subDay()]);
    expect($member->fresh()->displayStatus())->toBe('expired');

    $member->update(['status' => 'suspended']);
    expect($member->fresh()->displayStatus())->toBe('suspended');
});

it('aligns expired and expiring member lists with current membership validity', function () {
    $user = User::factory()->create(['role_id' => roleWithPermissions(['members.manage'])->id]);
    $none = Member::factory()->create(['status' => 'active']);
    $renewed = Member::factory()->create(['status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $renewed->id, 'start_date' => today()->subDays(40), 'end_date' => today()->subDays(10)]);
    MemberMembership::factory()->create(['member_id' => $renewed->id, 'start_date' => today(), 'end_date' => today()->addDays(60)]);
    $soon = Member::factory()->create(['status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $soon->id, 'start_date' => today(), 'end_date' => today()->addDay()]);
    $unpaid = Member::factory()->create(['status' => 'active']);
    MemberMembership::factory()->create(['member_id' => $unpaid->id, 'start_date' => today(), 'end_date' => today()->addDay(), 'payment_status' => 'unpaid']);

    foreach ([route('members.expired'), route('members.index', ['filter' => 'expired'])] as $url) {
        $this->actingAs($user)->get($url)->assertOk()->assertViewHas('members', fn ($members) => $members->pluck('id')->all() === [$none->id]);
    }
    foreach ([route('members.expiring-soon'), route('members.index', ['filter' => 'expiring'])] as $url) {
        $this->actingAs($user)->get($url)->assertOk()->assertViewHas('members', fn ($members) => $members->pluck('id')->all() === [$soon->id]);
    }
});

it('renumbers members chronologically within each registration year', function () {
    $later = Member::factory()->create(['member_no' => 'GMG26060001', 'created_at' => '2026-06-02 10:00:00']);
    $first = Member::factory()->create(['member_no' => 'GMG26060002', 'created_at' => '2026-06-01 10:00:00']);
    $otherYear = Member::factory()->create(['member_no' => 'GMG25060001', 'created_at' => '2025-06-01 10:00:00']);
    $savedAt = $first->updated_at;
    $migration = require database_path('migrations/2026_10_04_000003_renumber_members_by_registration_year.php');
    $migration->up();
    expect($first->fresh()->member_no)->toBe('GMG2600001')
        ->and($later->fresh()->member_no)->toBe('GMG2600002')
        ->and($otherYear->fresh()->member_no)->toBe('GMG2500001')
        ->and($first->fresh()->updated_at->equalTo($savedAt))->toBeTrue()
        ->and(Member::nextMemberNumber('26'))->toBe('GMG2600003')
        ->and(Member::nextMemberNumber('27'))->toBe('GMG2700001');
});

it('rejects invalid imported backup archives without adding history', function () {
    $user = User::factory()->create(['role_id' => roleWithPermissions(['backup.manage'])->id]);
    $this->actingAs($user)->post(route('backups.import'), [
        'backup_file' => UploadedFile::fake()->createWithContent('broken.zip', 'not a zip'),
    ])->assertSessionHasErrors('backup_file');
    expect(BackupLog::query()->count())->toBe(0);
});

it('uploads a backup through the protected backup import action', function () {
    $user = User::factory()->create(['role_id' => roleWithPermissions(['backup.manage'])->id]);
    $this->mock(BackupManager::class, function ($mock) {
        $mock->shouldReceive('backupPath')->andReturn(storage_path('app/backups'));
        $mock->shouldReceive('import')->once()->andReturn(new BackupLog);
    });
    $this->actingAs($user)->get(route('backups.index'))->assertOk()->assertSee('Upload Backup');
    $this->actingAs($user)->post(route('backups.import'), [
        'backup_file' => UploadedFile::fake()->create('demo.zip', 10, 'application/zip'),
    ])->assertRedirect(route('backups.index'))->assertSessionHas('success');
});

it('requires verified device read and write for door online status', function () {
    $door = AccessControllerSetting::query()->create([
        'name' => 'Handshake test', 'driver' => 'dahua_standalone', 'is_enabled' => true, 'host' => '192.0.2.1',
        'encrypted_credentials' => ['username' => 'admin', 'password' => 'demo', 'bridge_url' => 'http://bridge.test/dahua'],
    ]);
    Http::fake(['bridge.test/*' => Http::sequence()
        ->push(['ok' => true], 200)
        ->push(['ok' => true, 'read_verified' => true, 'write_verified' => true], 200)
        ->push(['ok' => false], 500)]);
    expect($door->isOnline())->toBeFalse();
    $door->encrypted_credentials = array_merge($door->encrypted_credentials, ['password' => 'updated']);
    expect($door->isOnline())->toBeTrue();
    expect($door->isOnline())->toBeTrue();
    $door->encrypted_credentials = array_merge($door->encrypted_credentials, ['password' => 'wrong']);
    expect($door->isOnline())->toBeFalse();
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request['command'] === 'handshake' && $request['door']['username'] === 'admin');
});
