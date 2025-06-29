<div class="wrap">
    <h1>PPC Global Settings</h1>
    <form method="post">
        <?php wp_nonce_field('ppc_settings_save'); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Express Delivery Charges</th>
                <td>
                    <input type="number" step="0.01" min="0" name="express_delivery_charges" value="<?php echo esc_attr(get_option('ppc_express_delivery_charges', 15)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">Express Delivery Type</th>
                <td>
                    <select name="express_delivery_type">
                        <option value="percent" <?php selected(get_option('ppc_express_delivery_type', 'percent'), 'percent'); ?>>Percent (%)</option>
                        <option value="flat" <?php selected(get_option('ppc_express_delivery_type', 'percent'), 'flat'); ?>>Flat Amount</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Minimum Order Quantity</th>
                <td>
                    <input type="number" min="1" name="minimum_order_quantity" value="<?php echo esc_attr(get_option('ppc_minimum_order_quantity', 100)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">PDF Quotation Note</th>
                <td>
                    <?php
                        wp_editor(get_option('ppc_pdf_quotation_note', ''), 'pdf_quotation_note', [
                            'textarea_name' => 'pdf_quotation_note',
                            'textarea_rows' => 6,
                            'media_buttons' => false,
                        ]);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Tax Percentage (%)</th>
                <td>
                    <input type="number" step="0.01" min="0" name="ppc_tax_percentage" value="<?php echo esc_attr(get_option('ppc_tax_percentage', '0')); ?>" class="regular-text" />
                    <span class="description">Set to 0 for no tax.</span>
                </td>
            </tr>
            <tr>
                <th scope="row">Default Currency</th>
                <td>
                    <select name="ppc_default_currency">
                        <option value="CZK" <?php selected(get_option('ppc_default_currency', 'CZK'), 'CZK'); ?>>CZK</option>
                        <option value="EUR" <?php selected(get_option('ppc_default_currency', 'CZK'), 'EUR'); ?>>EUR</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">CZK to EUR Rate</th>
                <td>
                    <input type="number" step="0.0001" min="0" name="ppc_czk_eur_rate" value="<?php echo esc_attr(get_option('ppc_czk_eur_rate', '0.042')); ?>" class="regular-text" />
                    <span class="description">Example: 1 CZK = 0.042 EUR</span>
                </td>
            </tr>
            <tr>
                <th scope="row">Discount Rules</th>
                <td>
                    <table id="discount-rules-table" style="min-width:300px;">
                        <?php
                        $discounts = get_option('ppc_discount_rules', []);
                        if (!is_array($discounts)) $discounts = [];
                        $last_index = 0;
                        foreach ($discounts as $i => $rule): $last_index = $i; ?>
                            <tr>
                                <td>
                                    <input type="number" name="discount_qty[]" min="1" value="<?php echo esc_attr($rule['qty']); ?>" placeholder="Quantity" style="width:70px;">
                                </td>
                                <td>
                                    <input type="number" name="discount_percent[]" min="0" max="100" step="0.01" value="<?php echo esc_attr($rule['percent']); ?>" placeholder="Discount %" style="width:80px;">
                                </td>
                                <td>
                                    <button type="button" class="button remove-discount-rule">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3"><button type="button" class="button" id="add-discount-rule">Add Rule</button></td>
                        </tr>
                    </table>
                    <p class="description">Set discount percentages for quantity breakpoints (e.g., 200+ gets 10%). Sorted automatically, highest quantity first.</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="ppc_settings_save" class="button button-primary">Save Settings</button>
        </p>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('add-discount-rule').addEventListener('click', function() {
            var row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" name="discount_qty[]" min="1" placeholder="Quantity" style="width:70px;"></td>
                <td><input type="number" name="discount_percent[]" min="0" max="100" step="0.01" placeholder="Discount %" style="width:80px;"></td>
                <td><button type="button" class="button remove-discount-rule">Remove</button></td>
            `;
            this.parentElement.parentElement.parentElement.insertBefore(row, this.parentElement.parentElement);
        });
        document.querySelectorAll('.remove-discount-rule').forEach(function(btn) {
            btn.addEventListener('click', function() {
                this.closest('tr').remove();
            });
        });
        document.getElementById('discount-rules-table').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-discount-rule')) {
                e.target.closest('tr').remove();
            }
        });
    });
</script>