# Module 07: Product & Pricing Management

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Allow gym staff to manage product list, pricing, and stock.

## Product Examples

- Mineral Water
- Sports Drink
- Protein Shake
- Supplement
- Towel
- Merchandise

## Features

- Add product
- Edit product
- Deactivate product
- Manage category
- Manage selling price
- Manage stock quantity
- Low stock indicator

## Suggested Database Tables

### product_categories
Fields:

- id
- name
- status
- created_at
- updated_at

### products
Fields:

- id
- category_id
- name
- sku
- description
- selling_price
- stock_quantity
- reorder_level
- status
- created_at
- updated_at

## Business Rules

1. Product cannot be sold if inactive.
2. Product stock should reduce after successful POS sale.
3. Stock cannot go negative unless admin allows it in settings.
4. Product price changes should not alter historical sales.

## Acceptance Criteria

- Admin can add and update products.
- Cashier can search and sell active products.
- Stock reduces after sale.
- Product change is logged in audit trail.
