<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\AccessControllerSetting;
use App\Support\Audit;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SystemSettings $settings): View
    {
        return view('settings.index', [
            'generalSettings' => [
                'gym_name' => $settings->get('gym_name'),
                'company_name' => $settings->get('company_name'),
                'gym_address' => $settings->get('gym_address'),
                'gym_contact_number' => $settings->get('gym_contact_number'),
                'receipt_footer' => $settings->get('receipt_footer'),
                'expiring_soon_days' => $settings->get('expiring_soon_days'),
                'payment_methods' => $settings->paymentMethods(),
            ],
            'availablePaymentMethods' => PaymentMethod::cases(),
            'doorSettings' => $this->floorDoorSettings(),
            'backupSettings' => [
                'backup_path' => $settings->get('backup_path'),
                'backup_retention_count' => $settings->get('backup_retention_count'),
                'backup_schedule' => $settings->get('backup_schedule'),
                'backup_os' => $settings->get('backup_os'),
                'pg_dump_binary' => $settings->get('backup.pg_dump_binary', config('gym.backup.pg_dump_binary', 'pg_dump')),
                'psql_binary' => $settings->get('backup.psql_binary', config('gym.backup.psql_binary', 'psql')),
            ],
        ]);
    }

    public function updateGeneral(Request $request, SystemSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'gym_name' => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:160'],
            'gym_address' => ['nullable', 'string', 'max:500'],
            'gym_contact_number' => ['required', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'expiring_soon_days' => ['required', 'integer', 'min:1', 'max:90'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['string', 'in:'.implode(',', PaymentMethod::values())],
        ]);

        $oldValues = [
            'gym_name' => $settings->get('gym_name'),
            'company_name' => $settings->get('company_name'),
            'gym_address' => $settings->get('gym_address'),
            'gym_contact_number' => $settings->get('gym_contact_number'),
            'receipt_footer' => $settings->get('receipt_footer'),
            'expiring_soon_days' => $settings->get('expiring_soon_days'),
            'payment_methods' => $settings->paymentMethods(),
        ];

        $settings->setMany([
            'gym_name' => $validated['gym_name'],
            'company_name' => $validated['company_name'],
            'gym_address' => $validated['gym_address'] ?? '',
            'gym_contact_number' => $validated['gym_contact_number'],
            'receipt_footer' => $validated['receipt_footer'] ?? '',
            'expiring_soon_days' => (int) $validated['expiring_soon_days'],
            'payment_methods' => array_values($validated['payment_methods']),
        ]);

        Audit::record($request, 'settings', 'general_updated', null, null, $oldValues, [
            ...$validated,
            'expiring_soon_days' => (int) $validated['expiring_soon_days'],
            'payment_methods' => array_values($validated['payment_methods']),
        ]);

        return back()->with('success', 'General settings updated.');
    }

    public function updateDoors(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'doors' => ['required', 'array', 'size:2'],
            'doors.*.id' => ['nullable', 'integer', 'exists:access_controller_settings,id'],
            'doors.*.name' => ['required', 'string', 'max:120'],
            'doors.*.host' => ['required', 'ip'],
            'doors.*.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'doors.*.username' => ['required', 'string', 'max:120'],
            'doors.*.password' => ['nullable', 'string', 'max:160'],
            'doors.*.card_number_format' => ['required', 'in:decimal,hex'],
            'doors.*.bridge_url' => ['nullable', 'url', 'max:500'],
            'doors.*.bridge_token' => ['nullable', 'string', 'max:500'],
            'doors.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        $oldValues = $this->floorDoorSettings()
            ->map(fn (AccessControllerSetting $setting): array => $setting->only(['id', 'name', 'host', 'port', 'is_enabled']))
            ->all();

        foreach ($validated['doors'] as $index => $door) {
            $defaultName = $index === 0 ? '1st Floor Door' : '2nd Floor Door';
            $existingDoor = AccessControllerSetting::query()->where('name', $defaultName)->first();
            $existingCredentials = $existingDoor?->encrypted_credentials ?? [];

            AccessControllerSetting::query()->updateOrCreate([
                'name' => $defaultName,
            ], [
                'driver' => 'dahua_standalone',
                'host' => $door['host'],
                'port' => $door['port'] ?? 37777,
                'is_enabled' => (bool) ($door['is_enabled'] ?? false),
                'encrypted_credentials' => [
                    'username' => $door['username'],
                    'password' => filled($door['password'] ?? null)
                        ? $door['password']
                        : ($existingCredentials['password'] ?? null),
                    'card_number_format' => $door['card_number_format'],
                    'bridge_url' => $door['bridge_url'] ?? null,
                    'bridge_token' => filled($door['bridge_token'] ?? null)
                        ? $door['bridge_token']
                        : ($existingCredentials['bridge_token'] ?? null),
                ],
            ]);
        }

        $newValues = $this->floorDoorSettings()
            ->map(fn (AccessControllerSetting $setting): array => $setting->only(['id', 'name', 'host', 'port', 'is_enabled']))
            ->all();

        Audit::record($request, 'settings', 'door_access_updated', null, null, ['doors' => $oldValues], ['doors' => $newValues]);

        return back()->with('success', 'Door access settings updated.');
    }

    public function updateBackup(Request $request, SystemSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'backup_path' => ['required', 'string', 'max:500'],
            'backup_retention_count' => ['required', 'integer', 'min:1', 'max:365'],
            'backup_schedule' => ['required', 'in:daily,weekly,manual'],
            'backup_os' => ['required', 'in:windows,linux,macos,custom'],
            'pg_dump_binary' => ['required', 'string', 'max:500'],
            'psql_binary' => ['required', 'string', 'max:500'],
            'current_password' => ['required', 'current_password'],
        ]);

        $this->validateBackupSettingPaths($validated['backup_path'], $validated['pg_dump_binary'], $validated['psql_binary']);

        $oldValues = [
            'backup_path' => $settings->get('backup_path'),
            'backup_retention_count' => $settings->get('backup_retention_count'),
            'backup_schedule' => $settings->get('backup_schedule'),
            'backup_os' => $settings->get('backup_os'),
            'pg_dump_binary' => $settings->get('backup.pg_dump_binary', config('gym.backup.pg_dump_binary', 'pg_dump')),
            'psql_binary' => $settings->get('backup.psql_binary', config('gym.backup.psql_binary', 'psql')),
        ];

        $settings->setMany([
            'backup_path' => $validated['backup_path'],
            'backup_retention_count' => (int) $validated['backup_retention_count'],
            'backup_schedule' => $validated['backup_schedule'],
            'backup_os' => $validated['backup_os'],
            'backup.pg_dump_binary' => $validated['pg_dump_binary'],
            'backup.psql_binary' => $validated['psql_binary'],
        ]);

        Audit::record($request, 'settings', 'backup_updated', null, null, $oldValues, $validated);

        return back()->with('success', 'Backup & restore settings updated.');
    }

    public function browseBackupFolders(Request $request, SystemSettings $settings): JsonResponse
    {
        $roots = $this->backupFolderRoots($settings);
        $requestedPath = (string) $request->query('path', '');
        $currentPath = $requestedPath !== ''
            ? $requestedPath
            : (string) $settings->get('backup_path', storage_path('app/backups'));

        if (! $this->isAllowedFolderPath($currentPath, $roots) || ! is_dir($currentPath)) {
            $currentPath = $roots[0]['path'] ?? storage_path('app');
        }

        $currentPath = realpath($currentPath) ?: $currentPath;
        $directories = collect(File::directories($currentPath))
            ->filter(fn (string $path): bool => is_readable($path) && $this->isAllowedFolderPath($path, $roots))
            ->sortBy(fn (string $path): string => str($path)->afterLast(DIRECTORY_SEPARATOR)->lower()->toString())
            ->values()
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => realpath($path) ?: $path,
                'writable' => is_writable($path),
            ])
            ->all();

        $parent = dirname($currentPath);

        return response()->json([
            'current' => $currentPath,
            'parent' => $parent !== $currentPath && $this->isAllowedFolderPath($parent, $roots) ? $parent : null,
            'writable' => is_writable($currentPath),
            'roots' => $roots,
            'directories' => $directories,
        ]);
    }

    private function validateBackupSettingPaths(string $backupPath, string $pgDumpBinary, string $psqlBinary): void
    {
        $publicPath = realpath(public_path());
        $candidatePath = realpath($backupPath) ?: $backupPath;

        if ($publicPath && str_starts_with($candidatePath, $publicPath)) {
            throw ValidationException::withMessages([
                'backup_path' => 'Backup location cannot be inside the public web folder.',
            ]);
        }

        foreach (['pg_dump_binary' => $pgDumpBinary, 'psql_binary' => $psqlBinary] as $field => $binary) {
            $isSimpleCommand = preg_match('/^[A-Za-z0-9_.-]+$/', $binary) === 1;
            $isExecutablePath = str_contains($binary, DIRECTORY_SEPARATOR) && File::isFile($binary) && is_executable($binary);

            if (! $isSimpleCommand && ! $isExecutablePath) {
                throw ValidationException::withMessages([
                    $field => 'Use a command name or a full executable file path.',
                ]);
            }
        }
    }

    /**
     * @return array<int, array{label: string, path: string}>
     */
    private function backupFolderRoots(SystemSettings $settings): array
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;
        $backupPath = (string) $settings->get('backup_path', storage_path('app/backups'));
        $paths = [
            'Current backup folder' => is_dir($backupPath) ? $backupPath : dirname($backupPath),
            'Application storage' => storage_path('app'),
            'Application folder' => base_path(),
            'Home folder' => $home,
            'Users' => '/Users',
            'Home directories' => '/home',
            'Optional apps' => '/opt',
            'Variable data' => '/var',
            'Windows C Drive' => 'C:\\',
        ];

        return collect($paths)
            ->filter(fn (?string $path): bool => $path !== null && is_dir($path) && is_readable($path))
            ->map(fn (string $path, string $label): array => [
                'label' => $label,
                'path' => realpath($path) ?: $path,
            ])
            ->unique('path')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{label: string, path: string}>  $roots
     */
    private function isAllowedFolderPath(string $path, array $roots): bool
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            return false;
        }

        foreach ($roots as $root) {
            $rootPath = rtrim((string) realpath($root['path']), DIRECTORY_SEPARATOR);

            if ($rootPath !== '' && ($realPath === $rootPath || str_starts_with($realPath, $rootPath.DIRECTORY_SEPARATOR))) {
                return true;
            }
        }

        return false;
    }

    private function floorDoorSettings(): Collection
    {
        return collect(['1st Floor Door', '2nd Floor Door'])
            ->map(function (string $name): AccessControllerSetting {
                return AccessControllerSetting::query()->where('name', $name)->first()
                    ?? new AccessControllerSetting([
                        'name' => $name,
                        'driver' => 'dahua_standalone',
                        'port' => 37777,
                        'is_enabled' => true,
                    ]);
            });
    }
}
