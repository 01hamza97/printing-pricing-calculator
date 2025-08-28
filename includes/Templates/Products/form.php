<?php
    // Helper for param data (object or array)
    function ppc_get_param($params, $id, $key) {
        foreach ($params as $p) {
            if ((is_object($p) && $p->parameter_id == $id) || (is_array($p) && $p['parameter_id'] == $id)) {
                return is_object($p) ? $p->$key : $p[$key];
            }
        }
        return null;
    }

    $assigned_set = array_map('intval', $assigned_category_ids ?? []);
?>
<div class="wrap">
    <?php if (isset($_GET['duplicated'])): ?>
        <div class="notice notice-success"><p><?php echo esc_html__( 'Product duplicated successfully. You are editing the new copy.', 'printing-pricing-calculator' ); ?></p></div>
    <?php endif; ?>
    <h1><?php echo isset($_GET['id']) ? esc_html__( 'Edit', 'printing-pricing-calculator' ) : esc_html__( 'Add', 'printing-pricing-calculator' ); ?> <?php echo esc_html__( 'Product', 'printing-pricing-calculator' ); ?></h1>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success"><p><?php echo esc_html__( 'Product saved successfully.', 'printing-pricing-calculator' ); ?></p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('save_product'); ?>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Title', 'printing-pricing-calculator' ); ?></h2>
        <input type="text" name="title" class="widefat" value="<?php echo esc_attr($data['title']); ?>" required />
        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Slug', 'printing-pricing-calculator' ); ?></h2>
        <input type="text" name="slug" class="widefat" value="<?php echo esc_attr($data['slug']); ?>" />

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Content', 'printing-pricing-calculator' ); ?></h2>
        <?php
            wp_editor($data['content'], 'content', [
                'textarea_name' => 'content',
                'textarea_rows' => 6,
            ]);
        ?>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Base Price', 'printing-pricing-calculator' ); ?></h2>
        <input type="number" step="0.01" name="base_price" class="regular-text" value="<?php echo esc_attr($data['base_price']); ?>" required />

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Express Delivery Charges', 'printing-pricing-calculator' ); ?></h2>
        <input type="number" step="0.01" name="express_delivery_value" class="regular-text" value="<?php echo esc_attr($data['express_delivery_value'] ?? ''); ?>" />
        <select name="express_delivery_type">
            <option value="percent" <?php selected(($data['express_delivery_type'] ?? '') === 'percent'); ?>><?php echo esc_html__( 'Percent (%)', 'printing-pricing-calculator' ); ?></option>
            <option value="flat" <?php selected(($data['express_delivery_type'] ?? '') === 'flat'); ?>><?php echo esc_html__( 'Flat Amount', 'printing-pricing-calculator' ); ?></option>
        </select>
        <p class="description"><?php echo esc_html__( 'Leave blank to use the global setting.', 'printing-pricing-calculator' ); ?></p>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Minimum Order Quantity', 'printing-pricing-calculator' ); ?></h2>
        <input type="number" step="1" name="min_order_qty" class="regular-text" value="<?php echo esc_attr(isset($data['min_order_qty']) ? $data['min_order_qty'] : ''); ?>"/>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Status', 'printing-pricing-calculator' ); ?></h2>
        <select name="status">
            <option value="active"   <?php selected($data['status'], 'active'); ?>><?php echo esc_html__( 'Active', 'printing-pricing-calculator' ); ?></option>
            <option value="inactive" <?php selected($data['status'], 'inactive'); ?>><?php echo esc_html__( 'Inactive', 'printing-pricing-calculator' ); ?></option>
        </select>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Base Product Image', 'printing-pricing-calculator' ); ?></h2>
        <?php if (! empty($data['image_url'])): ?>
            <img src="<?php echo esc_url($data['image_url']); ?>" style="max-width: 150px; display:block; margin-bottom: 10px;" alt="<?php echo esc_attr__( 'Product Image', 'printing-pricing-calculator' ); ?>" />
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/*" />
        <input type="hidden" name="image_url" value="<?php echo esc_attr($data['image_url']); ?>" />

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Product Instructions File', 'printing-pricing-calculator' ); ?></h2>
        <?php
            $ppc_pdf_id  = $data['instructions_file_id'];
            $ppc_pdf_url = $ppc_pdf_id ? wp_get_attachment_url($ppc_pdf_id) : '';
        ?>
        <input type="file" name="ppc_instructions_pdf" accept="application/pdf" />
        <?php if ($ppc_pdf_url): ?>
            <p><?php echo esc_html__( 'Current file:', 'printing-pricing-calculator' ); ?> <a href="<?php echo esc_url($ppc_pdf_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'View/Download', 'printing-pricing-calculator' ); ?></a></p>
            <label>
                <input type="checkbox" name="ppc_instructions_pdf_remove" value="1">
                <?php echo esc_html__( 'Remove current file', 'printing-pricing-calculator' ); ?>
            </label>
        <?php endif; ?>
        <p class="description"><?php echo esc_html__( 'Upload a single PDF used as the global instructions file shown on all product pages.', 'printing-pricing-calculator' ); ?></p>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Product-Specific Discount Rules', 'printing-pricing-calculator' ); ?></h2>
        <table id="discount-rules-table" class="widefat fixed striped" style="max-width:600px;">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Quantity ≥', 'printing-pricing-calculator' ); ?></th>
                    <th><?php echo esc_html__( 'Discount (%)', 'printing-pricing-calculator' ); ?></th>
                    <th><?php echo esc_html__( 'Action', 'printing-pricing-calculator' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $discounts = $data['discount_rules'] ?? [];
                if (!is_array($discounts)) $discounts = [];
                foreach ($discounts as $i => $rule): ?>
                    <tr>
                        <td>
                            <input type="number" name="discount_qty[]" min="1" value="<?php echo esc_attr($rule['qty']); ?>" class="small-text" />
                        </td>
                        <td>
                            <input type="number" name="discount_percent[]" min="0" max="100" step="0.01" value="<?php echo esc_attr($rule['percent']); ?>" class="small-text" />
                        </td>
                        <td>
                            <button type="button" class="button remove-discount-rule"><?php echo esc_html__( 'Remove', 'printing-pricing-calculator' ); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><button type="button" class="button" id="add-discount-rule"><?php echo esc_html__( 'Add Rule', 'printing-pricing-calculator' ); ?></button></td>
                </tr>
            </tfoot>
        </table>
        <p class="description"><?php echo esc_html__( 'Set discount percentages for quantity breakpoints (leave empty for no product-specific discount; global will be used).', 'printing-pricing-calculator' ); ?></p>

        <h2 class="wp-heading-inline"><?php echo esc_html__( 'File Check Service', 'printing-pricing-calculator' ); ?></h2>
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th><label for="file_check_price"><?php echo esc_html__( 'File Check Price', 'printing-pricing-calculator' ); ?></label></th>
                <td>
                    <input type="number" step="0.01" min="0" name="file_check_price" id="file_check_price" value="<?php echo esc_attr($data['file_check_price'] ?? ''); ?>" class="regular-text" />
                    <span class="description"><?php echo esc_html__( 'Leave blank to use global setting.', 'printing-pricing-calculator' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="file_check_required"><?php echo esc_html__( 'File Check Required?', 'printing-pricing-calculator' ); ?></label></th>
                <td>
                    <select name="file_check_required" id="file_check_required">
                        <option value="0" <?php selected($data['file_check_required'] ?? 0, 0); ?>><?php echo esc_html__( 'Optional', 'printing-pricing-calculator' ); ?></option>
                        <option value="1" <?php selected($data['file_check_required'] ?? 0, 1); ?>><?php echo esc_html__( 'Required', 'printing-pricing-calculator' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><span><?php echo esc_html__( 'Categories', 'printing-pricing-calculator' ); ?></span></h2>
            </div>
            <div class="inside">
                <?php if (!empty($all_categories)): ?>
                <label for="ppc-category-select" class="screen-reader-text"><?php echo esc_html__( 'Categories', 'printing-pricing-calculator' ); ?></label>
                <select
                    id="ppc-category-select"
                    name="category_ids[]"
                    multiple
                    style="max-width: 520px; width: 100%;"
                >
                    <?php foreach ($all_categories as $cat): ?>
                    <?php
                        $cid   = (int) $cat['id'];
                        $label = $cat['name'] . ' (' . $cat['slug'] . ')';
                    ?>
                    <option value="<?php echo $cid; ?>" <?php selected(in_array($cid, $assigned_set, true)); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="margin-top:6px;"><?php echo esc_html__( 'Type to search, select multiple. Use Backspace or the “x” to remove.', 'printing-pricing-calculator' ); ?></p>
                <?php else: ?>
                <p><em><?php echo esc_html__( 'No active categories found.', 'printing-pricing-calculator' ); ?></em></p>
                <?php endif; ?>
            </div>
        </div>


        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Selected Parameters', 'printing-pricing-calculator' ); ?></h2>
        
        <div id="ppc-selected-params">
            <?php foreach ($selectedParameters as $param): ?>
                <?php include __DIR__ . '/param-row.php'; ?>
            <?php endforeach; ?>

            <!-- <button type="button" id="save-param-order" class="button button-secondary">Save Parameter Order</button> -->
        </div>
        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Search Parameters', 'printing-pricing-calculator' ); ?></h2>
        <div style="margin-bottom:16px;">
            <input type="text" id="ppc-param-search" class="regular-text" placeholder="<?php echo esc_attr__( 'Search parameters...', 'printing-pricing-calculator' ); ?>" autocomplete="off" />
        </div>
        <div id="ppc-param-search-results"></div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Product', 'printing-pricing-calculator' ); ?></button>
        </p>
    </form>
</div>
<style>
/* Highlight on drag */
.ppc-param-placeholder {
    border: 2px dashed #2196F3;
    background: #e3f2fd;
    min-height: 48px;
}
.ppc-param-row { transition: box-shadow 0.1s; }
.ppc-param-row.ui-sortable-helper { box-shadow: 0 4px 12px rgba(60,60,100,0.08); }

