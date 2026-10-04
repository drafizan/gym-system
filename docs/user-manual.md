# Gorilla Mutantz Gym User Manual

## 1. Purpose

This manual is for staff who use the Gorilla Mutantz Gym access system during daily operations.

The current system includes:

- Login and logout
- Daily sales report dashboard
- Member registration
- Membership package and renewal workflows
- Weekly sales trend view
- Password change
- Theme switcher
- Role-based menu access

Some modules shown in the system plan are not active yet. RFID cards, POS, product management, backups, and settings will be documented after those modules are built.

## 2. Opening The System

Open the system in a browser:

```text
https://gmutantz.test
```

You will be redirected to the login page if you are not signed in.

## 3. Login

1. Enter your username.
2. Enter your password.
3. Click `Sign In`.

If login fails:

- Check username spelling.
- Check password.
- Wait if too many failed attempts were made.
- Contact support if you forgot your password.

## 4. Forgot Password

On the login page:

1. Click `Forgot password?`.
2. A support popup will appear.
3. Contact support through WhatsApp.

WhatsApp:

```text
https://wa.me/601128520309
```

Include:

- Your staff name
- Your branch, when applicable
- Your username, if known

For security, users cannot reset their own password from the login page.

## 5. Logout

To logout:

1. Click the logout icon in the top-right header.
2. The system returns to the login page.

Always logout when leaving the computer unattended.

## 6. Change Password

To change your own password:

1. Login to the system.
2. Click the lock icon in the top-right header.
3. Enter your current password.
4. Enter your new password.
5. Confirm the new password.
6. Click `Update Password`.

Use a password that is not shared with other systems.

## 7. Dashboard

After login, the first page shows the Daily Sales Report dashboard.

The dashboard currently includes:

- Total Revenue
- Membership Sales
- Product Sales
- Cash Collection
- Online Payment
- Sales Breakdown chart
- Payment Method Breakdown chart
- Weekly Sales Trend chart
- Sales Details table

The dashboard values are currently sample report data until the real POS and sales modules are completed.

## 8. Daily Sales Report Filters

Current available filter:

- Date

Outlet selection is hidden until multibranch mode is active.

Buttons:

- `Generate Report`: reserved for refreshing the report.
- `Export`: reserved for XLSX report export.

These actions will become fully functional when the Daily Sales Report module is connected to real sales data.

## 9. Sales Details Table

The Sales Details section shows sample transactions.

Columns:

- Time
- Receipt No.
- Type
- Description
- Category
- Payment Method
- Amount
- Received By

Tabs:

- All Transactions
- Membership Sales
- Product Sales
- Other Sales

Search and filter controls are present for the future live report workflow.

## 10. Interface Style

The system uses the standard light Metronic-style administration interface.

## 11. Menu Access

The sidebar only shows the menu items your role can access.

### Administrator

Can access all areas, including:

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

Can access operational areas:

- Dashboard
- Reports
- Members
- Memberships
- Access Control
- Sales
- Products
- Audit Trail

### Cashier

Can access front-desk areas:

- Dashboard
- Members
- Sales
- Reports

If you cannot see a menu item, your role does not currently have access.

## 12. Users & Roles

Only Administrator users can manage users.

Administrators can:

- Create user accounts
- Edit user accounts
- Assign roles
- Deactivate users
- Reactivate users

If your account is inactive, you cannot login. Contact an administrator.

## 13. Member Registration

Path:

```text
Members > Manage Members
```

Users with member access can:

- View member records
- Search by member number, name, phone, or IC/passport
- Filter members by Active, Expiring, Expired, Suspended, Missing Photo, Has RFID, and No RFID
- Export the current member list to CSV
- Import member records from CSV
- Register a new member
- Edit member information
- View member profile
- Suspend a member
- Reactivate a suspended member

To register a member:

1. Open `Members > Manage Members`.
2. Click `Register Member`.
3. Fill in the member details.
4. Add a photo by upload or camera capture, when available.
5. Select the membership package and review the start date, end date, and amount.
6. Review the POS Summary below Membership Information and choose the payment method. New registrations include the one-time Registration Fee (the configured package price, RM60 if not configured).
7. Click `Save & Continue to POS`. This requires member and sales permissions.
8. Review the prefilled member, package, dates, amount, registration fee, and payment method. Add a payment reference or discount if needed.
9. Click `Complete Sale` to create the paid membership, sale, payment, and receipt and queue membership door-access synchronization.

The system creates the member number when saving registration. The new membership is created when the POS payment is completed. The fee is a sale item, not an additional membership. Repeating the same checkout does not create another sale. Checkout details remain available in the current staff session; complete checkout before the session expires.

Membership Renewal in POS does not add a Registration Fee.

CSV import requires at least:

- `full_name`
- `phone`

Optional CSV columns include `email`, `ic_passport_no`, `gender`, `address`, and `rfid_card_number`.

Member profile shows:

- Member photo
- Member number
- Member status
- Personal information
- Emergency contact
- Latest membership
- Membership status and expiry
- RFID card number, when entered

RFID sync details will become fully live after the RFID and 2-unit door access modules are completed.

## 14. Memberships

Path:

```text
Memberships
```

Users with membership access can:

- View and manage membership packages
- Assign membership from a member profile
- Renew membership from a member profile
- View expiring soon memberships
- View expired memberships
- Suspend an active membership

Default packages:

- Walk-in
- Monthly
- Quarterly
- Yearly

Renewal rule:

- If renewed before expiry, the new expiry extends from the current expiry date.
- If renewed after expiry, the new expiry extends from the renewal date.

POS membership sales are not active yet. They will use the same assignment and renewal rules when the POS module is built.

## 15. Audit Trail

Audit Trail records important system activity such as:

- Login
- Logout
- Password change
- User creation
- User update
- User deactivation
- User reactivation
- Member registration
- Member update
- Member suspension
- Member reactivation
- Membership package creation and update
- Membership assignment
- Membership renewal
- Membership suspension
- Membership expiry detection

Only roles with Audit Trail access can view audit records.

Audit records cannot be edited or deleted from the system.

## 16. Good Daily Practice

- Do not share your account.
- Logout after using the system.
- Do not save passwords on shared computers.
- Report suspicious activity to the administrator.
- Use the correct account for each staff member.
- Contact support if you cannot login.

## 17. Planned Modules

The following modules are planned and will be documented when completed:

- RFID card assignment
- Door access synchronization
- Product management
- POS sales
- Receipt printing
- Daily sales report XLSX export
- Settings
