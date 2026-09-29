# Gorilla Mutantz Gym MACS - Project Function Documentation

Document date: 25 August 2026  
System name: Membership and Access Control System (MACS)  
Company: Gorilla Mutantz Gym Sdn Bhd  
Application type: Local Laravel web application with PostgreSQL database and Dahua door access integration

## 1. System Overview

MACS is built to manage gym membership operations, front-desk sales, RFID door access, personal training activity, backup and restore, audit trail, and daily reporting from one local system.

The system is designed for local network operation. The Laravel application, PostgreSQL database, Dahua SDK bridge, and Dahua ASI1201E-D access controllers can operate without internet access when installed on the gym local network.

Main system areas:

- Login and authentication
- Dashboard
- Member management
- Membership management
- Product and pricing management
- POS sales
- Daily sales report
- Personal training
- RFID card management
- Dahua door access integration
- User and role management
- Audit trail
- Backup and restore
- System settings
- Deployment update function

## 2. Login And Authentication

Available functions:

- Username and password login.
- Logout.
- Inactive-user login blocking.
- Login rate limiting.
- Login failure audit logging.
- Last login date/time update.
- Password change for authenticated users.
- Forgot-password support modal that directs staff to contact support.
- Security headers on login and authenticated pages.

Current seeded administrator account:

- Username: `admin`
- Password: configured in database seeder
- Role: Administrator

Security behavior:

- Passwords are hashed using Laravel hashing.
- Browser sessions are encrypted.
- Sessions expire on close.
- Session lifetime is configured for short local-machine usage.
- Failed login attempts are recorded without storing attempted passwords.

