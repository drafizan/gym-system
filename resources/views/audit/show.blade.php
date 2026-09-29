@extends('layouts.app')

@section('title', 'Audit Detail')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('audit.index') }}">Audit Trail</a>
    <span>Detail</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Security</p>
            <h1>Audit Detail</h1>
        </div>
        <div class="toolbar-actions">
            <x-icon-action icon="back" label="Back to audit trail" href="{{ route('audit.index') }}" />
        </div>
    </div>

    <section class="audit-detail-grid">
        <x-panel title="Event">
            <div class="sync-list">
                <div><span>Date</span><strong>{{ $auditLog->created_at->format('d M Y, h:i A') }}</strong></div>
                <div><span>User</span><strong>{{ $auditLog->user?->name ?? 'System' }}</strong></div>
                <div><span>Module</span><strong>{{ str($auditLog->module)->headline() }}</strong></div>
                <div><span>Action</span><strong>{{ str($auditLog->action)->headline() }}</strong></div>
                <div><span>Record</span><strong>{{ class_basename($auditLog->record_type) ?: '-' }} {{ $auditLog->record_id ? '#'.$auditLog->record_id : '' }}</strong></div>
                <div><span>IP Address</span><strong>{{ $auditLog->ip_address ?? '-' }}</strong></div>
            </div>
        </x-panel>

        <x-panel title="Request">
            <p class="muted audit-user-agent">{{ $auditLog->user_agent ?? 'No user agent recorded.' }}</p>
        </x-panel>
    </section>

    @php
        $oldValues = $auditLog->old_values ?? [];
        $newValues = $auditLog->new_values ?? [];
        $changedKeys = collect(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))))
            ->reject(fn ($key) => in_array($key, ['created_at', 'updated_at'], true))
            ->filter(fn ($key) => ($oldValues[$key] ?? null) !== ($newValues[$key] ?? null));
    @endphp

    @if ($changedKeys->isNotEmpty())
        <section class="table-shell audit-change-summary">
            <div class="table-shell-header">
                <h2>Change Summary</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Before</th>
                            <th>After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changedKeys as $key)
                            <tr>
                                <td>{{ str($key)->headline() }}</td>
                                <td>{{ is_scalar($oldValues[$key] ?? null) ? ($oldValues[$key] ?? '-') : json_encode($oldValues[$key] ?? []) }}</td>
                                <td>{{ is_scalar($newValues[$key] ?? null) ? ($newValues[$key] ?? '-') : json_encode($newValues[$key] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="audit-values-grid">
        <x-panel title="Old Values">
            <pre class="audit-json">{{ json_encode($auditLog->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-panel>

        <x-panel title="New Values">
            <pre class="audit-json">{{ json_encode($auditLog->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-panel>
    </section>
@endsection
