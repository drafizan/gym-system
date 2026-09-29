# Module 03: Customer Registration

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Allow staff to register and manage gym members locally.

## Member Fields

### Personal Information
- Full name
- IC / Passport number
- Date of birth
- Gender
- Phone number
- Email
- Address

### Emergency Contact
- Contact name
- Relationship
- Contact number

### Membership Information
- Membership package
- Start date
- End date
- Payment status
- Remarks

### Photo
- Capture member photo using USB camera
- Save photo path in database
- Store image file locally

## Features

- Create member
- Update member
- View member profile
- Search member by name, phone, IC, or member number
- Capture photo from browser camera
- Replace photo
- Suspend member
- Reactivate member

## Suggested Database Tables

### members
Fields:

- id
- member_no
- full_name
- ic_passport_no
- date_of_birth
- gender
- phone
- email
- address
- emergency_contact_name
- emergency_contact_relationship
- emergency_contact_phone
- photo_path
- status
- remarks
- created_by
- updated_by
- created_at
- updated_at

## Camera Implementation

Use browser camera API:

```javascript
navigator.mediaDevices.getUserMedia({ video: true })
```

Save captured image as uploaded file to:

```text
/storage/app/public/members/photos/
```

## Acceptance Criteria

- Staff can register a new member.
- Member number is generated automatically.
- Photo can be captured from USB camera.
- Member can be searched quickly.
- Member profile shows latest membership and RFID card status.
- Member update is logged in audit trail.
