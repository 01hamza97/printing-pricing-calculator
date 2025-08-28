<!-- templates/frontend/calculator-ui.php -->
<style>
.table-wrapper thead {
    border-bottom: 1px solid #f4f4f5;
}

.table-wrapper thead th {
    font-size: 12px;
    font-weight: 600;
    color: #111111a6;
    padding: 10px 0;
}

.table-wrapper tbody td {
    padding: 5px 0;
    font-size: 13px;
    font-weight: 400;
    color: #111111a6;
}

/* Base + focus + disabled states for selects used in the calculator */
.ppc-select {          /* slate-100ish */
  border: 1px solid #008ec0;            /* gray-200ish */
  color: #008ec0;
}
.ppc-select:disabled {
  border: 1px solid #008ec0;           /* slightly dimmer */
  color: #9ca3af;                       /* gray-400ish */
  cursor: not-allowed;
  opacity: 1;                            /* keep text readable */
}

</style>
 
<div class="w-5/6 mx-auto my-8 font-sans">
  <div class="flex">
    <div class="w-1/3 px-2 mx-2">
      <h4 class="text-base !font-bold mb-[14px] text-[#008ec0]"><?php echo esc_html__( 'Parameters', 'printing-pricing-calculator' ); ?></h4>
      <div class="col-span-2 rounded-md px-5 py-6 border border-[#008ec0] mb-4">
        <div class="">
          <div class="relative overflow-hidden rounded-lg">
            <img
              src="<?php echo esc_url($product['image_url']); ?>"
              alt="<?php echo esc_attr($product['title']); ?>"
              class="w-full h-auto object-cover transition-transform duration-200 ease-out will-change-transform select-none pointer-events-none"
              draggable="false"
            />
          </div>
        </div>
      </div>
      <div class="col-span-2 rounded-md px-5 py-6 border border-[#008ec0] mb-4">
        <?php foreach ($parameters as $p): ?>
          <div class="parameter-wrapper">
            <label for="param_<?php echo (int)$p['id']; ?>" class="font-semibold text-sm block mb-1 text-zinc-700">
              <?php echo esc_html($p['front_name']); ?>
            </label>

            <div class="flex items-center gap-2">
              <select
                id="param_<?php echo (int)$p['id']; ?>"
                name="parameters[<?php echo (int)$p['id']; ?>]"
                class="ppc-select bg-zinc-100 rounded-md px-3 py-4 w-[96%] text-base mb-3"
                data-param-id="<?php echo (int)$p['id']; ?>"
                data-param-title="<?php echo esc_attr($p['title']); ?>"
              >
                <option value="" data-cost="0"><?php echo esc_html__( 'Select an option', 'printing-pricing-calculator' ); ?></option>
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
                    <?php // if (floatval($cost) > 0): ?>
                      <!-- (<?php // echo number_format((float)$cost, 2); ?>) -->
                    <?php // endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <!-- info tooltip -->
              <span class="inline-block align-middle relative group ml-2 cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="#008ec0"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                </svg>
                <span class="absolute left-7 top-1/2 -translate-y-1/2 bg-[#008ec0] text-white text-[13px] rounded px-2 py-1 opacity-0 group-hover:opacity-100 transition pointer-events-none z-50 shadow-lg min-w-[200px] max-w-xs whitespace-normal break-words">
                  <?php 
                    $html = html_entity_decode($p['content'], ENT_QUOTES, 'UTF-8');
                    echo wp_kses_post($html); ?>
                </span>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="col-span-2 rounded-md px-5 py-6 border border-[#008ec0] mb-4">
        <a download href="<?php echo wp_get_attachment_url($product['instructions_file_id']) ?>" target="_blank" class="bg-[#008ec0] border border-blue-300 rounded-md !text-white w-full block text-base font-normal py-4 text-center !no-underline"><?php echo esc_html__( 'Download Instructions File', 'printing-pricing-calculator' ); ?></a>
      </div>
      <div class="col-span-2 rounded-md px-5 py-6 border border-[#008ec0]">
        <span class="bg-[#008ec0] border border-blue-300 rounded-md text-white w-full block text-base font-normal py-4 px-3 no-underline mb-4">
          <div class="flex items-center justify-between">
            <span><i class="fa-solid fa-paperclip mr-1 text-white"></i><?php echo esc_html__( 'No File Selected', 'printing-pricing-calculator' ); ?></span>
            <span><i class="fa-solid fa-trash text-white hover:cursor-pointer"></i></span>
          </div>
                    </span>
        <!-- file upload (shown/hidden by File Check state) -->
        <div class="w-full max-w-lg" id="ppc-file-upload-wrapper">
          <div class="flex h-32 flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-white text-center hover:border-zinc-400 transition-colors">
            <!-- icon -->
            <i class="fa-solid fa-arrow-up-from-bracket"></i>

            <p class="text-sm text-zinc-600">
              <?php echo esc_html__( 'Drop here to attach or', 'printing-pricing-calculator' ); ?>
              <label for="ppc-file-upload" class="cursor-pointer text-[#008ec0] hover:underline"><?php echo esc_html__( 'upload', 'printing-pricing-calculator' ); ?></label>
            </p>
            <p class="mt-1 text-xs text-zinc-400"><?php echo esc_html__( 'Max size: 5GB', 'printing-pricing-calculator' ); ?></p>

            <input id="ppc-file-upload" type="file" class="sr-only" />
          </div>
        </div>
      </div>
    </div>
    <div class="w-1/3 px-2 mx-2">
      <div class="sticky top-8">
        <h4 class="text-base !font-bold mb-[14px] text-[#008ec0]"><?php echo esc_html__( 'Number Of Pieces', 'printing-pricing-calculator' ); ?></h4>
        <div class="col-span-2 rounded-md px-5 py-6 border border-[#008ec0] mb-4">
            <div class="mb-6">
              <div class="flex items-center justify-between">
                <div class="">
                  <label class="inline-flex items-center">
                    <input type="checkbox" id="ppc-express" class="accent-cyan-500 mr-2" />
                    <span class="font-normal text-sm text-zinc-700">
                      100 <?php echo esc_html__( 'PCS', 'printing-pricing-calculator' ); ?>
                    </span>
                  </label>
                </div>
                <p class="font-normal text-sm text-zinc-700">100 <?php echo esc_html__( 'CZK', 'printing-pricing-calculator' ); ?></p>
              </div>
              <div class="flex items-center justify-between">
                <div class="">
                  <label class="inline-flex items-center">
                    <input type="checkbox" id="ppc-express" class="accent-cyan-500 mr-2" />
                    <span class="font-normal text-sm text-zinc-700">
                      200 <?php echo esc_html__( 'PCS', 'printing-pricing-calculator' ); ?>
                    </span>
                  </label>
                </div>
                <p class="font-normal text-sm text-zinc-700">100 <?php echo esc_html__( 'CZK', 'printing-pricing-calculator' ); ?></p>
              </div>
              <div class="flex items-center justify-between">
                <div class="">
                  <label class="inline-flex items-center">
                    <input type="checkbox" id="ppc-express" class="accent-cyan-500 mr-2" />
                    <span class="font-normal text-sm text-zinc-700">
                      200 <?php echo esc_html__( 'PCS', 'printing-pricing-calculator' ); ?>
                    </span>
                  </label>
                </div>
                <p class="font-normal text-sm text-zinc-700">100 <?php echo esc_html__( 'CZK', 'printing-pricing-calculator' ); ?></p>
              </div>
              <div class="flex items-center justify-between">
                <div class="">
                  <label class="inline-flex items-center">
                    <input type="checkbox" id="ppc-express" class="accent-cyan-500 mr-2" />
                    <span class="font-normal text-sm text-zinc-700">
                      400 <?php echo esc_html__( 'PCS', 'printing-pricing-calculator' ); ?>
                    </span>
                  </label>
                </div>
                <p class="font-normal text-sm text-zinc-700">100 <?php echo esc_html__( 'CZK', 'printing-pricing-calculator' ); ?></p>
              </div>
            </div>
            <h5 class="text-base !font-semibold text-zinc-600 mb-1"><?php echo esc_html__( 'Enter Your Quantity', 'printing-pricing-calculator' ); ?></h5>
            <input type="text" placeholder="<?php echo esc_attr__( 'text', 'printing-pricing-calculator' ); ?>" class="bg-zinc-100 rounded-md px-3 py-3 w-full text-base">
        </div>
        <!-- display controls -->
        <div class="mb-4 mt-4 w-full grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- With/Without Tax -->
          <div class="text-start">
            <div class="text-sm font-medium mb-2"><?php echo esc_html__( 'Price Gross / Net', 'printing-pricing-calculator' ); ?></div>
            <label for="ppc-show-tax-toggle" class="inline-flex items-center cursor-pointer">
              <input id="ppc-show-tax-toggle" type="checkbox" class="sr-only peer">
              <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
            </label>
          </div>
          <div class="text-end">
            <div class="text-sm font-medium mb-2"><?php echo esc_html__( 'Price Per Piece', 'printing-pricing-calculator' ); ?></div>
            <label for="ppc-price-unit-toggle" class="inline-flex items-center cursor-pointer">
              <input id="ppc-price-unit-toggle" type="checkbox" class="sr-only peer">
              <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
            </label>
          </div>
        </div>
      </div>
    </div>
    <div class="w-1/3 px-2 mx-2">
      <div class="sticky top-8">
        <h4 class="text-base !font-bold mb-11 text-[#008ec0]"><?php echo esc_html__( 'Recapitulation', 'printing-pricing-calculator' ); ?></h4>
        <div class="flex flex-col">
          <div class="px-4">
            <!-- Quantity -->
            <div class="mb-3">
              <div class="relative inline-block">
                <div class="flex items-center justify-center border border-blue-300 rounded-md bg-[#008ec0] px-4 py-2 text-lg font-medium text-white mb-4">
                <input
                  type="number"
                  id="ppc-qty"
                  class="w-25 outline-none"
                />
                <span class=""><?php echo esc_html__( 'PCS', 'printing-pricing-calculator' ); ?></span>
              </div>
              <small class="text-gray-600 block text-sm mb-8"><?php echo esc_html__( 'Minimum order:', 'printing-pricing-calculator' ); ?> <?php echo (int)$min_order_qty; ?></small>
              <small class="text-red-600 hidden block" id="ppc-qty-error"></small>
            </div>
  
            <!-- Express -->
            <div class="">
              <label class="inline-flex items-center">
                <input type="checkbox" id="ppc-express" class="accent-[#008ec0] mr-2" />
                <span class="font-normal text-sm text-zinc-700">
                  <?php echo esc_html__( 'Express Delivery', 'printing-pricing-calculator' ); ?> (
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
            <div class="mb-8">
              <label class="inline-flex items-center">
                <input type="checkbox"
                  class="accent-[#008ec0] mr-2"
                  id="ppc-file-check"
                  <?php if (!empty($file_check_required)) echo 'checked disabled'; ?>
                >
                <span class="font-normal text-sm text-zinc-700">
                  <?php echo esc_html__( 'File Check Service', 'printing-pricing-calculator' ); ?> (<?php echo number_format((float)$file_check_price, 2); ?>)
                </span>
              </label>
            </div>
  
            <!-- Price title -->
            <!-- <div class="mb-3">
              <?php // echo esc_html__( 'Price', 'printing-pricing-calculator' ); ?> <span id="ppc-price-type-label" class="text-base font-medium"></span>
            </div> -->
  
            <!-- Summary table -->
            <div class="overflow-x-auto">
              <table class="text-base text-left w-full table-wrapper" id="ppc-summary-table">
                <thead>
                  <tr>
                    <th><?php echo esc_html__( 'Item', 'printing-pricing-calculator' ); ?></th>
                    <th><?php echo esc_html__( 'Unit', 'printing-pricing-calculator' ); ?></th>
                    <th><?php echo esc_html__( 'Price', 'printing-pricing-calculator' ); ?></th>
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
                    <td colspan="2"><?php echo esc_html__( 'Discount', 'printing-pricing-calculator' ); ?></td>
                    <td id="ppc-discount">0.00</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="font-semibold text-xs text-zinc-600">
                    <td colspan="2"><?php echo esc_html__( 'Total W/O Tax', 'printing-pricing-calculator' ); ?></td>
                    <td id="ppc-no-tax-total">0.00</td>
                  </tr>
                  <tr class="font-semibold text-xs text-zinc-600">
                    <td colspan="2"><?php echo esc_html__( 'Tax', 'printing-pricing-calculator' ); ?>(<?php echo (float)$tax ?>%)</td>
                    <td id="ppc-tax-amount">0.00</td>
                  </tr>
                  <tr class="font-semibold text-xs text-zinc-600">
                    <td colspan="2"><?php echo esc_html__( 'Total', 'printing-pricing-calculator' ); ?></td>
                    <td id="ppc-grand-total">0.00</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
  
          <!-- Order notes / instructions -->
          <div class="rounded-md py-4 mb-6">
            <label for="ppc-order-notes" class="block text-sm font-medium mb-2">
              <?php echo esc_html__( 'Notes / Instructions (optional)', 'printing-pricing-calculator' ); ?>
            </label>
            <textarea
              id="ppc-order-notes"
              class="w-full bg-[#008ec0] text-white border border-blue-300 rounded-md p-3 text-sm min-h-[96px] resize-y"
              placeholder="<?php echo esc_attr__( 'Anything we should know about your order?', 'printing-pricing-calculator' ); ?>"
              maxlength="1000"
            ></textarea>
            <div class="mt-1 text-xs text-gray-500">
              <span id="ppc-notes-count">0</span>/1000
            </div>
          </div>
  
          <div class="border border-blue-300 rounded-md text-[#008ec0] hover:text-white w-full block text-lg font-normal py-4 mb-4 text-center  hover:cursor-pointer hover:bg-[#00a3ca]" id="ppc-download-pdf">
            <?php echo esc_html__( 'download calculation (pdf?)', 'printing-pricing-calculator' ); ?>
          </div>
          <button id="ppc-add-to-cart" type="button"
            class="border border-blue-300 rounded-md bg-[#008ec0] text-white w-full block text-lg font-semibold py-4 cursor-pointer hover:bg-[#00a3ca]">
            <?php echo esc_html__( 'OBJEDNAT', 'printing-pricing-calculator' ); ?>
          </button>
        </div>
      </div>
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
  const orderNotes = document.getElementById('ppc-order-notes');
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


  // Bind (works even if radios were removed)
  const showTaxToggle   = document.getElementById('ppc-show-tax-toggle');
  const priceUnitToggle = document.getElementById('ppc-price-unit-toggle');

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

  if (orderNotes) {
    const counter = document.getElementById('ppc-notes-count');
    const updateCount = () => { if (counter) counter.textContent = String(orderNotes.value.length); };
    orderNotes.addEventListener('input', updateCount);
    updateCount();
  }

  function ppcFormatMoney(amount) {
    const cfg = window.ppcCurrency || {
      symbol: 'Kč', position: 'right_space', thousand_sep: ' ', decimal_sep: ',', num_decimals: 2
    };
    const nbspace = '\u00A0'; // keep number and symbol together
    const sepTh   = (cfg.thousand_sep === ' ') ? nbspace : cfg.thousand_sep;

    const n = Math.abs(Number(amount) || 0).toFixed(cfg.num_decimals);
    let [intPart, decPart] = n.split('.');
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, sepTh);

    const number = (cfg.num_decimals > 0) ? intPart + cfg.decimal_sep + decPart : intPart;
    const neg = (amount < 0) ? '-' : '';
    const hasSpace = (cfg.position === 'left_space' || cfg.position === 'right_space');
    const space = hasSpace ? nbspace : '';

    switch (cfg.position) {
      case 'left':        return neg + cfg.symbol + number;
      case 'left_space':  return neg + cfg.symbol + space + number;
      case 'right':       return neg + number + cfg.symbol;
      case 'right_space':
      default:            return neg + number + space + cfg.symbol;
    }
  }

  function getDiscountPercent(qty, rules) {
    // assumes rules are sorted desc by qty threshold
    for (let i = 0; i < rules.length; i++) {
      if (qty >= parseInt(rules[i].qty)) return parseFloat(rules[i].percent);
    }
    return 0;
  }

  function updateSummary() {
    // --- helpers/refs ---
    const $ = (sel) => document.querySelector(sel);
    const qty = Math.max(parseInt((qtyInput?.value ?? '0'), 10) || 0, 0);

    // // UI toggles (fallback to radios if toggles are not present)
    // const showTaxToggle   = document.getElementById('ppc-show-tax-toggle');
    // const priceUnitToggle = document.getElementById('ppc-price-unit-toggle');

    // --- parameters (per-piece adders) + show selected in table ---
    let perPieceAdders = 0;

    paramIds.forEach((paramId) => {
      const sel = document.getElementById('param_' + paramId);
      if (!sel) return;

      const selectedOpt = sel.options[sel.selectedIndex] || {};
      const optText = (selectedOpt.text || '').replace(/\([^)]+\)/, '').trim();
      const optCost = parseFloat(selectedOpt.dataset?.cost || 0) || 0;

      // show selected in params table (row should already exist)
      const row = paramsTable?.querySelector('[data-param-id="' + paramId + '"]');
      if (row) {
        row.classList.remove('hidden');
        const chosenEl = row.querySelector('#ppc-selected-option-' + paramId);
        const costEl   = row.querySelector('#ppc-option-cost-' + paramId);
        if (chosenEl) chosenEl.textContent = selectedOpt.value ? optText : '—';
        if (costEl)   costEl.textContent   = selectedOpt.value && optCost ? (optCost * Math.max(qty, 1)).toFixed(2) : '0.00';
      }

      if (selectedOpt.value && optCost) {
        perPieceAdders += optCost; // per-piece adder
      }
    });

    // Apply conditional visibility/logic if provided
    if (typeof window.ppc_apply_conditions === 'function') window.ppc_apply_conditions();

    // --- base totals (no tax) ---
    const perPiece          = (parseFloat(basePrice) || 0) + perPieceAdders; // per-piece price before discounts
    const preDiscountTotal  = perPiece * Math.max(qty, 1);

    // discount
    const discountPercent   = getDiscountPercent(qty || 0, discountRules) || 0;
    const discountAmount    = preDiscountTotal * (discountPercent / 100);
    let   finalNoTax        = preDiscountTotal - discountAmount;

    // --- file check (flat) ---
    let fileCheckAmount = 0;
    if (fileCheckBox && fileCheckBox.checked) {
      fileCheckAmount = parseFloat(fileCheckPrice) || 0;
      finalNoTax += fileCheckAmount;

      if (!document.getElementById('ppc-file-check-row')) {
        const tr = document.createElement('tr');
        tr.id = 'ppc-file-check-row';
        tr.innerHTML = '<td><?php echo esc_js( __( 'File Check Service', 'printing-pricing-calculator' ) ); ?></td><td>1</td><td id="ppc-file-check-cost">0.00</td>';
        const discountCell = document.getElementById('ppc-discount');
        const discountRow  = discountCell ? discountCell.parentElement : null;
        if (discountRow && paramsTable) {
          paramsTable.insertBefore(tr, discountRow);
        } else {
          paramsTable?.appendChild(tr);
        }
      }
      const fc = document.getElementById('ppc-file-check-cost');
      if (fc) fc.textContent = ppcFormatMoney(fileCheckAmount);
    } else {
      const r = document.getElementById('ppc-file-check-row');
      if (r) r.remove();
    }

    // --- express delivery ---
    let expressAmount = 0;
    if (expressCheckbox && expressCheckbox.checked) {
      const type  = (expressType === 'percent') ? 'percent' : 'flat';
      const value = parseFloat(expressValue) || 0;

      expressAmount = (type === 'percent') ? (finalNoTax * (value / 100)) : value;
      finalNoTax   += expressAmount;

      if (!document.getElementById('ppc-express-row')) {
        const tr = document.createElement('tr');
        tr.id = 'ppc-express-row';
        const label = (type === 'percent')
          ? `<?php echo esc_js( __( 'Express Delivery', 'printing-pricing-calculator' ) ); ?> (+${value}%)`
          : `<?php echo esc_js( __( 'Express Delivery', 'printing-pricing-calculator' ) ); ?> (+${value.toFixed(2)})`;
        tr.innerHTML = `<td>${label}</td><td>1</td><td id="ppc-express-cost">0.00</td>`;
        const discountCell = document.getElementById('ppc-discount');
        const discountRow  = discountCell ? discountCell.parentElement : null;
        if (discountRow && paramsTable) {
          paramsTable.insertBefore(tr, discountRow);
        } else {
          paramsTable?.appendChild(tr);
        }
      }
      const ex = document.getElementById('ppc-express-cost');
      if (ex) ex.textContent = ppcFormatMoney(expressAmount);
    } else {
      const r = document.getElementById('ppc-express-row');
      if (r) r.remove();
    }

    // --- tax ---
    const taxRate      = parseFloat(tax) || 0;
    const taxAmount    = taxRate > 0 ? finalNoTax * (taxRate / 100) : 0;
    const finalWithTax = finalNoTax + taxAmount;

    // --- write base cells (original values; backend uses WITH tax) ---
    const $discountCell   = document.getElementById('ppc-discount');
    const $noTaxTotalCell = document.getElementById('ppc-no-tax-total');
    const $taxAmountCell  = document.getElementById('ppc-tax-amount');
    const $grandTotalCell = document.getElementById('ppc-grand-total');

    if ($discountCell)   $discountCell.textContent   = ppcFormatMoney(discountAmount);
    if ($noTaxTotalCell) $noTaxTotalCell.textContent = ppcFormatMoney(finalNoTax);
    if ($taxAmountCell)  $taxAmountCell.textContent  = ppcFormatMoney(taxAmount);
    if ($grandTotalCell) $grandTotalCell.textContent = ppcFormatMoney(finalWithTax);

    // --- expose values for Add to Cart (total = WITH tax) ---
    window.ppc_calc_data = {
      qty,
      discountAmount,
      taxAmount,
      total: finalWithTax,
      totalWithoutTax: finalNoTax
    };

    // --- VIEW-ONLY transform from toggles/radios ---
    const showTax = showTaxToggle
      ? (!showTaxToggle.checked ? 'with' : 'without')
      : (document.querySelector('input[name="show_tax"]:checked')?.value || 'with');

    const priceUnit = priceUnitToggle
      ? (!priceUnitToggle.checked ? 'total' : 'piece')
      : (document.querySelector('input[name="price_unit"]:checked')?.value || 'total');

    // aria for accessibility
    if (showTaxToggle)   showTaxToggle.setAttribute('aria-checked', showTaxToggle.checked ? 'true' : 'false');
    if (priceUnitToggle) priceUnitToggle.setAttribute('aria-checked', priceUnitToggle.checked ? 'true' : 'false');

    let displayTotal = (showTax === 'with') ? finalWithTax : finalNoTax;
    let displayTax   = (showTax === 'with') ? taxAmount    : 0;

    if (priceUnit === 'piece' && qty > 0) {
      displayTotal = displayTotal / qty;
      displayTax   = displayTax / qty;
    }

    // Overwrite visible cells to reflect the current view
    if ($noTaxTotalCell) $noTaxTotalCell.textContent = ppcFormatMoney(
      showTax === 'with' ? (finalWithTax - taxAmount) : finalNoTax
    );
    if ($taxAmountCell)  $taxAmountCell.textContent  = ppcFormatMoney(displayTax);
    if ($grandTotalCell) $grandTotalCell.textContent = ppcFormatMoney(displayTotal);

    // Option costs (per-piece vs total in view)
    paramIds.forEach((paramId) => {
      const sel = document.getElementById('param_' + paramId);
      if (!sel) return;
      const selectedOpt = sel.options[sel.selectedIndex] || {};
      const optCost = parseFloat(selectedOpt.dataset?.cost || 0) || 0;
      const el = document.getElementById('ppc-option-cost-' + paramId);
      if (!el) return;

      if (!selectedOpt.value || !optCost) {
        el.textContent = '0.00';
        return;
      }
      const shown = (priceUnit === 'piece') ? optCost : (optCost * Math.max(qty, 1));
      el.textContent = ppcFormatMoney(shown);
    });

    // Price label
    const label = (priceUnit === 'piece'
                    ? '<?php echo esc_js( __( 'Per Piece', 'printing-pricing-calculator' ) ); ?>'
                    : '<?php echo esc_js( __( 'Total', 'printing-pricing-calculator' ) ); ?>')
                  + (showTax === 'with'
                    ? ' <?php echo esc_js( __( '(With Tax)', 'printing-pricing-calculator' ) ); ?>'
                    : ' <?php echo esc_js( __( '(Without Tax)', 'printing-pricing-calculator' ) ); ?>');
    const $label = document.getElementById('ppc-price-type-label');
    if ($label) $label.textContent = label;
  }

  // Hook up interactions
  selects.forEach(sel => sel.addEventListener('change', updateSummary));
  if (expressCheckbox) expressCheckbox.addEventListener('change', updateSummary);
  showTaxToggle?.addEventListener('change', updateSummary);
  priceUnitToggle?.addEventListener('change', updateSummary);
  fileCheckBox?.addEventListener('change', updateSummary);

  // Quantity validation
  qtyInput.addEventListener('blur', function() {
    let val = parseInt(qtyInput.value, 10);
    if (isNaN(val) || val < minQty) {
      qtyInput.value = minQty;
      qtyError.textContent = "<?php echo esc_js( __( 'Minimum order is', 'printing-pricing-calculator' ) ); ?> " + minQty;
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
      .catch(() => alert('<?php echo esc_js( __( 'PDF download failed.', 'printing-pricing-calculator' ) ); ?>'));
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
    formData.append('customer_note', orderNotes ? orderNotes.value : '');

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
        alert((data && data.data) || '<?php echo esc_js( __( 'Failed to add to cart', 'printing-pricing-calculator' ) ); ?>');
      }
    })
    .catch(() => alert('<?php echo esc_js( __( 'Failed to add to cart', 'printing-pricing-calculator' ) ); ?>'));
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
        const wrap = sel.closest('.parameter-wrapper');
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
      if (!Array.isArray(groups) || !groups.length) return;

      groups.forEach(group => {
        const rows = Array.isArray(group.rows) ? group.rows : [];
        rows.forEach(row => {
          const tPid = parseInt(row.target_param_id || 0, 10);
          if (!tPid) return;

          const sel = document.getElementById('param_' + tPid);
          if (!sel) return;

          const tOid   = parseInt(row.target_option_id || 0, 10); // 0 => ANY
          const action = (row.action === 'hide') ? 'hide' : 'show';
          const wrap   = sel.closest('.parameter-wrapper');

          // --- Whole parameter (ANY) ---
          if (tOid === 0) {
            if (action === 'hide') {
              // Option A (disable param completely):
              sel.disabled = true;

              // Keep placeholder (index 0) enabled so selectedIndex=0 remains valid
              for (let i = 0; i < sel.options.length; i++) {
                sel.options[i].disabled = (i === 0) ? false : true;
              }

              // If user had selected something, silently reset to placeholder (no dispatch)
              if (sel.selectedIndex !== 0) sel.selectedIndex = 0;

              // Or, if you prefer to hide the whole block instead of disabling:
              // if (wrap) wrap.classList.add('hidden');

            } else {
              sel.disabled = false;
              for (let i = 0; i < sel.options.length; i++) {
                sel.options[i].disabled = false;
              }
              if (wrap) wrap.classList.remove('hidden');
            }
            return; // done for ANY
          }

          // --- Specific option within the select ---
          for (let i = 0; i < sel.options.length; i++) {
            const opt = sel.options[i];
            const oid = parseInt(opt.getAttribute('data-option-id') || '0', 10);
            if (oid !== tOid) continue;

            if (action === 'hide') {
              opt.disabled = true;

              // If that option was selected, silently reset to placeholder
              const selectedOpt = sel.options[sel.selectedIndex];
              const selectedOid = selectedOpt ? parseInt(selectedOpt.getAttribute('data-option-id') || '0', 10) : 0;
              if (selectedOid === tOid) sel.selectedIndex = 0;
            } else {
              opt.disabled = false;
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
    // document.querySelectorAll('select[name^="parameters"]').forEach(sel => {
    //     sel.addEventListener('change', evaluateConditions);
    // });
    })();

});
</script>

<script>
(function () {
  const boxes = document.querySelectorAll('.ppc-zoom');

  boxes.forEach(box => {
    const img = box.querySelector('img');
    const zoom = parseFloat(getComputedStyle(box).getPropertyValue('--ppc-zoom')) || 2;
    let locked = false;

    const setOrigin = (e) => {
      const r = box.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width) * 100;
      const y = ((e.clientY - r.top) / r.height) * 100;
      img.style.transformOrigin = `${x}% ${y}%`;
    };
    const scaleTo = (s) => { img.style.transform = `scale(${s})`; };

    // Hover zoom (follows cursor)
    box.addEventListener('mouseenter', (e) => { setOrigin(e); if (!locked) scaleTo(zoom); });
    box.addEventListener('mousemove',  (e) => { setOrigin(e); if (!locked) scaleTo(zoom); });
    box.addEventListener('mouseleave', () => { if (!locked) scaleTo(1); });

    // Click to lock/unlock zoom (useful on mobile / detailed viewing)
    box.addEventListener('click', (e) => {
      locked = !locked;
      if (locked) {
        setOrigin(e);
        scaleTo(zoom);
        box.classList.remove('cursor-zoom-in');
        box.classList.add('cursor-zoom-out');
      } else {
        scaleTo(1);
        box.classList.add('cursor-zoom-in');
        box.classList.remove('cursor-zoom-out');
      }
    });
  });
})();

document.addEventListener("DOMContentLoaded", function() {
    const input = document.getElementById("ppc-file-upload");
    const dropZone = document.getElementById("ppc-file-upload-wrapper");
    // Locate the "file bar" (the row with filename on left and trash on right) 
    const wrapper = dropZone ? dropZone.parentElement : null;
    const fileBar = (wrapper && dropZone) ? Array.from(wrapper.children).find(el => el !== dropZone) : null;
    // Spots inside the file bar 
    const nameSpan = fileBar?.querySelector("div > span:first-child") || null;
    const paperclipHTML = nameSpan?.querySelector("i") ? nameSpan.querySelector("i").outerHTML : "";
    const trashContainer = fileBar?.querySelector("div > span:last-child") || null;
    const DEFAULT_LABEL = "<?php echo esc_js( __( 'No File Selected', 'printing-pricing-calculator' ) ); ?>";

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        } [m]));
    }

    function setLabel(text) {
        if (!nameSpan) return;
        const safe = text ? escapeHtml(text) : DEFAULT_LABEL;
        nameSpan.innerHTML = (paperclipHTML ? paperclipHTML + " " : "") + safe;
    }

    function toggleTrash(show) {
        if (!trashContainer) return;
        trashContainer.style.display = show ? "" : "none";
        trashContainer.setAttribute("aria-hidden", show ? "false" : "true");
    }

    function clearInput() {
        if (!input) return;
        input.value = "";
        setLabel(DEFAULT_LABEL);
        toggleTrash(false);
        input.dispatchEvent(new Event("change", {
            bubbles: true
        }));
    }
    // Input change -> show file name and toggle trash 
    if (input) {
        input.addEventListener("change", () => {
            const file = input.files && input.files[0];
            setLabel(file ? file.name : DEFAULT_LABEL);
            toggleTrash(!!file);
        });
    }
    // Drag & Drop handlers 
    if (dropZone && input) {
        const highlightOn = () => dropZone.classList.add("border-blue-400", "bg-blue-50");
        const highlightOff = () => dropZone.classList.remove("border-blue-400", "bg-blue-50");
        ["dragenter", "dragover"].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) e.dataTransfer.dropEffect = "copy";
                highlightOn();
            });
        });
        ["dragleave", "dragend"].forEach(evt => {
            dropZone.addEventListener(evt, e => {
                e.preventDefault();
                e.stopPropagation();
                highlightOff();
            });
        });
        dropZone.addEventListener("drop", e => {
            e.preventDefault();
            e.stopPropagation();
            highlightOff();
            const files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) return;
            // Only the first file (input isn't multiple)
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            input.dispatchEvent(new Event("change", {
                bubbles: true
            }));
        });
    }
    // Clicking the trash icon clears the input
    if (fileBar) {
        fileBar.addEventListener("click", e => {
            if (e.target && e.target.closest(".fa-trash")) {
                e.preventDefault();
                clearInput();
            }
        });
    }
    // Initialize on load
    const initialFile = input && input.files && input.files[0];
    setLabel(initialFile ? initialFile.name : DEFAULT_LABEL);
    toggleTrash(!!initialFile);
});
</script>
