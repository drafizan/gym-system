# Gorilla Mutantz Gym System Admin Manual

## 1. Purpose

This manual is for the system administrator who installs, configures, secures, updates, and monitors the Gorilla Mutantz Gym access system.

The current system includes:

- Login and logout
- Role-based access
- User management
- Member registration
- Membership management
- Backup
- Password change
- Audit trail
- Daily sales report dashboard layout
- Protected deployment update button
- Theme preference saving

RFID cards, POS, and broader settings screens are planned modules and will be documented as they are completed.

## 2. Access URL

Local Herd URL:

```text
https://gmutantz.test
```

Default administrator login:

```text
Username: admin
Password: Abcd@1234
```

Change this password before real operation.

## 3. Administrator Responsibilities

The administrator is responsible for:

- Keeping administrator credentials secure
- Creating and deactivating user accounts
- Assigning the correct user role
- Reviewing audit logs
- Running system updates only when approved
- Maintaining deployment secrets
- Preparing production environment values
- Ensuring staff use the correct role for their work

## 4. Roles And Permissions

### Administrator

Full system access:

- Dashboard
- Reports
- Members
- Memberships
- Access Control
- Sales
- Products
- Users & Roles
- Backup
- Audit Trail
- Settings

### Manager

Operational access:

- Dashboard
- Reports
- Members
- Memberships
- Access Control
- Sales
- Products
- Audit Trail

No access to:

- Users & Roles
- Backup
- Settings

### Cashier

Front-desk access:

- Dashboard
- Members
- Sales
- Reports

No access to:

- Membership administration
- Access Control
- Product management
- Users & Roles
- Backup
- Audit Trail
- Settings

## 5. Login And Logout

1. Open `http://gmutantz.test`.
2. Enter username and password.
3. Click `Sign In`.
4. To logout, click the logout icon in the top-right header.

Security behavior:

- Failed login attempts are rate-limited.
- Inactive users cannot login.
- Login and logout actions are recorded in Audit Trail.
- Failed and blocked login attempts are recorded in Audit Trail without storing password values.
- Sessions expire after 30 minutes of inactivity and when the browser is closed.
- The last login time is stored for each user.

Login page UAT status:

- Live network health checks are configured for the actual 1st Floor Door (`192.168.100.11`) and 2nd Floor Door (`192.168.100.12`).
- The `Last sync` metric reads the latest controller `last_sync_at` value recorded by access sync.
- The `Pending syncs` metric counts pending records from the live RFID/member access sync queue.
- The forgot-password flow uses the support modal only. Self-service password reset is intentionally disabled for the local offline environment.
- System-side login UAT is documented in `docs/login-authentication-uat.md`.
- Final client sign-off must still be witnessed on the actual front-desk machine during Deployment & UAT.

Dahua door access configuration:

1. Open `Settings`.
2. Go to `Dahua Door Access Configuration`.
3. Update the IP address for `1st Floor Door` or `2nd Floor Door`.
4. Click `Save Dahua Configuration`.

The login and sidebar status capsules show whether the Dahua device is ready to receive data from the system, for example `1st Floor Door Online` or `1st Floor Door Offline`. The IP address is kept in Settings for administration.

Default assumed values:

- `1st Floor Door`: `192.168.100.11`
- `2nd Floor Door`: `192.168.100.12`

Dahua SDK bridge heartbeat:

- The local SDK bridge is checked through `http://127.0.0.1:8787/health`.
- The Laravel scheduler runs `php artisan access:bridge-heartbeat` every minute.
- If the bridge is down, the heartbeat starts `scripts/dahua_sdk_bridge.py` and writes logs to `storage/logs/dahua-bridge.log`.
- Dahua sync, door command, and access history requests also run the heartbeat automatically before contacting the local bridge.
- A local lock file at `storage/app/dahua-bridge.lock` prevents overlapping requests from restarting the bridge at the same time.
- The bridge binds to `127.0.0.1` by default, so it is only reachable from the local machine.
- The heartbeat only starts the known local bridge script. If a stale PID file exists, it only terminates the process when the command line matches `dahua_sdk_bridge.py`.
- After machine reboot, the Laravel scheduler must be running before the heartbeat can restart the bridge.

Manual bridge checks:

```bash
php artisan access:bridge-heartbeat --status
php artisan access:bridge-heartbeat
```

Authorized card-list verification:

```bash
php artisan access:verify-card-list "1st Floor Door"
php artisan access:verify-card-list "2nd Floor Door"
```

This command reads the Dahua controller's local card list and compares it against MACS active authorized RFID cards. A card is expected on the controller only when the RFID card is active, the member is active, and the member has an active non-expired membership package that allows door access.

If the controller contains extra unauthorized cards, remove them through the audited sync path:

