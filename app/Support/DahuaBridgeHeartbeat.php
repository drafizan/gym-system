<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DahuaBridgeHeartbeat
{
    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $health = $this->health();
        $pid = $this->pid();

        return [
            ...$health,
            'pid' => $pid,
            'pid_running' => $pid !== null && $this->pidBelongsToBridge($pid),
            'script' => $this->scriptPath(),
            'log_file' => $this->logFile(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureRunning(): array
    {
        return $this->withBridgeLock(fn (): array => $this->ensureRunningLocked());
    }

    /**
     * @return array<string, mixed>
     */
    public function restart(): array
    {
        return $this->withBridgeLock(function (): array {
            $pid = $this->pid();

            if ($pid !== null) {
                if ($this->pidBelongsToBridge($pid)) {
                    $this->terminateBridge($pid);
                } else {
                    File::delete($this->pidFile());
                }
            }

            $start = $this->startBridge();

            if (! $start['ok']) {
                return [
                    ...$this->status(),
                    ...$start,
                    'message' => $start['message'],
                ];
            }

            usleep(max(100, (int) config('gym.access.dahua_bridge_restart_wait_ms', 700)) * 1000);

            $health = $this->health();

            return [
                ...$this->status(),
                'ok' => $health['healthy'],
                'started' => true,
                'start' => $start,
                'message' => $health['healthy']
                    ? 'Dahua bridge was restarted and is healthy.'
                    : 'Dahua bridge was restarted but is not responding to health checks yet.',
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureRunningLocked(): array
    {
        $health = $this->health();

        if ($health['healthy']) {
            return [
                ...$this->status(),
                'ok' => true,
                'started' => false,
                'message' => 'Dahua bridge is healthy.',
            ];
        }

        $pid = $this->pid();

        if ($pid !== null) {
            if ($this->pidBelongsToBridge($pid)) {
                $this->terminateBridge($pid);
            } else {
                File::delete($this->pidFile());
            }
        }

        $start = $this->startBridge();

        if (! $start['ok']) {
            return [
                ...$this->status(),
                ...$start,
                'message' => $start['message'],
            ];
        }

        usleep(max(100, (int) config('gym.access.dahua_bridge_restart_wait_ms', 700)) * 1000);

        $health = $this->health();

        return [
            ...$this->status(),
            'ok' => $health['healthy'],
            'started' => true,
            'start' => $start,
            'message' => $health['healthy']
                ? 'Dahua bridge was started and is healthy.'
                : 'Dahua bridge was started but is not responding to health checks yet.',
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withBridgeLock(callable $callback): array
    {
        $lockFile = storage_path('app/dahua-bridge.lock');

        File::ensureDirectoryExists(dirname($lockFile));

        $handle = fopen($lockFile, 'c');

        if ($handle === false) {
            return $callback();
        }

        try {
            flock($handle, LOCK_EX);

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        $url = $this->healthUrl();

        try {
            $response = Http::timeout(1)->acceptJson()->get($url);
        } catch (ConnectionException $exception) {
            return [
                'healthy' => false,
                'health_url' => $url,
                'http_status' => null,
                'error' => str($exception->getMessage())->limit(250)->toString(),
            ];
        } catch (Throwable $exception) {
            return [
                'healthy' => false,
                'health_url' => $url,
                'http_status' => null,
                'error' => str($exception->getMessage())->limit(250)->toString(),
            ];
        }

        return [
            'healthy' => $response->successful(),
            'health_url' => $url,
            'http_status' => $response->status(),
            'response' => $response->json() ?? str($response->body())->limit(250)->toString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function startBridge(): array
    {
        if (app()->runningUnitTests()) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Dahua bridge process launch is disabled during automated tests.',
            ];
        }

        $script = $this->scriptPath();

        if (! File::exists($script)) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Dahua bridge script not found: '.$script,
            ];
        }

        File::ensureDirectoryExists(dirname($this->pidFile()));
        File::ensureDirectoryExists(dirname($this->logFile()));

        return $this->startBridgeProcess($script);
    }

    /**
     * @return array<string, mixed>
     */
    private function startBridgeProcess(string $script): array
    {
        $python = (string) config('gym.access.dahua_bridge_python', 'python');
        $launcher = base_path('scripts/start_dahua_bridge.py');

        $python = (new ExecutableFinder)->find($python) ?? $python;

        if (! is_file($python)) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Dahua bridge Python runtime not found: '.$python,
            ];
        }

        if (! File::exists($launcher)) {
            return [
                'ok' => false,
                'started' => false,
                'message' => 'Dahua bridge launcher not found: '.$launcher,
            ];
        }

        $process = new Process([
            $python,
            $launcher,
            '--python', $python,
            '--script', $script,
            '--log', $this->logFile(),
            '--working-directory', base_path(),
            '--host', (string) config('gym.access.dahua_bridge_host', '127.0.0.1'),
            '--port', (string) config('gym.access.dahua_bridge_port', 8787),
            '--sdk', (string) config('gym.access.dahua_bridge_netsdk', ''),
        ], base_path(), null, null, 10);
        $process->run();

        $pid = trim($process->getOutput());
        $started = $process->isSuccessful() && ctype_digit($pid);

        if ($started) {
            File::put($this->pidFile(), $pid);
        }

        return [
            'ok' => $started,
            'started' => $started,
            'pid' => $started ? (int) $pid : null,
            'message' => $started
                ? 'Dahua bridge start command executed.'
                : 'Failed to start Dahua bridge.',
            'error' => $started ? null : trim($process->getErrorOutput() ?: $process->getOutput()),
        ];
    }

    private function pid(): ?int
    {
        $pidFile = $this->pidFile();

        if (! File::exists($pidFile)) {
            return null;
        }

        $pid = trim((string) File::get($pidFile));

        return ctype_digit($pid) ? (int) $pid : null;
    }

    private function pidBelongsToBridge(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        $process = new Process(['ps', '-p', (string) $pid, '-o', 'command=']);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str($process->getOutput())->contains('dahua_sdk_bridge.py');
    }

    private function terminateBridge(int $pid): void
    {
        if (! $this->pidBelongsToBridge($pid)) {
            return;
        }

        (new Process(['kill', '-TERM', (string) $pid]))->run();
        File::delete($this->pidFile());
    }

    private function healthUrl(): string
    {
        return (string) config('gym.access.dahua_bridge_health_url', 'http://127.0.0.1:8787/health');
    }

    private function scriptPath(): string
    {
        return (string) config('gym.access.dahua_bridge_script', base_path('scripts/dahua_sdk_bridge.py'));
    }

    private function pidFile(): string
    {
        return (string) config('gym.access.dahua_bridge_pid_file', storage_path('app/dahua-bridge.pid'));
    }

    private function logFile(): string
    {
        return (string) config('gym.access.dahua_bridge_log_file', storage_path('logs/dahua-bridge.log'));
    }
}
