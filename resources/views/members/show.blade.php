@extends('layouts.app')

@section('title', 'Member Profile')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <a href="{{ route('members.index') }}">Members</a>
    <span>{{ $member->member_no }}</span>
@endsection

@php
    $initials = collect(explode(' ', $member->full_name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    $photoUrl = $member->photo_path ? Storage::disk(config('gym.members.photo_disk'))->url($member->photo_path) : null;
    $membership = $member->latestMembership;
    $activeRfidCard = $member->activeRfidCard;
    $rfidNumber = $activeRfidCard?->card_number ?? $member->rfid_card_number;
    $latestSync = $member->latestAccessSyncLog;
    $accessSyncLabel = $latestSync
        ? str($latestSync->status)->headline().' - '.str($latestSync->action)->headline()
        : 'No sync queued';
    $canManageAccess = auth()->user()?->hasPermission('access.manage');
@endphp

@section('content')
    <section class="profile-hero profile-overview">
        <div class="profile-main">
            <div class="profile-avatar-xl">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $member->full_name }}">
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>

            <div class="profile-identity">
                <div class="profile-name-row">
                    <h1>{{ $member->full_name }}</h1>
                    <span class="sale-type-pill {{ $member->displayStatusTone() }}">{{ str($member->displayStatus())->headline() }}</span>
                </div>
                <div class="profile-meta-row">
                    <span>{{ $member->member_no }}</span>
                    <span>{{ $member->phone ?: 'No phone' }}</span>
                    <span>{{ $member->email ?? 'No email' }}</span>
                </div>
            </div>
        </div>

        <div class="profile-actions">
            <x-icon-action icon="edit" label="Edit profile" href="{{ route('members.edit', $member) }}" />
            @if (auth()->user()?->hasPermission('memberships.manage'))
                @if ($membership)
                    <a class="btn btn-primary" href="{{ route('member-memberships.renew', [$member, $membership]) }}">Renew Membership</a>
                @endif
            @endif
            @if ($member->isSuspended())
                <form method="POST" action="{{ route('members.reactivate', $member) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-primary" type="submit">Reactivate</button>
                </form>
            @else
                <form method="POST" action="{{ route('members.suspend', $member) }}">
                    @csrf
                    @method('PATCH')
                    <x-icon-action icon="ban" label="Suspend member" type="submit" variant="danger-soft" />
                </form>
            @endif
        </div>
    </section>

    <section class="profile-stat-grid" aria-label="Member status summary">
        <article class="profile-stat-card">
            <span>Membership</span>
            <strong>{{ $membership?->package?->name ?? 'Not assigned' }}</strong>
        </article>
        <article class="profile-stat-card">
            <span>Expiry Date</span>
            <strong>{{ $membership?->end_date?->format('d M Y') ?? '-' }}</strong>
        </article>
        <article class="profile-stat-card">
            <span>RFID Card</span>
            <strong>{{ $rfidNumber ?? 'Not assigned' }}</strong>
        </article>
        <article class="profile-stat-card">
            <span>Access Sync</span>
            <strong>{{ $accessSyncLabel }}</strong>
        </article>
    </section>

    <section class="member-profile-grid">
        <div class="profile-column">
            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>Contact</h2>
                </div>
                <div class="profile-info-table">
                    <div><span>Phone</span><strong>{{ $member->phone ?: '-' }}</strong></div>
                    <div><span>Email</span><strong>{{ $member->email ?? '-' }}</strong></div>
                    <div><span>Emergency Name</span><strong>{{ $member->emergency_contact_name ?? '-' }}</strong></div>
                    <div><span>Relationship</span><strong>{{ $member->emergency_contact_relationship ?? '-' }}</strong></div>
                    <div><span>Emergency Phone</span><strong>{{ $member->emergency_contact_phone ?? '-' }}</strong></div>
                </div>
            </article>

            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>Referral</h2>
                </div>
                <div class="profile-info-table">
                    <div><span>Referred By</span><strong>{{ $member->referrer?->full_name ?? '-' }}</strong></div>
                    <div><span>Member No.</span><strong>{{ $member->referrer?->member_no ?? '-' }}</strong></div>
                    <div><span>Phone</span><strong>{{ $member->referrer?->phone ?? '-' }}</strong></div>
                </div>
            </article>
        </div>

        <div class="profile-column profile-column-main">
            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>Personal Information</h2>
                </div>
                <div class="profile-info-table profile-info-table-wide">
                    <div><span>IC / Passport</span><strong>{{ $member->ic_passport_no ?? '-' }}</strong></div>
                    <div><span>Gender</span><strong>{{ $member->gender ? str($member->gender)->headline() : '-' }}</strong></div>
                    <div><span>Date of Birth</span><strong>{{ $member->date_of_birth?->format('d M Y') ?? '-' }}</strong></div>
                    <div><span>Joined</span><strong>{{ $member->created_at?->format('d M Y') }}</strong></div>
                    <div class="profile-info-full"><span>Address</span><strong>{{ $member->address ?? '-' }}</strong></div>
                </div>
            </article>

            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>Membership &amp; Access</h2>
                </div>
                <div class="profile-info-table profile-info-table-wide">
                    <div><span>Package</span><strong>{{ $membership?->package?->name ?? 'Not assigned' }}</strong></div>
                    <div><span>Status</span><strong>{{ $membership ? str($membership->status)->headline() : 'Not assigned' }}</strong></div>
                    <div><span>Start Date</span><strong>{{ $membership?->start_date?->format('d M Y') ?? '-' }}</strong></div>
                    <div><span>Expiry Date</span><strong>{{ $membership?->end_date?->format('d M Y') ?? '-' }}</strong></div>
                    <div><span>RFID Card</span><strong>{{ $rfidNumber ?? 'Not assigned' }}</strong></div>
                    <div><span>Access Sync</span><strong>{{ $accessSyncLabel }}</strong></div>
                    <div><span>Last Sync Update</span><strong>{{ $latestSync?->updated_at?->format('d M Y, h:i A') ?? '-' }}</strong></div>
                </div>
                @if (auth()->user()?->hasPermission('memberships.manage') && $membership && $membership->status === 'active')
                    <form class="profile-card-action" method="POST" action="{{ route('member-memberships.suspend', [$member, $membership]) }}">
                        @csrf
                        @method('PATCH')
                        <x-icon-action icon="ban" label="Suspend membership" type="submit" variant="danger-soft" />
                    </form>
                @endif
            </article>

            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>RFID Card Control</h2>
                </div>
                <div class="profile-info-table profile-info-table-wide">
                    <div><span>Active Card</span><strong>{{ $activeRfidCard?->card_number ?? 'Not assigned' }}</strong></div>
                    <div><span>Card Status</span><strong>{{ $activeRfidCard ? str($activeRfidCard->status)->headline() : 'Not assigned' }}</strong></div>
                    <div><span>Assigned Date</span><strong>{{ $activeRfidCard?->assigned_at?->format('d M Y, h:i A') ?? '-' }}</strong></div>
                    <div><span>Assigned By</span><strong>{{ $activeRfidCard?->assignedBy?->name ?? '-' }}</strong></div>
                    <div class="profile-info-full"><span>Card Remarks</span><strong>{{ $activeRfidCard?->remarks ?: '-' }}</strong></div>
                </div>

                @if ($canManageAccess)
                    <div class="profile-card-action rfid-action-row">
                        @if ($activeRfidCard)
                            <x-icon-action icon="edit" label="Update RFID card" href="{{ route('rfid-cards.replace', $activeRfidCard) }}" />
                            <form method="POST" action="{{ route('rfid-cards.deactivate', $activeRfidCard) }}" onsubmit="return confirm('Deactivate this RFID card?');">
                                @csrf
                                @method('PATCH')
                                <x-icon-action icon="ban" label="Deactivate RFID card" type="submit" variant="danger-soft" />
                            </form>
                            <form method="POST" action="{{ route('rfid-cards.block', $activeRfidCard) }}" onsubmit="return confirm('Block this RFID card?');">
                                @csrf
                                @method('PATCH')
                                <x-icon-action icon="trash" label="Block RFID card" type="submit" variant="danger-soft" />
                            </form>
                        @else
                            <a class="btn btn-primary" href="{{ route('members.rfid-cards.create', $member) }}">Assign RFID Card</a>
                        @endif
                        <x-icon-action icon="eye" label="View RFID card history" href="{{ route('members.rfid-cards.history', $member) }}" />
                    </div>
                @endif
            </article>

            <article class="profile-card">
                <div class="profile-card-header">
                    <h2>Remarks</h2>
                </div>
                <p class="profile-note">{{ $member->remarks ?: 'No internal remarks recorded.' }}</p>
            </article>
        </div>
    </section>
@endsection
