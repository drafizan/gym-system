import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = source.indexOf("document.querySelectorAll('[data-membership-package-select]')");
const end = source.indexOf("document.querySelectorAll('[data-pt-package-assignment]')", start);
const membershipCode = source.slice(start, end);

function control(value = '') {
    return {
        value, listeners: {},
        addEventListener(event, callback) { (this.listeners[event] ??= []).push(callback); },
        dispatch(event) { for (const callback of this.listeners[event] ?? []) callback(); },
    };
}

for (const fee of [0, 60]) {
    test(`membership summary tracks package and amount changes with fee ${fee}`, () => {
        const amount = control();
        const membershipTotal = { textContent: 'RM 0.00' };
        const total = { textContent: 'RM 0.00' };
        const summary = {
            dataset: { registrationFee: String(fee) },
            querySelector: selector => selector === '[data-registration-membership-total]' ? membershipTotal : total,
        };
        const form = { querySelector: selector => ({
            '[data-membership-amount]': amount,
            '[data-registration-pos]': summary,
        })[selector] ?? null };
        const select = Object.assign(control(), {
            options: [{ dataset: { price: '109' } }, { dataset: { price: '169' } }],
            selectedIndex: 0, dataset: {}, closest: () => form,
        });
        vm.runInNewContext(membershipCode, { document: { querySelectorAll: () => [select] } });
        assert.equal(amount.value, '109.00');
        assert.equal(membershipTotal.textContent, 'RM 109.00');
        assert.equal(total.textContent, `RM ${(109 + fee).toFixed(2)}`);
        select.selectedIndex = 1;
        select.dispatch('change');
        assert.equal(membershipTotal.textContent, 'RM 169.00');
        assert.equal(total.textContent, `RM ${(169 + fee).toFixed(2)}`);
        amount.value = '120.50';
        amount.dispatch('input');
        assert.equal(total.textContent, `RM ${(120.50 + fee).toFixed(2)}`);
        amount.value = '135';
        amount.dispatch('change');
        assert.equal(membershipTotal.textContent, 'RM 135.00');
        assert.equal(total.textContent, `RM ${(135 + fee).toFixed(2)}`);
    });
}

test('PT assignment summary updates when package or price changes', () => {
    const ptStart = source.indexOf("document.querySelectorAll('[data-pt-package-assignment]')");
    const ptEnd = source.indexOf("document.querySelectorAll('[data-filterable-combobox]')", ptStart);
    const price = control();
    const totals = [{ textContent: '' }, { textContent: '' }];
    const select = Object.assign(control(), {
        options: [{ dataset: { price: '500' } }, { dataset: { price: '800' } }], selectedIndex: 0,
    });
    const form = {
        querySelector: selector => selector === '[data-pt-package-select]' ? select : price,
        querySelectorAll: () => totals,
    };
    vm.runInNewContext(source.slice(ptStart, ptEnd), { document: { querySelectorAll: () => [form] } });
    assert.equal(totals[0].textContent, 'RM 500.00');
    select.selectedIndex = 1;
    select.dispatch('change');
    assert.equal(totals[1].textContent, 'RM 800.00');
    price.value = '450';
    price.dispatch('input');
    assert.equal(totals[0].textContent, 'RM 450.00');
    assert.equal(totals[1].textContent, 'RM 450.00');
});

test('locked PT checkout preserves PT sale type and selected package', () => {
    const start = source.indexOf('    const syncSaleTypeAvailability = () => {');
    const end = source.indexOf('    const syncAvailableProducts = () => {', start);
    const pt = { value: 'pt_session', checked: true, disabled: true };
    const product = { value: 'product_sale', checked: false, disabled: true };
    vm.runInNewContext(source.slice(start, end) + '\nsyncSaleTypeAvailability();', {
        form: { querySelector: () => ({ value: '1' }) },
        saleTypeInputs: [product, pt],
        hasSelectedMember: () => true,
    });
    assert.equal(pt.checked, true);
    assert.equal(product.checked, false);
});
