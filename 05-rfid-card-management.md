# Module 05: RFID Card Management

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Assign RFID cards to members and manage access card lifecycle.

## Card Type
Recommended card:

- EM RFID compatible card
- 125kHz proximity card
- Size: 85.6mm x 54mm x 0.8mm

## Features

- Assign RFID card to member
- Replace lost card
- Deactivate card
- View card history
- Prevent duplicate active card number
- Trigger access controller sync after card changes

## Card Status

- Active
- Inactive
- Lost
- Replaced
- Blocked

## Suggested Database Tables

### rfid_cards
Fields:

- id
- member_id
- card_number
- card_type
- status
- assigned_at
- deactivated_at
- replaced_by_card_id
- remarks
- created_by
- updated_by
- created_at
- updated_at

## Business Rules

1. One member may have one active RFID card.
2. Same card number cannot be active for more than one member.
3. Replaced cards must be deactivated.
4. Expired member cards must be disabled in access controller.
5. Active member cards must be synced to access controller.

## Acceptance Criteria

- Staff can assign RFID card to member.
- Duplicate card is rejected.
- Lost card can be deactivated.
- New replacement card can be assigned.
- Card change creates audit log.
- Card change triggers door access sync.
