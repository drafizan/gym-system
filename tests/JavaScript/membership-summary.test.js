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
