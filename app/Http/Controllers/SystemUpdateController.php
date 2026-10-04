<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class SystemUpdateController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'administrator', 403);
        if (! config('gym.deployment.enabled')) {
            return redirect()->route('settings.index')->with('error', 'System updates are disabled. Enable DEPLOYMENT_UPDATES_ENABLED on this installation.');
        }

        $php = (new PhpExecutableFinder)->find();
        if (! $php) {
            return redirect()->route('settings.index')->with('error', 'PHP command-line runtime could not be found.');
        }
        $timeout = max(60, (int) config('gym.deployment.timeout', 300));
        set_time_limit($timeout + 30);
        $process = new Process([$php, base_path('scripts/deploy.php')], base_path());
        $process->setTimeout($timeout);
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return redirect()->route('settings.index')->with('error', 'System update timed out. Review the output before retrying.')
                ->with('deployment_output', substr($process->getOutput().$process->getErrorOutput(), -12000));
        }

        return redirect()->route('settings.index')
            ->with($process->isSuccessful() ? 'success' : 'error', $process->isSuccessful() ? 'System updated successfully, including database migrations.' : 'System update failed. Review the output below.')
            ->with('deployment_output', substr($process->getOutput().$process->getErrorOutput(), -12000));
    }
}
