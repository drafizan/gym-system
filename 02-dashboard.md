# Module 02: Dashboard

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Provide a quick overview of gym business performance and system status.

## Dashboard KPI Cards

- Total Members
- Active Members
- Expired Members
- Memberships Expiring Soon
- Today's Sales
- Membership Sales
- Product Sales
- Monthly Revenue

## Dashboard Sections

### Recent Activities
Show latest activities such as:

- New member registration
- Membership renewal
- Product sale
- Card assignment
- Access sync activity

### Membership Status Overview
Display:

- Active
- Expiring soon
- Expired
- Suspended

### Sales Overview
Display weekly or daily sales chart:

- Membership sales
- Product sales

### System Status
Display:

- Database status
- Backup status
- Door access controller status
- Last access sync time

## Data Sources

- members
- member_memberships
- sales
- sale_items
- rfid_cards
- backup_logs
- audit_logs

## Acceptance Criteria

- Dashboard loads in under 3 seconds on local network.
- KPI cards show correct totals.
- Recent activities are shown in descending order.
- Expiring soon uses configurable threshold, default 7 days.
- System status shows latest sync and backup information.
