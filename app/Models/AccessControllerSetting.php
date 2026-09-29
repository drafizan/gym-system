<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
            "access-controller-online:{$this->id}:{$this->host}:".$this->effectivePort(),
            now()->addSeconds(30),
            fn (): bool => $this->canOpenTcpConnection()
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

    private function canOpenTcpConnection(): bool
    {
        $connection = @fsockopen((string) $this->host, $this->effectivePort(), $errno, $errstr, 0.35);

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
