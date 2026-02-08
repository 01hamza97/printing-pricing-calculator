<?php
// includes/Templates/Categories/form.php
if ( ! current_user_can('manage_options') ) { wp_die( esc_html__( 'Unauthorized', 'printing-pricing-calculator' ) ); }

$editing = !empty($row['id']);
$title   = $editing ? __( 'Edit Category', 'printing-pricing-calculator' ) : __( 'Add New Category', 'printing-pricing-calculator' );

$action_url = add_query_arg(['page' => 'ppc-categories-edit', 'id' => (int)($row['id'] ?? 0)], admin_url('admin.php'));

$image_id  = isset($row['image_id']) ? (int)$row['image_id'] : 0;
$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
?>
<div class="wrap">
  <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
  <a href="<?php echo esc_url( add_query_arg(['page'=>'ppc-categories'], admin_url('admin.php')) ); ?>" class="page-title-action">
    <?php echo esc_html__( 'Back to List', 'printing-pricing-calculator' ); ?>
  </a>
  <hr class="wp-header-end">

  <?php if (!empty($error)): ?>
    <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
  <?php endif; ?>

  <form method="post" action="<?php echo esc_url($action_url); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('ppc_cat_edit'); ?>
    <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>" />

    <!-- Name -->
    <h2 class="screen-reader-text"><?php echo esc_html__( 'Enter Name here', 'printing-pricing-calculator' ); ?></h2>
    <input type="text" name="name" id="ppc-cat-name" placeholder="<?php echo esc_attr__( 'Enter Name here', 'printing-pricing-calculator' ); ?>"
           value="<?php echo esc_attr($row['name'] ?? ''); ?>"
           style="font-size: 1.7em; width: 100%; padding: 8px 10px; margin-bottom: 20px; border: 1px solid #ccd0d4; background: #fff;" required>

    <!-- Slug -->
    <div style="margin-bottom:16px;">
        <h2 class="wp-heading-inline"><?php echo esc_html__( 'Slug', 'printing-pricing-calculator' ); ?></h2>
        <input type="text" name="slug" class="widefat"
               value="<?php echo esc_attr($row['slug'] ?? ''); ?>"
               placeholder="<?php echo esc_attr__( 'Auto-generated if left blank', 'printing-pricing-calculator' ); ?>" />
        <p class="description"><?php echo esc_html__( 'Leave blank to auto-generate from name. Must be unique.', 'printing-pricing-calculator' ); ?></p>
    </div>

    <!-- Description -->
    <div>
        <label for="description" style="font-weight: 400; display: inline-block; position: relative; z-index: 9; top: 30px; font-size: 24px;">
            <?php echo esc_html__( 'Description', 'printing-pricing-calculator' ); ?>
        </label>
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

    <h2 class="wp-heading-inline">Parent Category</h2>
    <select name="parent_id" class="widefat">
    <option value="">— None —</option>
    <?php
    $all_cats = $wpdb->get_results("SELECT id, name FROM " . CATEGORY_TABLE . " WHERE id != " . (int)($row['id'] ?? 0) . " ORDER BY name ASC");
    foreach ($all_cats as $cat) {
        printf(
            '<option value="%d" %s>%s</option>',
            $cat->id,
            selected($cat->id, $row['parent_id'] ?? '', false),
            esc_html($cat->name)
        );
    }
    ?>
    </select>

    <!-- Status -->
    <h2 class="wp-heading-inline"><?php echo esc_html__( 'Status', 'printing-pricing-calculator' ); ?></h2>
    <select name="status" style="margin-bottom:16px;">
        <option value="active"   <?php selected(($row['status'] ?? 'active'), 'active'); ?>><?php echo esc_html__( 'Active', 'printing-pricing-calculator' ); ?></option>
        <option value="inactive" <?php selected(($row['status'] ?? 'active'), 'inactive'); ?>><?php echo esc_html__( 'Inactive', 'printing-pricing-calculator' ); ?></option>
    </select>

    <!-- Image -->
    <h2 class="wp-heading-inline">
        <?php echo esc_html__( 'Category Image', 'printing-pricing-calculator' ); ?>
    </h2>

    <div style="margin:8px 0 16px;">
        <div id="ppc-category-image-preview" style="margin-bottom:10px;">
            <?php if ( $image_id && $image_url ) : ?>
                <img src="<?php echo esc_url( $image_url ); ?>"
                    style="max-width: 150px; display:block; margin-bottom: 10px;"
                    alt="">
            <?php else : ?>
                <em><?php echo esc_html__( 'No image selected.', 'printing-pricing-calculator' ); ?></em>
            <?php endif; ?>
        </div>

        <input type="hidden"
            name="image_id"
            id="ppc-category-image-id"
            value="<?php echo esc_attr( $image_id ); ?>">

        <button type="button"
                class="button"
                id="ppc-category-image-select">
            <?php esc_html_e( 'Select image', 'printing-pricing-calculator' ); ?>
        </button>

        <button type="button"
                class="button"
                id="ppc-category-image-remove"
                <?php if ( ! $image_id ) echo 'style="display:none"'; ?>>
            <?php esc_html_e( 'Remove image', 'printing-pricing-calculator' ); ?>
        </button>
    </div>


    <?php submit_button( $editing ? __( 'Update Category', 'printing-pricing-calculator' ) : __( 'Create Category', 'printing-pricing-calculator' ) ); ?>
  </form>
</div>

<script>
jQuery(function($) {
    var frame;
    var $imageId   = $('#ppc-category-image-id');
    var $preview   = $('#ppc-category-image-preview');
    var $removeBtn = $('#ppc-category-image-remove');

    $('#ppc-category-image-select').on('click', function(e) {
        e.preventDefault();

        // Re-use existing frame if it exists
        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: '<?php echo esc_js( __( 'Select or upload image', 'printing-pricing-calculator' ) ); ?>',
            button: {
                text: '<?php echo esc_js( __( 'Use this image', 'printing-pricing-calculator' ) ); ?>'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();

            $imageId.val(attachment.id);

            var imgUrl = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            $preview.html(
                '<img src="' + imgUrl + '" ' +
                'style="max-width: 150px; display:block; margin-bottom: 10px;" alt="">'
            );

            $removeBtn.show();
        });

        frame.open();
    });

    $removeBtn.on('click', function(e) {
        e.preventDefault();

        $imageId.val('');
        $preview.html('<em><?php echo esc_js( __( 'No image selected.', 'printing-pricing-calculator' ) ); ?></em>');
        $removeBtn.hide();
    });
});
</script>