.ppc-param-toggle {
    transition: transform 0.2s;
}
.ppc-param-toggle.rotated {
    transform: rotate(180deg);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('discount-rules-table').getElementsByTagName('tbody')[0];
        document.getElementById('add-discount-rule').addEventListener('click', function() {
            var row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" name="discount_qty[]" min="1" class="small-text" /></td>
                <td><input type="number" name="discount_percent[]" min="0" max="100" step="0.01" class="small-text" /></td>
                <td><button type="button" class="button remove-discount-rule"><?php echo esc_js( __( 'Remove', 'printing-pricing-calculator' ) ); ?></button></td>
            `;
            table.appendChild(row);
        });
        table.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-discount-rule')) {
                e.target.closest('tr').remove();
            }
        });
    });
</script>
<script>
(function($) {
    // Track selected parameter IDs
    var selectedIds = <?php echo json_encode(array_column($selectedParameters, 'id')); ?>;
    var $search = $('#ppc-param-search');
    var $results = $('#ppc-param-search-results');
    var $selected = $('#ppc-selected-params');

    // Remove handler
    $selected.on('click', '.ppc-param-remove', function() {
        var $row = $(this).closest('.ppc-param-row');
        var paramId = $row.data('param-id');
        $row.remove();
        selectedIds = selectedIds.filter(function(id) { return id != paramId; });
    });

    // AJAX search
    $search.on('input', function() {
        var val = $(this).val().trim();
        if (val.length < 2) {
            $results.html('');
            return;
        }
        $results.html('<em><?php echo esc_js( __( 'Searching...', 'printing-pricing-calculator' ) ); ?></em>');
        $.post(ajaxurl, {
            action: 'ppc_param_search',
            search: val,
            exclude: selectedIds
        }, function(resp) {
            if (!resp.success || !resp.data || !resp.data.params) {
                $results.html('<em><?php echo esc_js( __( 'No results', 'printing-pricing-calculator' ) ); ?></em>');
                return;
            }
            var html = '';
            if (resp.data.params.length) {
                resp.data.params.forEach(function(param) {
                    if (selectedIds.includes(parseInt(param.id))) return;
                    html += '<div class="ppc-param-search-row" data-param-id="' + param.id + '" style="padding:6px 0; border-bottom:1px #eee solid;">';
                    html += '<strong>' + param.title + '</strong> (' + param.front_name + ') <button type="button" class="button ppc-param-add" style="margin-left:10px;"><?php echo esc_js( __( 'Add', 'printing-pricing-calculator' ) ); ?></button>';
                    html += '</div>';
                });
            } else {
                html = '<em><?php echo esc_js( __( 'No results', 'printing-pricing-calculator' ) ); ?></em>';
            }
            $results.html(html);
        });
    });

    // Handler for adding a parameter (fetch row markup via AJAX)
    $results.on('click', '.ppc-param-add', function() {
        var $row = $(this).closest('.ppc-param-search-row');
        var paramId = $row.data('param-id');
        if (selectedIds.includes(paramId)) return;

        // Fetch parameter markup from server
        $.post(ajaxurl, {
            action: 'ppc_param_row_markup',
            param_id: paramId
        }, function(resp) {
            if (resp.success && resp.data && resp.data.html) {
                $selected.append(resp.data.html);
                selectedIds.push(paramId);
                $row.remove();
            } else {
                alert('<?php echo esc_js( __( 'Could not load parameter.', 'printing-pricing-calculator' ) ); ?>');
            }
        });
    });
})(jQuery);
</script>

<script>
    jQuery(document).ready(function($){
        function updateParamPositions() {
            $('#ppc-selected-params .ppc-param-row').each(function(i) {
                $(this).find('.ppc-param-position').val(i);
            });
        }

        // When sorting stops, update the positions
        $('#ppc-selected-params').sortable({
            handle: '.ppc-param-drag',
            items: '.ppc-param-row',
            placeholder: 'ppc-param-placeholder',
            forcePlaceholderSize: true,
            cursor: 'move',
            stop: function(event, ui) {
                updateParamPositions();
            }
        });

        // Also update on page load (for any reason)
        updateParamPositions();

        // Handle "Save Parameter Order" button click
        $('#save-param-order').on('click', function(){
            // Build data array
            var paramIds = [];
            var positions = [];
            $('#ppc-selected-params .ppc-param-row').each(function(){
                paramIds.push($(this).data('param-id'));
                positions.push($(this).find('.ppc-param-position').val());
            });

            // Send AJAX request to save order
            $.post(ajaxurl, {
                action: 'ppc_save_param_order',
                product_id: <?php echo json_encode($_GET['id'] ?? 0); ?>,
                param_ids: paramIds,
                positions: positions
            }, function(resp){
                if (resp.success) {
                    alert('<?php echo esc_js( __( 'Parameter order saved!', 'printing-pricing-calculator' ) ); ?>');
                } else {
                    alert('<?php echo esc_js( __( 'Failed to save order.', 'printing-pricing-calculator' ) ); ?>');
                }
            });
        });
        // Accordion toggle
        $('#ppc-selected-params').on('click', '.ppc-param-header', function(e){
            // Prevent drag, remove from triggering accordion
            if ($(e.target).hasClass('ppc-param-drag') || $(e.target).hasClass('ppc-param-remove')) return;
            var $row = $(this).closest('.ppc-param-row');
            $row.find('.ppc-param-details').slideToggle(120);
            $row.find('.ppc-param-toggle').toggleClass('rotated');
        });
    });
    
</script>
