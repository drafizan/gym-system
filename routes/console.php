<?php

use App\Contracts\AccessControllerClient;
use App\Enums\AccessSyncAction;
use App\Enums\MembershipStatus;
use App\Enums\RecordStatus;
use App\Enums\RfidCardStatus;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\MemberMembership;
use App\Models\RfidCard;
use App\Services\AccessSyncManager;
use App\Support\BackupManager;
use App\Support\DahuaBridgeHeartbeat;
use App\Support\SystemSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('memberships:detect-expired {--sync : Process pending access sync after detecting expired memberships} {--sync-limit=500 : Maximum access sync records to process}', function (AccessSyncManager $manager) {
    $today = now()->toDateString();
    $queuedAccessDisables = 0;

    $expiredMemberships = MemberMembership::query()
        ->with(['member', 'package'])
        ->where('status', MembershipStatus::Active->value)
        ->whereDate('end_date', '<', $today)
        ->get();

    $expiredMemberships->each(function (MemberMembership $membership) use ($today, &$queuedAccessDisables): void {
        $oldValues = $membership->toArray();

        $membership->update([
            'status' => MembershipStatus::Expired->value,
        ]);

        AuditLog::query()->create([
            'module' => 'memberships',
            'action' => 'expired',
            'record_type' => MemberMembership::class,
            'record_id' => $membership->id,
            'old_values' => $oldValues,
            'new_values' => $membership->fresh()->toArray(),
        ]);

        $hasAnotherActiveAccessMembership = $membership->member?->memberships()
            ->whereKeyNot($membership->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('package', fn ($query) => $query->where('access_allowed', true))
            ->exists() ?? false;

        if (! $membership->package?->access_allowed || $hasAnotherActiveAccessMembership) {
            return;
        }

        AccessSyncLog::query()->create([
            'member_id' => $membership->member_id,
            'member_membership_id' => $membership->id,
            'action' => AccessSyncAction::DisableCard->value,
            'status' => 'pending',
            'payload' => [
                'member_no' => $membership->member?->member_no,
                'rfid_card_number' => $membership->member?->rfid_card_number,
                'membership_status' => MembershipStatus::Expired->value,
                'start_date' => $membership->start_date?->toDateString(),
                'end_date' => $membership->end_date?->toDateString(),
            ],
        ]);

        $queuedAccessDisables++;
    });

    $this->info($expiredMemberships->count().' expired membership(s) detected.');
    $this->info($queuedAccessDisables.' access disable sync record(s) queued.');

    if ($this->option('sync')) {
        $limit = max(1, min((int) $this->option('sync-limit'), 500));
        $synced = $manager->syncPending($limit);

        $this->info($synced.' access sync record(s) processed.');
    }
})->purpose('Detect expired memberships and optionally sync access devices');

Artisan::command('access:sync-pending {--limit=50}', function (AccessSyncManager $manager) {
    $limit = max(1, min((int) $this->option('limit'), 500));
    $synced = $manager->syncPending($limit);

    $this->info($synced.' access sync record(s) processed.');
})->purpose('Process pending access controller sync records');

Artisan::command('access:sync-authorized {--limit=500 : Maximum authorized cards to queue}', function (AccessSyncManager $manager) {
    $limit = max(1, min((int) $this->option('limit'), 5000));
    $today = now()->toDateString();

    $cards = RfidCard::query()
        ->with([
            'member' => fn ($query) => $query->with(['memberships' => fn ($membershipQuery) => $membershipQuery
                ->with('package')
                ->where('status', MembershipStatus::Active->value)
                ->whereDate('end_date', '>=', $today)
                ->whereHas('package', fn ($packageQuery) => $packageQuery->where('access_allowed', true))
                ->orderByDesc('end_date'),
            ]),
        ])
        ->where('status', RfidCardStatus::Active->value)
        ->whereHas('member', function ($query) use ($today): void {
            $query
                ->where('status', RecordStatus::Active->value)
                ->whereHas('memberships', function ($membershipQuery) use ($today): void {
                    $membershipQuery
                        ->where('status', MembershipStatus::Active->value)
                        ->whereDate('end_date', '>=', $today)
                        ->whereHas('package', fn ($packageQuery) => $packageQuery->where('access_allowed', true));
                });
        })
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if ($cards->isEmpty()) {
        $this->warn('No authorized active RFID cards found in MACS.');
        $this->line('Active members: '.MemberMembership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereDate('end_date', '>=', $today)
            ->distinct('member_id')
            ->count('member_id'));
        $this->line('Active RFID cards: '.RfidCard::query()
            ->where('status', RfidCardStatus::Active->value)
            ->count());
        $this->line('Active access-allowed memberships: '.MemberMembership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('package', fn ($packageQuery) => $packageQuery->where('access_allowed', true))
            ->count());

        return self::SUCCESS;
    }

    $cards->each(function (RfidCard $card): void {
        $member = $card->member;
        $membership = $member?->memberships->first();

        AccessSyncLog::query()->create([
            'member_id' => $card->member_id,
            'member_membership_id' => $membership?->id,
            'rfid_card_id' => $card->id,
            'action' => AccessSyncAction::FullSync->value,
            'status' => 'pending',
            'payload' => [
                'member_no' => $member?->member_no,
                'card_number' => $card->card_number,
                'rfid_card_number' => $card->card_number,
                'card_status' => $card->status,
                'membership_status' => $membership?->status,
                'start_date' => $membership?->start_date?->toDateString(),
                'end_date' => $membership?->end_date?->toDateString(),
                'source' => 'authorized-access-full-sync',
            ],
        ]);
    });

    $synced = $manager->syncPending($cards->count());

    $this->info($cards->count().' authorized card sync record(s) queued.');
    $this->info($synced.' access sync record(s) processed.');

    return self::SUCCESS;
})->purpose('Queue and sync all currently authorized RFID cards to enabled access controllers');

Artisan::command('access:diagnose-card {card : RFID card number printed on the card or entered in MACS}', function () {
    $input = trim((string) $this->argument('card'));
    $normalized = ltrim(preg_replace('/\D+/', '', $input) ?: '', '0') ?: '0';
    $today = now()->toDateString();

    $this->line('Input card: '.$input);
    $this->line('Normalized decimal: '.$normalized);
    $this->line('Dahua card format: '.str_pad(strtoupper(dechex((int) $normalized)), 8, '0', STR_PAD_LEFT));

    $cards = RfidCard::query()
        ->with(['member.memberships.package'])
        ->get()
        ->filter(function (RfidCard $card) use ($normalized): bool {
            $cardNumber = ltrim(preg_replace('/\D+/', '', $card->card_number) ?: '', '0') ?: '0';

            return $cardNumber === $normalized;
        })
        ->values();

    if ($cards->isEmpty()) {
        $this->error('Card is not registered in MACS.');

        return self::FAILURE;
    }

    $cards->each(function (RfidCard $card) use ($today): void {
        $member = $card->member;
        $eligibleMemberships = $member?->memberships
            ->filter(fn (MemberMembership $membership): bool => $membership->status === MembershipStatus::Active->value
                && $membership->end_date?->toDateString() >= $today
                && (bool) $membership->package?->access_allowed)
            ->values() ?? collect();

        $this->line('RFID card status: '.$card->status);
        $this->line('Member: '.($member?->member_no ?? '-').' / '.($member?->full_name ?? '-'));
        $this->line('Member status: '.($member?->status ?? '-'));
        $this->line('Active access-allowed memberships: '.$eligibleMemberships->count());

        if ($eligibleMemberships->isEmpty()) {
            $this->warn('Not eligible for door sync: member must have an active, unexpired membership package with door access enabled.');

            return;
        }

        $eligibleMemberships->each(function (MemberMembership $membership): void {
            $this->info('Eligible membership: '.$membership->package?->name.' until '.$membership->end_date?->toDateString());
        });
    });

    return self::SUCCESS;
})->purpose('Diagnose whether a card is eligible for Dahua access sync');

Artisan::command('access:bridge-heartbeat {--status : Only check bridge status without starting it} {--restart : Restart the known local Dahua bridge process}', function (DahuaBridgeHeartbeat $heartbeat) {
    $result = $this->option('restart')
        ? $heartbeat->restart()
        : ($this->option('status')
        ? $heartbeat->status()
        : $heartbeat->ensureRunning());

    if ($result['healthy'] ?? false) {
        $this->info($result['message'] ?? 'Dahua bridge is healthy.');
    } else {
        $this->warn($result['message'] ?? 'Dahua bridge is not healthy.');
    }

    $this->line('Health URL: '.$result['health_url']);
    $this->line('PID: '.($result['pid'] ?? '-'));
    $this->line('Log: '.($result['log_file'] ?? config('gym.access.dahua_bridge_log_file')));

    return ($result['healthy'] ?? false) || ($result['ok'] ?? false)
        ? self::SUCCESS
        : self::FAILURE;
})->purpose('Keep the local Dahua SDK bridge alive');

Artisan::command('access:door-command {action : unlock, lock, status, or sync-time} {door=1st Floor Door : Door id or name} {--seconds=5 : Unlock pulse duration in seconds}', function (AccessControllerClient $client) {
    $command = str((string) $this->argument('action'))->lower()->toString();

    if (! in_array($command, ['unlock', 'lock', 'status', 'sync-time'], true)) {
        $this->error('Command must be one of: unlock, lock, status, sync-time.');

        return self::FAILURE;
    }

    $doorArgument = (string) $this->argument('door');
    $door = AccessControllerSetting::query()
        ->where('id', ctype_digit($doorArgument) ? (int) $doorArgument : 0)
        ->orWhere('name', $doorArgument)
        ->first();

    if (! $door) {
        $this->error('Door not found: '.$doorArgument);

        return self::FAILURE;
    }

    if (! $door->is_enabled) {
        $this->error($door->displayName().' is disabled in settings.');

        return self::FAILURE;
    }

    $result = $client->command($door, $command, [
        'unlock_seconds' => $this->option('seconds'),
    ]);

    if ($result->successful) {
        $this->info($result->message);
        $this->line(json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    $this->error($result->message);

    if ($result->response !== null) {
        $this->line(json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    return self::FAILURE;
})->purpose('Send a Dahua bridge door command to lock, unlock, or check status');

Artisan::command('access:verify-card-list {door=1st Floor Door : Door id or name} {--limit=500 : Maximum controller cards to read} {--remove-extra : Remove controller cards that MACS does not currently authorize}', function (AccessControllerClient $client, AccessSyncManager $manager) {
    $doorArgument = (string) $this->argument('door');
    $door = AccessControllerSetting::query()
        ->where('id', ctype_digit($doorArgument) ? (int) $doorArgument : 0)
        ->orWhere('name', $doorArgument)
        ->first();

    if (! $door) {
        $this->error('Door not found: '.$doorArgument);

        return self::FAILURE;
    }

    if (! $door->is_enabled) {
        $this->error($door->displayName().' is disabled in settings.');

        return self::FAILURE;
    }

    $normalizeCard = function (string $cardNumber): string {
        $cardNumber = trim($cardNumber);
        $digits = preg_replace('/\D+/', '', $cardNumber) ?: '';

        if ($digits !== '') {
            return ltrim($digits, '0') ?: '0';
        }

        return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $cardNumber) ?: $cardNumber);
    };

    $expectedCards = RfidCard::query()
        ->where('status', RfidCardStatus::Active->value)
        ->whereHas('member', function ($query): void {
            $query
                ->where('status', RecordStatus::Active->value)
                ->whereHas('memberships', function ($membershipQuery): void {
                    $membershipQuery
                        ->where('status', MembershipStatus::Active->value)
                        ->whereDate('end_date', '>=', now()->toDateString())
                        ->whereHas('package', fn ($packageQuery) => $packageQuery->where('access_allowed', true));
                });
        })
        ->pluck('card_number')
        ->map(fn (string $cardNumber): string => $normalizeCard($cardNumber))
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $result = $client->cardList($door, [
        'limit' => max(1, min((int) $this->option('limit'), 5000)),
    ]);

    if (! $result->successful) {
        $this->error($result->message);

        if ($result->response !== null) {
            $this->line(json_encode($result->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::FAILURE;
    }

    $cards = collect(data_get($result->response, 'body.cards', data_get($result->response, 'cards', [])));
    $deviceCards = $cards
        ->map(function (array $card) use ($normalizeCard): string {
            $primary = (string) ($card['card_number_decimal'] ?? $card['card_number'] ?? '');

            return $normalizeCard($primary);
        })
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $deviceSearchCards = $cards
        ->flatMap(fn (array $card): array => [
            (string) ($card['card_number_decimal'] ?? ''),
            (string) ($card['card_number'] ?? ''),
        ])
        ->map(fn (string $cardNumber): string => $normalizeCard($cardNumber))
        ->filter()
        ->unique()
        ->values();

    $missing = $expectedCards->reject(fn (string $cardNumber): bool => $deviceSearchCards->contains($cardNumber))->values();
    $extra = $deviceCards->reject(fn (string $cardNumber): bool => $expectedCards->contains($cardNumber))->values();

    $this->line('Door: '.$door->displayName());
    $this->line('Expected authorized cards in MACS: '.$expectedCards->count());
    $this->line('Cards stored locally on controller: '.$deviceCards->count());

    if ($missing->isEmpty() && $extra->isEmpty()) {
        $this->info('Controller local card list matches MACS authorized cards.');

        return self::SUCCESS;
    }

    if ($missing->isNotEmpty()) {
        $this->error('Missing on controller: '.$missing->implode(', '));
    }

    if ($extra->isNotEmpty()) {
        $this->error('Extra on controller: '.$extra->implode(', '));
    }

    if ($missing->isEmpty() && $extra->isNotEmpty() && $this->option('remove-extra')) {
        $failed = false;

        foreach ($extra as $cardNumber) {
            $log = AccessSyncLog::query()->create([
                'action' => AccessSyncAction::DeleteCard->value,
                'status' => 'pending',
                'payload' => [
                    'card_number' => $cardNumber,
                    'source' => 'verify-card-list-remove-extra',
                ],
            ]);

            $syncedLog = $manager->syncLog($door, $log);

            if ($syncedLog->status === 'success') {
                $this->info('Removed extra controller card: '.$cardNumber);
            } else {
                $failed = true;
                $this->error('Failed to remove extra controller card '.$cardNumber.': '.$syncedLog->error);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    return self::FAILURE;
})->purpose('Compare MACS active authorized RFID cards with the controller local card list');

Artisan::command('backup:run {--type=scheduled}', function (BackupManager $manager) {
    $type = in_array($this->option('type'), ['manual', 'scheduled'], true)
        ? $this->option('type')
        : 'scheduled';

    AuditLog::query()->create([
        'module' => 'backup',
        'action' => 'started',
        'record_type' => BackupLog::class,
        'new_values' => ['backup_type' => $type],
    ]);

    $log = $manager->run($type);

    AuditLog::query()->create([
        'module' => 'backup',
        'action' => $log->status === 'completed' ? 'completed' : 'failed',
        'record_type' => BackupLog::class,
        'record_id' => $log->id,
        'new_values' => [
            'backup_type' => $log->backup_type,
            'status' => $log->status,
            'filename' => $log->filename,
            'file_size' => $log->file_size,
            'error_message' => $log->error_message,
        ],
    ]);

    $this->info('Backup '.$log->status.'.');
})->purpose('Create a local system backup');

Schedule::command('access:bridge-heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('access:sync-pending')->everyThirtyMinutes();
Schedule::command('memberships:detect-expired --sync --sync-limit=500')->dailyAt('21:00')->withoutOverlapping();
Schedule::command('backup:run')
    ->dailyAt('22:00')
    ->when(fn () => app(SystemSettings::class)->get('backup_schedule', 'daily') === 'daily');
Schedule::command('backup:run')
    ->weeklyOn(0, '22:00')
    ->when(fn () => app(SystemSettings::class)->get('backup_schedule', 'daily') === 'weekly');
