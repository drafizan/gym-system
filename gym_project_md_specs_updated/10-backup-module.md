# Module 10: Backup Module

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Protect local gym data through manual and scheduled backups.

## Backup Types

- Manual backup
- Daily automatic backup

## Backup Contents

- PostgreSQL database dump
- Uploaded member photos
- System configuration file, if required

## Backup Storage

Default:

```text
C:\GymSystem\Backups\
```

or Linux equivalent:

```text
/opt/gymsystem/backups/
```

Optional:

- Google Drive upload

## Features

- Backup Now button
- Backup history
- Backup success/failure status
- Download backup file
- Auto-delete old backups after configured retention

## Suggested Database Tables

### backup_logs
Fields:

- id
- backup_type
- file_path
- file_size
- status
- error_message
- started_at
- completed_at
- created_by
- created_at
- updated_at

## Backup Command Example

PostgreSQL:

```bash
pg_dump -U postgres gym_db > backup.sql
```

Then compress:

```bash
backup_YYYYMMDD_HHMM.zip
```

## Retention

Default retention:

```text
Keep latest 30 backups
```

## Acceptance Criteria

- Admin can trigger manual backup.
- System creates valid backup file.
- Backup is logged.
- Failed backup shows error message.
- Daily scheduled backup can run.
