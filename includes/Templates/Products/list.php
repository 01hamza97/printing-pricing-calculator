<div class="wrap">
    <h1 class="wp-heading-inline">Products</h1>
    <a href="<?php echo admin_url('admin.php?page=ppc-product-edit'); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">
    <form method="get">
        <input type="hidden" name="page" value="ppc-products">
        <input type="search" name="s" placeholder="Search title..." value="<?php echo esc_attr($search); ?>">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
            <option value="inactive" <?php selected($status_filter, 'inactive'); ?>>Inactive</option>
        </select>
        <button class="button">Filter</button>
    </form>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Title</th>
                <th>Base Price</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="7">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product['id']); ?></td>
                        <td>
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo esc_url($product['image_url']); ?>" alt="Product Image" style="max-width: 50px; height: auto;" />
                            <?php else: ?>
                                <span class="dashicons dashicons-format-image"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product['title']); ?></td>
                        <td><?php echo esc_html($product['base_price']); ?></td>
                        <td><?php echo esc_html(ucfirst($product['status'])); ?></td>
                        <td><?php echo esc_html($product['created_at']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=ppc-product-edit&id=' . $product['id']); ?>" class="button">Edit</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ppc-products&action=delete&id=' . $product['id']), 'delete_product_' . $product['id']); ?>" class="button delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if ($pagination): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages"> <?php echo $pagination; ?> </div>
        </div>
    <?php endif; ?>
</div>