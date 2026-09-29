# Gym Management System Development Checklist

This checklist is based on the project Markdown specifications in the root folder. The duplicated files in `gym_project_md_specs_updated/` match the root specs.

## Current Module Completion Snapshot

Updated: 5 August 2026

Current overall progress: **93.3%**

Completed checklist items: **152 / 163**

### Module Progress

| Module | Progress | Status |
| --- | ---: | --- |
| 0. Project Foundation | 100% | Complete |
| 1. UI Architecture | 100% | Complete |
| 2. Authentication, Roles, Permissions | 100% | Complete |
| 3. Audit Trail | 100% | Complete |
| 4. Member Registration | 100% | Complete |
| 5. Membership Management | 100% | Complete |
| 6. RFID Card Management | 100% | Complete |
| 7. Door Access Integration | 100% | Complete |
| 8. Product & Pricing | 100% | Complete |
| 9. POS Sales | 100% | POS, receipt, history, discounts, stock updates, and voiding are complete |
| 10. Dashboard | 100% | Complete |
| 11. Daily Sales Report | 100% | Complete |
| 12. Backup Module | 92.9% | Backup/restore/settings/security guardrails done; cloud upload optional only |
| 13. Settings | 100% | Complete |
| 14. Testing & Quality | 100% | Complete |
| 15. Deployment & UAT | 44.4% | Deployment script, production env template, handover notes, backup instructions, and UAT sign-off form are prepared; real machine setup, scheduler/queue confirmation, staff training, and UAT execution pending |

Notes:

- Core daily operation modules are mostly complete: members, memberships, POS, dashboard, audit, and backup are usable.
- Lower percentages are mainly hardware, deployment, and UAT items that need real environment confirmation.

## 0. Project Foundation

- [x] Confirm local development environment baseline: PHP 8.4 via Laravel Herd, Composer, and Node are available; PostgreSQL app config is ready, but `psql` CLI is not currently in PATH.
- [x] Switch database from default SQLite to PostgreSQL in `.env` and `.env.example`.
- [x] Set project app name, timezone, locale, and local URL.
- [x] Run baseline validation, migration, test, and frontend build checks.
- [x] Confirm frontend assets for the chosen UI stack: Laravel Blade + Vite + Tailwind-style custom CSS following the purchased Metronic Tailwind HTML Demo 1 reference.
- [x] Remove or stop relying on starter/template UI that conflicts with the custom Blade admin shell.
- [x] Define shared app constants/enums for statuses, roles, sale types, payment methods, sync actions, and membership statuses.
- [x] Decide where uploaded member photos and backup files live in development and production.

## 1. UI Architecture

- [x] Use the purchased Metronic Tailwind HTML Demo 1 folder as the visual reference: `/Users/drafizandrahman/Downloads/metronic-tailwind-html-demos/dist/html/demo1`.
- [x] Build all styling custom with Blade + Vite custom CSS following the purchased Metronic reference.
- [x] Build the main Blade layout: header, light sidebar, content area, footer.
- [x] Add partials: sidebar, header, footer, breadcrumb, flash messages, validation errors.
- [x] Remove demo-only sidebar items and keep only real system modules for production navigation.
- [x] Restyle the app shell/sidebar to match purchased Metronic Demo 1: light sidebar, thin borders, compact menu rows, blue active states, line-guided submenu, subtle cards, mobile drawer, and desktop collapse button.
- [x] Add responsive sidebar behavior with active menu highlighting.
- [x] Add authenticated user menu, current date, notifications placeholder, branch name, and system version.
- [x] Create reusable Blade components for cards, tables, status badges, filters, form rows, and action buttons.
- [x] Apply custom theme colors from the purchased Metronic Demo 1 reference.
- [x] Ensure desktop, tablet, and mobile layouts work without SPA frameworks.

## 2. Authentication, Roles, And Permissions

- [x] Create/adjust migrations for users, roles, permissions, and role permissions.
- [x] Add seeders for Administrator, Manager, Cashier, default permissions, and role mappings.
- [x] Build login, logout, password change, and last-login tracking.
- [x] Build user CRUD with deactivate support.
- [x] Add middleware or policies for permission-based access.
- [x] Hide sidebar/menu items based on role permissions.
- [x] Add tests for valid login, invalid login, deactivated users, and protected routes.
- [x] Log login and user-management actions to audit logs.
- [x] Add Settings page for configurable 1st Floor Door and 2nd Floor Door IP addresses.

### Login / Authentication UAT Status

- [x] Add live network health checks for the actual 1st Floor Door (`192.168.100.11`) and 2nd Floor Door (`192.168.100.12`) devices. Current login/sidebar `Online` status means the configured Dahua TCP port is reachable from the MACS machine.
- [x] Connect Dahua/access-device sync status so the `Last sync` metric reflects access controller sync activity.
- [x] Confirm the `Pending syncs` metric reads from live RFID/member access sync queue records.
- [x] Finalize the support-modal password reset process for the offline local deployment model.
- [x] Document Login / Authentication UAT coverage in `docs/login-authentication-uat.md`.
- [x] Move final client approval for login-page wording, door status labels, and displayed operational metrics into Deployment & UAT sign-off.

