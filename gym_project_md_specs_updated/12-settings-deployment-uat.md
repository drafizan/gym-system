# Module 12: Settings, Deployment & UAT

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Prepare the system for local deployment, configuration, testing, and handover.

## System Settings

Settings should include:

- Gym name
- Gym address
- Contact number
- Receipt footer
- Expiring soon threshold
- Backup schedule
- Door access controller IP
- Door access controller credentials
- Default payment methods
- Default membership packages

## Deployment Environment

Recommended:

- Windows 11 Pro mini PC or local server
- PostgreSQL
- Laravel application
- Browser access through local network

Example local URL:

```text
http://192.168.1.10
```

## Folder Structure

Suggested:

```text
C:\GymSystem\
├── app\
├── backups\
├── member-photos\
└── logs\
```

## UAT Checklist

### Authentication
- Login works
- Roles work
- Unauthorized access blocked

### Member Registration
- Member can be created
- Photo can be captured
- Member can be searched

### Membership
- Package can be created
- Membership can be assigned
- Membership can be renewed
- Expired member is detected

### RFID
- Card can be assigned
- Duplicate card is blocked
- Card can be deactivated

### Door Access
- Active card syncs to controller
- Expired card is disabled
- Manual sync works

### Product
- Product can be added
- Product price can be updated
- Stock is tracked

### POS
- Product sale works
- Membership sale works
- Receipt is generated
- Sales history is correct

### Reports
- Daily sales report matches POS transactions
- Export works

### Backup
- Manual backup works
- Backup log is created

### Audit
- User actions are logged

## Handover Items

- Admin login details
- Deployment notes
- Backup instructions
- Basic user manual
- UAT sign-off form

## Acceptance Criteria

- All UAT checklist items passed.
- Client signs UAT acceptance.
- System is deployed to one branch.
- Staff training completed.
