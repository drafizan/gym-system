document.querySelectorAll('[data-menu-trigger]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const group = trigger.closest('[data-menu-group]');

        if (!group) {
            return;
        }

        if (document.body.classList.contains('sidebar-collapsed')) {
            document.body.classList.remove('sidebar-collapsed');
            document.querySelectorAll('[data-menu-group].is-open').forEach((openGroup) => {
                if (openGroup !== group) {
                    openGroup.classList.remove('is-open');
                }
            });
            group.classList.add('is-open');

            return;
        }

        group.classList.toggle('is-open');
    });
});

document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
    });
});

document.querySelectorAll('[data-sidebar-collapse]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
    });
});

const closeModal = (modal) => {
    modal?.classList.remove('is-open');
    modal?.setAttribute('aria-hidden', 'true');
};

document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const modal = document.querySelector(`[data-modal="${trigger.dataset.modalOpen}"]`);

        modal?.classList.add('is-open');
        modal?.setAttribute('aria-hidden', 'false');
        modal?.querySelector('[data-modal-close]')?.focus();
    });
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-modal-close]')) {
            closeModal(modal);
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('[data-modal].is-open').forEach(closeModal);
});

document.querySelectorAll('[data-sales-tabs]').forEach((tabs) => {
    const card = tabs.closest('.sales-details-card');
    const rows = Array.from(card?.querySelectorAll('[data-sales-category]') ?? []);
    const emptyRow = card?.querySelector('.sales-filter-empty');
    const pagination = card?.querySelector('[data-sales-pagination]');
    const paginationStatus = card?.querySelector('[data-sales-pagination-status]');
    const paginationButtons = card?.querySelector('[data-sales-pagination-buttons]');
    const pageSize = Number.parseInt(pagination?.dataset.pageSize || '8', 10);
    let activeFilter = 'all';
    let currentPage = 1;

    const visibleRowsForFilter = () => rows.filter((row) => activeFilter === 'all' || row.dataset.salesCategory === activeFilter);

    const renderPagination = (filteredRows) => {
        if (!pagination || !paginationButtons || !paginationStatus) {
            return;
        }

        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), pageCount);
        const start = filteredRows.length === 0 ? 0 : (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, filteredRows.length);

        paginationStatus.textContent = `Showing ${start} to ${end} of ${filteredRows.length} entries`;
        paginationButtons.innerHTML = '';

        const addButton = (label, targetPage, options = {}) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = Boolean(options.disabled);
            button.classList.toggle('active', Boolean(options.active));
            button.setAttribute('aria-label', options.ariaLabel || label);
            button.addEventListener('click', () => {
                if (button.disabled) {
                    return;
                }

                currentPage = targetPage;
                applySalesTableState();
            });
            paginationButtons.appendChild(button);
        };

        addButton('Prev', currentPage - 1, {
            disabled: currentPage === 1,
            ariaLabel: 'Previous page',
        });

        Array.from({ length: pageCount }, (_, index) => index + 1).forEach((page) => {
            addButton(String(page), page, {
                active: page === currentPage,
                ariaLabel: `Page ${page}`,
            });
        });

        addButton('Next', currentPage + 1, {
            disabled: currentPage === pageCount,
            ariaLabel: 'Next page',
        });
    };

    function applySalesTableState() {
        const filteredRows = visibleRowsForFilter();
        const startIndex = (currentPage - 1) * pageSize;
        const visiblePageRows = new Set(filteredRows.slice(startIndex, startIndex + pageSize));

        rows.forEach((row) => {
            row.hidden = !visiblePageRows.has(row);
        });

        if (emptyRow) {
            emptyRow.hidden = filteredRows.length > 0;
        }

        renderPagination(filteredRows);
    }

    tabs.querySelectorAll('[data-sales-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.salesFilter || 'all';
            currentPage = 1;

            tabs.querySelectorAll('[data-sales-filter]').forEach((tab) => {
                tab.classList.toggle('active', tab === button);
            });

            applySalesTableState();
        });
    });

    applySalesTableState();
});

