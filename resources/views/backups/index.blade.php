@extends('layouts.app')

@section('title', 'Backup')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>Backup</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">System</p>
            <h1>Backup & Restore</h1>
        </div>
        <form method="POST" action="{{ route('backups.store') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Backup Now</button>
        </form>
    </div>

    <section class="backup-overview">
        <div class="backup-card">
            <span>Latest backup</span>
            <strong>{{ $latest?->completed_at?->format('d M Y, h:i A') ?? '-' }}</strong>
            <small>{{ $latest?->filename ?? 'No backup has been created yet.' }}</small>
        </div>
        <div class="backup-card">
            <span>Status</span>
            <strong>{{ $latest ? str($latest->status)->headline() : '-' }}</strong>
            <small>{{ $latest?->error_message ? str($latest->error_message)->limit(90) : 'Backup runs manually or by schedule.' }}</small>
        </div>
        <div class="backup-card">
            <span>Backup folder</span>
            <strong>Local storage</strong>
            <small>{{ $backupPath }}</small>
        </div>
    </section>

    <section class="table-shell">
        <div class="table-shell-header">
            <h2>Backup History</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Created By</th>
                        <th>Error</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td>{{ $backup->started_at?->format('d M Y, h:i A') ?? $backup->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ str($backup->backup_type)->headline() }}</td>
                            <td><span class="sale-type-pill {{ $backup->statusTone() }}">{{ str($backup->status)->headline() }}</span></td>
                            <td>{{ $backup->filename ?? '-' }}</td>
                            <td>{{ $backup->formattedFileSize() }}</td>
                            <td>{{ $backup->user?->name ?? 'System' }}</td>
                            <td>{{ $backup->error_message ? str($backup->error_message)->limit(60) : '-' }}</td>
                            <td>
                                @if ($backup->isDownloadable())
                                    <div class="table-actions">
                                        <x-icon-action icon="download" label="Download backup" href="{{ route('backups.download', $backup) }}" />
                                        <x-icon-action icon="restore" label="Restore backup" type="button" variant="danger-soft" data-modal-open="restore-backup-{{ $backup->id }}" />
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No backup history found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            {{ $backups->links() }}
        </div>
    </section>

    @foreach ($backups as $backup)
        @if ($backup->isDownloadable())
            <div class="modal-backdrop" data-modal="restore-backup-{{ $backup->id }}" aria-hidden="true">
                <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="restore-backup-title-{{ $backup->id }}">
                    <button type="button" class="modal-close" data-modal-close aria-label="Close restore modal"></button>
                    <p class="eyebrow">System Restore</p>
                    <h2 id="restore-backup-title-{{ $backup->id }}">Restore backup</h2>
                    <p>This will replace the current database and member photos with data from {{ $backup->filename }}. Type RESTORE to continue.</p>

                    <form class="modal-form" method="POST" action="{{ route('backups.restore', $backup) }}">
                        @csrf
                        <label class="field">
                            <span>Confirmation</span>
                            <input type="text" name="restore_confirmation" autocomplete="off" placeholder="RESTORE" required>
                        </label>
                        <label class="field">
                            <span>Current Password</span>
                            <input type="password" name="current_password" autocomplete="current-password" placeholder="Enter current password" required>
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-danger-soft">Restore Backup</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif
    @endforeach
@endsection
