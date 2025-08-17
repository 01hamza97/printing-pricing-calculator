<?php
namespace PPC\Categories;

class CategoriesEdit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            null, // hidden from sidebar
            'Edit Category',
            'Edit Category',
            'manage_options',
            'ppc-categories-edit',
            [$this, 'render_form']
        );
    }

    public function render_form() {
        if ( ! current_user_can('manage_options') ) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        $action  = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $is_edit = isset($_GET['id']) && (int) $_GET['id'] > 0;
        $id      = $is_edit ? (int) $_GET['id'] : 0;

        // DELETE
        if ( $action === 'delete' && $id ) {
            check_admin_referer('ppc_cat_delete_' . $id);
            $wpdb->delete(PRODUCT_CATEGORY_TABLE, ['category_id' => $id]);
            $wpdb->delete(CATEGORY_TABLE,         ['id'          => $id]);
            echo("<script>location.href = '".admin_url('admin.php?page=ppc-categories')."'</script>");
            exit;
        }

        // LOAD row (for GET or after failed POST)
        if ($is_edit) {
            $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM " . CATEGORY_TABLE . " WHERE id = %d", $id), ARRAY_A );
        }
        if (empty($row)) {
            $row = ['id'=>0, 'name'=>'', 'slug'=>'', 'description'=>'', 'status'=>'active', 'image_id'=>0];
        }

        // SAVE
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            check_admin_referer('ppc_cat_edit');

            $cid    = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $name   = isset($_POST['name']) ? sanitize_text_field( wp_unslash($_POST['name']) ) : '';
            $slug   = isset($_POST['slug']) && trim($_POST['slug']) !== '' ? sanitize_title($_POST['slug']) : sanitize_title($name);
            $desc   = isset($_POST['description']) ? wp_kses_post( wp_unslash($_POST['description']) ) : '';
            $status = (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'inactive' : 'active';

            if ($name === '') {
                $error = 'Name is required.';
            } elseif ($slug === '') {
                $error = 'Slug is required.';
            } else {
                // ensure slug uniqueness
                $original_slug = $slug;
                $i = 2;
                while (true) {
                    $query  = "SELECT id FROM " . CATEGORY_TABLE . " WHERE slug = %s";
                    $params = [$slug];
                    if ($cid) { $query .= " AND id <> %d"; $params[] = $cid; }
                    $exists = $wpdb->get_var( $wpdb->prepare($query, ...$params) );
                    if (!$exists) break;
                    $slug = $original_slug . '-' . $i++;
                }
            }

            // Handle image upload (store attachment ID)
            $attach_id = isset($row['image_id']) ? (int)$row['image_id'] : 0;

            if ( ! empty($_FILES['image_file']['name']) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $uploaded = wp_handle_upload( $_FILES['image_file'], ['test_form' => false] );
                if ( ! isset($uploaded['error']) ) {
                    $file_path = $uploaded['file'];
                    $file_name = basename($file_path);
                    $attachment = [
                        'post_mime_type' => $uploaded['type'],
                        'post_title'     => sanitize_file_name($file_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    ];
                    $attach_id = (int) wp_insert_attachment($attachment, $file_path);
                    if ($attach_id) {
                        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                        wp_update_attachment_metadata($attach_id, $attach_data);
                    }
                }
            }

            if (empty($error)) {
                $data = [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $desc,
                    'status'      => $status,
                    'image_id'    => $attach_id,
                    'updated_at'  => current_time('mysql'),
                ];

                if ($cid) {
                    $wpdb->update(CATEGORY_TABLE, $data, ['id' => $cid]);
                    $message = 'Category updated.';
                } else {
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert(CATEGORY_TABLE, $data);
                    $cid = (int) $wpdb->insert_id;
                    $message = 'Category created.';
                }

                echo("<script>location.href = '".admin_url('admin.php?page=ppc-categories&id='.$cid)."'</script>");
                exit;
            }

            // show form again with error
            $row = [
                'id'          => $cid,
                'name'        => $name,
                'slug'        => $slug,
                'description' => $desc,
                'status'      => $status,
                'image_id'    => $attach_id,
            ];
            $is_edit = (bool) $cid;
        }

        // Render form template
        include plugin_dir_path(__FILE__) . '../Templates/Categories/form.php';
    }
}