document.querySelectorAll('[data-camera-preview]').forEach((preview) => {
    const panel = preview.closest('.camera-panel');
    const canvas = panel?.querySelector('[data-camera-canvas]');
    const input = panel?.querySelector('[data-camera-input]');
    const photoPreview = panel?.querySelector('[data-photo-preview]');
    const photoUpload = panel?.querySelector('[data-photo-upload]');
    const status = panel?.querySelector('[data-camera-status]');
    const startButton = panel?.querySelector('[data-camera-start]');
    const captureButton = panel?.querySelector('[data-camera-capture]');
    let stream = null;

    const stopCameraStream = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        preview.srcObject = null;
    };

    const requestCameraStream = () => {
        if (navigator.mediaDevices?.getUserMedia) {
            return navigator.mediaDevices.getUserMedia({ video: true });
        }

        const legacyGetUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia;

        if (!legacyGetUserMedia) {
            return Promise.reject(new Error('camera_unavailable'));
        }

        return new Promise((resolve, reject) => {
            legacyGetUserMedia.call(navigator, { video: true }, resolve, reject);
        });
    };

    const markCameraUnavailable = (message) => {
        panel?.classList.add('camera-unavailable');
        startButton?.setAttribute('disabled', 'disabled');
        captureButton?.setAttribute('disabled', 'disabled');

        if (status) {
            status.textContent = message;
        }
    };

    if (!navigator.mediaDevices?.getUserMedia && !navigator.getUserMedia && !navigator.webkitGetUserMedia && !navigator.mozGetUserMedia) {
        markCameraUnavailable('Camera access is blocked by this browser address.');
        return;
    }

    startButton?.addEventListener('click', async () => {
        try {
            stopCameraStream();
            panel?.classList.remove('has-photo');

            if (photoPreview) {
                photoPreview.hidden = true;
                photoPreview.removeAttribute('src');
            }

            preview.hidden = false;
            stream = await requestCameraStream();
            preview.srcObject = stream;
            await preview.play();

            if (status) {
                status.textContent = 'Camera is ready.';
            }
        } catch {
            if (status) {
                status.textContent = 'Camera permission was blocked by the browser.';
            }
        }
    });

    captureButton?.addEventListener('click', () => {
        if (!stream || !canvas || !input) {
            if (status) {
                status.textContent = 'Start the camera before capturing.';
            }

            return;
        }

        canvas.width = 640;
        canvas.height = 480;
        canvas.getContext('2d').drawImage(preview, 0, 0);
        input.value = canvas.toDataURL('image/jpeg', 0.86);
        stopCameraStream();

        if (photoPreview) {
            photoPreview.src = input.value;
            photoPreview.hidden = false;
        }

        preview.hidden = true;
        panel?.classList.add('has-photo');

        if (status) {
            status.textContent = 'Photo captured. Camera stopped.';
        }
    });

    photoUpload?.addEventListener('change', () => {
        const file = photoUpload.files?.[0];

        if (!file || !photoPreview) {
            return;
        }

        stopCameraStream();
        if (input) {
            input.value = '';
        }

        photoPreview.src = URL.createObjectURL(file);
        photoPreview.hidden = false;
        preview.hidden = true;
        panel?.classList.add('has-photo');

        if (status) {
            status.textContent = 'Photo selected for upload.';
        }
    });
});

document.querySelectorAll('[data-backup-os-form]').forEach((form) => {
    const osSelect = form.querySelector('[data-backup-os-select]');
    const pgDumpInput = form.querySelector('[data-backup-pg-dump]');
    const psqlInput = form.querySelector('[data-backup-psql]');
    const presets = {
        windows: {
            pgDump: 'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
            psql: 'C:\\Program Files\\PostgreSQL\\16\\bin\\psql.exe',
        },
        linux: {
            pgDump: 'pg_dump',
            psql: 'psql',
        },
        macos: {
            pgDump: '/Applications/Postgres.app/Contents/Versions/latest/bin/pg_dump',
            psql: '/Applications/Postgres.app/Contents/Versions/latest/bin/psql',
        },
    };

    osSelect?.addEventListener('change', () => {
        const preset = presets[osSelect.value];

        if (!preset || !pgDumpInput || !psqlInput) {
            return;
        }

        pgDumpInput.value = preset.pgDump;
        psqlInput.value = preset.psql;
    });
});

