# Gorilla Mutantz Gym MACS Backup Instructions

## Backup Schedule

Automatic backup runs at 10:00 PM when the Laravel scheduler is active.

The frequency is controlled from:

```text
Settings > Backup & Restore Configuration
```

Available schedules:

- Daily
- Weekly

Door access expired-membership sync runs separately at 9:00 PM.

## What A Backup Includes

Each backup contains:

- PostgreSQL database dump
- member photo files
- required configuration snapshot
- backup manifest

Backup filename format:

```text
backup_YYYYMMDD_HHMM.zip
```

## Backup Location

The backup save location is configurable from Settings.

Recommended examples:

```text
macOS: /Users/shared/GorillaMutantzBackups
Windows: C:\GorillaMutantz\Backups
Linux: /var/backups/gmutantz
```

The backup folder must not be inside the public web folder.

## Manual Backup

Path:

```text
Backup & Restore > Backup Now
```

Steps:

1. Login as Administrator.
2. Open `Backup & Restore`.
3. Click `Backup Now`.
4. Confirm the new backup shows `Completed`.
5. Download the backup file and store it in the approved external location if required.

Command-line backup:

```bash
php artisan backup:run
```

## Restore

Restore is restricted to administrators.

Steps:

1. Open `Backup & Restore`.
2. Choose a completed backup.
3. Click restore.
4. Type `RESTORE`.
5. Enter the administrator current password.
6. Confirm the restore.
7. Login again and verify members, sales, photos, and settings.

Important:

- Restore replaces the current database and member photos.
- Restore must not be tested during live front-desk operation.
- Always create a fresh backup before restoring an older backup.

## PostgreSQL Tool Paths

The backup tool requires `pg_dump`.

The restore tool requires `psql`.

Set these under:

```text
Settings > Backup & Restore Configuration
```

Operating system presets:

- macOS / Postgres.app
- Windows / PostgreSQL Installer
- Linux
- Custom path

## Daily Checks

The administrator should confirm:

- Latest scheduled backup exists.
- Latest backup status is `Completed`.
- Backup file size is not zero.
- Backup folder still has enough disk space.
- Failed backup entries are reviewed in Audit Trail.

## Recovery Test

Before final handover, perform one controlled restore test using a non-live backup window.

Record:

- backup filename
- restore date/time
- person performing restore
- result
- any issue found

