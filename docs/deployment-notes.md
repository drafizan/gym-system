# Gorilla Mutantz Gym MACS Deployment Notes

## Purpose

This document is the deployment handover checklist for the local Gorilla Mutantz Gym Membership and Access Control System (MACS).

The system is intended to run on a trusted local machine inside the gym network and continue operating without internet access after installation.

## Target Setup

- Application: Laravel / Blade / Vite
- Database: PostgreSQL
- Access device integration: Dahua ASI1201E-D through local SDK bridge
- Door units:
  - `1st Floor Door`: `192.168.100.11`
  - `2nd Floor Door`: `192.168.100.12`
- Dahua SDK bridge: `http://127.0.0.1:8787`
- Door access autosync: every 30 minutes
- Expired membership access sync: daily at 9:00 PM
- Automatic backup: daily or weekly at 10:00 PM, based on Settings

For Windows/Linux runtime paths and SDK installation, see
[the shared platform guide](platform-unification.md).

## Required Software

- PHP 8.4 or compatible Laravel-supported PHP version
- Composer
- Node.js and NPM
- PostgreSQL server
- PostgreSQL client tools: `pg_dump` and `psql`
- Git, if using the dashboard update button
- Python 3, for the Dahua SDK bridge
- Dahua SDK bridge dependencies and native SDK files, installed locally

OpenOffice or LibreOffice is not required for the current XLSX report export. Install LibreOffice only if future PDF or office-document conversion is approved.

## Production Environment

Use `.env.production.example` as the deployment template.

Important production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` generated on the deployment machine
- `APP_URL` set to the actual local HTTPS URL
- `DB_*` values set to the local PostgreSQL database
- `SESSION_SECURE_COOKIE=true` when using HTTPS
- `GYM_BACKUP_PATH` set outside the public web directory
- `GYM_DAHUA_BRIDGE_URL=http://127.0.0.1:8787/dahua`
- `GYM_ACCESS_CARD_LIST_TIMEOUT=30`
- `DEPLOYMENT_UPDATES_ENABLED=false` until the update workflow is approved
- `DEPLOYMENT_SECRET` set only on the deployment machine

Do not store real production passwords in project documentation.

## Installation Steps

1. Copy the project to the deployment machine.
2. Create the PostgreSQL database and application database user.
3. Copy `.env.production.example` to `.env`.
4. Fill in machine-specific paths, credentials, and URL values.
5. Generate an application key:

```bash
php artisan key:generate
```

6. Install PHP dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

7. Install and build frontend assets:

```bash
npm ci
npm run build
```

8. Run database migrations and seed baseline records:

```bash
php artisan migrate --force
php artisan db:seed --force
```

9. Link public storage:

```bash
php artisan storage:link
```

10. Optimize Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Permissions

The web server user must be able to read and write:

- `storage/`
- `bootstrap/cache/`
- configured backup folder
- member photo storage folder

The backup folder must not be inside `public/`.

## Scheduler

The Laravel scheduler must run every minute.

macOS / Linux cron:

```bash
* * * * * cd /path/to/gmutantz && php artisan schedule:run >> /dev/null 2>&1
```

Windows Task Scheduler:

```text
Program: php
Arguments: artisan schedule:run
Start in: C:\path\to\gmutantz
Run: every 1 minute
```

Confirm with:

```bash
php artisan schedule:list
```

Expected schedule:

- `access:bridge-heartbeat`: every minute
- `access:sync-pending`: every 30 minutes
- `memberships:detect-expired --sync --sync-limit=500`: daily at 9:00 PM
- `backup:run`: daily or weekly at 10:00 PM

## Queue Worker

The application currently uses the database queue connection.

Run a queue worker when background jobs are introduced or enabled:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

For Windows, configure this as a supervised task. For Linux, use Supervisor or systemd.

## Dahua SDK Bridge

The bridge should be bound only to `127.0.0.1`.

Health check:

```bash
php artisan access:bridge-heartbeat --status
```

Manual start/check:

```bash
php artisan access:bridge-heartbeat
```

Door command tests:

```bash
php artisan access:door-command status "1st Floor Door"
php artisan access:door-command sync-time "1st Floor Door"
```

Authorized card-list verification:

```bash
php artisan access:verify-card-list "1st Floor Door"
php artisan access:verify-card-list "2nd Floor Door"
```

The verification must report that the controller local card list matches MACS authorized cards before door access UAT is signed off.
If a controller contains old cards that MACS does not authorize, run `php artisan access:verify-card-list "1st Floor Door" --remove-extra` to remove extras through an audited `DELETE_CARD` sync log.

The second floor device must be physically online and reachable before UAT can pass for both doors.

## Deployment Update Button

The dashboard update button is disabled by default.

Enable only on the approved deployment machine:

```env
DEPLOYMENT_UPDATES_ENABLED=true
DEPLOYMENT_SECRET=CHANGE_THIS_LONG_RANDOM_SECRET
DEPLOYMENT_SCRIPT_PATH=/path/to/gmutantz/scripts/deploy.sh
```

Security rules:

- Only the `admin` username can see the update button.
- The deployment secret must not be shared through chat or committed to source control.
- The button runs only the fixed deployment script.
- Test manually before client handover.

## Handover Verification

Run before UAT:

```bash
php artisan test
npm run build
php artisan schedule:list
php artisan access:bridge-heartbeat --status
```

Then verify manually:

- Login and logout
- Member registration and edit
- Membership assignment and renewal
- RFID card assignment and sync
- Door event history
- POS sale and receipt
- Daily sales report XLSX export
- Backup now and backup download
- Audit log visibility
- Settings save flow