```bash
php artisan access:verify-card-list "1st Floor Door" --remove-extra
```

Local scheduler setup examples:

```bash
# macOS / Linux cron
* * * * * cd /path/to/gmutantz && php artisan schedule:run >> /dev/null 2>&1
```

For Windows, create a Task Scheduler task that runs every minute:

```text
Program: php
Arguments: artisan schedule:run
Start in: C:\path\to\gmutantz
```

Environment values can be changed when required:

```text
GYM_DAHUA_BRIDGE_HEALTH_URL=http://127.0.0.1:8787/health
GYM_DAHUA_BRIDGE_HOST=127.0.0.1
GYM_DAHUA_BRIDGE_PORT=8787
GYM_DAHUA_BRIDGE_PYTHON=python3
GYM_DAHUA_BRIDGE_SCRIPT=/path/to/gmutantz/scripts/dahua_sdk_bridge.py
GYM_DAHUA_BRIDGE_LOG_FILE=/path/to/gmutantz/storage/logs/dahua-bridge.log
GYM_DAHUA_BRIDGE_PID_FILE=/path/to/gmutantz/storage/app/dahua-bridge.pid
```

## 6. Password Management

Users can change their own password from the lock icon in the top header.

Password change requires:

- Current password
- New password
- New password confirmation

Password changes are recorded in Audit Trail. Password values are never stored in audit payloads.

Forgotten password process:

- The login page shows a `Forgot password?` support modal.
- The user should contact support through WhatsApp.
- Password resets are handled by the administrator.

Support WhatsApp:

```text
https://wa.me/601128520309
```

## 7. User Management

Path:

```text
Users & Roles > Manage Users
```

Administrator can:

- View users
- Create users
- Edit user details
- Assign role
- Change a user's password
- Deactivate users
- Reactivate users

Recommended rules:

- Do not share one account between staff.
- Give each staff member their own username.
- Use Cashier role for front-desk users.
- Use Manager role for trusted operational supervisors.
- Keep Administrator role limited.
- Deactivate old staff accounts immediately.

## 8. Audit Trail

Path:

```text
Audit Trail
```

Audit Trail records:

- User
- Module
- Action
- Record type
- Record ID
- Old values
- New values
- IP address
- User agent
- Date and time

Member create, update, suspend, and reactivate actions are included in the audit trail. Membership package, assignment, renewal, suspension, and expiry actions are also included.

Available filters:

- Module
- Action
- User
- Date from
- Date to

Audit details show:

- Event metadata
- Change summary table for fields that changed
- Request user agent
- Old values
- New values

Audit logs are read-only. There are no edit or delete actions for audit records.

Sensitive fields are excluded from audit payloads, including:

- Passwords
- Password confirmations
- Current password
- Remember tokens
- Tokens
- Secrets
- Deployment keys
- Encrypted credentials

## 9. Member Registration Administration

Path:

```text
Members > Manage Members
```

Administrator, Manager, and Cashier roles can access member registration when assigned the `members.manage` permission.

Member records include:

- Automatic member number
- Full name
- IC/passport number
- Date of birth
- Gender
- Phone
- Email
- Address
- Emergency contact
- Photo path
- Status
- Remarks
- Created by and updated by user references

Security and audit behavior:

- Member routes require authentication.
- Member routes require member management permission.
- Create and update forms validate all input before saving.
- Uploaded photos are limited to image files up to 2 MB.
- Captured camera photos are stored only after valid image data is received.
- Create, update, suspend, and reactivate actions are logged in Audit Trail.
- Member records are suspended or reactivated instead of being deleted.

Current profile membership behavior:

- Latest membership shows the active package when assigned.
- Expiry date and status are visible on member profile.
- RFID card shows `Not assigned yet`.

RFID access sync will connect to 2 physical floor doors after the Door Access Integration module is completed.

## 10. Membership Administration

Paths:

```text
Memberships > Packages
Memberships > Expiring Soon
Memberships > Expired Members
Members > Member Profile > Assign/Renew Membership
```

Default packages are seeded:

- Walk-in
- Monthly
- Quarterly
- Yearly

Administrators and managers with `memberships.manage` can:

- Create membership packages
- Edit package duration, price, walk-in flag, access flag, and status
- Assign a package to a member
- Renew a member membership
- Suspend a member membership
- View expiring soon memberships
- View expired memberships

Expiry behavior:

- `memberships:detect-expired` marks overdue active memberships as expired.
- The Laravel scheduler runs `memberships:detect-expired --sync --sync-limit=500` daily at `21:00`.
- Expired and suspended memberships create access-disable sync logs and the daily job immediately pushes those changes to the Dahua device queue.
- If the member has another active, valid membership with door access, the expired old membership is still marked expired but no disable sync is queued.
- Active memberships with access allowed create pending access-enable sync logs.

