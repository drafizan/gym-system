@extends('layouts.app')

@section('title', 'Settings')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Settings</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">System</p>
            <h1>Settings</h1>
        </div>
    </div>

    <section class="form-card settings-account-card">
        <div class="form-section-header">
            <h2>Account Security</h2>
            <p>Change the password for this staff account.</p>
        </div>

        <div class="settings-action-row">
            <div>
                <strong>Change Password</strong>
                <span>Update the password for the currently logged-in account.</span>
            </div>
            <a class="btn btn-light" href="{{ route('password.edit') }}">Change Password</a>
        </div>
    </section>

    <form method="POST" action="{{ route('settings.general.update') }}" class="form-grid">
        @csrf
        @method('PUT')

        <section class="form-card">
            <div class="form-section-header">
                <h2>Gym Profile &amp; Operations</h2>
                <p>Set the business details, receipt text, membership threshold, and payment methods used by the system.</p>
            </div>

            <div class="settings-backup-grid">
                <div class="form-row">
                    <label for="gym_name">Gym Name</label>
                    <input id="gym_name" name="gym_name" type="text" value="{{ old('gym_name', $generalSettings['gym_name']) }}" required>
                    @error('gym_name')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="company_name">Company Name</label>
                    <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $generalSettings['company_name']) }}" required>
                    @error('company_name')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="gym_contact_number">Contact Number</label>
                    <input id="gym_contact_number" name="gym_contact_number" type="text" value="{{ old('gym_contact_number', $generalSettings['gym_contact_number']) }}" required>
                    @error('gym_contact_number')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="expiring_soon_days">Expiring Soon Days</label>
                    <input id="expiring_soon_days" name="expiring_soon_days" type="number" min="1" max="90" value="{{ old('expiring_soon_days', $generalSettings['expiring_soon_days']) }}" required>
                    @error('expiring_soon_days')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="gym_address">Gym Address</label>
                    <textarea id="gym_address" name="gym_address" rows="3" placeholder="Optional">{{ old('gym_address', $generalSettings['gym_address']) }}</textarea>
                    @error('gym_address')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="receipt_footer">Receipt Footer</label>
                    <textarea id="receipt_footer" name="receipt_footer" rows="3" placeholder="Optional">{{ old('receipt_footer', $generalSettings['receipt_footer']) }}</textarea>
                    @error('receipt_footer')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <fieldset class="form-row settings-wide-row settings-checkbox-panel">
                    <legend>Payment Methods</legend>
                    <div class="settings-checkbox-grid">
                        @foreach ($availablePaymentMethods as $method)
                            <label class="settings-toggle">
                                <input type="checkbox" name="payment_methods[]" value="{{ $method->value }}" @checked(in_array($method->value, old('payment_methods', $generalSettings['payment_methods']), true))>
                                <span>{{ $method->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_methods')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </fieldset>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save General Settings</button>
            </div>
        </section>
    </form>

    <form method="POST" action="{{ route('settings.backup.update') }}" class="form-grid">
        @csrf
        @method('PUT')

        <section class="form-card">
            <div class="form-section-header">
                <h2>Backup & Restore Configuration</h2>
                <p>Set the backup folder and PostgreSQL tool paths for this machine.</p>
            </div>

            <div class="settings-backup-grid" data-backup-os-form>
                <div class="form-row settings-wide-row">
                    <label for="backup_path">Backup Save Location</label>
                    <div class="path-picker-control">
                        <input id="backup_path" name="backup_path" type="text" value="{{ old('backup_path', $backupSettings['backup_path']) }}" placeholder="/Users/.../storage/app/backups" data-folder-picker-target required>
                        <button class="btn btn-light" type="button" data-folder-picker-open data-folder-picker-url="{{ route('settings.backup.folders') }}">Choose Folder</button>
                    </div>
                    @error('backup_path')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="backup_schedule">Auto Backup Schedule</label>
                    <select id="backup_schedule" name="backup_schedule" required>
                        <option value="daily" @selected(old('backup_schedule', $backupSettings['backup_schedule']) === 'daily')>Daily</option>
                        <option value="weekly" @selected(old('backup_schedule', $backupSettings['backup_schedule']) === 'weekly')>Weekly</option>
                        <option value="manual" @selected(old('backup_schedule', $backupSettings['backup_schedule']) === 'manual')>Manual only</option>
                    </select>
                    @error('backup_schedule')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row">
                    <label for="backup_retention_count">Keep Latest Backups</label>
                    <input id="backup_retention_count" name="backup_retention_count" type="number" min="1" max="365" value="{{ old('backup_retention_count', $backupSettings['backup_retention_count']) }}" required>
                    @error('backup_retention_count')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="backup_os">Operating System</label>
                    <select id="backup_os" name="backup_os" data-backup-os-select required>
                        <option value="macos" @selected(old('backup_os', $backupSettings['backup_os']) === 'macos')>macOS / Postgres.app</option>
                        <option value="windows" @selected(old('backup_os', $backupSettings['backup_os']) === 'windows')>Windows / PostgreSQL Installer</option>
                        <option value="linux" @selected(old('backup_os', $backupSettings['backup_os']) === 'linux')>Linux</option>
                        <option value="custom" @selected(old('backup_os', $backupSettings['backup_os']) === 'custom')>Custom path</option>
                    </select>
                    @error('backup_os')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="pg_dump_binary">PostgreSQL Backup Tool</label>
                    <input id="pg_dump_binary" name="pg_dump_binary" type="text" value="{{ old('pg_dump_binary', $backupSettings['pg_dump_binary']) }}" placeholder="pg_dump" data-backup-pg-dump required>
                    @error('pg_dump_binary')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="psql_binary">PostgreSQL Restore Tool</label>
                    <input id="psql_binary" name="psql_binary" type="text" value="{{ old('psql_binary', $backupSettings['psql_binary']) }}" placeholder="psql" data-backup-psql required>
                    @error('psql_binary')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-row settings-wide-row">
                    <label for="backup_current_password">Current Password</label>
                    <input id="backup_current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password" required>
                    @error('current_password')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Backup Configuration</button>
            </div>
        </section>
    </form>

    <div class="modal-backdrop" data-modal="folder-picker" aria-hidden="true">
        <section class="modal-card folder-picker-modal" role="dialog" aria-modal="true" aria-labelledby="folder-picker-title">
            <button type="button" class="modal-close" data-modal-close aria-label="Close folder picker"></button>
            <p class="eyebrow">Backup Location</p>
            <h2 id="folder-picker-title">Choose backup folder</h2>
            <p>Select a folder on the machine running this system.</p>

            <div class="folder-picker-current">
                <span data-folder-picker-current>{{ $backupSettings['backup_path'] }}</span>
                <small data-folder-picker-status></small>
            </div>

            <div class="folder-picker-roots" data-folder-picker-roots></div>
            <div class="folder-picker-list" data-folder-picker-list></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                <button type="button" class="btn btn-primary" data-folder-picker-select>Use This Folder</button>
            </div>
        </section>
    </div>

    <form method="POST" action="{{ route('settings.doors.update') }}" class="form-grid">
        @csrf
        @method('PUT')

        <section class="form-card">
            <div class="form-section-header">
                <h2>Dahua Door Access Configuration</h2>
                <p>Configure each Dahua standalone TCP/IP device for local card sync. Dahua SDK port is 37777.</p>
            </div>

            @php
                $configuredDoors = $doorSettings->values();
                $defaultDoors = collect([
                    ['name' => '1st Floor Door', 'host' => '', 'port' => 37777, 'is_enabled' => true],
                    ['name' => '2nd Floor Door', 'host' => '', 'port' => 37777, 'is_enabled' => true],
                ]);
                $doors = $defaultDoors->map(function (array $defaultDoor, int $index) use ($configuredDoors): array {
                    $door = $configuredDoors->get($index);
                    $credentials = $door?->encrypted_credentials ?? [];

                    return [
                        'id' => old("doors.{$index}.id", $door?->id),
                        'name' => old("doors.{$index}.name", $door?->displayName() ?? $defaultDoor['name']),
                        'host' => old("doors.{$index}.host", $door?->host ?? $defaultDoor['host']),
                        'port' => old("doors.{$index}.port", $door?->port ?? $defaultDoor['port']),
                        'username' => old("doors.{$index}.username", $credentials['username'] ?? 'admin'),
                        'card_number_format' => old("doors.{$index}.card_number_format", $credentials['card_number_format'] ?? 'decimal'),
                        'bridge_url' => old("doors.{$index}.bridge_url", $credentials['bridge_url'] ?? ''),
                        'has_password' => filled($credentials['password'] ?? null),
                        'has_bridge_token' => filled($credentials['bridge_token'] ?? null),
                        'is_enabled' => old("doors.{$index}.is_enabled", $door?->is_enabled ?? $defaultDoor['is_enabled']),
                    ];
                });
            @endphp

            <div class="settings-door-grid">
                @foreach ($doors as $index => $door)
                    <div class="settings-door-card">
                        <input type="hidden" name="doors[{{ $index }}][id]" value="{{ $door['id'] }}">

                        <div class="form-row">
                            <label for="door_name_{{ $index }}">Door Name</label>
                            <input id="door_name_{{ $index }}" name="doors[{{ $index }}][name]" type="text" value="{{ $door['name'] }}" readonly required>
                            @error("doors.{$index}.name")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_host_{{ $index }}">IP Address</label>
                            <input id="door_host_{{ $index }}" name="doors[{{ $index }}][host]" type="text" inputmode="decimal" value="{{ $door['host'] }}" placeholder="Enter IP address" required>
                            @error("doors.{$index}.host")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_port_{{ $index }}">Port</label>
                            <input id="door_port_{{ $index }}" name="doors[{{ $index }}][port]" type="number" min="1" max="65535" value="{{ $door['port'] }}" placeholder="37777">
                            @error("doors.{$index}.port")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_username_{{ $index }}">Device Username</label>
                            <input id="door_username_{{ $index }}" name="doors[{{ $index }}][username]" type="text" value="{{ $door['username'] }}" autocomplete="off" required>
                            @error("doors.{$index}.username")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_password_{{ $index }}">Device Password</label>
                            <input id="door_password_{{ $index }}" name="doors[{{ $index }}][password]" type="password" value="" placeholder="{{ $door['has_password'] ? 'Saved - leave blank to keep' : 'Enter device password' }}" autocomplete="new-password">
                            @error("doors.{$index}.password")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_card_number_format_{{ $index }}">Card Number Format</label>
                            <select id="door_card_number_format_{{ $index }}" name="doors[{{ $index }}][card_number_format]" required>
                                <option value="decimal" @selected($door['card_number_format'] === 'decimal')>Decimal</option>
                                <option value="hex" @selected($door['card_number_format'] === 'hex')>Hexadecimal</option>
                            </select>
                            <p class="field-help">Match the card type configured in SmartPSS Lite before syncing cards.</p>
                            @error("doors.{$index}.card_number_format")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_bridge_url_{{ $index }}">Local SDK Bridge URL</label>
                            <input id="door_bridge_url_{{ $index }}" name="doors[{{ $index }}][bridge_url]" type="url" value="{{ $door['bridge_url'] }}" placeholder="http://127.0.0.1:8787/dahua">
                            <p class="field-help">Required for automatic sync because the manual does not provide direct card-management HTTP endpoints.</p>
                            @error("doors.{$index}.bridge_url")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label for="door_bridge_token_{{ $index }}">Bridge Token</label>
                            <input id="door_bridge_token_{{ $index }}" name="doors[{{ $index }}][bridge_token]" type="password" value="" placeholder="{{ $door['has_bridge_token'] ? 'Saved - leave blank to keep' : 'Optional local bridge token' }}" autocomplete="new-password">
                            @error("doors.{$index}.bridge_token")
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <label class="settings-toggle">
                            <input type="checkbox" name="doors[{{ $index }}][is_enabled]" value="1" @checked((bool) $door['is_enabled'])>
                            <span>Door enabled</span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Dahua Configuration</button>
            </div>
        </section>
    </form>
@endsection