document.querySelectorAll('[data-folder-picker-open]').forEach((button) => {
    const modal = document.querySelector('[data-modal="folder-picker"]');
    const targetInput = document.querySelector('[data-folder-picker-target]');
    const currentLabel = modal?.querySelector('[data-folder-picker-current]');
    const statusLabel = modal?.querySelector('[data-folder-picker-status]');
    const rootsContainer = modal?.querySelector('[data-folder-picker-roots]');
    const listContainer = modal?.querySelector('[data-folder-picker-list]');
    const selectButton = modal?.querySelector('[data-folder-picker-select]');
    const pickerUrl = button.dataset.folderPickerUrl;
    let currentPath = targetInput?.value || '';

    const renderButton = (label, path, className = 'btn btn-light') => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = className;
        item.textContent = label;
        item.addEventListener('click', () => loadFolder(path));

        return item;
    };

    const loadFolder = async (path = '') => {
        if (!pickerUrl || !modal || !currentLabel || !statusLabel || !rootsContainer || !listContainer) {
            return;
        }

        currentLabel.textContent = 'Loading...';
        statusLabel.textContent = '';
        rootsContainer.innerHTML = '';
        listContainer.innerHTML = '';

        const url = new URL(pickerUrl, window.location.origin);

        if (path) {
            url.searchParams.set('path', path);
        }

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
            },
        });
        const payload = await response.json();

        currentPath = payload.current;
        currentLabel.textContent = payload.current;
        statusLabel.textContent = payload.writable ? 'Writable' : 'Not writable';
        statusLabel.className = payload.writable ? 'text-success' : 'text-danger';

        payload.roots?.forEach((root) => {
            rootsContainer.appendChild(renderButton(root.label, root.path));
        });

        if (payload.parent) {
            listContainer.appendChild(renderButton('.. Parent folder', payload.parent, 'folder-picker-row'));
        }

        if (!payload.directories?.length) {
            const empty = document.createElement('p');
            empty.className = 'folder-picker-empty';
            empty.textContent = 'No readable folders found here.';
            listContainer.appendChild(empty);
            return;
        }

        payload.directories.forEach((directory) => {
            const row = renderButton(directory.name, directory.path, 'folder-picker-row');
            const badge = document.createElement('span');
            badge.textContent = directory.writable ? 'Writable' : 'Read only';
            badge.className = directory.writable ? 'text-success' : 'text-muted';
            row.appendChild(badge);
            listContainer.appendChild(row);
        });
    };

    button.addEventListener('click', () => {
        modal?.classList.add('is-open');
        modal?.setAttribute('aria-hidden', 'false');
        loadFolder(targetInput?.value || '');
    });

    selectButton?.addEventListener('click', () => {
        if (targetInput && currentPath) {
            targetInput.value = currentPath;
        }

        closeModal(modal);
    });
});

document.querySelectorAll('[data-membership-package-select]').forEach((select) => {
    const form = select.closest('form');
    const amountInput = form?.querySelector('[data-membership-amount]');
    const summary = form?.querySelector('[data-registration-pos]');
    const updateRegistrationSummary = () => {
        if (!summary) return;
        const amount = Number(amountInput?.value || 0);
        summary.querySelector('[data-registration-membership-total]').textContent = `RM ${amount.toFixed(2)}`;
        summary.querySelector('[data-registration-total]').textContent = `RM ${(amount + Number(summary.dataset.registrationFee)).toFixed(2)}`;
    };
    amountInput?.addEventListener('input', updateRegistrationSummary);
    select.addEventListener('change', () => queueMicrotask(updateRegistrationSummary));
    queueMicrotask(updateRegistrationSummary);
    const startDateInput = form?.querySelector('[data-membership-start-date]');
    const endDateInput = form?.querySelector('[data-membership-end-date]');

    const applySelectedPrice = (force = false) => {
        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption?.dataset.price;

        if (!amountInput || !price) {
            return;
        }

        if (force || amountInput.value === '') {
            amountInput.value = Number(price).toFixed(2);
        }
    };

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const applySelectedEndDate = (force = false) => {
        const selectedOption = select.options[select.selectedIndex];
        const durationDays = Number(selectedOption?.dataset.durationDays || 0);

        if (!startDateInput || !endDateInput || !startDateInput.value || !durationDays) {
            return;
        }

        if (!force && endDateInput.value !== '') {
            return;
        }

        const endDate = new Date(`${startDateInput.value}T00:00:00`);
        endDate.setDate(endDate.getDate() + durationDays - 1);
        endDateInput.value = formatDate(endDate);
    };

    applySelectedPrice();
    applySelectedEndDate();

    select.addEventListener('change', () => {
        applySelectedPrice(true);
        applySelectedEndDate(true);
    });

    startDateInput?.addEventListener('change', () => {
        applySelectedEndDate(true);
    });
});