Important routes:

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /password`
- `PUT /password`

## 3. Roles And Permissions

The system uses role-based permissions.

Available permissions:

- `dashboard.view`
- `reports.view`
- `members.manage`
- `memberships.manage`
- `access.manage`
- `sales.manage`
- `products.manage`
- `pt.manage`
- `users.manage`
- `backup.manage`
- `audit.view`
- `settings.manage`

Default roles:

| Role | Access Summary |
| --- | --- |
| Administrator | Full access to all modules, including users, settings, backup, restore, audit, sales, access control, and reports. |
| Manager | Operational access to most modules, excluding user management, backup management, and system settings by default. |
| Cashier | Front-desk access for dashboard, members, POS sales, and reports. |

## 4. Dashboard

Available functions:

- View daily sales overview.
- View sales cards for membership sales, product sales, PT sales, and total daily sales.
- View weekly sales trend.
- View sales breakdown chart.
- View payment method breakdown.
- View sales details table.
- Search dashboard sales details.
- Access shortcut button to POS.
- Access shortcut button to sync door access.
- View Dahua door access status below sales details.
- View controller online/offline status.
- View latest sync time and pending sync count.

Dashboard data sources:

- Sales records.
- Sale items.
- Sale payments.
- Member records.
- Membership records.
- Product records.
- PT session/package records.
- Access controller settings.
- Access sync logs.

Dashboard sales categories:

- Membership sales: membership sale, membership renewal, and walk-in membership access.
- Product sales: normal product item sales.
- PT sales: personal training package/session sales.
- Other sales: hidden unless a sale exists outside membership, product, and PT categories.

Performance requirement:

- Dashboard load should stay under 3 seconds on the local network.

Important route:

- `GET /`

## 5. Member Management

Available functions:

- View all members.
- Search member by member number, name, phone, or IC/passport.
- Filter member list by active, expiring, expired, suspended, missing photo, has RFID, or no RFID.
- Register new member.
- Edit existing member.
- View member profile.
- Suspend member.
- Reactivate suspended member.
- View expired members.
- View expiring-soon members.
- View members without photos.
- Export member list to CSV.
- Import members from CSV through an import popup.
- Capture or upload member photo.
- Store emergency contact details.
- Store referral details using active-member lookup.
- Store remarks/internal notes where applicable.

Member registration fields:

- Full name
- IC/passport number
- Date of birth
- Gender
- Phone number
- Email
- Address
- Emergency contact name
- Emergency contact relationship
- Emergency contact phone
- Join date
- Referred by
- Member photo
- RFID card number

Validation and rules:

- Phone number validation supports Malaysian phone number formats.
- Gender accepts male or female.
- RFID card number is manually entered until automatic card management is enabled.
- Existing member data is shown during edit.
- Member ID format uses `GMG` prefix.
- IC/passport is hidden from the main member list for privacy.

Important routes:

- `GET /members`
- `GET /members/create`
- `POST /members`
- `GET /members/{member}`
- `GET /members/{member}/edit`
- `PUT /members/{member}`
- `PATCH /members/{member}/suspend`
- `PATCH /members/{member}/reactivate`
- `GET /members/expired`
- `GET /members/expiring-soon`
- `GET /members/photo-capture`
- `GET /members/export`
- `POST /members/import`

## 6. Membership Management

Available functions:

- View membership packages.
- Create membership package.
- Edit membership package.
- Delete membership package when not blocked by related records.
- Assign membership to member.
- Renew existing membership.
- Suspend membership.
- View expired memberships.
- View expiring-soon memberships.
- Automatically calculate membership end date.
- Automatically fill membership amount from package price.
- Queue access sync when membership access status changes.

Membership package fields:

- Package name
- Duration days
- Price
- Status
- Walk-in flag
- Door access allowed flag

Membership rules:

- Membership expiry date is calculated as one day before the equivalent duration endpoint.
- Example: 30-day membership starting 19 June 2026 expires on 18 July 2026.
- Expiring soon is calculated based on the configured `expiring_soon_days` setting.
- Walk-in sales are treated under membership sales.
- Door access is enabled only when the package allows access.

Current pricing seed includes:

- Walk-in Citizen
- Walk-in Student
- Walk-in Senior Citizen
- Walk-in OKU
- Monthly Citizen
- Monthly Student
- Monthly Senior Citizen
- Monthly Special
- First-Time Registration Citizen
- First-Time Registration Student
- First-Time Registration Senior Citizen
- First-Time Registration Special
- Registration Fee

Important routes:

- `GET /membership-packages`
- `GET /membership-packages/create`
- `POST /membership-packages`
- `GET /membership-packages/{membershipPackage}/edit`
- `PUT /membership-packages/{membershipPackage}`
- `DELETE /membership-packages/{membershipPackage}`
- `GET /members/{member}/memberships/create`
- `POST /members/{member}/memberships`
- `GET /members/{member}/memberships/{memberMembership}/renew`
- `POST /members/{member}/memberships/{memberMembership}/renew`
- `PATCH /members/{member}/memberships/{memberMembership}/suspend`
- `GET /memberships/expired`
- `GET /memberships/expiring-soon`

## 7. Product And Pricing Management

Available functions:

- View products.
- Search products.
- Filter products by category and status.
- Create product.
- Edit product.
- Manage product categories.
- Create category.
- Edit category.
- Delete category if no products are assigned.
- View low-stock products.
- View product price-change history.
- Track stock quantity.
- Track reorder level.
- Track product selling price.
- Preserve historical sale price at transaction time.

Product fields:

- Product name
- SKU
- Category
- Selling price
- Stock quantity
- Reorder level
- Status
- Description

Product category fields:

- Category name
- Description
- Status

Pricing rule:

- Sales reports use the price captured in the sale item at transaction time.
- If a product price changes today, yesterday's completed sale keeps yesterday's captured unit price.

Current actual products seeded from client pricing:

- Mineral Water 600ml
- Mineral Water 1.5L
- Gorilla Turbo Tin
- Protein Mass 1kg
- Protein Whey 1kg
- Protein Blend 1kg
- BCAA
- Creatine
- Shirt
- Rental Towel

Important routes:

- `GET /products`
- `GET /products/create`
- `POST /products`
- `GET /products/{product}/edit`
- `PUT /products/{product}`
- `GET /products/low-stock`
- `GET /products/price-changes`
- `GET /product-categories`
- `GET /product-categories/create`
- `POST /product-categories`
- `GET /product-categories/{productCategory}/edit`
- `PUT /product-categories/{productCategory}`
- `DELETE /product-categories/{productCategory}`

## 8. POS Sales

Available functions:

- Create front-desk sale.
- Sell membership package.
- Renew membership.
- Sell multiple products in one transaction.
- Sell PT package/session products.
- Select member through searchable dropdown.
- Allow walk-in/no member for normal product sales.
- Prevent walk-in/no member from buying PT packages.
- Apply product-line discount.
- Apply whole-cart discount.
- Prevent duplicate product selection in one sale.
- Prevent product-line discount from exceeding product unit price.
- Validate stock availability.
- Deduct product stock after completed sale.
- Generate receipt.
- View sales history.
- Search sales history.
- End-of-day closing.
- End-of-day closing creates a daily backup.

Sale types:

- Membership Sale
- Membership Renewal
- Product Sale
- PT Session

Payment methods:

- Cash
- QR Pay
- Debit/Credit Card

POS business rules:

- At least one sale item is required.
- Total discount cannot exceed subtotal.
- Payment amount must match sale total.
- Only enabled payment methods can be used.
- PT sale requires an active member with an active membership.
- PT products cannot be sold under normal Product Sale.
- Normal products cannot be sold under PT Session sale type.
- Completed sale item prices are stored in the sale record to protect historical reports from future price changes.

Important routes:

- `GET /sales/pos`
- `POST /sales`
- `POST /sales/end-of-day`
- `GET /sales/history`
- `GET /sales/{sale}/receipt`

## 9. Daily Sales Report

Available functions:

- View daily or date-range sales report.
- Filter by date from and date to.
- Filter by payment method.
- Filter by cashier when user role allows it.
- Filter by sale type.
- Export report as `.xlsx`.
- Generate daily financial summary.
- Generate daily transaction breakdown.
- Restrict cashier report visibility to own transactions unless manager or administrator.

Report summary values:

- Total revenue
- Membership sales
- Product sales
- PT sales
- Walk-in sales
- Other sales
- Discount total
- Transaction count
- Payment method breakdown

Report transaction columns:

- Receipt number
- Time
- Type
- Description
- Payment method
- Amount
- Received by
- Member

Export format:

- XLSX only.
- PDF export support is intentionally removed.
- OpenOffice is not required for XLSX generation because export is generated directly by application code.

Important routes:

- `GET /reports/daily-sales`
- `GET /reports/daily-sales/export`

## 10. Personal Training

Available functions:

- Manage trainers.
- Create trainer.
- Edit trainer.
- Manage PT packages.
- Create PT package.
- Edit PT package.
- Assign PT package to eligible member.
- Sell PT package through POS.
- Track PT member package balances.
- Schedule PT sessions.
- Add eligible members one by one to a schedule.
- Prevent trainer schedule conflict.
- Complete scheduled session.
- Cancel scheduled session.
- Manually record completed/cancelled PT session.
- Calculate trainer commission.
- View trainer commission report.
- Provide dashboard data for PT sales and PT activity.

Trainer fields:

- Name
- Phone
- Email
- Specialization
- Commission per session
- Joined date
- Status
- Notes

PT package fields:

- Package name
- Number of sessions
- Price
- Commission per session
- Validity days
- Status
- Description

PT member package fields:

- Member
- PT package
- Total sessions
- Used sessions
- Remaining sessions
- Purchase date
- Expiry date
- Status
- Notes

Session tracking fields:

- Member PT package
- Trainer
- Session date
- Start time for scheduled sessions
- End time for scheduled sessions
- Duration minutes
- Status
- Commission amount
- Notes
- Recorded by

PT rules:

- Member must have an active membership before buying PT.
- Scheduled session requires at least one eligible member with remaining PT sessions.
- Trainer must be active.
- Trainer cannot have overlapping scheduled/completed sessions.
- Completing a session deducts one session from the member PT balance.
- When remaining sessions reach zero, the member PT package is marked completed.
- Cancelled sessions do not generate commission.
- Completed sessions generate commission based on package commission per session, falling back to trainer commission rate.

Important routes:

- `GET /pt/trainers`
- `GET /pt/trainers/create`
- `POST /pt/trainers`
- `GET /pt/trainers/{trainer}/edit`
- `PUT /pt/trainers/{trainer}`
- `GET /pt/packages`
- `GET /pt/packages/create`
- `POST /pt/packages`
- `GET /pt/packages/{package}/edit`
- `PUT /pt/packages/{package}`
- `GET /pt/member-packages/create`
- `POST /pt/member-packages`
- `GET /pt/schedule`
- `GET /pt/schedule/create`
- `POST /pt/schedule`
- `PATCH /pt/schedule/{session}/complete`
- `PATCH /pt/schedule/{session}/cancel`
- `GET /pt/sessions`
- `GET /pt/sessions/create`
- `POST /pt/sessions`
- `GET /pt/commission-report`

## 11. RFID Card Management

Available functions:

- View RFID card list.
- View RFID card history.
- View member-specific RFID card history.
- Assign RFID card to member.
- Update active RFID card number.
- Deactivate RFID card.
- Block RFID card.
- Queue door access sync after card changes.
- Diagnose card eligibility from command line.

RFID card statuses:

- Active
- Inactive
- Lost
- Blocked

Important behavior:

- The old `replaced` status has been removed from normal system usage.
- Card-number update keeps the card as a normal active card.
- One member can have only one active RFID card.
- One active RFID card number cannot be assigned to multiple members.
- Card sync requires an active member and active access-allowed membership.

Important routes:

- `GET /rfid-cards`
- `GET /rfid-cards/history`
- `GET /members/{member}/rfid-cards`
- `GET /members/{member}/rfid-cards/create`
- `POST /members/{member}/rfid-cards`
- `GET /rfid-cards/{rfidCard}/replace`
- `POST /rfid-cards/{rfidCard}/replace`
- `PATCH /rfid-cards/{rfidCard}/deactivate`
- `PATCH /rfid-cards/{rfidCard}/block`

Important command:

- `php artisan access:diagnose-card {card}`

## 12. Dahua Door Access Integration

Available functions:

- Configure 1st Floor Door.
- Configure 2nd Floor Door.
- Configure door IP address.
- Configure Dahua SDK port.
- Configure Dahua username/password.
- Configure decimal or hex card format.
- Configure local Dahua SDK bridge URL.
- Configure bridge token.
- Enable/disable each door.
- Sync pending access records to both enabled controllers.
- Sync all currently authorized cards to enabled controllers.
- Send door command: unlock.
- Send door command: lock.
- Send door command: status.
- Send door command: sync-time.
- Keep Dahua SDK bridge alive with heartbeat.
- View door event history.
- View controller online/offline status.
- View bridge online/offline status.
- Verify controller local card list against MACS authorized cards.

Door sync actions:

- `ADD_CARD`
- `UPDATE_CARD`
- `DELETE_CARD`
- `DISABLE_CARD`
- `ENABLE_CARD`
- `FULL_SYNC`

Door access eligibility:

- Member must be active.
- RFID card must be active.
- Membership must be active and unexpired.
- Membership package must have door access allowed.
- Door controller must be enabled in settings.
- Dahua SDK bridge must be reachable.

Configured door labels:

- 1st Floor Door
- 2nd Floor Door

Important routes:

- `POST /access/sync-now`
- `GET /door-access/history`
- `PUT /settings/door-access`

Important commands:

- `php artisan access:bridge-heartbeat`
- `php artisan access:bridge-heartbeat --status`
- `php artisan access:bridge-heartbeat --restart`
- `php artisan access:sync-pending --limit=50`
- `php artisan access:sync-authorized --limit=500`
- `php artisan access:door-command status "1st Floor Door"`
- `php artisan access:door-command unlock "1st Floor Door" --seconds=5`
- `php artisan access:door-command lock "1st Floor Door"`
- `php artisan access:door-command sync-time "1st Floor Door"`
- `php artisan access:verify-card-list "1st Floor Door" --limit=500`

## 13. Door Event History

Available functions:

- Pull Dahua device access history through the local SDK bridge.
- Filter by door.
- Filter by card number.
- Filter by date range.
- Control result limit.
- Display event time, door, card number, user ID, result, reason, method, and reader.

Important route:

- `GET /door-access/history`

## 14. Backup And Restore

Available functions:

- View backup history.
- Create manual backup.
- Download completed backup.
- Restore from completed backup.
- Require password confirmation before restore.
- Require exact restore confirmation word.
- Run scheduled backup.
- Run end-of-day backup from POS.
- Configure backup location.
- Configure backup retention count.
- Configure backup schedule.
- Configure operating system preset.
- Configure PostgreSQL `pg_dump` binary.
- Configure PostgreSQL `psql` binary.
- Browse backup folders from UI.
- Prevent backup location inside public web folder.
- Record backup and restore actions in audit trail.

Backup content:

- PostgreSQL database dump.
- Member photos.
- Selected configuration snapshot.
- Backup manifest.
- SHA-256 checksum.

Restore behavior:

- Extracts selected backup.
- Restores database dump.
- Restores member photos.
- Preserves restored backup record.
- Records restore success or failure.

Backup schedules:

- Daily backup at 10:00 PM when backup schedule is daily.
- Weekly backup on Sunday at 10:00 PM when backup schedule is weekly.
- Manual backup only when schedule is manual.

Important routes:

- `GET /backups`
- `POST /backups`
- `GET /backups/{backupLog}/download`
- `POST /backups/{backupLog}/restore`
- `GET /settings/backup/folders`
- `PUT /settings/backup`

Important command:

- `php artisan backup:run --type=scheduled`

## 15. Audit Trail

Available functions:

- View audit trail.
- Filter audit records by module.
- Filter by action.
- Filter by user.
- Filter by date from and date to.
- View audit detail record.
- Store old values and new values for important operations.
- Audit login, logout, failed login, member changes, membership changes, RFID changes, sales, settings, backups, restore, and PT actions.

Important routes:

- `GET /audit-trail`
- `GET /audit-trail/{auditLog}`

## 16. User Management

Available functions:

- View users.
- Create user.
- Edit user.
- Deactivate user.
- Reactivate user.
- Assign role.
- Update password through password-change function.

Important routes:

- `GET /users`
- `GET /users/create`
- `POST /users`
- `GET /users/{user}/edit`
- `PUT /users/{user}`
- `PATCH /users/{user}/deactivate`
- `PATCH /users/{user}/reactivate`

## 17. Settings

Available functions:

- Update gym name.
- Update company name.
- Update gym contact number.
- Update gym address.
- Update receipt footer.
- Update expiring-soon day threshold.
- Enable/disable payment methods.
- Configure Dahua door access.
- Configure backup and restore settings.

General settings fields:

- Gym name
- Company name
- Gym address
- Gym contact number
- Receipt footer
- Expiring soon days
- Payment methods

Door access settings fields:

- Door name
- IP address
- Port
- Username
- Password
- Card number format
- Bridge URL
- Bridge token
- Door enabled

Backup settings fields:

- Backup save location
- Retention count
- Backup schedule
- Operating system preset
- PostgreSQL backup tool path
- PostgreSQL restore tool path

Important routes:

- `GET /settings`
- `PUT /settings/general`
- `PUT /settings/door-access`
- `PUT /settings/backup`
- `GET /settings/backup/folders`

## 18. Global Search

Available functions:

- Search members.
- Search RFID cards.
- Search receipts.
- Open search results from top search bar.

Search covers:

- Member number
- Member name
- Member phone
- RFID card number
- Receipt number

Important route:

- `GET /search`

## 19. Deployment Update Function

Available functions:

- Trigger deployment update from the system.
- Runs configured deployment update command.
- Intended for controlled local deployment workflow.

Important route:

- `POST /deployment/update`

## 20. Scheduled System Tasks

The following tasks are registered in Laravel scheduler:

| Task | Schedule | Function |
| --- | --- | --- |
| Dahua bridge heartbeat | Every minute | Keeps local Dahua SDK bridge alive. |
| Access sync pending | Every 30 minutes | Processes pending or failed door access sync records. |
| Membership expiry detection | Daily at 9:00 PM | Marks expired memberships and syncs access disable actions. |
| Daily backup | Daily at 10:00 PM | Creates scheduled backup when backup schedule is daily. |
| Weekly backup | Sunday at 10:00 PM | Creates scheduled backup when backup schedule is weekly. |

## 21. Command Reference

Membership and access commands:

- `php artisan memberships:detect-expired`
- `php artisan memberships:detect-expired --sync --sync-limit=500`
- `php artisan access:sync-pending --limit=50`
- `php artisan access:sync-authorized --limit=500`
- `php artisan access:diagnose-card {card}`
- `php artisan access:bridge-heartbeat`
- `php artisan access:bridge-heartbeat --status`
- `php artisan access:bridge-heartbeat --restart`
- `php artisan access:door-command {unlock|lock|status|sync-time} "{door name}"`
- `php artisan access:verify-card-list "{door name}" --limit=500`

Backup commands:

- `php artisan backup:run --type=scheduled`
- `php artisan backup:run --type=manual`

Development and verification commands:

- `php artisan test`
- `./vendor/bin/pint --dirty`
- `npm run build`

## 22. Data Protection And Security Functions

Available protections:

- Laravel validation on all form input.
- Role and permission middleware.
- Authentication and active-user middleware.
- Password hashing.
- Encrypted sessions.
- Current-password confirmation for backup setting changes and restore.
- Audit trail for sensitive actions.
- SQL injection protection through Laravel query builder and Eloquent parameter binding.
- Backup path validation to prevent public web-folder backups.
- Restore confirmation word.
- Dahua bridge token support.
- Sensitive access controller credentials stored in encrypted settings.

## 23. Important Operational Rules

- Card access is controlled by membership eligibility, not just by card number.
- A card cannot sync to Dahua unless the member has an active access-allowed membership.
- Walk-in access is treated as membership sales.
- PT purchase requires an active membership.
- POS prices are captured at sale time and do not change when product or package prices are edited later.
- Cashier report access is limited to own sales unless the user is manager or administrator.
- Restore is sensitive and requires both current password and typed confirmation.
- End-of-day closing creates a backup.

## 24. Main Files For Developers

Routes:

- `routes/web.php`
- `routes/console.php`

Controllers:

- `app/Http/Controllers/MemberController.php`
- `app/Http/Controllers/MemberMembershipController.php`
- `app/Http/Controllers/MembershipPackageController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/SaleController.php`
- `app/Http/Controllers/PersonalTrainingController.php`
- `app/Http/Controllers/RfidCardController.php`
- `app/Http/Controllers/DoorAccessHistoryController.php`
- `app/Http/Controllers/DailySalesReportController.php`
- `app/Http/Controllers/BackupController.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/AuditLogController.php`
- `app/Http/Controllers/UserManagementController.php`
- `app/Http/Controllers/GlobalSearchController.php`

Services and support classes:

- `app/Support/SalesManager.php`
- `app/Support/RfidCardManager.php`
- `app/Services/AccessSyncManager.php`
- `app/Services/DahuaStandaloneAccessControllerClient.php`
- `app/Support/DahuaBridgeHeartbeat.php`
- `app/Support/BackupManager.php`
- `app/Support/DailySalesReport.php`
- `app/Support/DailySalesReportXlsxExporter.php`
- `app/Support/DashboardMetrics.php`
- `app/Support/ProductCatalogManager.php`
- `app/Support/PtProductCatalog.php`
- `app/Support/SystemSettings.php`
- `app/Support/Audit.php`

Views:

- `resources/views/dashboard.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/members/`
- `resources/views/memberships/`
- `resources/views/products/`
- `resources/views/sales/`
- `resources/views/pt/`
- `resources/views/rfid-cards/`
- `resources/views/access/`
- `resources/views/reports/`
- `resources/views/backups/`
- `resources/views/settings/`
- `resources/views/audit/`
- `resources/views/users/`

## 25. Known Integration Notes

- The Dahua ASI1201E-D integration depends on the local Dahua SDK bridge.
- The app talks to the bridge over local HTTP.
- The bridge talks to the Dahua device through the Dahua SDK port.
- Door status, lock/unlock, time sync, card sync, access history, and card list verification are handled through bridge endpoints where supported.
- Controller card-list support may depend on SDK/device behavior. If the device rejects card-list reads, card sync can still be processed through sync actions, but local list verification will need device-side validation.

