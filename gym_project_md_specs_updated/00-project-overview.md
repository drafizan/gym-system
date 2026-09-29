# Gym Membership & Access Control Management System
## Project Overview

## Project Status
Accepted project value: **RM13,800**

## Deployment Type
Local / on-premise deployment for **one branch**.

## Scope Summary
The system is a local gym management platform that manages:

- Customer registration
- Membership management
- RFID card assignment
- Door access control integration
- Product and pricing list
- Point-of-sale sales recording
- Daily sales reporting
- Data backup
- User management
- Audit trail

## Removed From Scope
The following items are not included in this RM13,800 scope:

- WhatsApp automation
- Billplz integration
- Online renewal payment
- Payment polling
- Cloud sync bridge
- Mobile app
- Multi-branch management
- Trainer management
- Class booking
- Member self-service portal


## Starter Kit & UI Decision

Use **No Starter Kit** in Laravel Herd.

Frontend architecture:

- Laravel Blade
- Bootstrap 5
- Traditional MVC

Do not use:

- React
- Vue
- Svelte
- Livewire
- InertiaJS
- SPA architecture

The UI must follow the standards in:

```text
00-ui-architecture.md
```

Design direction: Purchased Metronic Tailwind HTML Demo 1 inspired admin dashboard.

Reference:

```text
/Users/drafizandrahman/Downloads/metronic-tailwind-html-demos/dist/html/demo1
```

Use the reference for visual direction only. Do not copy proprietary Metronic source code unless a valid license is purchased.

## Recommended Tech Stack

- Backend: Laravel
- Frontend: Laravel Blade + Bootstrap 5
- Database: PostgreSQL
- Camera: Browser-based USB camera capture
- Access Control: Dahua-compatible door access controller
- Deployment: Local Windows mini PC or local server
- Backup: Local backup, optional Google Drive backup

## User Roles

- Administrator
- Manager
- Cashier

## Main Business Rules

1. Each member may have one active RFID card.
2. Membership expiry controls access permission.
3. Active membership means access card is active.
4. Expired or suspended membership means access card is disabled.
5. Product sales and membership sales are recorded in the POS.
6. Daily sales report must include membership sales, product sales, and payment breakdown.
7. All customer data is stored locally.
8. All important changes must be recorded in audit logs.

## Development Approach
Build module by module in this order:

1. Authentication and user management
2. Dashboard
3. Member registration
4. Membership management
5. RFID card management
6. Door access integration
7. Product management
8. POS
9. Daily sales report
10. Backup
11. Audit trail
12. Deployment and UAT
