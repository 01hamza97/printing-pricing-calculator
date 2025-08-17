<!-- templates/frontend/calculator-ui.php -->
<div class="w-5/6 mx-auto my-8 font-sans">
  <!-- Product header -->
  <div class="mb-6">
    <img src="<?php echo esc_url($product['image_url']); ?>" class="w-full rounded-lg object-cover max-h-44" alt="<?php echo esc_attr($product['title']); ?>" />
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- Left: parameters & controls -->
    <div class="col-span-2 bg-gray-50 rounded-lg p-6 shadow">
      <h4 class="text-lg font-semibold mb-4">Parameters</h4>

      <?php foreach ($parameters as $p): ?>
        <div class="mb-6">
          <label for="param_<?php echo (int)$p['id']; ?>" class="font-semibold text-base block mb-1">
            <?php echo esc_html($p['front_name']); ?>
          </label>

          <div class="flex items-start gap-2">
            <select
              id="param_<?php echo (int)$p['id']; ?>"
              name="parameters[<?php echo (int)$p['id']; ?>]"
              class="border rounded px-3 py-2 w-[96%]"
              data-param-id="<?php echo (int)$p['id']; ?>"
              data-param-title="<?php echo esc_attr($p['title']); ?>"
            >
              <option value="" data-cost="0">Select an option</option>
              <?php foreach ($p['options'] as $o): ?>
                <?php
                  $cost = !empty($o['override_price'])
                    ? $o['override_price']
                    : (!empty($o['meta_value']['cost']) ? $o['meta_value']['cost'] : 0);
                  $opt_label = $o['meta_value']['option'] ?? '';
                ?>
                <option
                  value="<?php echo esc_attr($opt_label); ?>"
                  data-cost="<?php echo esc_attr($cost); ?>"
                  data-option-id="<?php echo (int)($o['id'] ?? 0); ?>"
                >
                  <?php echo esc_html($opt_label); ?>
                  <?php if (floatval($cost) > 0): ?>
                    (<?php echo number_format((float)$cost, 2); ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>

            <!-- info tooltip -->
            <span class="inline-block align-middle relative group ml-2 cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#FEF3C7"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
              </svg>
              <span class="absolute left-7 top-1/2 -translate-y-1/2 bg-gray-900 text-white text-[13px] rounded px-2 py-1 opacity-0 group-hover:opacity-100 transition pointer-events-none z-50 shadow-lg min-w-[200px] max-w-xs whitespace-normal break-words">
                <?php echo esc_html($p['content']); ?>
              </span>
            </span>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- file upload (shown/hidden by File Check state) -->
      <div id="ppc-file-upload-wrapper" class="bg-gray-100 px-6 py-5 rounded-lg w-full mb-6 hidden">
        <label class="block text-sm font-medium mb-2">Upload file</label>
        <input type="file" id="ppc-file-upload" class="block w-full text-sm" />
      </div>

      <!-- display controls -->
      <div class="mb-4 w-full grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- With/Without Tax -->
        <div>
          <div class="text-sm font-medium mb-2">Show prices</div>
          <div class="inline-flex rounded-md shadow-sm overflow-hidden border">
            <label class="px-3 py-1.5 cursor-pointer text-sm bg-white hover:bg-gray-50 flex items-center gap-2">
              <input type="radio" name="show_tax" id="ppc-show-tax-yes" value="with" class="accent-blue-600" checked />
              With Tax
            </label>
            <label class="px-3 py-1.5 cursor-pointer text-sm bg-white hover:bg-gray-50 border-l flex items-center gap-2">
              <input type="radio" name="show_tax" id="ppc-show-tax-no" value="without" class="accent-blue-600" />
              Without Tax
            </label>
          </div>
        </div>

        <!-- Per Piece / Total -->
        <div>
          <div class="text-sm font-medium mb-2">Units</div>
          <div class="inline-flex rounded-md shadow-sm overflow-hidden border">
            <label class="px-3 py-1.5 cursor-pointer text-sm bg-white hover:bg-gray-50 flex items-center gap-2">
              <input type="radio" name="price_unit" id="ppc-unit-piece" value="piece" class="accent-blue-600" />
              Per Piece
            </label>
            <label class="px-3 py-1.5 cursor-pointer text-sm bg-white hover:bg-gray-50 border-l flex items-center gap-2">
              <input type="radio" name="price_unit" id="ppc-unit-total" value="total" class="accent-blue-600" checked />
              Total
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: summary + actions -->
    <div class="flex flex-col gap-4">
      <div class="bg-blue-50 text-blue-900 rounded-lg px-6 py-5 text-center text-xl font-bold shadow">
        <!-- Quantity -->
        <div class="mb-3">
          <label for="ppc-qty" class="font-medium block mb-1">Quantity</label>
          <input type="number" id="ppc-qty" class="border rounded px-3 py-1 w-24 text-center" />
          <small class="text-gray-600 block">Minimum order: <?php echo (int)$min_order_qty; ?></small>
          <small class="text-red-600 hidden block" id="ppc-qty-error"></small>
        </div>

        <!-- Express -->
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

        <!-- File Check -->
        <div class="mb-3">
          <label class="inline-flex items-center">
            <input type="checkbox"
              class="accent-green-700 mr-2"
              id="ppc-file-check"
              <?php if (!empty($file_check_required)) echo 'checked disabled'; ?>
            >
            <span class="font-medium">
              File Check Service (<?php echo number_format((float)$file_check_price, 2); ?>)
            </span>
          </label>
        </div>

        <!-- Price title -->
        <div class="mb-3">
          Price <span id="ppc-price-type-label" class="text-base font-medium"></span>
        </div>

        <!-- Summary table -->
        <div class="overflow-x-auto">
          <table class="text-base text-left w-full" id="ppc-summary-table">
            <thead>
              <tr>
                <th>Item</th>
                <th>Unit</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody id="ppc-selected-params-table">
              <?php foreach ($parameters as $p): ?>
                <tr class="hidden" data-param-id="<?php echo (int)$p['id']; ?>">
                  <td>
                    <?php echo esc_html($p['title']); ?>
                    (<span class="ppc-selected-option" id="ppc-selected-option-<?php echo (int)$p['id']; ?>">—</span>)
                  </td>
                  <td>1</td>
                  <td>
                    <span class="ppc-option-cost text-gray-500" id="ppc-option-cost-<?php echo (int)$p['id']; ?>">0.00</span>
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
                <td colspan="2">Tax(<?php echo (float)$tax ?>%)</td>
                <td id="ppc-tax-amount">0.00</td>
              </tr>
              <tr class="font-bold">
                <td colspan="2">Total</td>
                <td id="ppc-grand-total">0.00</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="bg-gray-200 rounded-lg px-6 py-4 text-center cursor-pointer shadow" id="ppc-download-pdf">
        download calculation (pdf?)
      </div>
      <button id="ppc-add-to-cart" type="button"
        class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-6 py-6 text-center font-bold text-lg cursor-pointer shadow transition disabled:opacity-50 disabled:cursor-not-allowed">
        Add to Cart
      </button>
    </div>
  </div>
</div>

<script>
window.ajaxurl = window.ajaxurl || "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";

document.addEventListener('DOMContentLoaded', function () {
  // Settings
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
  const basePrice = parseFloat('<?php echo (float)$product['base_price']; ?>') || 0;
  const paramIds = <?php echo wp_json_encode(array_map('intval', array_column($parameters, 'id'))); ?>;
  const tax = settings.tax ? parseFloat(settings.tax) : (<?php echo json_encode((float)$tax); ?> || 0);
  const expressValue = settings.express_delivery_value ? parseFloat(settings.express_delivery_value) : (<?php echo json_encode((float)$express_delivery['value']); ?> || 0);
  const expressType = settings.express_delivery_type || <?php echo json_encode($express_delivery['type']); ?> || 'percent';
  const minQty = settings.min_order_qty ? parseInt(settings.min_order_qty) : (<?php echo (int)$min_order_qty; ?> || 1);
  const discountRules = (settings.product_discount_rules && settings.product_discount_rules.length)
    ? settings.product_discount_rules
    : (settings.global_discount_rules || []);
  const fileCheckPrice = settings.file_check_price ? parseFloat(settings.file_check_price) : (<?php echo json_encode((float)$file_check_price); ?> || 0);
  const fileCheckRequired = settings.file_check_required ? parseInt(settings.file_check_required) : (<?php echo json_encode((int)$file_check_required); ?> || 0);
  qtyInput.value = minQty;

  const showTaxRadios = document.getElementsByName('show_tax');
  const priceUnitRadios = document.getElementsByName('price_unit');

  // File check gating
  if (fileCheckBox) {
    if (fileCheckBox.hasAttribute('disabled')) {
      fileCheckBox.checked = true;
      fileUploadWrapper.classList.remove('hidden');
      addToCartBtn.disabled = true;
    }
  }
  if (fileUpload) {
    fileUpload.addEventListener('change', function() {
      if (fileCheckBox && (fileCheckBox.checked || fileCheckBox.hasAttribute('disabled'))) {
        addToCartBtn.disabled = !fileUpload.value;
      }
    });
  }

  function getDiscountPercent(qty, rules) {
    // assumes rules are sorted desc by qty threshold
    for (let i = 0; i < rules.length; i++) {
      if (qty >= parseInt(rules[i].qty)) return parseFloat(rules[i].percent);
    }
    return 0;
  }

  function updateSummary() {
    const qty = Math.max(parseInt(qtyInput.value || 0, 10) || 0, 0);

    let perPieceAdders = 0;
    paramIds.forEach(paramId => {
      const sel = document.getElementById('param_' + paramId);
      const selectedOpt = sel.options[sel.selectedIndex];
      const optText = selectedOpt.text.replace(/\([^)]+\)/, '').trim();
      const optCost = parseFloat(selectedOpt.dataset.cost || 0);

      // show selected in table
      const row = paramsTable.querySelector('[data-param-id="'+paramId+'"]');
      if (row) {
        row.classList.remove('hidden');
        row.querySelector('#ppc-selected-option-' + paramId).textContent = selectedOpt.value ? optText : '—';
        const costCell = row.querySelector('#ppc-option-cost-' + paramId);
        // UPDATE VIEW ONLY (per piece cost displayed times qty)
        costCell.textContent = selectedOpt.value && optCost ? (optCost * Math.max(qty,1)).toFixed(2) : '0.00';
      }

      if (selectedOpt.value && optCost) {
        perPieceAdders += optCost; // this is per piece adder
      }
    });

    if (window.ppc_apply_conditions) window.ppc_apply_conditions();

    // per piece subtotal (base + adders) * qty
    const perPiece = basePrice + perPieceAdders;
    const preDiscountTotal = perPiece * Math.max(qty, 1);

    // discount
    const discountPercent = getDiscountPercent(qty || 0, discountRules);
    const discountAmount = preDiscountTotal * (discountPercent / 100);
    let finalNoTax = preDiscountTotal - discountAmount;

    // file check (flat)
    let fileCheckAmount = 0;
    if (fileCheckBox && fileCheckBox.checked) {
      fileCheckAmount = fileCheckPrice;
      finalNoTax += fileCheckAmount;
      // ensure row exists before the discount row
      if (!document.getElementById('ppc-file-check-row')) {
        const tr = document.createElement('tr');
        tr.id = 'ppc-file-check-row';
        tr.innerHTML = '<td>File Check Service</td><td>1</td><td id="ppc-file-check-cost">0.00</td>';
        const discountRow = document.getElementById('ppc-discount').parentElement;
        paramsTable.insertBefore(tr, discountRow);
      }
      document.getElementById('ppc-file-check-cost').textContent = fileCheckAmount.toFixed(2);
    } else {
      const r = document.getElementById('ppc-file-check-row');
      if (r) r.remove();
    }

    // express
    let expressAmount = 0;
    if (expressCheckbox && expressCheckbox.checked) {
      expressAmount = (expressType === 'percent')
        ? (finalNoTax * (expressValue / 100))
        : expressValue;
      finalNoTax += expressAmount;

      if (!document.getElementById('ppc-express-row')) {
        const tr = document.createElement('tr');
        tr.id = 'ppc-express-row';
        tr.innerHTML = '<td>Express Delivery (<?php echo esc_html($express_delivery['type'] === 'percent' ? '+' . (float)$express_delivery['value'] . '%' : '+' . number_format((float)$express_delivery['value'], 2)); ?>)</td><td>1</td><td id="ppc-express-cost">0.00</td>';
        const discountRow = document.getElementById('ppc-discount').parentElement;
        paramsTable.insertBefore(tr, discountRow);
      }
      document.getElementById('ppc-express-cost').textContent = expressAmount.toFixed(2);
    } else {
      const r = document.getElementById('ppc-express-row');
      if (r) r.remove();
    }

    // base view numbers (original values, total WITH tax for backend)
    const taxAmount = tax > 0 ? finalNoTax * (tax / 100) : 0;
    const finalWithTax = finalNoTax + taxAmount;

    // update base cells (the “originals”)
    document.getElementById('ppc-discount').textContent = discountAmount.toFixed(2);
    document.getElementById('ppc-no-tax-total').textContent = finalNoTax.toFixed(2);
    document.getElementById('ppc-tax-amount').textContent = taxAmount.toFixed(2);
    document.getElementById('ppc-grand-total').textContent = finalWithTax.toFixed(2);

    // Store values for Add to Cart — IMPORTANT: total is WITH TAX
    window.ppc_calc_data = {
      qty,
      discountAmount,
      taxAmount,
      total: finalWithTax,
      totalWithoutTax: finalWithTax - taxAmount
    };

    // ---- Display radios (VIEW-ONLY transform) ----
    const showTax = (document.querySelector('input[name="show_tax"]:checked')?.value || 'with');  // with/without
    const priceUnit = (document.querySelector('input[name="price_unit"]:checked')?.value || 'total'); // piece/total

    let displayTotal = (showTax === 'with') ? finalWithTax : finalNoTax;
    let displayTax = (showTax === 'with') ? taxAmount : 0;

    if (priceUnit === 'piece' && qty > 0) {
      displayTotal = displayTotal / qty;
      displayTax = displayTax / qty;
    }

    // Overwrite the visible cells with transformed view
    document.getElementById('ppc-no-tax-total').textContent = (showTax === 'with' ? (finalWithTax - taxAmount) : finalNoTax).toFixed(2);
    document.getElementById('ppc-tax-amount').textContent = displayTax.toFixed(2);
    document.getElementById('ppc-grand-total').textContent = displayTotal.toFixed(2);

    // Option costs (show per-piece or total in view)
    paramIds.forEach(paramId => {
      const sel = document.getElementById('param_' + paramId);
      const selectedOpt = sel.options[sel.selectedIndex];
      const optCost = parseFloat(selectedOpt.dataset.cost || 0);
      const el = document.getElementById('ppc-option-cost-' + paramId);
      if (!el) return;
      if (!selectedOpt.value || !optCost) {
        el.textContent = '0.00';
        return;
      }
      // If viewing per piece, show per piece cost; else show multiplied by qty
      const shown = (priceUnit === 'piece') ? optCost : (optCost * Math.max(qty,1));
      el.textContent = shown.toFixed(2);
    });

    // Price label
    const label = (priceUnit === 'piece' ? 'Per Piece' : 'Total') + (showTax === 'with' ? ' (With Tax)' : ' (Without Tax)');
    document.getElementById('ppc-price-type-label').textContent = label;
  }

  // Hook up interactions
  selects.forEach(sel => sel.addEventListener('change', updateSummary));
  if (expressCheckbox) expressCheckbox.addEventListener('change', updateSummary);
  if (fileCheckBox) fileCheckBox.addEventListener('change', function() {
    if (fileCheckBox.checked || fileCheckBox.hasAttribute('disabled')) {
      fileUploadWrapper.classList.remove('hidden');
      addToCartBtn.disabled = !fileUpload.value;
    } else {
      fileUploadWrapper.classList.add('hidden');
      addToCartBtn.disabled = false;
    }
    updateSummary();
  });
  Array.from(showTaxRadios).forEach(r => r.addEventListener('change', updateSummary));
  Array.from(priceUnitRadios).forEach(r => r.addEventListener('change', updateSummary));

  // Quantity validation
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

  // Initial calc
  updateSummary();

  // PDF generation (send original total WITH tax)
  document.getElementById('ppc-download-pdf').addEventListener('click', function() {
    const productTitle = "<?php echo esc_html($product['title']); ?>";
    const productImage = "<?php echo esc_url($product['image_url']); ?>";
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
    // IMPORTANT: total WITH TAX for backend
    const total = (window.ppc_calc_data && window.ppc_calc_data.total) ? window.ppc_calc_data.total : document.getElementById('ppc-grand-total').textContent;
    const fileCheckFilename = fileUpload && fileUpload.files.length ? fileUpload.files[0].name : '';

    const formData = new FormData();
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

    fetch(window.ajaxurl, { method: 'POST', body: formData })
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

  // ADD TO CART (send original total WITH tax)
  document.getElementById('ppc-add-to-cart').addEventListener('click', function () {
    const productTitle = "<?php echo esc_html($product['title']); ?>";
    const imageUrl = "<?php echo esc_url($product['image_url']); ?>";

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

    // IMPORTANT: backend receives WITH-TAX total
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
      if (data.success && data.data && data.data.cart_url) {
        window.location = data.data.cart_url;
      } else {
        alert((data && data.data) || 'Failed to add to cart');
      }
    })
    .catch(() => alert('Failed to add to cart'));
  });

  (function(){
    const conds = (window.ppc_settings && window.ppc_settings.conditions) || { option:{}, parameter:{} };

    // return a map of current selections: { [paramId]: { optionId, selectEl } }
    function getCurrentSelections() {
        const map = {};
        document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
        const pid = parseInt(sel.dataset.paramId, 10);
        if (!pid) return;
        const opt = sel.options[sel.selectedIndex];
        const oid = opt ? parseInt(opt.getAttribute('data-option-id') || '0', 10) : 0;
        map[pid] = { optionId: oid, selectEl: sel };
        });
        return map;
    }

    // Show all params and enable all options (baseline), then re-apply rules
    function resetVisibility() {
        document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
        // show parameter container
        const wrap = sel.closest('.mb-6');
        if (wrap) wrap.classList.remove('hidden');

        // enable all options
        for (let i = 0; i < sel.options.length; i++) {
            sel.options[i].disabled = false;
        }
        });
    }

    // Apply a list of groups (each group has rows) — this is "fire and apply", not predicate logic.
    // If target_option_id is empty/0 => applies to whole parameter (hide/show)
    // If target_option_id is provided => only that option is disabled/enabled
    function applyGroups(groups) {
        if (!Array.isArray(groups)) return;

        groups.forEach(group => {
            const rows = Array.isArray(group.rows) ? group.rows : [];

            rows.forEach(row => {
            const tPid = parseInt(row.target_param_id || 0, 10);
            if (!tPid) return;

            const tOid = parseInt(row.target_option_id || 0, 10); // 0 => ANY
            const action = (row.action === 'hide') ? 'hide' : 'show';

            const sel = document.getElementById('param_' + tPid);
            if (!sel) return;

            if (!tOid) {
                // Whole parameter
                const wrap = sel.closest('.mb-6');
                if (wrap) {
                if (action === 'hide') {
                    // if currently selected, clear it
                    if (sel.value) {
                    sel.selectedIndex = 0; // first option (e.g., "Select an option")
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    wrap.classList.add('hidden');
                } else {
                    wrap.classList.remove('hidden');
                }
                }
            } else {
                // Specific option within the select
                for (let i = 0; i < sel.options.length; i++) {
                const opt = sel.options[i];
                const oid = parseInt(opt.getAttribute('data-option-id') || '0', 10);
                if (oid !== tOid) continue;

                if (action === 'hide') {
                    opt.disabled = true;

                    // If this option is currently selected, clear selection and notify
                    const selectedOpt = sel.options[sel.selectedIndex];
                    const selectedOid = selectedOpt ? parseInt(selectedOpt.getAttribute('data-option-id') || '0', 10) : 0;

                    if (selectedOid === tOid) {
                    sel.selectedIndex = 0; // move to placeholder
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else {
                    opt.disabled = false;
                }
                }
            }
            });
        });
        }

    // Main evaluator — call this before recomputing totals (and on each change)
    function evaluateConditions() {
        resetVisibility();

        const selections = getCurrentSelections();

        // 1) Option-level rules: fire groups for each selected option id
        Object.values(selections).forEach(({ optionId }) => {
        if (optionId && conds.option && conds.option[optionId]) {
            applyGroups(conds.option[optionId]);
        }
        });

        // 2) Parameter-level rules: if parameter exists in the form, apply its groups
        if (conds.parameter) {
        Object.keys(conds.parameter).forEach(pid => {
            const sel = document.getElementById('param_' + pid);
            if (sel) applyGroups(conds.parameter[pid]);
        });
        }
    }

    // Expose for reuse in your summary function
    window.ppc_apply_conditions = evaluateConditions;

    // Run once on load so defaults are applied immediately
    evaluateConditions();

    // Also re-evaluate whenever any parameter changes
    document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
        sel.addEventListener('change', evaluateConditions);
    });
    })();

});
</script>