document.querySelectorAll('[data-pt-package-assignment]').forEach((form) => {
    const packageSelect = form.querySelector('[data-pt-package-select]');
    const priceInput = form.querySelector('[data-pt-package-price]');

    const applyPackagePrice = (force = false) => {
        const price = packageSelect?.options?.[packageSelect.selectedIndex]?.dataset.price;

        if (!priceInput || !price) {
            return;
        }

        if (force || priceInput.value === '') {
            priceInput.value = Number(price).toFixed(2);
        }
    };

    applyPackagePrice();
    packageSelect?.addEventListener('change', () => applyPackagePrice(true));
});

document.querySelectorAll('[data-filterable-combobox]').forEach((combobox) => {
    const nativeSelect = combobox.querySelector('[data-combobox-native]');
    const trigger = combobox.querySelector('[data-combobox-trigger]');
    const valueLabel = combobox.querySelector('[data-combobox-value]');
    const panel = combobox.querySelector('[data-combobox-panel]');
    const searchInput = combobox.querySelector('[data-combobox-search]');
    const optionButtons = Array.from(combobox.querySelectorAll('[data-combobox-option]'));
    const emptyLabel = combobox.querySelector('[data-combobox-empty]');

    if (!nativeSelect || !trigger || !panel || !valueLabel) {
        return;
    }

    const closeCombobox = () => {
        panel.hidden = true;
        combobox.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    };

    const openCombobox = () => {
        panel.hidden = false;
        combobox.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        searchInput.value = '';
        optionButtons.forEach((option) => {
            option.hidden = option.dataset.disabled === 'true';
        });
        if (emptyLabel) {
            emptyLabel.hidden = true;
        }
        window.requestAnimationFrame(() => searchInput?.focus());
    };

    const syncSelectedOption = () => {
        optionButtons.forEach((option) => {
            const isSelected = option.dataset.value === nativeSelect.value;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    };

    trigger.addEventListener('click', () => {
        if (panel.hidden) {
            openCombobox();
        } else {
            closeCombobox();
        }
    });

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        optionButtons.forEach((option) => {
            const isDisabled = option.dataset.disabled === 'true';
            const matches = !isDisabled && (query === '' || option.dataset.filter?.includes(query));
            option.hidden = !matches;

            if (matches) {
                visibleCount += 1;
            }
        });

        if (emptyLabel) {
            emptyLabel.hidden = visibleCount !== 0;
        }
    });

    optionButtons.forEach((option) => {
        option.addEventListener('click', () => {
            if (option.dataset.disabled === 'true') {
                return;
            }

            nativeSelect.value = option.dataset.value ?? '';
            valueLabel.textContent = option.dataset.label || option.textContent.trim();
            nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            syncSelectedOption();
            closeCombobox();
            trigger.focus();
        });
    });

    document.addEventListener('click', (event) => {
        if (!combobox.contains(event.target)) {
            closeCombobox();
        }
    });

    combobox.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeCombobox();
            trigger.focus();
        }
    });

    syncSelectedOption();
});

