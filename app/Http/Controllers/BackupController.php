<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Support\Audit;
use App\Support\BackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function import(Request $request, BackupManager $backups): RedirectResponse
    {
        $request->validate(['backup_file' => ['required', 'file', 'max:524288', 'extensions:zip']]);

        try {
            $backups->import($request->file('backup_file'), $request->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['backup_file' => $exception->getMessage()]);
        }

        return redirect()->route('backups.index')->with('success', 'Backup uploaded. Select Restore Backup to copy its data into this deployment.');
    }

    public function index(BackupManager $backups): View
    {
        $latest = BackupLog::query()->latest()->first();

        return view('backups.index', [
            'backups' => BackupLog::query()->latest()->paginate(15),
            'latest' => $latest,
            'backupPath' => $backups->backupPath(),
        ]);
    }

    public function store(Request $request, BackupManager $backups): RedirectResponse
    {
        Audit::record($request, 'backup', 'started', BackupLog::class, null, null, [
            'backup_type' => 'manual',
        ]);

        $log = $backups->run('manual', $request->user());

        Audit::record($request, 'backup', $log->status === 'completed' ? 'completed' : 'failed', BackupLog::class, $log->id, null, [
            'backup_type' => $log->backup_type,
            'status' => $log->status,
            'filename' => $log->filename,
            'file_size' => $log->file_size,
            'error_message' => $log->error_message,
        ]);

        if ($log->status === 'completed') {
            return back()->with('success', 'Backup completed successfully.');
        }

        return back()->with('error', 'Backup failed: '.$log->error_message);
    }

    public function download(BackupLog $backupLog): BinaryFileResponse
    {
        abort_unless($backupLog->isDownloadable(), 404);

        return response()->download($backupLog->path, $backupLog->filename);
    }

    public function restore(Request $request, BackupLog $backupLog, BackupManager $backups): RedirectResponse
    {
        $request->merge([
            'restore_confirmation' => str($request->input('restore_confirmation'))->trim()->upper()->toString(),
        ]);

        $validated = $request->validate([
            'restore_confirmation' => ['required', 'string', 'in:RESTORE'],
            'current_password' => ['required', 'current_password'],
        ], [
            'restore_confirmation.in' => 'Type RESTORE to confirm the backup restore.',
        ]);

        Audit::record($request, 'backup', 'restore_started', BackupLog::class, $backupLog->id, null, [
            'filename' => $backupLog->filename,
            'confirmation' => $validated['restore_confirmation'],
        ]);

        $result = $backups->restore($backupLog);

        Audit::record($request, 'backup', $result['status'] === 'completed' ? 'restore_completed' : 'restore_failed', BackupLog::class, $backupLog->id, null, [
            'filename' => $backupLog->filename,
            'status' => $result['status'],
            'error_message' => $result['error_message'],
        ]);

        if ($result['status'] === 'completed') {
            return back()->with('success', 'Backup restored successfully.');
        }

        return back()->with('error', 'Restore failed: '.$result['error_message']);
    }
}
