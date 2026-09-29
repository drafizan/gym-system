<?php

namespace App\Contracts;

use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Services\AccessSyncResult;

interface AccessControllerClient
{
    public function sync(AccessControllerSetting $setting, AccessSyncLog $log): AccessSyncResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function command(AccessControllerSetting $setting, string $command, array $options = []): AccessSyncResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function cardList(AccessControllerSetting $setting, array $options = []): AccessSyncResult;
}
