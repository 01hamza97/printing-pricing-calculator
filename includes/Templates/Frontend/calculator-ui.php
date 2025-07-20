<!-- templates/frontend/calculator-ui.php -->
<div class="w-5/6 mx-auto my-8 font-sans">
    <div class="mb-6">
        <img src="<?php echo $product['image_url']; ?>" class="w-full rounded-lg object-cover max-h-44" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="col-span-2 bg-gray-50 rounded-lg p-6 shadow">
            <h4 class="text-lg font-semibold mb-4">Parameters</h4>
            <?php foreach ($parameters as $p): ?>
                <div class="mb-6">
                    <label for="param_<?php echo $p['id']; ?>" class="font-semibold text-base block mb-1">
                        <?php echo esc_html($p['front_name']); ?>
                    </label>
                    <select
                        id="param_<?php echo $p['id']; ?>"
                        name="parameters[<?php echo $p['id']; ?>]"
                        class="border rounded px-3 py-1 w-[96%]"
                        data-param-id="<?php echo $p['id']; ?>"
                        data-param-title="<?php echo esc_attr($p['title']); ?>"
                    >
                        <option value="" data-cost="0">Select an option</option>
                        <?php foreach ($p['options'] as $o): ?>
                            <?php
                            $cost = !empty($o['override_price'])
                                ? $o['override_price']
                                : (!empty($o['meta_value']['cost']) ? $o['meta_value']['cost'] : 0);
                            ?>
                            <option value="<?php echo esc_attr($o['meta_value']['option'] ?? ''); ?>"
                                    data-cost="<?php echo esc_attr($cost); ?>">
                                <?php echo esc_html($o['meta_value']['option'] ?? ''); ?>
                                <?php if ($cost > 0): ?>
                                    (<?php echo number_format((float)$cost, 2); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="inline-block align-middle relative group ml-2 cursor-pointer ">
                        <!-- Heroicons Exclamation Circle -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#FEF3C7"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                        </svg>
                                <span class="absolute left-7 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-[15px] rounded px-2 py-1 opacity-0 group-hover:opacity-100 transition pointer-events-none z-50 shadow-lg min-w-[200px] max-w-xs whitespace-normal break-words">
                            <?php echo esc_html($p['content']); ?>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
            <div id="ppc-file-upload-wrapper" class="bg-gray-200 button px-6 py-6 rounded-lg w-full">
                <input type="file" id="ppc-file-upload" />
            </div>
            <div class="mb-6 w-full flex">
                <!-- Radio group 1: Show prices with/without tax -->
                <div class="mb-3 flex gap-6 items-center w-1/2">
                    <label class="flex items-center gap-1">
                        <input type="radio" name="show_tax" id="ppc-show-tax-yes" value="with" checked class="accent-blue-600" />
                        With Tax
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="show_tax" id="ppc-show-tax-no" value="without" class="accent-blue-600" />
                        Without Tax
                    </label>
                </div>
                <!-- Radio group 2: Show prices per piece or total -->
                <div class="mb-3 flex gap-6 items-center w-1/2">
                    <label class="flex items-center gap-1">
                        <input type="radio" name="price_unit" id="ppc-unit-piece" value="piece" checked class="accent-blue-600" />
                        Per Piece
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="price_unit" id="ppc-unit-total" value="total" class="accent-blue-600" />
                        Total
                    </label>
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-4">
            <div class="bg-blue-50 text-blue-900 rounded-lg px-6 py-5 text-center text-xl font-bold shadow">
                <div class="mb-3">
                    <label for="ppc-qty" class="font-medium block mb-1">Quantity</label>
                    <input
                        type="number"
                        id="ppc-qty"
                        class="border rounded px-3 py-1 w-24 text-center"
                    />
                    <small class="text-gray-500">Minimum order: <?php echo (int)$min_order_qty; ?></small>
                    <small class="text-red-600 hidden block" id="ppc-qty-error"></small>
                </div>
                <div class="mb-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="ppc-express" class="accent-green-700 mr-2" />
                        <span class="font-medium">
                            Express Delivery (
                            <?php
                                if ($express_delivery['type'] === 'percent') {
                                    echo '+' . floatval($express_delivery['value']) . '%';
                                } else {
                                    echo '+' . number_format((float)$express_delivery['value'], 2);
                                }
                            ?>
                            )
                        </span>
                    </label>
                </div>
                <div class="mb-3">
                    <label class="inline-flex items-center">
                        <input
                            type="checkbox"
                            class="accent-green-700 mr-2"
                            id="ppc-file-check"
                            <?php if (!empty($file_check_required)) echo 'checked disabled'; ?>
                        >
                        <span class="font-medium">
                            File Check Service (
                            <?php echo number_format((float)$file_check_price, 2); ?>
                            )
                        </span>
                    </label>
                </div>
                <div class="mb-3">
                    Price
                    <span id="ppc-price-type-label"></span>
                </div>
                <div>
                    <table class="alignleft text-base text-left w-full" id="ppc-summary-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="ppc-selected-params-table">
                            <?php foreach ($parameters as $p): ?>
                                <tr class="hidden" data-param-id="<?php echo $p['id']; ?>">
                                    <td><?php echo esc_html($p['title']); ?>(<span class="ppc-selected-option" id="ppc-selected-option-<?php echo $p['id']; ?>">—</span>)</td>
                                    <td>1</td>
                                    <td>
                                        <span class="ppc-option-cost text-gray-500" id="ppc-option-cost-<?php echo $p['id']; ?>">0.00</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="2">Discount</td>
                                <td id="ppc-discount">0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-bold">
                                <td colspan="2">Total W/O Tax</td>
                                <td id="ppc-no-tax-total">0.00</td>
                            </tr>
                            <tr class="font-bold">
                                <td colspan="2">Tax(<?php echo $tax ?>%) </td>
                                <td id="ppc-tax-amount"></td>
                            </tr>
                            <tr class="font-bold">
                                <td colspan="2">Total</td>
                                <td id="ppc-grand-total">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="bg-gray-200 rounded-lg px-6 py-4 text-center cursor-pointer shadow" id="ppc-download-pdf">download calculation (pdf?)</div>
            <button id="ppc-add-to-cart" type="button" disabled
                class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-6 py-6 text-center font-bold text-lg cursor-pointer shadow transition"
            >
                Add to Cart
            </button>
        </div>
    </div>
</div>

<script>
window.ajaxurl = window.ajaxurl || "<?php echo admin_url('admin-ajax.php'); ?>";

document.addEventListener('DOMContentLoaded', function () {
    // Access all relevant settings from window.ppc_settings
    const settings = window.ppc_settings || {};
    const selects = document.querySelectorAll('select[name^="parameters"]');
    const qtyInput = document.getElementById('ppc-qty');
    const expressCheckbox = document.getElementById('ppc-express');
    const fileCheckBox = document.getElementById('ppc-file-check');
    const fileUploadWrapper = document.getElementById('ppc-file-upload-wrapper');
    const fileUpload = document.getElementById('ppc-file-upload');
    const addToCartBtn = document.getElementById('ppc-add-to-cart');
    const paramsTable = document.getElementById('ppc-selected-params-table');
    const qtyError = document.getElementById('ppc-qty-error');
    const basePrice = parseFloat('<?php echo (float)$product['base_price']; ?>');
    const paramIds = <?php echo json_encode(array_column($parameters, 'id')); ?>;
    const tax = settings.tax ? parseFloat(settings.tax) : 0;
    const expressValue = settings.express_delivery_value ? parseFloat(settings.express_delivery_value) : 0;
    const expressType = settings.express_delivery_type || 'percent';
    const minQty = settings.min_order_qty ? parseInt(settings.min_order_qty) : 100;
    const discountRules = (settings.product_discount_rules && settings.product_discount_rules.length)
        ? settings.product_discount_rules
        : (settings.global_discount_rules || []);
    const fileCheckPrice = settings.file_check_price ? parseFloat(settings.file_check_price) : 0;
    const fileCheckRequired = settings.file_check_required ? parseInt(settings.file_check_required) : 0;
    qtyInput.value = minQty;
    const showTaxRadios = document.getElementsByName('show_tax');
    const priceUnitRadios = document.getElementsByName('price_unit');

    // // Helper to check state and toggle file upload/add to cart
    function updateFileUploadAndCart() {
        // If file check is checked or required
        fileUploadWrapper.style.display = '';
        // If no file selected, disable Add to Cart
        if (!fileUpload.value) {
            addToCartBtn.disabled = true;
            addToCartBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            addToCartBtn.disabled = false;
            addToCartBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    if (fileUpload) {
        fileUpload.addEventListener('change', updateFileUploadAndCart);
    }

    function getDiscountPercent(qty, discountRules) {
        for (let i = 0; i < discountRules.length; i++) {
            if (qty >= parseInt(discountRules[i].qty)) return parseFloat(discountRules[i].percent);
        }
        return 0;
    }

    function getCheckedValue(radios) {
        for (let r of radios) if (r.checked) return r.value;
        return null;
    }

    // Main calculation, store core values for submission, but only set base values in DOM
    function updateSummary() {
        let qty = parseInt(qtyInput.value, 10);

        let paramSum = 0;
        let optionCosts = {};
        paramIds.forEach(paramId => {
            const sel = document.getElementById('param_' + paramId);
            const selectedOpt = sel.options[sel.selectedIndex];
            const optText = selectedOpt.text.replace(/\([^)]+\)/, '').trim();
            const optCost = parseFloat(selectedOpt.dataset.cost || 0);

            document.getElementById('ppc-selected-option-' + paramId).textContent =
                selectedOpt.value ? optText : '—';

            // Store actual per-item cost for this param (used later for view logic)
            optionCosts[paramId] = optCost;

            document.getElementById('ppc-option-cost-' + paramId).setAttribute('data-base', optCost);
            document.getElementById('ppc-option-cost-' + paramId).textContent =
                selectedOpt.value && optCost ? (optCost * qty).toFixed(2) : '0.00';

            if (selectedOpt.value && optCost) {
                paramSum += optCost;
            }
        });

        // Discount
        let discountPercent = getDiscountPercent(qty, discountRules);
        let discount = discountPercent / 100;

        const paramsTotal = paramSum * qty;
        const preDiscountTotal = basePrice + paramsTotal;
        const discountAmount = preDiscountTotal * discount;
        let finalTotal = preDiscountTotal - discountAmount;

        document.getElementById('ppc-discount').setAttribute('data-base', discountAmount);
        document.getElementById('ppc-discount').textContent = discountAmount.toFixed(2);

        // Remove old file check row if present
        let fileCheckRow = document.getElementById('ppc-file-check-row');
        if (fileCheckRow) fileCheckRow.remove();

        // File Check Service: insert if checked or required
        let fileCheckAmount = 0;
        if (fileCheckBox && fileCheckBox.checked) {
            fileCheckAmount = fileCheckPrice;
            finalTotal += fileCheckAmount;
            fileCheckRow = document.createElement('tr');
            fileCheckRow.id = 'ppc-file-check-row';
            fileCheckRow.innerHTML = `<td>File Check Service</td><td>1</td><td id="ppc-file-check-cost">${fileCheckAmount.toFixed(2)}</td>`;
            // Insert before Discount row
            const discountRow = document.getElementById('ppc-discount').parentElement;
            paramsTable.insertBefore(fileCheckRow, discountRow);
        }

        // Remove old express row if present
        let expressRow = document.getElementById('ppc-express-row');
        if (expressRow) expressRow.remove();

        // Express Delivery: insert or remove row
        let expressAmount = 0;
        if (expressCheckbox && expressCheckbox.checked) {
            if (expressType === 'percent') {
                expressAmount = finalTotal * (expressValue / 100);
            } else {
                expressAmount = expressValue;
            }
            finalTotal += expressAmount;
            expressRow = document.createElement('tr');
            expressRow.id = 'ppc-express-row';
            expressRow.innerHTML = `<td>Express Delivery (${expressType === 'percent' ? '+' + expressValue + '%' : '+' + expressValue})</td><td>1</td><td id="ppc-express-cost">${expressAmount.toFixed(2)}</td>`;
            // Insert before discount row (now after file check row)
            const discountRow = document.getElementById('ppc-discount').parentElement;
            paramsTable.insertBefore(expressRow, discountRow);
        }

        // Calculate tax
        document.getElementById('ppc-no-tax-total').setAttribute('data-base', finalTotal);
        document.getElementById('ppc-no-tax-total').textContent = finalTotal.toFixed(2);

        let taxAmount = 0;
        if (tax > 0) {
            taxAmount = finalTotal * (tax / 100);
        }
        document.getElementById('ppc-tax-amount').setAttribute('data-base', taxAmount);
        document.getElementById('ppc-tax-amount').textContent = taxAmount.toFixed(2);

        let grandTotal = finalTotal + taxAmount;
        document.getElementById('ppc-grand-total').setAttribute('data-base', grandTotal);
        document.getElementById('ppc-grand-total').textContent = grandTotal.toFixed(2);

        // Store for add to cart (always with tax)
        window.ppc_calc_data = {
            qty,
            discountAmount,
            taxAmount,
            total: grandTotal,
            totalWithoutTax: finalTotal
        };

        // Now update visible display for all relevant fields according to view radios
        updateViewDisplay(qty, taxAmount, grandTotal, finalTotal, discountAmount, optionCosts);

        // Optionally, update label
        if (document.getElementById('ppc-price-type-label')) {
            let text = '';
            if (getCheckedValue(priceUnitRadios) === 'piece') {
                text += 'Per Piece';
            } else {
                text += 'Total';
            }
            text += getCheckedValue(showTaxRadios) === 'with' ? ' (With Tax)' : ' (Without Tax)';
            document.getElementById('ppc-price-type-label').textContent = text;
        }
    }

    // Display logic: override visible values according to view radios (but not the underlying calculation)
    function updateViewDisplay(qty, taxAmount, grandTotal, finalTotal, discountAmount, optionCosts) {
        // Get view
        let showTax = getCheckedValue(showTaxRadios) || "with";
        let priceUnit = getCheckedValue(priceUnitRadios) || "total";

        // Option costs
        paramIds.forEach(paramId => {
            const elem = document.getElementById('ppc-option-cost-' + paramId);
            let base = parseFloat(elem.getAttribute('data-base') || 0);
            let val = base * qty;
            if (priceUnit === 'piece' && qty > 0) val = base;
            elem.textContent = val.toFixed(2);
        });

        // Discount
        let discountElem = document.getElementById('ppc-discount');
        let discountBase = parseFloat(discountElem.getAttribute('data-base') || 0);
        let discountVal = discountBase;
        if (priceUnit === 'piece' && qty > 0) discountVal = discountBase / qty;
        discountElem.textContent = discountVal.toFixed(2);

        // Total w/o tax
        let noTaxElem = document.getElementById('ppc-no-tax-total');
        let noTaxBase = parseFloat(noTaxElem.getAttribute('data-base') || 0);
        let noTaxVal = noTaxBase;
        if (priceUnit === 'piece' && qty > 0) noTaxVal = noTaxBase / qty;
        noTaxElem.textContent = noTaxVal.toFixed(2);

        // Tax
        let taxElem = document.getElementById('ppc-tax-amount');
        let taxBase = parseFloat(taxElem.getAttribute('data-base') || 0);
        let taxVal = showTax === 'with' ? taxBase : 0;
        if (priceUnit === 'piece' && qty > 0) taxVal = taxVal / qty;
        taxElem.textContent = taxVal.toFixed(2);

        // Grand total
        let grandElem = document.getElementById('ppc-grand-total');
        let grandBase = parseFloat(grandElem.getAttribute('data-base') || 0);
        let grandVal = grandBase;
        if (showTax === 'without') grandVal = grandBase - taxBase;
        if (priceUnit === 'piece' && qty > 0) grandVal = grandVal / qty;
        grandElem.textContent = grandVal.toFixed(2);
    }

    selects.forEach(sel => sel.addEventListener('change', updateSummary));
    if (expressCheckbox) expressCheckbox.addEventListener('change', updateSummary);
    if (fileCheckBox) fileCheckBox.addEventListener('change', updateSummary);
    showTaxRadios.forEach(radio => radio.addEventListener('change', function() {
        updateViewDisplay(
            parseInt(qtyInput.value, 10),
            parseFloat(document.getElementById('ppc-tax-amount').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-grand-total').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-no-tax-total').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-discount').getAttribute('data-base') || 0),
            {}
        );
    }));
    priceUnitRadios.forEach(radio => radio.addEventListener('change', function() {
        updateViewDisplay(
            parseInt(qtyInput.value, 10),
            parseFloat(document.getElementById('ppc-tax-amount').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-grand-total').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-no-tax-total').getAttribute('data-base') || 0),
            parseFloat(document.getElementById('ppc-discount').getAttribute('data-base') || 0),
            {}
        );
    }));

    updateSummary();

    qtyInput.addEventListener('blur', function() {
        let val = parseInt(qtyInput.value, 10);
        if (isNaN(val) || val < minQty) {
            qtyInput.value = minQty;
            qtyError.textContent = `Minimum order is ${minQty}`;
            qtyError.classList.remove('hidden');
        } else {
            qtyError.textContent = '';
            qtyError.classList.add('hidden');
        }
        updateSummary();
    });

    qtyInput.addEventListener('input', function() {
        qtyError.textContent = '';
        qtyError.classList.add('hidden');
        updateSummary();
    });

    document.getElementById('ppc-download-pdf').addEventListener('click', function() {
        const productTitle = "<?php echo esc_html($product['title']); ?>";
        const productImage = "<?php echo $product['image_url']; ?>";
        const params = [];
        document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
            const paramId = sel.dataset.paramId;
            const paramTitle = sel.dataset.paramTitle;
            const selected = sel.options[sel.selectedIndex];
            params.push({
                id: paramId,
                title: paramTitle,
                value: selected.value,
                text: selected.text
            });
        });
        const qty = document.getElementById('ppc-qty').value;
        const express = expressCheckbox && expressCheckbox.checked;
        const fileCheck = fileCheckBox && fileCheckBox.checked;
        const summary = document.getElementById('ppc-summary-table').outerHTML;
        const total = document.getElementById('ppc-grand-total').getAttribute('data-base');
        const fileCheckFilename = fileUpload && fileUpload.files.length ? fileUpload.files[0].name : '';

        var formData = new FormData();
        formData.append('action', 'ppc_generate_pdf');
        formData.append('product_title', productTitle);
        formData.append('product_image', productImage);
        formData.append('params', JSON.stringify(params));
        formData.append('qty', qty);
        formData.append('express', express ? 1 : 0);
        formData.append('file_check', fileCheck ? 1 : 0);
        formData.append('file_check_filename', fileCheckFilename);
        formData.append('summary_html', summary);
        formData.append('total', total);

        fetch(window.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(resp => resp.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'quotation-<?php echo date('Ymd-His'); ?>.pdf';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(() => alert('PDF download failed.'));
    });

    // ADD TO CART FUNCTION
    document.getElementById('ppc-add-to-cart').addEventListener('click', function () {
        const productTitle = "<?php echo esc_html($product['title']); ?>";
        const imageUrl = "<?php echo $product['image_url']; ?>";
        // Build detailed params array for cart display
        const paramsArr = [];
        document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
            const paramId = sel.dataset.paramId;
            const paramTitle = sel.dataset.paramTitle;
            const selected = sel.options[sel.selectedIndex];
            paramsArr.push({
                id: paramId,
                title: paramTitle,
                value: selected.value,
                text: selected.text
            });
        });

        const fileCheck = fileCheckBox && fileCheckBox.checked ? 1 : 0;
        const qty = qtyInput.value;
        const express = expressCheckbox && expressCheckbox.checked ? 1 : 0;
        const fileInput = fileUpload;
        const summary = document.getElementById('ppc-summary-table').outerHTML;
        const total = window.ppc_calc_data ? window.ppc_calc_data.total : 0;
        const discount = window.ppc_calc_data ? window.ppc_calc_data.discountAmount : 0;
        const taxVal = window.ppc_calc_data ? window.ppc_calc_data.taxAmount : 0;

        const formData = new FormData();
        formData.append('action', 'ppc_add_to_cart');
        formData.append('ppc_product_title', productTitle);
        formData.append('ppc_product_id', "<?php echo esc_attr($product['id']); ?>");
        formData.append('params', JSON.stringify(paramsArr));
        formData.append('qty', qty);
        formData.append('express', express);
        formData.append('file_check', fileCheck);
        formData.append('summary_html', summary);
        formData.append('total', total);
        formData.append('discount', discount);
        formData.append('tax', taxVal);
        formData.append('image', imageUrl);

        if (fileCheck && fileInput && fileInput.files.length) {
            formData.append('file', fileInput.files[0]);
        }

        fetch(window.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                window.location = data.data.cart_url;
            } else {
                alert(data.data || 'Failed to add to cart');
            }
        });
    });

});
</script>
