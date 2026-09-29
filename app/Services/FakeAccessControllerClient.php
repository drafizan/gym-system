<?php

namespace App\Services;

use App\Contracts\AccessControllerClient;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;

class FakeAccessControllerClient implements AccessControllerClient
{
    public function sync(AccessControllerSetting $setting, AccessSyncLog $log): AccessSyncResult
    {
        return AccessSyncResult::success('Fake controller accepted sync request.', [
            'driver' => $setting->driver,
            'action' => $log->action,
            'member_id' => $log->member_id,
            'rfid_card_id' => $log->rfid_card_id,
        ]);
    }

    public function command(AccessControllerSetting $setting, string $command, array $options = []): AccessSyncResult
    {
        return AccessSyncResult::success('Fake controller accepted door command.', [
            'driver' => $setting->driver,
            'door' => $setting->displayName(),
            'command' => $command,
            'options' => $options,
        ]);
    }

    public function cardList(AccessControllerSetting $setting, array $options = []): AccessSyncResult
    {
        return AccessSyncResult::success('Fake controller returned card list.', [
            'driver' => $setting->driver,
            'door' => $setting->displayName(),
            'cards' => $options['cards'] ?? [],
        ]);
    }
}
