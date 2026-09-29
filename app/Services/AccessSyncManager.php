<?php

namespace App\Services;

use App\Contracts\AccessControllerClient;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccessSyncManager
{
    public function __construct(private readonly AccessControllerClient $client)
    {
        //
    }

    public function syncPending(int $limit = 50): int
    {
        $settings = AccessControllerSetting::query()
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        if ($settings->isEmpty()) {
            return 0;
        }

        $logs = AccessSyncLog::query()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $synced = 0;

        foreach ($logs as $log) {
            $this->syncLogToSettings($settings, $log);
            $synced++;
        }

        return $synced;
    }

    public function syncLog(AccessControllerSetting $setting, AccessSyncLog $log): AccessSyncLog
    {
        return $this->syncLogToSettings(collect([$setting]), $log);
    }

    /**
     * @param  iterable<AccessControllerSetting>  $settings
     */
    private function syncLogToSettings(iterable $settings, AccessSyncLog $log): AccessSyncLog
    {
        return DB::transaction(function () use ($settings, $log): AccessSyncLog {
            $responses = [];
            $errors = [];

            foreach ($settings as $setting) {
                $settingName = $setting->displayName();

                try {
                    $result = $this->client->sync($setting, $log);

                    $responses[$settingName] = [
                        'status' => $result->successful ? 'success' : 'failed',
                        'message' => $result->message,
                        'response' => $result->response,
                    ];

                    if (! $result->successful) {
                        $errors[] = $settingName.': '.$result->message;
                    }

                    $setting->forceFill([
                        'last_sync_at' => now(),
                        'last_sync_status' => $result->successful ? 'success' : 'failed',
                        'last_error' => $result->successful ? null : $result->message,
                    ])->save();
                } catch (Throwable $exception) {
                    $responses[$settingName] = [
                        'status' => 'failed',
                        'message' => $exception->getMessage(),
                    ];
                    $errors[] = $settingName.': '.$exception->getMessage();

                    $setting->forceFill([
                        'last_sync_at' => now(),
                        'last_sync_status' => 'failed',
                        'last_error' => $exception->getMessage(),
                    ])->save();
                }
            }

            $log->forceFill([
                'status' => empty($errors) ? 'success' : 'failed',
                'response' => json_encode(['controllers' => $responses]),
                'error' => empty($errors) ? null : implode('; ', $errors),
            ])->save();

            return $log->fresh();
        });
    }
}
