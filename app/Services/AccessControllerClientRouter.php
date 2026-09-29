<?php

namespace App\Services;

use App\Contracts\AccessControllerClient;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;

class AccessControllerClientRouter implements AccessControllerClient
{
    public function __construct(
        private readonly FakeAccessControllerClient $fake,
        private readonly DahuaStandaloneAccessControllerClient $dahua,
    ) {
        //
    }

    public function sync(AccessControllerSetting $setting, AccessSyncLog $log): AccessSyncResult
    {
        return match ($setting->driver) {
            'fake' => $this->fake->sync($setting, $log),
            'dahua_standalone' => $this->dahua->sync($setting, $log),
            default => AccessSyncResult::failed('Unsupported access controller driver: '.$setting->driver),
        };
    }

    public function command(AccessControllerSetting $setting, string $command, array $options = []): AccessSyncResult
    {
        return match ($setting->driver) {
            'fake' => $this->fake->command($setting, $command, $options),
            'dahua_standalone' => $this->dahua->command($setting, $command, $options),
            default => AccessSyncResult::failed('Unsupported access controller driver: '.$setting->driver),
        };
    }

    public function cardList(AccessControllerSetting $setting, array $options = []): AccessSyncResult
    {
        return match ($setting->driver) {
            'fake' => $this->fake->cardList($setting, $options),
            'dahua_standalone' => $this->dahua->cardList($setting, $options),
            default => AccessSyncResult::failed('Unsupported access controller driver: '.$setting->driver),
        };
    }
}
