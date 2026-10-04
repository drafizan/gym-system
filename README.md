# Gym Management System

One shared application supports Windows, Linux, and the existing macOS setup.
See [platform setup and branch comparison](docs/platform-unification.md) and
[deployment notes](docs/deployment-notes.md).

# Gym Management System MD Specification Pack

This ZIP contains module-by-module development specifications for the accepted RM13,800 gym membership and access control project.

## Files

- 00-ui-architecture.md

- 00-project-overview.md
- 01-authentication-user-management.md
- 02-dashboard.md
- 03-member-registration.md
- 04-membership-management.md
- 05-rfid-card-management.md
- 06-door-access-integration.md
- 07-product-management.md
- 08-pos-sales.md
- 09-daily-sales-report.md
- 10-backup-module.md
- 11-audit-trail.md
- 12-settings-deployment-uat.md

## Scope Reminder

Included:
- Local gym management system
- Customer registration
- Membership management
- RFID card management
- Door access control integration
- Product/POS module
- Daily sales report
- Backup
- User management
- Audit trail

Excluded:
- WhatsApp automation
- Billplz integration
- Online renewal payment
- Mobile app
- Multi-branch


## UI Decision

Use Laravel Herd with **No Starter Kit**.

Build the frontend using:

- Laravel Blade
- Bootstrap 5
- Traditional MVC

Follow the custom UI architecture in `00-ui-architecture.md`.

Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.