## 3. Audit Trail Foundation

- [x] Create `audit_logs` migration and model early because other modules depend on it.
- [x] Build an audit logging service that records user, module, action, record type, record ID, old values, new values, IP, and user agent.
- [x] Exclude sensitive values such as passwords and encrypted credentials from audit payloads.
- [x] Add admin audit log listing, filters, and detail view.
- [x] Protect audit logs from normal edit/delete actions.

## 4. Member Registration

- [x] Create members migration, model, factory, form request, controller, and routes.
- [x] Implement automatic member number generation.
- [x] Build member list with search by name, phone, IC/passport, and member number.
- [x] Build create, edit, show/profile, suspend, and reactivate workflows.
- [x] Add browser camera capture using `navigator.mediaDevices.getUserMedia`.
- [x] Store member photos under public storage and save the photo path.
- [x] Show latest membership and RFID card status on member profile.
- [x] Add member list filters for Active, Expiring, Expired, Suspended, Missing Photo, Has RFID, and No RFID.
- [x] Add CSV member import and export for offline front-desk operations.
- [x] Improve member profile fallbacks for missing contact/photo/access-sync data.
- [x] Log member create/update/suspend/reactivate actions.
- [x] Add audit detail change summary for easier member-change review.

## 5. Membership Management

- [x] Create migrations/models for membership packages and member memberships.
- [x] Seed default packages: Walk-in, Monthly, Quarterly, Yearly.
- [x] Seed realistic sample members and memberships across active, expiring, expired, suspended, walk-in, RFID, and no-RFID cases.
- [x] Build package CRUD with duration, price, access allowed, walk-in flag, and status.
- [x] Build assign membership and renew membership flows from member profile; POS will reuse this flow when POS is built.
- [x] Implement renewal date rules for before-expiry and after-expiry renewals.
- [x] Add expiring soon and expired member lists using configurable threshold, default 7 days.
- [x] Add scheduled job/command to detect expired memberships.
- [x] Trigger access sync logs when membership access status changes.
- [x] Log membership assignment, renewal, suspension, expiry, and status changes.

## 6. RFID Card Management

- [x] Create `rfid_cards` migration and model.
- [x] Enforce one active RFID card per member.
- [x] Prevent duplicate active card numbers.
- [x] Build backend card lifecycle service for assign card, replace lost card, deactivate card, and block card.
- [x] Build assign card, replace lost card, deactivate card, and card history screens.
- [x] Mark replaced cards inactive and link them to replacement cards.
- [x] Trigger access sync after card assign, replace, deactivate, or block.
- [x] Show current card state on member profile.
- [x] Log all card lifecycle changes.

## 7. Door Access Integration

- [x] Create `access_sync_logs` and `access_controller_settings` migrations/models.
- [x] Build controller settings screen with encrypted credentials.
- [x] Define access sync actions: ADD_CARD, UPDATE_CARD, DELETE_CARD, DISABLE_CARD, ENABLE_CARD, FULL_SYNC.
- [x] Build an access controller service behind an interface so Dahua integration can be tested separately.
- [x] Align Dahua standalone settings and sync payloads with the Access Standalone User Manual V1.0.3 and actual device probe: TCP/IP device, SDK port 37777, encrypted device login, decimal/hex card format, numeric device user ID, and local SDK bridge handoff.
- [x] Connect Dahua SDK bridge door commands for `unlock`, `lock`, and `status` through `php artisan access:door-command`.
- [x] Build manual sync button under the top bar as `Sync Door Access Now`.
- [x] Add queued/scheduled sync every 30 minutes for pending changes.
- [x] Record success and failed sync results with payloads, responses, and errors.
- [x] Show controller status and last sync time on dashboard.
- [x] Confirm the controller keeps the latest authorized card list locally using `php artisan access:verify-card-list`.

## 8. Product And Pricing Management

- [x] Create product categories and products migrations/models.
- [x] Build backend category CRUD service.
- [x] Build backend product CRUD service with SKU, description, selling price, stock quantity, reorder level, and status.
- [x] Build category and product management screens.
- [x] Add product search for POS.
- [x] Add low-stock indicators.
- [x] Prevent inactive products from being sold.
- [x] Preserve product price history after product price changes.
- [x] Log product creation and updates.

## 9. POS Sales

- [x] Create migrations/models for sales, sale items, and payments.
- [x] Implement receipt number generation.
- [x] Build backend sale completion service for member selection, membership package items, product items, totals, discount, remarks, and payment method.
- [x] Build POS screen for member selection, membership package items, product items, totals, discount, remarks, and payment method.
- [x] Support sale types: membership sale, membership renewal, product sale, walk-in sale.
- [x] Update product stock only after completed sale.
- [x] Create or update membership records after completed membership sale/renewal.
- [x] Build receipt view/print screen.
- [x] Build sales history.
- [x] Add manager/admin-only sale voiding.
- [x] Ensure completed cashier sales cannot be deleted by the POS service.
- [x] Log completed and voided sales.

