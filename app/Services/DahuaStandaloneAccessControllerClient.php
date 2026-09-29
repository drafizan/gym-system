<?php

namespace App\Services;

use App\Contracts\AccessControllerClient;
use App\Models\AccessControllerSetting;
use App\Models\AccessSyncLog;
use App\Support\DahuaBridgeHeartbeat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DahuaStandaloneAccessControllerClient implements AccessControllerClient
{
    public function __construct(
        private readonly DahuaBridgeHeartbeat $heartbeat,
    ) {
        //
    }

    public function sync(AccessControllerSetting $setting, AccessSyncLog $log): AccessSyncResult
    {
        $credentials = $setting->encrypted_credentials ?? [];
        $bridgeUrl = $this->bridgeUrl($credentials);

        if ($bridgeUrl === '') {
            return $this->missingBridgeResult('sync');
        }

        $bridge = $this->ensureBridge('sync', $bridgeUrl);

        if ($bridge !== null) {
            return $bridge;
        }

        $payload = $this->bridgePayload($setting, $log, $credentials);

        try {
            $response = $this->bridgeRequest($credentials)->post($bridgeUrl.'/sync-card', $payload);
        } catch (ConnectionException $exception) {
            return $this->bridgeUnavailableResult('sync', $bridgeUrl, $exception);
        }

        if ($response->successful()) {
            return AccessSyncResult::success('Dahua bridge accepted sync request.', [
                'driver' => $setting->driver,
                'bridge_url' => $bridgeUrl,
                'http_status' => $response->status(),
                'action' => $log->action,
                'body' => $response->json() ?? [],
            ]);
        }

        return AccessSyncResult::failed('Dahua bridge rejected sync request with HTTP '.$response->status().'.', [
            'driver' => $setting->driver,
            'bridge_url' => $bridgeUrl,
            'http_status' => $response->status(),
            'body' => str($response->body())->limit(500)->toString(),
        ]);
    }

    public function command(AccessControllerSetting $setting, string $command, array $options = []): AccessSyncResult
    {
        $command = str($command)->lower()->toString();

        if (! in_array($command, ['unlock', 'lock', 'status', 'sync-time'], true)) {
            return AccessSyncResult::failed('Unsupported Dahua door command: '.$command);
        }

        $credentials = $setting->encrypted_credentials ?? [];
        $bridgeUrl = $this->bridgeUrl($credentials);

        if ($bridgeUrl === '') {
            return $this->missingBridgeResult($command);
        }

        $bridge = $this->ensureBridge($command, $bridgeUrl);

        if ($bridge !== null) {
            return $bridge;
        }

        $payload = [
            'command' => $command,
            'door' => $this->doorPayload($setting, $credentials),
            'options' => [
                'unlock_seconds' => max(1, min((int) ($options['unlock_seconds'] ?? 5), 60)),
                'device_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
            ],
        ];

        try {
            $response = $this->bridgeRequest($credentials)->post($bridgeUrl.'/door-command', $payload);
        } catch (ConnectionException $exception) {
            return $this->bridgeUnavailableResult($command, $bridgeUrl, $exception);
        }

        if ($response->successful()) {
            return AccessSyncResult::success('Dahua bridge accepted '.$command.' command.', [
                'driver' => $setting->driver,
                'bridge_url' => $bridgeUrl,
                'http_status' => $response->status(),
                'command' => $command,
                'body' => $response->json() ?? [],
            ]);
        }

        return AccessSyncResult::failed('Dahua bridge rejected '.$command.' command with HTTP '.$response->status().'.', [
            'driver' => $setting->driver,
            'bridge_url' => $bridgeUrl,
            'http_status' => $response->status(),
            'command' => $command,
            'body' => str($response->body())->limit(500)->toString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function accessHistory(AccessControllerSetting $setting, array $filters = []): AccessSyncResult
    {
        $credentials = $setting->encrypted_credentials ?? [];
        $bridgeUrl = $this->bridgeUrl($credentials);

        if ($bridgeUrl === '') {
            return $this->missingBridgeResult('access history');
        }

        $bridge = $this->ensureBridge('access history', $bridgeUrl);

        if ($bridge !== null) {
            return $bridge;
        }

        $payload = [
            'door' => $this->doorPayload($setting, $credentials),
            'filters' => [
                'card_number' => $this->normalizeCardNumber((string) ($filters['card_number'] ?? ''), (string) ($credentials['card_number_format'] ?? 'decimal')),
                'date_from' => $filters['date_from'] ?? now()->toDateString(),
                'date_to' => $filters['date_to'] ?? now()->toDateString(),
                'limit' => max(1, min((int) ($filters['limit'] ?? 50), 200)),
            ],
        ];

        try {
            $response = $this->bridgeRequest($credentials)->post($bridgeUrl.'/access-history', $payload);
        } catch (ConnectionException $exception) {
            return $this->bridgeUnavailableResult('access history', $bridgeUrl, $exception);
        }

        if ($response->successful()) {
            return AccessSyncResult::success('Dahua bridge returned access history.', [
                'driver' => $setting->driver,
                'bridge_url' => $bridgeUrl,
                'http_status' => $response->status(),
                'body' => $response->json() ?? [],
            ]);
        }

        return AccessSyncResult::failed('Dahua bridge rejected access history request with HTTP '.$response->status().'.', [
            'driver' => $setting->driver,
            'bridge_url' => $bridgeUrl,
            'http_status' => $response->status(),
            'body' => str($response->body())->limit(500)->toString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function cardList(AccessControllerSetting $setting, array $options = []): AccessSyncResult
    {
        $credentials = $setting->encrypted_credentials ?? [];
        $bridgeUrl = $this->bridgeUrl($credentials);

        if ($bridgeUrl === '') {
            return $this->missingBridgeResult('card list');
        }

        $bridge = $this->ensureBridge('card list', $bridgeUrl);

        if ($bridge !== null) {
            return $bridge;
        }

        $payload = [
            'door' => $this->doorPayload($setting, $credentials),
            'options' => [
                'limit' => max(1, min((int) ($options['limit'] ?? 500), 5000)),
            ],
        ];

        try {
            $response = $this->bridgeRequest($credentials, (int) config('gym.access.card_list_timeout', 30))
                ->post($bridgeUrl.'/card-list', $payload);
        } catch (ConnectionException $exception) {
            return $this->bridgeUnavailableResult('card list', $bridgeUrl, $exception);
        }

        if ($response->successful()) {
            return AccessSyncResult::success('Dahua bridge returned local card list.', [
                'driver' => $setting->driver,
                'bridge_url' => $bridgeUrl,
                'http_status' => $response->status(),
                'body' => $response->json() ?? [],
            ]);
        }

        return AccessSyncResult::failed('Dahua bridge rejected card list request with HTTP '.$response->status().'.', [
            'driver' => $setting->driver,
            'bridge_url' => $bridgeUrl,
            'http_status' => $response->status(),
            'body' => str($response->body())->limit(500)->toString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function bridgePayload(AccessControllerSetting $setting, AccessSyncLog $log, array $credentials): array
    {
        $log->loadMissing('member');
        $sourcePayload = $log->payload ?? [];
        $cardNumber = (string) ($sourcePayload['card_number'] ?? $sourcePayload['rfid_card_number'] ?? '');

        return [
            'action' => $log->action,
            'door' => [
                ...$this->doorPayload($setting, $credentials),
            ],
            'user' => [
                'id' => $this->deviceUserId($log),
                'member_id' => $log->member_id,
                'member_no' => $sourcePayload['member_no'] ?? $log->member?->member_no,
                'name' => $log->member?->full_name,
            ],
            'card' => [
                'number' => $this->normalizeCardNumber($cardNumber, (string) ($credentials['card_number_format'] ?? 'decimal')),
                'format' => $credentials['card_number_format'] ?? 'decimal',
                'status' => $sourcePayload['card_status'] ?? null,
            ],
            'validity' => [
                'start_date' => $sourcePayload['start_date'] ?? null,
                'end_date' => $sourcePayload['end_date'] ?? null,
                'membership_status' => $sourcePayload['membership_status'] ?? null,
            ],
            'manual_reference' => [
                'protocol' => 'TCP/IP',
                'default_port' => 37777,
                'unlock_mode' => 'card',
                'offline_card_capacity' => 30000,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{name: string, host: string|null, port: int, username: mixed, password: mixed}
     */
    private function doorPayload(AccessControllerSetting $setting, array $credentials): array
    {
        return [
            'name' => $setting->displayName(),
            'host' => $setting->host,
            'port' => $setting->effectivePort(),
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function bridgeUrl(array $credentials): string
    {
        return rtrim((string) ($credentials['bridge_url'] ?? config('gym.access.dahua_bridge_url', '')), '/');
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function bridgeRequest(array $credentials, ?int $timeout = null): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout($timeout ?? (int) config('gym.access.timeout', 8))->acceptJson();

        if (filled($credentials['bridge_token'] ?? null)) {
            $request = $request->withToken((string) $credentials['bridge_token']);
        }

        return $request;
    }

    private function ensureBridge(string $operation, string $bridgeUrl): ?AccessSyncResult
    {
        if (! str($bridgeUrl)->startsWith('http://127.0.0.1:')) {
            return null;
        }

        $bridge = $this->heartbeat->ensureRunning();

        if (($bridge['ok'] ?? false) === true) {
            return null;
        }

        return AccessSyncResult::failed('Dahua SDK bridge is unavailable for '.$operation.'.', [
            'driver' => 'dahua_standalone',
            'bridge_url' => $bridgeUrl,
            'health_url' => $bridge['health_url'] ?? config('gym.access.dahua_bridge_health_url'),
            'error' => $bridge['message'] ?? $bridge['error'] ?? 'Bridge heartbeat failed.',
        ]);
    }

    private function missingBridgeResult(string $operation): AccessSyncResult
    {
        return AccessSyncResult::failed(
            'Dahua standalone '.$operation.' requires a local SDK bridge endpoint. Configure Local SDK Bridge URL in Settings.'
        );
    }

    private function bridgeUnavailableResult(string $operation, string $bridgeUrl, ConnectionException $exception): AccessSyncResult
    {
        return AccessSyncResult::failed('Dahua SDK bridge is unavailable for '.$operation.'.', [
            'driver' => 'dahua_standalone',
            'bridge_url' => $bridgeUrl,
            'error' => str($exception->getMessage())->limit(300)->toString(),
        ]);
    }

    private function deviceUserId(AccessSyncLog $log): int
    {
        $memberNo = (string) (($log->payload ?? [])['member_no'] ?? $log->member?->member_no ?? '');
        $digits = preg_replace('/\D+/', '', $memberNo) ?: '';

        if ($digits !== '') {
            return (int) substr($digits, -9);
        }

        return (int) ($log->member_id ?? 0);
    }

    private function normalizeCardNumber(string $cardNumber, string $format): string
    {
        $cardNumber = trim($cardNumber);

        return $format === 'hex'
            ? strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $cardNumber) ?: '')
            : (preg_replace('/\D+/', '', $cardNumber) ?: $cardNumber);
    }
}
