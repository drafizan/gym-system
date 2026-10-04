<?php

// Run with: php scripts/deploy.php
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
chdir($root);
$lock = fopen($root.'/storage/app/system-update.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another system update is already running.\n");
    exit(1);
}

function runUpdateCommand(array $command, string $root): string
{
    $process = new Process($command, $root, ['GIT_TERMINAL_PROMPT' => '0', 'COMPOSER_NO_INTERACTION' => '1']);
    $process->setTimeout(600);
    $process->run(fn ($type, $output) => print ($output));
    if (! $process->isSuccessful()) {
        throw new RuntimeException('Update stopped: '.$command[0].' exited with '.$process->getExitCode().'.');
    }

    return trim($process->getOutput());
}

try {
    echo "Checking repository...\n";
    $status = runUpdateCommand(['git', 'status', '--porcelain'], $root);
    if ($status !== '') {
        throw new RuntimeException('Local changes exist. Commit or remove them before updating.');
    }
    $branch = runUpdateCommand(['git', 'branch', '--show-current'], $root);
    if ($branch !== 'main') {
        throw new RuntimeException('Switch this installation to main before updating.');
    }
    echo "Pulling latest main from Git...\n";
    runUpdateCommand(['git', 'pull', '--ff-only', 'origin', 'main'], $root);
    echo "Installing PHP dependencies...\n";
    runUpdateCommand(['composer', 'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction'], $root);
    echo "Installing and building frontend assets...\n";
    runUpdateCommand(['npm', 'ci'], $root);
    runUpdateCommand(['npm', 'run', 'build'], $root);
    echo "Updating database tables...\n";
    runUpdateCommand([PHP_BINARY, 'artisan', 'optimize:clear'], $root);
    runUpdateCommand([PHP_BINARY, 'artisan', 'migrate', '--force'], $root);
    echo "Refreshing application caches...\n";
    foreach (['config:cache', 'route:cache', 'view:cache', 'queue:restart'] as $command) {
        runUpdateCommand([PHP_BINARY, 'artisan', $command], $root);
    }
    echo "System update completed successfully.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