document.querySelectorAll('[data-pt-schedule-form]').forEach((form) => {
    const picker = form.querySelector('[data-pt-member-picker]');
    const addButton = form.querySelector('[data-pt-member-add]');
    const list = form.querySelector('[data-pt-member-list]');
    const emptyLabel = form.querySelector('[data-pt-member-empty]');
    const combobox = picker?.closest('[data-filterable-combobox]');
    const valueLabel = combobox?.querySelector('[data-combobox-value]');
    const optionButtons = Array.from(combobox?.querySelectorAll('[data-combobox-option]') ?? []);

    if (!picker || !addButton || !list) {
        return;
    }

    const selectedValues = () => Array.from(list.querySelectorAll('[data-pt-member-item]'))
        .map((item) => item.dataset.value)
        .filter(Boolean);

    const resetPicker = () => {
        picker.value = '';

        if (valueLabel) {
            valueLabel.textContent = 'Select eligible member';
        }

        optionButtons.forEach((button) => {
            button.classList.toggle('is-selected', button.dataset.value === '');
            button.setAttribute('aria-selected', button.dataset.value === '' ? 'true' : 'false');
        });
    };

    const syncAvailability = () => {
        const selected = selectedValues();

        Array.from(picker.options).forEach((option) => {
            if (option.value === '') {
                option.disabled = false;
                option.hidden = false;
                return;
            }

            const isSelected = selected.includes(option.value);
            option.disabled = isSelected;
            option.hidden = isSelected;
        });

        optionButtons.forEach((button) => {
            if (button.dataset.value === '') {
                button.dataset.disabled = 'false';
                button.hidden = false;
                return;
            }

            const isSelected = selected.includes(button.dataset.value);
            button.dataset.disabled = isSelected ? 'true' : 'false';
            button.hidden = isSelected;
        });

        if (emptyLabel) {
            emptyLabel.hidden = selected.length > 0;
        }
    };

    const createSelectedRow = (value, label, meta) => {
        const row = document.createElement('div');
        row.className = 'selected-member-row';
        row.dataset.ptMemberItem = '';
        row.dataset.value = value;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'pt_member_package_ids[]';
        input.value = value;

        const text = document.createElement('div');
        const name = document.createElement('strong');
        const detail = document.createElement('small');

        name.textContent = label;
        detail.textContent = meta;
        text.append(name, detail);

        const removeButton = document.createElement('button');
        removeButton.className = 'icon-action danger';
        removeButton.type = 'button';
        removeButton.dataset.ptMemberRemove = '';
        removeButton.setAttribute('aria-label', 'Remove member');
        removeButton.title = 'Remove member';
        removeButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"></path></svg>';

        row.append(input, text, removeButton);

        return row;
    };

    addButton.addEventListener('click', () => {
        const value = picker.value;

        if (!value || selectedValues().includes(value)) {
            return;
        }

        const option = picker.options[picker.selectedIndex];
        const label = option?.dataset.label || option?.textContent?.trim() || 'Selected member';
        const meta = option?.dataset.meta || `${option?.dataset.remaining || 0} sessions left`;

        list.appendChild(createSelectedRow(value, label, meta));
        resetPicker();
        syncAvailability();
    });

    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-pt-member-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-pt-member-item]')?.remove();
        syncAvailability();
    });

    syncAvailability();
});

