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
?>
<div class="wrap">
    <h1><?php echo isset($_GET['id']) ? 'Edit' : 'Add'; ?> Product</h1>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success"><p>Product saved successfully.</p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('save_product'); ?>

        <h2 class="wp-heading-inline">Title</h2>
        <input type="text" name="title" class="widefat" value="<?php echo esc_attr($data['title']); ?>" required />
        <h2 class="wp-heading-inline">Slug</h2>
        <input type="text" name="slug" class="widefat" value="<?php echo esc_attr($data['slug']); ?>" />

        <h2 class="wp-heading-inline">Content</h2>
        <?php
            wp_editor($data['content'], 'content', [
                'textarea_name' => 'content',
                'textarea_rows' => 6,
            ]);
        ?>

        <h2 class="wp-heading-inline">Base Price</h2>
        <input type="number" step="0.01" name="base_price" class="regular-text" value="<?php echo esc_attr($data['base_price']); ?>" required />

        <h2 class="wp-heading-inline">Express Delivery Charges</h2>
        <input type="number" step="0.01" name="express_delivery_value" class="regular-text" value="<?php echo esc_attr($data['express_delivery_value'] ?? ''); ?>" />
        <select name="express_delivery_type">
            <option value="percent" <?php selected(($data['express_delivery_type'] ?? '') === 'percent'); ?>>Percent (%)</option>
            <option value="flat" <?php selected(($data['express_delivery_type'] ?? '') === 'flat'); ?>>Flat Amount</option>
        </select>
        <p class="description">Leave blank to use the global setting.</p>

        <h2 class="wp-heading-inline">Minimum Order Quantity</h2>
        <input type="number" step="1" name="min_order_qty" class="regular-text" value="<?php echo esc_attr(isset($data['min_order_qty']) ? $data['min_order_qty'] : ''); ?>"/>

        <h2 class="wp-heading-inline">Status</h2>
        <select name="status">
            <option value="active"                                   <?php selected($data['status'], 'active'); ?>>Active</option>
            <option value="inactive"                                     <?php selected($data['status'], 'inactive'); ?>>Inactive</option>
        </select>

        <h2 class="wp-heading-inline">Base Product Image</h2>
        <?php if (! empty($data['image_url'])): ?>
            <img src="<?php echo esc_url($data['image_url']); ?>" style="max-width: 150px; display:block; margin-bottom: 10px;" />
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/*" />
        <input type="hidden" name="image_url" value="<?php echo esc_attr($data['image_url']); ?>" />

        <h2 class="wp-heading-inline">Product-Specific Discount Rules</h2>
        <table id="discount-rules-table" class="widefat fixed striped" style="max-width:600px;">
            <thead>
                <tr>
                    <th>Quantity ≥</th>
                    <th>Discount (%)</th>
                    <th>Action</th>
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
                            <button type="button" class="button remove-discount-rule">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><button type="button" class="button" id="add-discount-rule">Add Rule</button></td>
                </tr>
            </tfoot>
        </table>
        <p class="description">Set discount percentages for quantity breakpoints (leave empty for no product-specific discount; global will be used).</p>

        <h2 class="wp-heading-inline">File Check Service</h2>
        <table class="form-table" style="max-width:600px;">
            <tr>
                <th><label for="file_check_price">File Check Price</label></th>
                <td>
                    <input type="number" step="0.01" min="0" name="file_check_price" id="file_check_price" value="<?php echo esc_attr($data['file_check_price'] ?? ''); ?>" class="regular-text" />
                    <span class="description">Leave blank to use global setting.</span>
                </td>
            </tr>
            <tr>
                <th><label for="file_check_required">File Check Required?</label></th>
                <td>
                    <select name="file_check_required" id="file_check_required">
                        <option value="0" <?php selected($data['file_check_required'] ?? 0, 0); ?>>Optional</option>
                        <option value="1" <?php selected($data['file_check_required'] ?? 0, 1); ?>>Required</option>
                    </select>
                </td>
            </tr>
        </table>


        <h2 class="wp-heading-inline">Selected Parameters</h2>
        
        <div id="ppc-selected-params">
            <?php foreach ($selectedParameters as $param): ?>
                <?php include __DIR__ . '/param-row.php'; ?>
            <?php endforeach; ?>
        </div>
        <h2 class="wp-heading-inline">Search Parameters</h2>
        <div style="margin-bottom:16px;">
            <input type="text" id="ppc-param-search" class="regular-text" placeholder="Search parameters..." autocomplete="off" />
        </div>
        <div id="ppc-param-search-results"></div>

        <p class="submit">
            <button type="submit" class="button button-primary">Save Product</button>
        </p>
    </form>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('discount-rules-table').getElementsByTagName('tbody')[0];
        document.getElementById('add-discount-rule').addEventListener('click', function() {
            var row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" name="discount_qty[]" min="1" class="small-text" /></td>
                <td><input type="number" name="discount_percent[]" min="0" max="100" step="0.01" class="small-text" /></td>
                <td><button type="button" class="button remove-discount-rule">Remove</button></td>
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
        $results.html('<em>Searching...</em>');
        $.post(ajaxurl, {
            action: 'ppc_param_search',
            search: val,
            exclude: selectedIds
        }, function(resp) {
            if (!resp.success || !resp.data || !resp.data.params) {
                $results.html('<em>No results</em>');
                return;
            }
            var html = '';
            if (resp.data.params.length) {
                resp.data.params.forEach(function(param) {
                    if (selectedIds.includes(parseInt(param.id))) return;
                    html += '<div class="ppc-param-search-row" data-param-id="' + param.id + '" style="padding:6px 0; border-bottom:1px #eee solid;">';
                    html += '<strong>' + param.title + '</strong> (' + param.front_name + ') <button type="button" class="button ppc-param-add" style="margin-left:10px;">Add</button>';
                    html += '</div>';
                });
            } else {
                html = '<em>No results</em>';
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
                alert('Could not load parameter.');
            }
        });
    });
})(jQuery);
</script>