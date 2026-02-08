<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__( 'Products', 'printing-pricing-calculator' ); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=ppc-product-edit'); ?>" class="page-title-action">
        <?php echo esc_html__( 'Add New', 'printing-pricing-calculator' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php
    global $wpdb;

    // Fetch categories for filter dropdown
    $categories = $wpdb->get_results("SELECT id, name FROM " . CATEGORY_TABLE . " ORDER BY name ASC", ARRAY_A);
    ?>

    <div style="display: flex; margin-bottom: 20px; flex-wrap: wrap; gap: 20px;">
        <form method="get" style="display: flex; align-items: center; gap: 8px;">
            <input type="hidden" name="page" value="ppc-products">
            <input type="search" name="s" placeholder="<?php echo esc_attr__( 'Search title...', 'printing-pricing-calculator' ); ?>" value="<?php echo esc_attr($search); ?>">

            <!-- Status Filter -->
            <select name="status">
                <option value=""><?php echo esc_html__( 'All Statuses', 'printing-pricing-calculator' ); ?></option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>
                    <?php echo esc_html__( 'Active', 'printing-pricing-calculator' ); ?>
                </option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>
                    <?php echo esc_html__( 'Inactive', 'printing-pricing-calculator' ); ?>
                </option>
            </select>

            <!-- Category Filter -->
            <select name="category" style="min-width:180px;">
                <option value=""><?php echo esc_html__( 'All Categories', 'printing-pricing-calculator' ); ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat['id']); ?>" <?php selected($category_filter, $cat['id']); ?>>
                        <?php echo esc_html($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button"><?php echo esc_html__( 'Filter', 'printing-pricing-calculator' ); ?></button>
        </form>

        <!-- Export Buttons -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="<?php echo admin_url('admin-post.php?action=ppc_export_products_flat'); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Export Flat CSV', 'printing-pricing-calculator' ); ?>
            </a>
            <a href="<?php echo admin_url('admin-post.php?action=ppc_export_products_zip'); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Export Full Data (ZIP of CSVs)', 'printing-pricing-calculator' ); ?>
            </a>
        </div>

        <!-- Import -->
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>" style="display:flex; align-items:center; gap:8px;">
            <?php wp_nonce_field('ppc_import_zip'); ?>
            <input type="file" name="ppc_import_zip" accept=".zip" required>
            <input type="hidden" name="action" value="ppc_import_products_zip">
            <button class="button button-primary" type="submit">
                <?php echo esc_html__( 'Import ZIP', 'printing-pricing-calculator' ); ?>
            </button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'ID', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Image', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Title', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Category', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Base Price', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Slug', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Status', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Created', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Actions', 'printing-pricing-calculator' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="9"><?php echo esc_html__( 'No products found.', 'printing-pricing-calculator' ); ?></td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?php
                    // Fetch categories for this product
                    $cats = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT c.name FROM " . CATEGORY_TABLE . " c
                             INNER JOIN " . PRODUCT_CATEGORY_TABLE . " pc ON pc.category_id = c.id
                             WHERE pc.product_id = %d",
                            $product['id']
                        )
                    );
                    $cat_list = !empty($cats) ? implode(', ', array_map('esc_html', $cats)) : '—';
                    ?>
                    <tr>
                        <td><?php echo esc_html($product['id']); ?></td>
                        <td>
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo esc_url($product['image_url']); ?>" alt="<?php echo esc_attr__( 'Product Image', 'printing-pricing-calculator' ); ?>" style="max-width: 50px; height: auto;" />
                            <?php else: ?>
                                <span class="dashicons dashicons-format-image"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product['title']); ?></td>
                        <td><?php echo $cat_list; ?></td>
                        <td><?php echo esc_html($product['base_price']); ?></td>
                        <td><?php echo esc_html($product['slug']); ?></td>
                        <td><?php echo esc_html(ucfirst($product['status'])); ?></td>
                        <td><?php echo esc_html($product['created_at']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=ppc-product-edit&id=' . $product['id']); ?>" class="button">
                                <?php echo esc_html__( 'Edit', 'printing-pricing-calculator' ); ?>
                            </a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ppc-products&action=delete&id=' . $product['id']), 'delete_product_' . $product['id']); ?>" class="button delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this product?', 'printing-pricing-calculator' ) ); ?>');">
                                <?php echo esc_html__( 'Delete', 'printing-pricing-calculator' ); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ppc-product-edit&duplicate_id=' . intval($product['id'])); ?>" class="button ppc-duplicate-link">
                                <?php echo esc_html__( 'Duplicate', 'printing-pricing-calculator' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($pagination): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages"><?php echo $pagination; ?></div>
        </div>
    <?php endif; ?>
</div>
