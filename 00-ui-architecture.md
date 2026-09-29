# UI Architecture & Development Standards

## Frontend Architecture

This project shall use:

- Laravel Blade
- Bootstrap 5
- PostgreSQL
- Laravel Authentication
- Traditional MVC

Do NOT use:

- React
- Vue
- Svelte
- Livewire
- InertiaJS
- SPA architecture

The system shall be built as a traditional Laravel Blade application.

---

## UI Design Direction

The visual direction shall follow:

Purchased Metronic Tailwind HTML Demo 1

Reference:

/Users/drafizandrahman/Downloads/metronic-tailwind-html-demos/dist/html/demo1

This reference is for visual inspiration only.

Do NOT copy any Metronic proprietary source code unless a valid license is purchased.

---

## Layout Structure

All pages shall use a consistent layout:

```text
Header
Sidebar + Content Area
Footer
```

Recommended Blade structure:

```text
resources/views/layouts/app.blade.php
resources/views/partials/sidebar.blade.php
resources/views/partials/header.blade.php
resources/views/partials/footer.blade.php
resources/views/partials/breadcrumb.blade.php
```

---

## Sidebar Requirements

Dark sidebar.

Menu items:

- Dashboard
- Members
- Memberships
- Access Control
- Sales
- Products
- Reports
- Backup
- Users
- Audit Trail
- Settings

Requirements:

- Collapsible
- Icon based
- Active menu highlight
- Mobile responsive

---

## Header Requirements

Top navigation bar.

Features:

- System name
- Current date
- Logged-in user
- Notifications
- Profile menu

---

## Footer Requirements

Display:

- System version
- Copyright
- Branch name

---

## Dashboard Style

Use card-based layout.

Cards:

- Total Members
- Active Members
- Expiring Soon
- Expired Members
- Today's Sales
- Membership Sales
- Product Sales
- Monthly Revenue

Use Bootstrap cards, rounded corners, and soft shadows.

---

## Forms

All forms must:

- Use Bootstrap 5
- Be responsive
- Use two-column layout on desktop
- Use single-column layout on mobile
- Use clear validation messages

---

## Tables

Use Bootstrap tables.

Requirements:

- Search
- Sort
- Pagination
- Export support where applicable

---

## Color Palette

Primary:

```css
#009EF7
```

Success:

```css
#50CD89
```

Warning:

```css
#FFC700
```

Danger:

```css
#F1416C
```

Dark Sidebar:

```css
#1E1E2D
```

---

## Icons

Use one of the following:

- Bootstrap Icons
- Font Awesome 6

Use icons consistently in sidebar, buttons, cards, and status indicators.

---

## Responsive Requirements

Desktop:

```text
≥1200px
```

Tablet:

```text
768px - 1199px
```

Mobile:

```text
≤767px
```

---

## Coding Standards

- Use Laravel Resource Controllers
- Use Service Layer Pattern for business logic
- Use Repository Pattern only when useful
- Use Form Request Validation
- Use Eloquent ORM
- Avoid raw SQL unless necessary
- Use migrations for all tables
- Use named routes
- Use reusable Blade components where useful

---

## Performance Requirements

- Dashboard load under 3 seconds on local network
- Tables should use pagination
- Large images should be optimized
- Do not build as SPA

---

## Deliverable Quality

The final UI should look comparable to the purchased Metronic Tailwind HTML Demo 1 reference while remaining implemented in this Laravel app using:

```text
Laravel Blade + Bootstrap 5
```
