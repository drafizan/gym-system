# Module 08: Point-of-Sale (POS)

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Record membership sales, renewals, walk-ins, and product sales.

## Sale Types

- Membership sale
- Membership renewal
- Product sale
- Walk-in sale

## Payment Methods

- Cash
- Bank transfer
- QR
- Other

## Features

- Select member
- Add membership package
- Add products
- Calculate total
- Record payment method
- Print or view receipt
- Cancel sale before completion
- View sales history

## Suggested Database Tables

### sales
Fields:

- id
- receipt_no
- member_id
- sale_type
- subtotal
- discount
- total_amount
- payment_status
- sold_by
- sold_at
- remarks
- created_at
- updated_at

### sale_items
Fields:

- id
- sale_id
- item_type
- item_id
- description
- quantity
- unit_price
- total_price
- created_at
- updated_at

### payments
Fields:

- id
- sale_id
- payment_method
- amount
- reference_no
- paid_at
- received_by
- created_at
- updated_at

## Receipt Information

Receipt should show:

- Receipt number
- Date and time
- Cashier name
- Member name, if applicable
- Items
- Quantity
- Amount
- Payment method
- Total

## Business Rules

1. Completed sale cannot be deleted by cashier.
2. Voiding sale requires manager or admin.
3. Product stock reduces after completed sale.
4. Membership renewal creates or updates membership record.
5. Every completed sale appears in daily sales report.

## Acceptance Criteria

- Cashier can complete product sale.
- Cashier can complete membership sale.
- Receipt number is generated.
- Sale appears in daily sales report.
- Product stock is updated.
- Sale activity is logged.
