# Module 04: Membership Management

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Manage gym membership packages, renewals, expiry tracking, and membership status.

## Membership Package Types

Default packages:

- Walk-in
- Monthly
- Quarterly
- Yearly

Admin can add or edit packages.

## Package Fields

- Package name
- Duration in days
- Price
- Access allowed
- Status

## Membership Status

- Active
- Expiring Soon
- Expired
- Suspended
- Cancelled

## Features

- Create membership package
- Assign package to member
- Renew membership
- Suspend membership
- Track expiry date
- Show expiring soon list
- Show expired member list

## Renewal Rule

If a member renews before expiry:

```text
New expiry date = current expiry date + package duration
```

If a member renews after expiry:

```text
New expiry date = renewal date + package duration
```

## Suggested Database Tables

### membership_packages
Fields:

- id
- name
- duration_days
- price
- is_walk_in
- access_allowed
- status
- created_at
- updated_at

### member_memberships
Fields:

- id
- member_id
- package_id
- start_date
- end_date
- status
- payment_status
- amount
- created_by
- created_at
- updated_at

## Access Control Rule

When membership status changes:

- Active = card should be active
- Expired = card should be disabled
- Suspended = card should be disabled

This should create an access sync job.

## Acceptance Criteria

- Admin can manage packages.
- Staff can assign membership to member.
- Renewal calculates expiry correctly.
- Expired members are detected automatically.
- Membership status affects RFID access status.
