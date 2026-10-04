@extends('layouts.app')

@section('title', 'POS Terminal')

@section('breadcrumbs')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>POS Terminal</span>
@endsection

@section('content')
    <div class="page-toolbar">
        <div>
            <p class="eyebrow">Sales</p>
            <h1>POS Terminal</h1>
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-light" href="{{ route('sales.history') }}">Sales History</a>
            <form method="POST" action="{{ route('sales.end-of-day') }}" onsubmit="return confirm('Run end of day closing and create a backup now?');">
                @csrf
                <button class="btn btn-primary" type="submit">End of Day</button>
            </form>
        </div>
    </div>

    @php
        $selectedMemberId = old('member_id', $checkout['member_id'] ?? null);
        $selectedMember = $members->firstWhere('id', (int) $selectedMemberId);
        $selectedMemberLabel = $selectedMember
            ? $selectedMember->full_name.' · '.$selectedMember->member_no
            : 'Walk-in / no member';
        $selectedSaleType = old('sale_type', $checkout['sale_type'] ?? \App\Enums\SaleType::ProductSale->value);
        $oldProductItems = collect(old('product_items', []))
            ->filter(fn ($item) => is_array($item))
            ->values();
        if ($oldProductItems->isEmpty()) {
            $oldProductItems = collect([[
                'product_id' => old('product_id'),
                'quantity' => old('quantity', 1),
            ]]);
        }
        $ptProductCatalog = app(\App\Support\PtProductCatalog::class);
    @endphp

    <form class="pos-shell" method="POST" action="{{ route('sales.store') }}" data-pos-form>
        @csrf
        @if ($checkout)
            <input type="hidden" name="registration_checkout_token" value="{{ $checkout['registration_checkout_token'] }}">
            <input type="hidden" name="start_date" value="{{ $checkout['start_date'] }}">
            <input type="hidden" name="end_date" value="{{ $checkout['end_date'] }}">
            <input type="hidden" name="membership_amount" value="{{ $checkout['membership_amount'] }}" data-pos-membership-amount>
            <input type="hidden" value="{{ $checkout['registration_fee'] }}" data-pos-registration-fee>
            <p class="form-span-2">New member checkout · Starts {{ $checkout['start_date'] }} · Registration Fee RM {{ number_format($checkout['registration_fee'], 2) }} included.</p>
        @endif

        <section class="pos-card pos-entry-panel">
            <div class="pos-card-header">
                <div>
                    <h2>New Sale</h2>
                    <p>Member, item, quantity, payment.</p>
                </div>
                <span class="status-pill success">Counter POS</span>
            </div>

            <div class="pos-section">
                <span class="pos-section-title">Customer</span>
                <label class="form-row">
                    <span>Member</span>
                    <div class="filterable-combobox" @if ($checkout) inert @endif data-filterable-combobox>
                        <select name="member_id" data-combobox-native @disabled(! empty($checkout)) tabindex="-1" aria-hidden="true">
                            <option value="" data-filter="walk-in no member">Walk-in / no member</option>
                            @foreach ($members as $member)
                                <option
                                    value="{{ $member->id }}"
                                    data-filter="{{ str($member->full_name.' '.$member->member_no.' '.$member->phone)->lower() }}"
                                    @selected((string) old('member_id', $checkout['member_id'] ?? null) === (string) $member->id)
                                >
                                    {{ $member->full_name }} · {{ $member->member_no }}
                                </option>
                            @endforeach
                        </select>
                        <button class="combobox-trigger" type="button" data-combobox-trigger aria-haspopup="listbox" aria-expanded="false">
                            <span data-combobox-value>{{ $selectedMemberLabel }}</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"></path>
                            </svg>
                        </button>
                        <div class="combobox-panel" data-combobox-panel hidden>
                            <div class="combobox-search">
                                <input type="search" data-combobox-search placeholder="Search member name, phone, or member no.">
                            </div>
                            <div class="combobox-options" data-combobox-options role="listbox">
                                <button class="combobox-option" type="button" data-combobox-option data-value="" data-label="Walk-in / no member" data-filter="walk-in no member" role="option">
                                    Walk-in / no member
                                </button>
                                @foreach ($members as $member)
                                    <button
                                        class="combobox-option"
                                        type="button"
                                        data-combobox-option
                                        data-value="{{ $member->id }}"
                                        data-label="{{ $member->full_name }} · {{ $member->member_no }}"
                                        data-filter="{{ str($member->full_name.' '.$member->member_no.' '.$member->phone)->lower() }}"
                                        role="option"
                                    >
                                        <strong>{{ $member->full_name }}</strong>
                                        <small>{{ $member->member_no }} · {{ $member->phone }}</small>
                                    </button>
                                @endforeach
                                <p class="combobox-empty" data-combobox-empty hidden>No member found.</p>
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            <div class="pos-section">
                <span class="pos-section-title">Sale Type</span>
                <div class="pos-type-grid" role="radiogroup" aria-label="Sale type">
                    @foreach ($saleTypes as $type)
                        <label class="pos-type-option">
                            <input type="radio" name="sale_type" value="{{ $type->value }}" @checked($selectedSaleType === $type->value) data-pos-sale-type @disabled(! empty($checkout))>
                            <span>{{ $type->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="pos-section">
                <span class="pos-section-title">Item</span>
                <div class="pos-item-grid" data-pos-product-list>
                    <label class="form-row" data-pos-package-row>
                        <span>Membership Package</span>
                        <select name="membership_package_id" data-pos-package-select @disabled(! empty($checkout))>
                            <option value="" data-price="0" data-label="No membership package">No membership package</option>
                            @foreach ($packages as $package)
                                <option
                                    value="{{ $package->id }}"
                                    data-price="{{ (float) $package->price }}"
                                    data-label="{{ $package->name }}"
                                    @selected((string) old('membership_package_id', $checkout['membership_package_id'] ?? null) === (string) $package->id)
                                >
                                    {{ $package->name }} · RM {{ number_format((float) $package->price, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <div class="pos-product-lines" data-pos-product-row>
                        <div class="pos-product-line-header">
                            <span>Product</span>
                            <span>Quantity</span>
                            <span>Discount (RM)</span>
                            <span></span>
                        </div>
                        @foreach ($oldProductItems as $index => $item)
                            <div class="pos-product-line" data-pos-product-line>
                                <label class="form-row">
                                    <select name="product_items[{{ $index }}][product_id]" data-pos-product-select>
                                        <option value="" data-price="0" data-label="No product">No product</option>
                                        @foreach ($products as $product)
                                            @php($isPtProduct = $ptProductCatalog->isPersonalTrainingProduct($product))
                                            <option
                                                value="{{ $product->id }}"
                                                data-price="{{ (float) $product->selling_price }}"
                                                data-label="{{ $product->name }}"
                                                data-stock="{{ $product->stock_quantity }}"
                                                data-is-pt="{{ $isPtProduct ? '1' : '0' }}"
                                                data-requires-active-membership="{{ $isPtProduct ? '1' : '0' }}"
                                                @selected((string) ($item['product_id'] ?? '') === (string) $product->id)
                                            >
                                                {{ $product->name }} · RM {{ number_format((float) $product->selling_price, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="form-row">
                                    <div class="pos-stepper">
                                        <button type="button" data-pos-qty-minus aria-label="Reduce quantity">-</button>
                                        <input type="number" name="product_items[{{ $index }}][quantity]" min="1" max="999" value="{{ $item['quantity'] ?? 1 }}" data-pos-quantity>
                                        <button type="button" data-pos-qty-plus aria-label="Increase quantity">+</button>
                                    </div>
                                </label>

                                <label class="form-row">
                                    <input type="number" name="product_items[{{ $index }}][discount]" step="0.01" min="0" value="{{ $item['discount'] ?? 0 }}" data-pos-line-discount>
                                </label>

                                <button class="btn btn-light icon-action pos-remove-product" type="button" data-pos-remove-product data-tooltip="Remove product" aria-label="Remove product" title="Remove product">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 6L6 18"></path>
                                        <path d="M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        @endforeach

                        <button class="btn btn-light pos-add-product" type="button" data-pos-add-product>
                            Add More Product
                        </button>
                        <p class="pos-requirement-note">Personal training packages can only be sold to members with an active membership.</p>
                    </div>

                    <template data-pos-product-template>
                        <div class="pos-product-line" data-pos-product-line>
                            <label class="form-row">
                                <select name="product_items[__INDEX__][product_id]" data-pos-product-select>
                                    <option value="" data-price="0" data-label="No product">No product</option>
                                    @foreach ($products as $product)
                                        @php($isPtProduct = $ptProductCatalog->isPersonalTrainingProduct($product))
                                        <option
                                            value="{{ $product->id }}"
                                            data-price="{{ (float) $product->selling_price }}"
                                            data-label="{{ $product->name }}"
                                            data-stock="{{ $product->stock_quantity }}"
                                            data-is-pt="{{ $isPtProduct ? '1' : '0' }}"
                                            data-requires-active-membership="{{ $isPtProduct ? '1' : '0' }}"
                                        >
                                            {{ $product->name }} · RM {{ number_format((float) $product->selling_price, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="form-row">
                                <div class="pos-stepper">
                                    <button type="button" data-pos-qty-minus aria-label="Reduce quantity">-</button>
                                    <input type="number" name="product_items[__INDEX__][quantity]" min="1" max="999" value="1" data-pos-quantity>
                                    <button type="button" data-pos-qty-plus aria-label="Increase quantity">+</button>
                                </div>
                            </label>

                            <label class="form-row">
                                <input type="number" name="product_items[__INDEX__][discount]" step="0.01" min="0" value="0" data-pos-line-discount>
                            </label>

                            <button class="btn btn-light icon-action pos-remove-product" type="button" data-pos-remove-product data-tooltip="Remove product" aria-label="Remove product" title="Remove product">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M18 6L6 18"></path>
                                    <path d="M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <aside class="pos-card pos-checkout-panel">
            <div class="pos-card-header">
                <div>
                    <h2>Checkout</h2>
                    <p>Check total before saving the sale.</p>
                </div>
            </div>

            <div class="pos-receipt-preview" data-pos-receipt-preview>
                <div class="pos-receipt-line" data-pos-receipt-empty>
                    <div>
                        <strong data-pos-line-name>No item selected</strong>
                        <span data-pos-line-meta>Add an item to continue</span>
                    </div>
                    <b data-pos-line-total>RM 0.00</b>
                </div>
            </div>

            <label class="form-row pos-cart-discount">
                <span>Cart Discount (RM)</span>
                <input type="number" name="discount" step="0.01" min="0" value="{{ old('discount', 0) }}" data-pos-discount>
            </label>

            <div class="pos-totals">
                <div><span>Subtotal</span><strong data-pos-subtotal>RM 0.00</strong></div>
                <div><span>Discount</span><strong data-pos-discount-label>RM 0.00</strong></div>
                <div class="pos-total-row"><span>Total</span><strong data-pos-total>RM 0.00</strong></div>
            </div>

            <div class="pos-payment-grid">
                <label class="form-row">
                    <span>Payment Method</span>
                    <select name="payment_method" required>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" @selected(old('payment_method', $checkout['payment_method'] ?? 'cash') === $method)>{{ \App\Enums\PaymentMethod::labelFor($method) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-row">
                    <span>Payment Reference</span>
                    <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Optional">
                </label>

                <label class="form-row">
                    <span>Remarks</span>
                    <textarea name="remarks" rows="4" placeholder="Optional">{{ old('remarks') }}</textarea>
                </label>
            </div>

            <div class="pos-actions">
                <button class="btn btn-light" type="reset" @disabled(! empty($checkout))>Clear</button>
                <button class="btn btn-primary" type="submit">Complete Sale</button>
            </div>
        </aside>
    </form>
@endsection
