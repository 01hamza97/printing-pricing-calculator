<!-- templates/frontend/calculator-ui.php -->
<div class="w-5/6 mx-auto my-8 font-sans">
    <div class="mb-6">
        <img src="<?php echo $product['image_url']; ?>" class="w-full rounded-lg object-cover max-h-44" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="col-span-2 bg-gray-50 rounded-lg p-6 shadow">
            <h4 class="text-lg font-semibold mb-4">Parameters</h4>
            <?php foreach ($parameters as $param): ?>
                <div class="mb-6">
                    <label for="param_<?php echo $param['id']; ?>" class="font-semibold text-base block mb-1">
                        <?php echo esc_html($param['title']); ?>
                    </label>
                    <select
                        id="param_<?php echo $param['id']; ?>"
                        name="parameters[<?php echo $param['id']; ?>]"
                        class="border rounded px-3 py-1"
                        data-param-id="<?php echo $param['id']; ?>"
                        data-param-title="<?php echo esc_attr($param['title']); ?>"
                    >
                        <option value="" data-cost="0">Select an option</option>
                        <?php foreach ($param['options'] as $opt): ?>
                            <?php
                            $cost = !empty($opt['override_price'])
                                ? $opt['override_price']
                                : (!empty($opt['meta_value']['cost']) ? $opt['meta_value']['cost'] : 0);
                            ?>
                            <option value="<?php echo esc_attr($opt['meta_value']['option'] ?? ''); ?>"
                                    data-cost="<?php echo esc_attr($cost); ?>">
                                <?php echo esc_html($opt['meta_value']['option'] ?? ''); ?>
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
                <input type="number" value="100" id="ppc-qty" class="border rounded px-3 py-1 w-24 text-center" />
              </div>
              <div class="mb-3">
                  <label class="inline-flex items-center">
                      <input type="checkbox" id="ppc-express" class="accent-green-700 mr-2" />
                      <span class="font-medium">Express Delivery (+15%)</span>
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
                    <?php foreach ($parameters as $param): ?>
                      <tr data-param-id="<?php echo $param['id']; ?>">
                        <td><?php echo esc_html($param['title']); ?>(<span class="ppc-selected-option" id="ppc-selected-option-<?php echo $param['id']; ?>">—</span>)</td>
                        <td>1</td>
                        <td>
                          <span class="ppc-option-cost text-gray-500" id="ppc-option-cost-<?php echo $param['id']; ?>">0.00</span>
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

    function updateSummary() {
        let qty = Math.max(100, parseInt(qtyInput.value) || 100);
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
        let discount = 0;
        if (qty === 200) discount = 0.10;
        else if (qty > 250) discount = 0.15;

        const paramsTotal = paramSum * qty;
        const preDiscountTotal = basePrice + paramsTotal;
        const discountAmount = preDiscountTotal * discount;
        let finalTotal = preDiscountTotal - discountAmount;

        document.getElementById('ppc-discount').textContent = '-' + discountAmount.toFixed(2);

        // Express Delivery: insert or remove row
        let expressAmount = 0;
        let expressRow = document.getElementById('ppc-express-row');
        if (expressCheckbox.checked) {
            expressAmount = finalTotal * 0.15;
            finalTotal += expressAmount;
            if (!expressRow) {
                expressRow = document.createElement('tr');
                expressRow.id = 'ppc-express-row';
                expressRow.innerHTML = `<td>Express Delivery (+15%)</td><td id=\"ppc-express-cost\">+${expressAmount.toFixed(2)}</td>`;
                // Insert before discount row
                const discountRow = document.getElementById('ppc-discount').parentElement;
                paramsTable.insertBefore(expressRow, discountRow);
            } else {
                document.getElementById('ppc-express-cost').textContent = '+' + expressAmount.toFixed(2);
            }
        } else if (expressRow) {
            expressRow.remove();
        }

        document.getElementById('ppc-grand-total').textContent = finalTotal.toFixed(2);
    }

    selects.forEach(sel => sel.addEventListener('change', updateSummary));
    qtyInput.addEventListener('input', updateSummary);
    expressCheckbox.addEventListener('change', updateSummary);
    updateSummary();
});
</script>
