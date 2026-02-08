<?php
$is_edit = isset($_GET['id']);
?>

<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__( 'Edit Parameter', 'printing-pricing-calculator' ) : esc_html__( 'Add New Parameter', 'printing-pricing-calculator' ); ?></h1>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('save_parameter'); ?>

        <!-- Title Field -->
        <h2 class="screen-reader-text"><?php echo esc_html__( 'Enter title here', 'printing-pricing-calculator' ); ?></h2>
        <input type="text" name="title" id="title" placeholder="<?php echo esc_attr__( 'Enter title here', 'printing-pricing-calculator' ); ?>"
               value="<?php echo esc_attr($data['title'] ?? ''); ?>"
               style="font-size: 1.7em; width: 100%; padding: 8px 10px; margin-bottom: 20px; border: 1px solid #ccd0d4; background: #fff;" required>

        <div>
            <h2 class="wp-heading-inline"><?php echo esc_html__( 'Slug', 'printing-pricing-calculator' ); ?></h2>
            <input type="text" name="slug" class="widefat" 
                value="<?php echo esc_attr($data['slug'] ?? ''); ?>" 
                placeholder="<?php echo esc_attr__( 'Auto-generated if left blank', 'printing-pricing-calculator' ); ?>" 
                <?php if ($is_edit && !current_user_can('manage_options')) echo __('readonly', 'printing-pricing-calculator'); ?> />
            <small class="text-gray-500"><?php echo esc_html__( 'Leave blank to auto-generate from title. Must be unique.', 'printing-pricing-calculator' ); ?></small>
        </div>

        <div>
            <!-- Content Editor -->
            <label for="content" style="font-weight: 400; display: inline-block; position: relative; z-index: 9; top: 30px; font-size: 24px;"><?php echo esc_html__( 'Description', 'printing-pricing-calculator' ); ?></label>
            <div style="margin-bottom: 20px;">
                <?php
                wp_editor(
                    $data['content'] ?? '',
                    'content',
                    [
                        'textarea_name' => 'content',
                        'media_buttons' => false,
                        'textarea_rows' => 8,
                        'teeny' => true,
                    ]
                );
                ?>
            </div>
        </div>
        <div style="margin-bottom: 20px;">
            <label for="front_name" style="font-weight: 400; display: block; font-size: 24px; margin-bottom:10px;"><?php echo esc_html__( 'Front Name', 'printing-pricing-calculator' ); ?></label>
            <input id="front_name" type="text" name="front_name" value="<?php echo esc_attr($data['front_name'] ?? ''); ?>" placeholder="<?php echo esc_attr__( 'Front Screen Name', 'printing-pricing-calculator' ); ?>" required>
        </div>

        <!-- Options Section -->
        <hr>
        <h2 style="margin-top: 30px;"><?php echo esc_html__( 'Options', 'printing-pricing-calculator' ); ?></h2>

        <div id="pc-parameter-options">
            <?php
            global $wpdb;
            $options = [];

            if (!empty($data['id'])) {
                $meta_table = $wpdb->prefix . 'ppc_parameter_meta';
                $options = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM $meta_table WHERE parameter_id = %d", $data['id'])
                );
            }

            if (!empty($options)) :
                foreach ($options as $index => $opt) :
                    $opt_values = maybe_unserialize($opt->meta_value);
            ?>
                <div class="pc-option-group" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ccc;">
                    <input type="hidden" name="options[<?php echo $index; ?>][meta_id]" value="<?php echo $opt->id; ?>">
                    <input type="text" name="options[<?php echo $index; ?>][option]" value="<?php echo esc_attr($opt_values['option'] ?? ''); ?>" placeholder="<?php echo esc_attr__( 'Option title', 'printing-pricing-calculator' ); ?>" required>
                    <input type="text" name="options[<?php echo $index; ?>][slug]" value="<?php echo esc_attr($opt_values['slug'] ?? ''); ?>" placeholder="<?php echo esc_attr__( 'Auto-generated if left blank', 'printing-pricing-calculator' ); ?>">
                    <input type="number" step="0.01" name="options[<?php echo $index; ?>][cost]" value="<?php echo esc_attr($opt_values['cost'] ?? ''); ?>" placeholder="<?php echo esc_attr__( 'Cost', 'printing-pricing-calculator' ); ?>" required>
                    <input type="file" name="option_files[<?php echo $opt->id; ?>]">
                    <?php if (!empty($opt_values['image'])): ?>
                        <br><img src="<?php echo esc_url($opt_values['image']); ?>" style="max-width:100px;">
                    <?php endif; ?>
                    <input type="hidden" name="options[<?php echo $index; ?>][existing_image]" value="<?php echo esc_url($opt_values['image'] ?? ''); ?>">
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Option Template -->
        <template id="pc-option-template">
            <div class="pc-option-group" style="margin-bottom: 15px; padding: 15px; border: 1px solid #ccc;">
                <input type="text" name="options[new_{{i}}][option]" placeholder="<?php echo esc_attr__( 'Option title', 'printing-pricing-calculator' ); ?>" required>
                <input type="text" name="options[new_{{i}}][slug]" placeholder="<?php echo esc_attr__( 'Auto-generated if left blank', 'printing-pricing-calculator' ); ?>">
                <input type="number" step="0.01" name="options[new_{{i}}][cost]" placeholder="<?php echo esc_attr__( 'Cost', 'printing-pricing-calculator' ); ?>" required>
                <input type="file" name="option_files[new_{{i}}]">
                <button type="button" class="button remove-option"><?php echo esc_html__( 'Remove', 'printing-pricing-calculator' ); ?></button>
            </div>
        </template>

        <button type="button" class="button button-secondary" id="add-option">+ <?php echo esc_html__( 'Add Option', 'printing-pricing-calculator' ); ?></button>

        <script>
        document.getElementById('add-option').addEventListener('click', function () {
            const template = document.getElementById('pc-option-template').innerHTML;
            const container = document.getElementById('pc-parameter-options');
            const index = container.querySelectorAll('.pc-option-group').length;
            container.insertAdjacentHTML('beforeend', template.replace(/{{i}}/g, index));
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-option')) {
                e.target.closest('.pc-option-group').remove();
            }
        });
        </script>

        <br><br>
        <?php submit_button( $is_edit ? __( 'Update Parameter', 'printing-pricing-calculator' ) : __( 'Add Parameter', 'printing-pricing-calculator' ) ); ?>
    </form>
</div>
