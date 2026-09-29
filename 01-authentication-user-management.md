# Module 01: Authentication & User Management

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Provide secure login and role-based access for system users.

## Roles

### Administrator
Full access to all modules.

### Manager
Access to reports, members, sales, products, and daily operations.

### Cashier
Access to registration, POS, basic member lookup, and daily sales.

## Features

- Login
- Logout
- User creation
- User update
- User deactivate
- Password change
- Role assignment
- Permission-based menu display

## Suggested Database Tables

### users
Fields:

- id
- name
- email
- username
- password
- role_id
- status
- last_login_at
- created_at
- updated_at

### roles
Fields:

- id
- name
- description
- created_at
- updated_at

### permissions
Fields:

- id
- key
- name
- description

### role_permissions
Fields:

- id
- role_id
- permission_id

## Permission Examples

- view_dashboard
- manage_members
- manage_memberships
- manage_rfid_cards
- manage_products
- use_pos
- view_reports
- manage_backup
- manage_users
- view_audit_logs
- manage_settings

## Acceptance Criteria

- User can login with valid credentials.
- Invalid login is rejected.
- User sees only menus allowed by role.
- Administrator can create and deactivate users.
- Passwords are hashed.
- Login activity is recorded in audit log.
