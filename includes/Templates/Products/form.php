<?php
// Ensure this file is included in a context where $data and $parameters are defined.
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

        <h2 class="wp-heading-inline">Content</h2>
        <?php
        wp_editor($data['content'], 'content', [
            'textarea_name' => 'content',
            'textarea_rows' => 6
        ]);
        ?>

        <h2 class="wp-heading-inline">Base Price</h2>
        <input type="number" step="0.01" name="base_price" class="regular-text" value="<?php echo esc_attr($data['base_price']); ?>" required />

        <h2 class="wp-heading-inline">Status</h2>
        <select name="status">
            <option value="active" <?php selected($data['status'], 'active'); ?>>Active</option>
            <option value="inactive" <?php selected($data['status'], 'inactive'); ?>>Inactive</option>
        </select>

        <h2 class="wp-heading-inline">Base Product Image</h2>
        <?php if (!empty($data['image_url'])): ?>
            <img src="<?php echo esc_url($data['image_url']); ?>" style="max-width: 150px; display:block; margin-bottom: 10px;" />
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/*" />
        <input type="hidden" name="image_url" value="<?php echo esc_attr($data['image_url']); ?>" />

        <h2 class="wp-heading-inline">Select Parameters</h2>
        <?php foreach ($parameters as $param): ?>
            <fieldset style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;">
                <legend><strong><?php echo esc_html($param['title']); ?></strong></legend>
                <label>
                    <input type="checkbox" name="parameters[]" value="<?php echo esc_attr($param['id']); ?>" <?php checked(in_array($param['id'], $data['params'])); ?> /> Use this parameter
                </label>

                <?php if (!empty($param['options'])): ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Option</th>
                                <th>Image</th>
                                <th>Base Cost</th>
                                <th>Override Price</th>
                                <th>Enable</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($param['options'] as $opt): ?>
                                <tr>
                                    <td><?php echo esc_html($opt['title']); ?></td>
                                    <td>
                                        <?php if (!empty($opt['image'])): ?>
                                            <img src="<?php echo esc_url($opt['image']); ?>" style="width: 50px; height: auto;" />
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($opt['cost']); ?></td>
                                    <td>
                                        <input type="number" step="0.01" name="override_prices[<?php echo esc_attr($opt['id']); ?>]" value="<?php echo esc_attr($data['option_prices'][$opt['id']] ?? $opt['cost']); ?>" class="small-text" />
                                    </td>
                                    <td>
                                        <input type="checkbox" name="selected_options[]" value="<?php echo esc_attr($opt['id']); ?>" <?php checked(isset($data['option_prices'][$opt['id']])); ?> />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>

        <p class="submit">
            <button type="submit" class="button button-primary">Save Product</button>
        </p>
    </form>
</div>
