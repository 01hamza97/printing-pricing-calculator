<?php
// includes/Templates/Categories/form.php
if ( ! current_user_can('manage_options') ) { wp_die('Unauthorized'); }

$editing = !empty($row['id']);
$title   = $editing ? 'Edit Category' : 'Add New Category';

$action_url = add_query_arg(['page' => 'ppc-categories-edit', 'id' => (int)($row['id'] ?? 0)], admin_url('admin.php'));

$image_id  = isset($row['image_id']) ? (int)$row['image_id'] : 0;
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
?>
<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
  <a href="<?php echo esc_url( add_query_arg(['page'=>'ppc-categories'], admin_url('admin.php')) ); ?>" class="page-title-action">Back to List</a>
  <hr class="wp-header-end">

  <?php if (!empty($error)): ?>
    <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url($action_url); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('ppc_cat_edit'); ?>
    <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>" />

    <!-- Name -->
    <h2 class="screen-reader-text">Enter Name here</h2>
    <input type="text" name="name" id="ppc-cat-name" placeholder="Enter Name here"
           value="<?php echo esc_attr($row['name'] ?? ''); ?>"
           style="font-size: 1.7em; width: 100%; padding: 8px 10px; margin-bottom: 20px; border: 1px solid #ccd0d4; background: #fff;" required>

    <!-- Slug -->
    <div style="margin-bottom:16px;">
        <h2 class="wp-heading-inline">Slug</h2>
        <input type="text" name="slug" class="widefat"
               value="<?php echo esc_attr($row['slug'] ?? ''); ?>"
               placeholder="Auto-generated if left blank" />
        <p class="description">Leave blank to auto-generate from name. Must be unique.</p>
    </div>

    <!-- Description -->
    <div>
        <label for="description" style="font-weight: 400; display: inline-block; position: relative; z-index: 9; top: 30px; font-size: 24px;">Description</label>
        <div style="margin-bottom: 20px;">
            <?php
            wp_editor(
                $row['description'] ?? '',
                'description',
                [
                    'textarea_name' => 'description',
                    'media_buttons' => false,
                    'textarea_rows' => 8,
                    'teeny'         => true,
                ]
            );
            ?>
        </div>
    </div>

    <!-- Status -->
    <h2 class="wp-heading-inline">Status</h2>
    <select name="status" style="margin-bottom:16px;">
        <option value="active"   <?php selected(($row['status'] ?? 'active'), 'active'); ?>>Active</option>
        <option value="inactive" <?php selected(($row['status'] ?? 'active'), 'inactive'); ?>>Inactive</option>
    </select>

    <!-- Image -->
    <h2 class="wp-heading-inline">Category Image</h2>
    <div style="margin:8px 0 16px;">
        <?php if ($image_url): ?>
            <img src="<?php echo esc_url($image_url); ?>" style="max-width: 150px; display:block; margin-bottom: 10px;" alt="">
        <?php else: ?>
            <em>No image selected.</em>
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/*" />
    </div>

    <?php submit_button( $editing ? 'Update Category' : 'Create Category' ); ?>
  </form>
</div>
