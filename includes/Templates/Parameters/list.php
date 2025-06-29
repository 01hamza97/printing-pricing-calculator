<div class="wrap">
    <h1>
        Parameters
        <a href="<?php echo admin_url('admin.php?page=ppc-parameter-edit'); ?>" class="page-title-action">Add New</a>
    </h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Content</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Actions</th>
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
                            <a href="<?php echo admin_url('admin.php?page=ppc-parameters&action=deactivate&id=' . $param->id); ?>" class="button">Deactivate</a>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=ppc-parameters&action=activate&id=' . $param->id); ?>" class="button button-secondary">Activate</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=ppc-parameter-edit&id=' . $param->id); ?>">Edit</a> |
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ppc-parameters&delete=' . $param->id), 'delete_param_' . $param->id); ?>"
                           onclick="return confirm('Are you sure you want to delete this parameter?')">
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4">No parameters found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
