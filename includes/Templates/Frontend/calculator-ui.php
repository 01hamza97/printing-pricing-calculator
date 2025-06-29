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
                        class="border rounded px-3 py-1"
                        data-param-id="<?php echo $p['id']; ?>"
                        data-param-title="<?php echo esc_attr($p['title']); ?>"
                    >
                        <option value="" data-cost="0">Select an option</option>
                        <?php foreach ($p['options'] as $o): ?>
                            <?php
                            $cost = !empty($opt['override_price'])
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
                </div>
            <?php endforeach; ?>


            <!-- Parameter fields go here -->
        </div>
        <div class="flex flex-col gap-4">
            <div class="bg-blue-50 text-blue-900 rounded-lg px-6 py-5 text-center text-xl font-bold shadow">
              <div class="mb-3">
                  <label for="ppc-qty" class="font-medium block mb-1">Quantity</label>
                  <input 
                      type="number" 
                      value="<?php echo (int)$min_order_qty; ?>" 
                      id="ppc-qty" 
                      class="border rounded px-3 py-1 w-24 text-center"
                      min="<?php echo (int)$min_order_qty; ?>"
                  />
                  <small class="text-gray-500">Minimum order: <?php echo (int)$min_order_qty; ?></small>
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
                                echo '+'.number_format((float)$express_delivery['value'], 2);
                            }
                        ?>
                        )
                    </span>
                </label>
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
                    <tr>
                      <td>Base Price</td>
                      <td>1</td>
                      <td id="ppc-base-price"><?php echo number_format((float)$product['base_price'], 2); ?></td>
                    </tr>
                    <?php foreach ($parameters as $p): ?>
                      <tr data-param-id="<?php echo $p['id']; ?>">
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
            <div class="bg-gray-200 rounded-lg px-6 py-4 text-center cursor-pointer shadow">download calculation (pdf?)</div>
            <div class="bg-green-700 hover:bg-green-800 text-white rounded-lg px-6 py-6 text-center font-bold text-lg cursor-pointer shadow transition">CTA ORDER</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('select[name^="parameters"]');
    const qtyInput = document.getElementById('ppc-qty');
    const expressCheckbox = document.getElementById('ppc-express');
    const basePrice = parseFloat('<?php echo (float)$product['base_price']; ?>');
    const paramIds = <?php echo json_encode(array_column($parameters, 'id')); ?>;
    const paramsTable = document.getElementById('ppc-selected-params-table');
    const tax = <?php echo $tax; ?>;
    // These are PHP values printed into JS.
    const expressValue = <?php echo json_encode((float)$express_delivery['value']); ?>;
    const expressType = <?php echo json_encode($express_delivery['type']); ?>;
    const discountRules = <?php echo json_encode($discount_rules); ?>;

    function updateSummary() {
       let qty = Math.max(<?php echo (int)$min_order_qty; ?>, parseInt(qtyInput.value) || <?php echo (int)$min_order_qty; ?>);
       qtyInput.value = qty;

        let paramSum = 0;
        paramIds.forEach(paramId => {
            const sel = document.getElementById('param_' + paramId);
            const selectedOpt = sel.options[sel.selectedIndex];
            const optText = selectedOpt.text.replace(/\([^)]+\)/, '').trim();
            const optCost = parseFloat(selectedOpt.dataset.cost || 0);

            document.getElementById('ppc-selected-option-' + paramId).textContent =
                selectedOpt.value ? optText : '—';
            document.getElementById('ppc-option-cost-' + paramId).textContent =
                selectedOpt.value && optCost ? ' (' + (optCost * qty).toFixed(2) + ')' : '';

            if (selectedOpt.value && optCost) {
                paramSum += optCost;
            }
        });

        // Discount calculation
        let discount = getDiscountPercent(qty, discountRules)/100;

        const paramsTotal = paramSum * qty;
        const preDiscountTotal = basePrice + paramsTotal;
        const discountAmount = preDiscountTotal * discount;
        let finalTotal = preDiscountTotal - discountAmount;
        let taxAmount = 0;
        if (tax > 0) {
            taxAmount = finalTotal * (tax / 100);
            finalTotal += taxAmount;
        }

        document.getElementById('ppc-discount').textContent = '-' + discountAmount.toFixed(2);

        // Express Delivery: insert or remove row
        let expressAmount = 0;
        let expressRow = document.getElementById('ppc-express-row');
        if (expressCheckbox.checked) {
            if (expressType === 'percent') {
                expressAmount = finalTotal * (expressValue / 100);
            } else {
                expressAmount = expressValue;
            }
            finalTotal += expressAmount;
            if (tax > 0) {
                taxAmount = finalTotal * (tax / 100);
                finalTotal += taxAmount;
            }
            if (!expressRow) {
                expressRow = document.createElement('tr');
                expressRow.id = 'ppc-express-row';
                expressRow.innerHTML = `<td>Express Delivery (${expressType === 'percent' ? expressValue + '%' : '+' + expressValue})</td><td id="ppc-express-cost">+${expressAmount.toFixed(2)}</td>`;
                // Insert before discount row
                const discountRow = document.getElementById('ppc-discount').parentElement;
                paramsTable.insertBefore(expressRow, discountRow);
            } else {
                document.getElementById('ppc-express-cost').textContent = '+' + expressAmount.toFixed(2);
                expressRow.cells[0].textContent = `Express Delivery (${expressType === 'percent' ? expressValue + '%' : '+' + expressValue})`;
            }
        } else if (expressRow) {
            expressRow.remove();
        }

        document.getElementById('ppc-grand-total').textContent = finalTotal.toFixed(2);
        document.getElementById('ppc-tax-amount').textContent = taxAmount.toFixed(2);
    }

    selects.forEach(sel => sel.addEventListener('change', updateSummary));
    qtyInput.addEventListener('input', updateSummary);
    expressCheckbox.addEventListener('change', updateSummary);
    updateSummary();
});

function getDiscountPercent(qty, discountRules) {
  for (var i = 0; i < discountRules.length; i++) {
    if (qty >= parseInt(discountRules[i].qty)) return parseFloat(discountRules[i].percent);
  }
  return 0;
}
</script>
