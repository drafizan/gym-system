<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

#[Fillable([
    'name',
    'driver',
    'host',
    'port',
    'is_enabled',
    'encrypted_credentials',
    'last_sync_at',
    'last_sync_status',
    'last_error',
])]
class AccessControllerSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'encrypted_credentials' => 'encrypted:array',
            'last_sync_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        return match ($this->name) {
            'Door Access Unit 1' => '1st Floor Door',
            'Door Access Unit 2' => '2nd Floor Door',
            default => $this->name,
        };
    }

    public function isReadyForSync(): bool
    {
        return $this->is_enabled
            && filled($this->host)
            && $this->last_sync_status === 'success';
    }

    public function isOnline(): bool
    {
        if ($this->driver === 'fake') {
            return $this->isReadyForSync();
        }

        if (! $this->is_enabled || blank($this->host)) {
            return false;
        }

        return Cache::remember(
            'access-controller-handshake:'.$this->id.':'.hash('sha256', json_encode([$this->host, $this->effectivePort(), $this->encrypted_credentials])),
            now()->addSeconds(30),
            fn (): bool => $this->canReadAndWrite()
        );
    }

    public function statusLabel(): string
    {
        return $this->displayName().' '.($this->isOnline() ? 'Online' : 'Offline');
    }

    public function effectivePort(): int
    {
        return (int) ($this->port ?: ($this->driver === 'dahua_standalone' ? 37777 : 80));
    }

    private function canReadAndWrite(): bool
    {
        $credentials = $this->encrypted_credentials ?? [];
        $url = rtrim((string) ($credentials['bridge_url'] ?? config('gym.access.dahua_bridge_url', '')), '/');
        if ($this->driver !== 'dahua_standalone' || $url === '' || blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)) {
            return false;
        }

        try {
            $request = Http::connectTimeout(1)->timeout(12)->acceptJson();
            if (filled($credentials['bridge_token'] ?? null)) {
                $request = $request->withToken($credentials['bridge_token']);
            }
            $response = $request->post($url.'/door-command', [
                'command' => 'handshake',
                'door' => [
                    'host' => $this->host,
                    'port' => $this->effectivePort(),
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                ],
            ]);

            return $response->successful()
                && $response->json('ok') === true
                && $response->json('read_verified') === true
                && $response->json('write_verified') === true;
        } catch (ConnectionException $exception) {
            return false;
        }
    }
}
