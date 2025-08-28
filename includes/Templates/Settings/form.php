<div class="wrap">
    <h1><?php echo esc_html__( 'PPC Global Settings', 'printing-pricing-calculator' ); ?></h1>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ppc_settings_save'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__( 'Express Delivery Charges', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <input type="number" step="0.01" min="0" name="express_delivery_charges" value="<?php echo esc_attr(get_option('ppc_express_delivery_charges', 15)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Express Delivery Type', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <select name="express_delivery_type">
                        <option value="percent" <?php selected(get_option('ppc_express_delivery_type', 'percent'), 'percent'); ?>><?php echo esc_html__( 'Percent (%)', 'printing-pricing-calculator' ); ?></option>
                        <option value="flat" <?php selected(get_option('ppc_express_delivery_type', 'percent'), 'flat'); ?>><?php echo esc_html__( 'Flat Amount', 'printing-pricing-calculator' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Minimum Order Quantity', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <input type="number" min="1" name="minimum_order_quantity" value="<?php echo esc_attr(get_option('ppc_minimum_order_quantity', 100)); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'PDF Quotation Note', 'printing-pricing-calculator' ); ?></th>
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
                <th scope="row"><?php echo esc_html__( 'Tax Percentage (%)', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <input type="number" step="0.01" min="0" name="ppc_tax_percentage" value="<?php echo esc_attr(get_option('ppc_tax_percentage', '0')); ?>" class="regular-text" />
                    <span class="description"><?php echo esc_html__( 'Set to 0 for no tax.', 'printing-pricing-calculator' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Default Currency', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <select name="ppc_default_currency">
                        <option value="CZK" <?php selected(get_option('ppc_default_currency', 'CZK'), 'CZK'); ?>><?php echo esc_html__( 'CZK', 'printing-pricing-calculator' ); ?></option>
                        <option value="EUR" <?php selected(get_option('ppc_default_currency', 'CZK'), 'EUR'); ?>><?php echo esc_html__( 'EUR', 'printing-pricing-calculator' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'CZK to EUR Rate', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <input type="number" step="0.0001" min="0" name="ppc_czk_eur_rate" value="<?php echo esc_attr(get_option('ppc_czk_eur_rate', '0.042')); ?>" class="regular-text" />
                    <span class="description"><?php echo esc_html__( 'Example: 1 CZK = 0.042 EUR', 'printing-pricing-calculator' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Cart Expiration (days)', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <input type="number" min="1" name="ppc_cart_expiry_days"
                        value="<?php echo esc_attr(get_option('ppc_cart_expiry_days', 2)); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__( 'Custom product uploads & cart items will be deleted after this number of days if not ordered.', 'printing-pricing-calculator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ppc_file_check_price"><?php echo esc_html__( 'File Check Price', 'printing-pricing-calculator' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" min="0" name="ppc_file_check_price" id="ppc_file_check_price" value="<?php echo esc_attr(get_option($data['ppc_file_check_price'] ?? '')); ?>" class="regular-text" />
                    <span class="description"><?php echo esc_html__( 'Leave blank to use global setting.', 'printing-pricing-calculator' ); ?></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Discount Rules', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <table id="discount-rules-table" style="min-width:300px;">
                        <?php
                        $discounts = get_option('ppc_discount_rules', []);
                        if (!is_array($discounts)) $discounts = [];
                        $last_index = 0;
                        foreach ($discounts as $i => $rule): $last_index = $i; ?>
                            <tr>
                                <td>
                                    <input type="number" name="discount_qty[]" min="1" value="<?php echo esc_attr($rule['qty']); ?>" placeholder="<?php echo esc_attr__( 'Quantity', 'printing-pricing-calculator' ); ?>" style="width:70px;">
                                </td>
                                <td>
                                    <input type="number" name="discount_percent[]" min="0" max="100" step="0.01" value="<?php echo esc_attr($rule['percent']); ?>" placeholder="<?php echo esc_attr__( 'Discount %', 'printing-pricing-calculator' ); ?>" style="width:80px;">
                                </td>
                                <td>
                                    <button type="button" class="button remove-discount-rule"><?php echo esc_html__( 'Remove', 'printing-pricing-calculator' ); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3"><button type="button" class="button" id="add-discount-rule"><?php echo esc_html__( 'Add Rule', 'printing-pricing-calculator' ); ?></button></td>
                        </tr>
                    </table>
                    <p class="description"><?php echo esc_html__( 'Set discount percentages for quantity breakpoints (e.g., 200+ gets 10%). Sorted automatically, highest quantity first.', 'printing-pricing-calculator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__( 'Global Instructions (PDF)', 'printing-pricing-calculator' ); ?></th>
                <td>
                    <?php
                    $ppc_pdf_id  = (int) get_option('ppc_instructions_pdf_id', 0);
                    $ppc_pdf_url = $ppc_pdf_id ? wp_get_attachment_url($ppc_pdf_id) : '';
                    ?>
                    <input type="file" name="ppc_instructions_pdf" accept="application/pdf" />
                    <?php if ($ppc_pdf_url): ?>
                        <p><?php echo esc_html__( 'Current file:', 'printing-pricing-calculator' ); ?>
                            <a href="<?php echo esc_url($ppc_pdf_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'View/Download', 'printing-pricing-calculator' ); ?></a>
                        </p>
                        <label>
                            <input type="checkbox" name="ppc_instructions_pdf_remove" value="1">
                            <?php echo esc_html__( 'Remove current file', 'printing-pricing-calculator' ); ?>
                        </label>
                    <?php endif; ?>
                    <p class="description"><?php echo esc_html__( 'Upload a single PDF used as the global instructions file shown on all product pages.', 'printing-pricing-calculator' ); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="ppc_settings_save" class="button button-primary"><?php echo esc_html__( 'Save Settings', 'printing-pricing-calculator' ); ?></button>
        </p>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('add-discount-rule').addEventListener('click', function() {
            var row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" name="discount_qty[]" min="1" placeholder="<?php echo esc_attr__( 'Quantity', 'printing-pricing-calculator' ); ?>" style="width:70px;"></td>
                <td><input type="number" name="discount_percent[]" min="0" max="100" step="0.01" placeholder="<?php echo esc_attr__( 'Discount %', 'printing-pricing-calculator' ); ?>" style="width:80px;"></td>
                <td><button type="button" class="button remove-discount-rule"><?php echo esc_html__( 'Remove', 'printing-pricing-calculator' ); ?></button></td>
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