## 10. Dashboard

- [x] Add backend KPI queries for total members, active members, expired members, expiring soon, today's sales, membership sales, product sales, and monthly revenue.
- [x] Add backend recent activity feed from audit logs.
- [x] Add backend membership status overview.
- [x] Add backend daily or weekly sales chart split by membership and product sales.
- [x] Add backend system status data for database, backup, controller, and last access sync.
- [x] Connect live dashboard backend data to dashboard screen.
- [x] Keep dashboard load under 3 seconds on local network.

## 11. Daily Sales Report

- [x] Build backend report filters for date, date range, cashier, payment method, and sale type.
- [x] Add backend KPI summary: total revenue, membership sales, product sales, walk-in sales, payment breakdown, transaction count.
- [x] Add backend transaction list with receipt, type, description, payment method, amount, and received by.
- [x] Restrict cashier reporting to own sales when permission requires it.
- [x] Build daily sales report screen.
- [x] Add XLSX export.
- [x] Add tests that report totals match POS transactions.

## 12. Backup Module

- [x] Create `backup_logs` migration and model.
- [x] Define backup paths for Windows and local development.
- [x] Build Backup Now action for administrators.
- [x] Include PostgreSQL dump, member photos, and required config files.
- [x] Compress backups using `backup_YYYYMMDD_HHMM.zip` naming.
- [x] Add backup history with status, file size, timestamps, errors, and download link.
- [x] Add admin-only restore action with confirmation guard.
- [x] Restore PostgreSQL dump and member photos from completed local backup files.
- [x] Add Settings page controls for backup save location, retention count, schedule, PostgreSQL backup tool, and PostgreSQL restore tool.
- [x] Add security checkpoint guardrails: current password required for restore and backup settings changes, public-folder backup paths blocked, PostgreSQL tool paths constrained, and captured photo uploads validated.
- [x] Add daily scheduled backup.
- [x] Add retention cleanup, default latest 30 backups.
- [ ] Optionally add Google Drive upload only if approved later.
- [x] Log backup started, completed, failed, restore started, restore completed, and restore failed events.

## 13. Settings

- [x] Create a settings storage approach for gym name, address, contact number, receipt footer, expiring threshold, backup schedule, controller settings, payment methods, and default packages.
- [x] Build settings screens with role protection.
- [x] Validate settings and encrypt sensitive values.
- [x] Use settings in backend dashboard, POS payment, backup status, and membership expiry paths.
- [x] Use settings in future report exports, receipts, and access sync configuration screens.

## 14. Testing And Quality

- [x] Add feature tests for all main CRUD flows.
- [x] Add tests for role permissions and unauthorized access.
- [x] Add tests for renewal date calculation.
- [x] Add tests for one-active-card and duplicate-card rules.
- [x] Add tests for product search, low stock, price history, and inactive product sale blocking.
- [x] Add tests for stock updates and sale voiding.
- [x] Add tests for daily sales report totals.
- [x] Add tests for audit logging coverage.
- [x] Run formatting with Laravel Pint.
- [x] Run the full automated test suite before each handover milestone.

## 15. Deployment And UAT

- [x] Prepare production `.env` for one-branch local deployment.
- [x] Add protected dashboard Update button for deployment from GitHub.
- [x] Add fixed deployment script for `git pull`, dependency install, migrations, asset build, and Laravel cache refresh.
- [ ] Set `DEPLOYMENT_UPDATES_ENABLED=true` and a strong `DEPLOYMENT_SECRET` only on the approved deployment machine.
- [ ] Confirm the deployment machine can run Git, Composer, PHP, Node, and NPM from the web server user.
- [ ] Confirm XLSX export works on the deployment machine without OpenOffice/LibreOffice; install LibreOffice only if future PDF/document conversion is approved.
- [ ] Prepare PostgreSQL database and backup folder on the target Windows mini PC or local server.
- [ ] Configure storage links and file permissions.
- [ ] Configure Laravel scheduler and queue worker.
- [ ] Configure local network browser access, for example `http://192.168.1.10`.
- [ ] Run UAT: authentication, members, memberships, RFID, door access, products, POS, reports, backup, audit.
- [ ] Prepare admin login details.
- [x] Prepare deployment notes.
- [x] Prepare backup instructions.
- [x] Prepare system admin manual.
- [x] Prepare basic user manual.
- [x] Prepare UAT sign-off form.
- [ ] Complete staff training and client sign-off.

## Suggested Build Order

1. Project foundation and UI architecture.
2. Authentication, roles, permissions, and audit foundation.
3. Members, memberships, and RFID cards.
4. Door access settings and sync workflow.
5. Products and POS.
6. Dashboard and daily sales report.
7. Backup, settings polish, deployment, and UAT.
