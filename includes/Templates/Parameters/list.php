<div class="wrap">
    <h1>
        <?php echo esc_html__( 'Parameters', 'printing-pricing-calculator' ); ?>
        <a href="<?php echo admin_url('admin.php?page=ppc-parameter-edit'); ?>" class="page-title-action">
            <?php echo esc_html__( 'Add New', 'printing-pricing-calculator' ); ?>
        </a>
    </h1>

    <div style="display: flex; margin-bottom: 20px; flex-wrap: wrap; gap: 20px;">
        <!-- Export Buttons -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="<?php echo admin_url('admin-post.php?action=ppc_export_parameters_zip'); ?>" class="button button-secondary">
                <?php echo esc_html__( 'Export Full Parameters Data (ZIP of CSVs)', 'printing-pricing-calculator' ); ?>
            </a>
        </div>

        <!-- Import -->
        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>" style="display:flex; align-items:center; gap:8px;">
            <?php wp_nonce_field('ppc_import_zip'); ?>
            <input type="file" name="ppc_import_zip" accept=".zip" required>
            <input type="hidden" name="action" value="ppc_import_parameters_zip">
            <button class="button button-primary" type="submit">
                <?php echo esc_html__( 'Import ZIP', 'printing-pricing-calculator' ); ?>
            </button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'ID', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Title', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Content', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Slug', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Status', 'printing-pricing-calculator' ); ?></th>
                <th><?php echo esc_html__( 'Actions', 'printing-pricing-calculator' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($parameters)) : ?>
            <?php foreach ($parameters as $param) : ?>
                <tr>
                    <td><?php echo esc_html($param->id); ?></td>
                    <td><?php echo esc_html($param->title); ?></td>
                    <td><?php echo esc_html($param->content); ?></td>
                    <td><?php echo esc_html($param->slug); ?></td>
                    <td>
                        <?php if ($param->status === 'active'): ?>
                            <a href="<?php echo admin_url('admin.php?page=ppc-parameters&action=deactivate&id=' . $param->id); ?>" class="button">
                                <?php echo esc_html__( 'Deactivate', 'printing-pricing-calculator' ); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=ppc-parameters&action=activate&id=' . $param->id); ?>" class="button button-secondary">
                                <?php echo esc_html__( 'Activate', 'printing-pricing-calculator' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=ppc-parameter-edit&id=' . $param->id); ?>">
                            <?php echo esc_html__( 'Edit', 'printing-pricing-calculator' ); ?>
                        </a> |
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ppc-parameters&delete=' . $param->id), 'delete_param_' . $param->id); ?>"
                           onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this parameter?', 'printing-pricing-calculator' ) ); ?>')">
                            <?php echo esc_html__( 'Delete', 'printing-pricing-calculator' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4"><?php echo esc_html__( 'No parameters found.', 'printing-pricing-calculator' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