POS membership sales will reuse this membership logic when the POS module is built.

## 11. Deployment Update Button

The dashboard includes an admin-only `Update` button.

It is disabled by default and requires production configuration:

```env
DEPLOYMENT_UPDATES_ENABLED=true
DEPLOYMENT_SECRET=your-strong-secret
```

When triggered, the fixed deployment script runs:

```text
scripts/deploy.sh
```

The script performs:

- Git fetch and pull
- Composer install
- Database migrations
- NPM install
- Frontend build
- Laravel cache refresh

Security notes:

- Only username `admin` can see and run the update button.
- The action requires the deployment key.
- Attempts are rate-limited.
- The web page can only run the fixed script, not arbitrary commands.
- Use this only on the approved deployment machine.

## 12. Multibranch Behavior

Current mode is single-branch.

Branch and outlet labels are hidden while:

```env
GYM_MULTIBRANCH_ENABLED=false
```

When multibranch is ready, enable:

```env
GYM_MULTIBRANCH_ENABLED=true
GYM_BRANCH_NAME="Branch Name"
```

Branch name and outlet selector will appear again.

## 13. Interface Style

The system uses the standard light Metronic-style administration interface.

After logout and next login, the system restores the last theme.

## 14. Backup

Path:

```text
Backup
```

Administrator can:

- Run `Backup Now`.
- View backup history.
- Download completed backup files.
- Restore a completed backup after typing `RESTORE`.
- See failed backup errors.
- Keep the latest 30 completed backups by default.
- Change backup save location under `Settings > Backup & Restore Configuration`.

Backup files are created as:

```text
backup_YYYYMMDD_HHMM.zip
```

Each backup includes:

- PostgreSQL database dump.
- Member photos from local storage.
- Required system configuration snapshots.
- Backup manifest file.

Local development backup folder:

```text
/Users/drafizandrahman/Herd/gmutantz/storage/app/backups
```

Suggested Windows deployment backup folder:

```text
C:\GymSystem\Backups
```

Backup settings path:

```text
Settings > Backup & Restore Configuration
```

Available backup settings:

- Backup save location, selected with the in-app folder picker
- Auto backup schedule
- Number of latest backups to keep
- Operating system preset
- PostgreSQL backup tool path
- PostgreSQL restore tool path

Operating system presets:

- `macOS / Postgres.app` fills `/Applications/Postgres.app/Contents/Versions/latest/bin/pg_dump` and `/Applications/Postgres.app/Contents/Versions/latest/bin/psql`.
- `Windows / PostgreSQL Installer` fills `C:\Program Files\PostgreSQL\16\bin\pg_dump.exe` and `C:\Program Files\PostgreSQL\16\bin\psql.exe`.
- `Linux` fills `pg_dump` and `psql`.
- `Custom path` keeps the tool fields editable for non-standard installations.

Important notes:

- The server must be able to run `pg_dump`.
- The server must be able to run `psql` for restore.
- If `pg_dump` or `psql` is not in the system path, set `GYM_PG_DUMP_BINARY` and `GYM_PSQL_BINARY` to the full executable paths.
- Changing Backup & Restore settings requires the administrator's current password.
- Restore requires both the `RESTORE` confirmation text and the administrator's current password.
- Backup files cannot be configured to save inside the public web folder.
- Failed backups are recorded in Backup History and Audit Trail.
- Restore attempts are recorded in Audit Trail.
- Restore replaces the current database and member photos with the selected backup data.
- Daily automatic backup is scheduled for 10:00 PM when the Laravel scheduler is running.
- The Dahua SDK bridge heartbeat also requires the Laravel scheduler to be running.
- Google Drive or cloud backup is not enabled yet and should only be added after approval.

## 15. Production Checklist

Before production use:

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Set a strong `APP_KEY`.
- Use `.env.production.example` as the deployment template.
- Confirm PostgreSQL credentials.
- Change default admin password.
- Set correct `APP_URL`.
- Configure deployment secret only on the deployment machine.
- Confirm the deployment script runs under the correct server user.
- Confirm XLSX export works on the deployment machine without OpenOffice/LibreOffice. LibreOffice should only be installed if future PDF/document conversion is approved.
- Confirm backups before enabling real operations.
- Confirm only trusted users have Administrator access.
- Follow `docs/deployment-notes.md`, `docs/backup-instructions.md`, and `docs/uat-sign-off-form.md` during handover.

## 16. Maintenance Notes

Run these checks before handover or after major changes:

```bash
php artisan test
npm run build
```

Current completed modules:

- Project foundation
- UI architecture
- Authentication, roles, and permissions
- Audit trail foundation
- Member registration
- Membership management
- Backup

Planned modules will be added to this manual as they are completed.