document.querySelectorAll('[data-pos-form]').forEach((form) => {
    const saleTypeInputs = Array.from(form.querySelectorAll('[data-pos-sale-type]'));
    const productRow = form.querySelector('[data-pos-product-row]');
    const packageRow = form.querySelector('[data-pos-package-row]');
    const productList = form.querySelector('[data-pos-product-list]');
    const productTemplate = form.querySelector('[data-pos-product-template]');
    const addProductButton = form.querySelector('[data-pos-add-product]');
    const packageSelect = form.querySelector('[data-pos-package-select]');
    const memberSelect = form.querySelector('select[name="member_id"]');
    const discountInput = form.querySelector('[data-pos-discount]');
    const receiptPreview = form.querySelector('[data-pos-receipt-preview]');
    const subtotalLabel = form.querySelector('[data-pos-subtotal]');
    const discountLabel = form.querySelector('[data-pos-discount-label]');
    const totalLabel = form.querySelector('[data-pos-total]');
    const currency = (value) => `RM ${Number(value || 0).toFixed(2)}`;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));
    let productIndex = form.querySelectorAll('[data-pos-product-line]').length;

    const selectedSaleType = () => saleTypeInputs.find((input) => input.checked)?.value || 'product_sale';
    const isProductMode = (saleType) => ['product_sale', 'pt_session'].includes(saleType);
    const isPtSale = (saleType) => saleType === 'pt_session';
    const hasSelectedMember = () => Boolean(memberSelect?.value);
    const selectedOption = (select) => select?.options?.[select.selectedIndex];
    const productLines = () => Array.from(form.querySelectorAll('[data-pos-product-line]'));
    const syncSaleTypeAvailability = () => {
        const ptSaleInput = saleTypeInputs.find((input) => input.value === 'pt_session');

        if (!ptSaleInput) {
            return;
        }

        const disabled = !hasSelectedMember() || !!form.querySelector('[data-pos-registration-fee]');
        ptSaleInput.disabled = disabled;
        ptSaleInput.closest('.pos-type-option')?.classList.toggle('is-disabled', disabled);

        if (disabled && ptSaleInput.checked) {
            const productSaleInput = saleTypeInputs.find((input) => input.value === 'product_sale') || saleTypeInputs[0];
            productSaleInput.checked = true;
        }
    };
    const syncAvailableProducts = () => {
        const saleType = selectedSaleType();
        const productMode = isProductMode(saleType);
        const ptMode = isPtSale(saleType);
        const selectedValues = productLines()
            .map((line) => line.querySelector('[data-pos-product-select]')?.value)
            .filter(Boolean);

        productLines().forEach((line) => {
            const select = line.querySelector('[data-pos-product-select]');

            if (!select) {
                return;
            }

            Array.from(select.options).forEach((option) => {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const isPtProduct = option.dataset.isPt === '1';
                const invalidForSaleType = !productMode || (ptMode ? !isPtProduct : isPtProduct);
                const isSelectedElsewhere = selectedValues.includes(option.value) && select.value !== option.value;
                option.hidden = invalidForSaleType || isSelectedElsewhere;
                option.disabled = invalidForSaleType || isSelectedElsewhere;

                if (select.value === option.value && invalidForSaleType) {
                    select.value = '';
                }
            });
        });
    };
    const productItems = () => productLines()
        .map((line) => {
            const select = line.querySelector('[data-pos-product-select]');
            const quantityInput = line.querySelector('[data-pos-quantity]');
            const discountInput = line.querySelector('[data-pos-line-discount]');
            const option = selectedOption(select);
            const quantity = Math.max(1, Number(quantityInput?.value || 1));
            const unitPrice = Number(option?.dataset.price || 0);
            const lineSubtotal = unitPrice * quantity;
            const discount = Math.min(Math.max(0, Number(discountInput?.value || 0)), lineSubtotal);
            const cappedDiscount = Math.min(discount, unitPrice);

            if (discountInput) {
                discountInput.max = String(unitPrice);

                if (Number(discountInput.value || 0) > unitPrice) {
                    discountInput.value = String(unitPrice);
                }
            }

            return {
                line,
                select,
                quantityInput,
                label: option?.dataset.label || 'No product',
                quantity,
                unitPrice,
                discount: cappedDiscount,
                subtotal: lineSubtotal,
                hasItem: unitPrice > 0 && select?.value,
            };
        })
        .filter((item) => item.hasItem);

    const selectedItems = () => {
        const saleType = selectedSaleType();
        const isProductSale = isProductMode(saleType);

        if (isProductSale) {
            return {
                saleType,
                isProductSale,
                items: productItems(),
            };
        }

        const option = selectedOption(packageSelect);
        const unitPrice = Number(form.querySelector('[data-pos-membership-amount]')?.value ?? option?.dataset.price ?? 0);
        const label = option?.dataset.label || 'No membership package';

        return {
            saleType,
            isProductSale,
            items: unitPrice > 0 && packageSelect?.value ? [{
                label,
                quantity: 1,
                unitPrice,
                subtotal: unitPrice,
                hasItem: true,
            }] : [],
        };
    };

    const syncItemMode = () => {
        const saleType = selectedSaleType();
        const isProductSale = isProductMode(saleType);

        if (productRow) {
            productRow.hidden = !isProductSale;
        }

        if (packageRow) {
            packageRow.hidden = isProductSale;
        }

        productLines().forEach((line) => {
            line.querySelectorAll('select, input, button').forEach((control) => {
                if (control.matches('[data-pos-remove-product]')) {
                    control.disabled = !isProductSale || productLines().length <= 1;
                    return;
                }

                control.disabled = !isProductSale;
            });
        });

        addProductButton?.toggleAttribute('disabled', !isProductSale);

        if (packageSelect) {
            packageSelect.disabled = isProductSale || !!form.querySelector('[data-pos-registration-fee]');
        }
    };

    const updateSummary = () => {
        syncSaleTypeAvailability();
        syncAvailableProducts();
        syncItemMode();
        const selected = selectedItems();
        const fee = form.querySelector('[data-pos-registration-fee]');
        if (fee) {
            const amount = Number(fee.value);
            selected.items.push({ label: 'Registration Fee', quantity: 1, unitPrice: amount, subtotal: amount, discount: 0 });
        }
        const subtotal = selected.items.reduce((sum, item) => sum + item.subtotal, 0);
        const lineDiscount = selected.isProductSale
            ? selected.items.reduce((sum, item) => sum + item.discount, 0)
            : 0;
        const maxCartDiscount = Math.max(0, subtotal - lineDiscount);
        let cartDiscount = Math.max(0, Number(discountInput?.value || 0));

        if (discountInput) {
            discountInput.max = String(maxCartDiscount);

            if (cartDiscount > maxCartDiscount) {
                cartDiscount = maxCartDiscount;
                discountInput.value = String(maxCartDiscount);
            }
        }

        const appliedDiscount = Math.min(lineDiscount + cartDiscount, subtotal);
        const total = Math.max(0, subtotal - appliedDiscount);

        if (receiptPreview) {
            if (selected.items.length === 0) {
                receiptPreview.innerHTML = `
                        <div class="pos-receipt-line">
                            <div>
                                <strong>No item selected</strong>
                                <span>Add an item to continue</span>
                            </div>
                            <b>${currency(0)}</b>
                        </div>
                `;
            } else {
                receiptPreview.innerHTML = selected.items.map((item) => `
                    <div class="pos-receipt-line">
                        <div>
                            <strong>${escapeHtml(item.label)}</strong>
                            <span>${selected.isProductSale ? `${item.quantity} x ` : ''}${currency(item.unitPrice)}${item.discount > 0 ? `, discount ${currency(item.discount)}` : ''}</span>
                        </div>
                        <b>${currency(item.subtotal)}</b>
                    </div>
                `).join('');
            }

        }

        if (subtotalLabel) {
            subtotalLabel.textContent = currency(subtotal);
        }

        if (discountLabel) {
            discountLabel.textContent = currency(appliedDiscount);
        }

        if (totalLabel) {
            totalLabel.textContent = currency(total);
        }
    };

    const bindProductLine = (line) => {
        line.querySelector('[data-pos-product-select]')?.addEventListener('change', updateSummary);
        line.querySelector('[data-pos-quantity]')?.addEventListener('input', updateSummary);
        line.querySelector('[data-pos-line-discount]')?.addEventListener('input', updateSummary);
        line.querySelector('[data-pos-qty-minus]')?.addEventListener('click', () => {
            const quantityInput = line.querySelector('[data-pos-quantity]');
            quantityInput.value = Math.max(1, Number(quantityInput.value || 1) - 1);
            updateSummary();
        });
        line.querySelector('[data-pos-qty-plus]')?.addEventListener('click', () => {
            const quantityInput = line.querySelector('[data-pos-quantity]');
            quantityInput.value = Math.min(999, Number(quantityInput.value || 1) + 1);
            updateSummary();
        });
        line.querySelector('[data-pos-remove-product]')?.addEventListener('click', () => {
            if (productLines().length <= 1) {
                return;
            }

            line.remove();
            updateSummary();
        });
    };

    const addProductLine = () => {
        if (!productTemplate || !addProductButton) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = productTemplate.innerHTML.replaceAll('__INDEX__', String(productIndex));
        productIndex += 1;
        const line = wrapper.firstElementChild;
        addProductButton.before(line);
        bindProductLine(line);
        updateSummary();
    };

    saleTypeInputs.forEach((input) => input.addEventListener('change', updateSummary));
    memberSelect?.addEventListener('change', updateSummary);
    packageSelect?.addEventListener('change', updateSummary);
    discountInput?.addEventListener('input', updateSummary);
    addProductButton?.addEventListener('click', addProductLine);
    productLines().forEach(bindProductLine);

    form.addEventListener('reset', () => {
        window.requestAnimationFrame(updateSummary);
    });

    updateSummary();
});
