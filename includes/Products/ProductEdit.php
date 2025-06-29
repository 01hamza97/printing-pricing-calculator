<?php
namespace PPC\Products;

use WP_Post;
use wpdb;

class ProductEdit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            null,
            'Edit Product',
            'Edit Product',
            'manage_options',
            'ppc-product-edit',
            [$this, 'render']
        );
    }

    public function ppc_generate_unique_slug($title, $wpdb, $product_table, $current_id = null) {
        $slug = sanitize_title($title);
        $original_slug = $slug;
        $i = 2;

        // Loop until slug is unique (ignore current ID if editing)
        while (true) {
            $query = "SELECT id FROM $product_table WHERE slug = %s";
            $params = [$slug];
            if ($current_id) {
                $query .= " AND id != %d";
                $params[] = $current_id;
            }
            $exists = $wpdb->get_var($wpdb->prepare($query, ...$params));
            if (!$exists) break;
            $slug = $original_slug . '-' . $i++;
        }
        return $slug;
    }

    public function render() {
        global $wpdb;

        $product_table = PRODUCT_TABLE;
        $pivot_table = PRODUCT_PARAMETERS_TABLE;
        $option_price_table = PRODUCT_PARAM_META_TABLE;
        $param_table = PARAM_TABLE;
        $meta_table = META_TABLE;

        $is_edit = isset($_GET['id']);
        $id = $is_edit ? intval($_GET['id']) : 0;
        $data = [
            'title' => '',
            'slug' => '',
            'content' => '',
            'base_price' => '',
            'status' => 'active',
            'image_url' => '',
            'params' => [],
            'option_prices' => [],
            'min_order_qty' => null
        ];

        // Load existing data
        if ($is_edit && $id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $product_table WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                $data = array_merge($data, $row);

                // Load selected parameters
                $data['params'] = $wpdb->get_results($wpdb->prepare(
                    "SELECT parameter_id, is_required FROM $pivot_table WHERE product_id = %d",
                    $id
                ));

                // Load option prices
                $existing_prices = $wpdb->get_results(
                    $wpdb->prepare("SELECT option_id, override_price FROM $option_price_table WHERE product_id = %d", $id),
                    OBJECT_K
                );
                foreach ($existing_prices as $opt_id => $obj) {
                    $data['option_prices'][$opt_id] = $obj->override_price;
                }
            }
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('save_product')) {
            $title = sanitize_text_field($_POST['title']);
            $content = wp_kses_post($_POST['content']);
            $base_price = floatval($_POST['base_price']);
            $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'inactive';
            $express_delivery_value = isset($_POST['express_delivery_value']) && $_POST['express_delivery_value'] !== '' 
                ? floatval($_POST['express_delivery_value']) 
                : null;
            $express_delivery_type = in_array($_POST['express_delivery_type'], ['percent', 'flat']) 
                ? $_POST['express_delivery_type'] 
                : null;
            $min_order_qty = isset($_POST['min_order_qty']) && $_POST['min_order_qty'] !== '' 
                ? floatval($_POST['min_order_qty']) 
                : null;

            $slug = null;
            if($is_edit) {
                $slug = $this->ppc_generate_unique_slug($title, $wpdb, $product_table, $id);
            } else {
                $slug = $this->ppc_generate_unique_slug($title, $wpdb, $product_table);
            }

            $image_url = '';
            if (!empty($_FILES['image_file']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // Remove old file if exists (same as before)
                if ($is_edit && !empty($data['image_url'])) {
                    $upload_dir = wp_upload_dir();
                    $old_file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $data['image_url']);
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }

                // Handle upload
                $uploaded = wp_handle_upload($_FILES['image_file'], ['test_form' => false]);
                if (!isset($uploaded['error'])) {
                    $file_path = $uploaded['file'];
                    $file_name = basename($file_path);

                    // Prepare attachment data
                    $attachment = [
                        'post_mime_type' => $uploaded['type'],
                        'post_title'     => sanitize_file_name($file_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ];
                    // Insert attachment
                    $attach_id = wp_insert_attachment($attachment, $file_path);

                    // Generate metadata and update
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);

                    // Get the attachment URL
                    $image_url = wp_get_attachment_url($attach_id);
                }
            }

            if ($is_edit) {
                $wpdb->update($product_table, [
                    'title' => $title,
                    'content' => $content,
                    'base_price' => $base_price,
                    'slug' => $slug,
                    'express_delivery_value' => $express_delivery_value,
                    'express_delivery_type'  => $express_delivery_type,
                    'min_order_qty' => $min_order_qty,
                    'status' => $status,
                    'image_url' => $image_url,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $id]);
            } else {
                $wpdb->insert($product_table, [
                    'title' => $title,
                    'content' => $content,
                    'base_price' => $base_price,
                    'express_delivery_value' => $express_delivery_value,
                    'express_delivery_type'  => $express_delivery_type,
                    'min_order_qty' => $min_order_qty,
                    'slug' => $slug,
                    'status' => $status,
                    'image_url' => $image_url,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $id = $wpdb->insert_id;
            }

            // Sync parameters
            $wpdb->delete($pivot_table, ['product_id' => $id]);
            if (!empty($_POST['parameters'])) {
                foreach ($_POST['parameters'] as $param_id) {
                    $is_required = (!empty($_POST['is_required'][$param_id]) && $_POST['is_required'][$param_id] == 1) ? 1 : 0;
                    $wpdb->insert($pivot_table, [
                        'product_id' => $id,
                        'parameter_id' => intval($param_id),
                        'is_required' => $is_required // Save the required flag!
                    ]);
                }
            }

            // Sync option pricing
            $wpdb->delete($option_price_table, ['product_id' => $id]);
            if (!empty($_POST['selected_options'])) {
                foreach ($_POST['selected_options'] as $option_id) {
                    $override_price = isset($_POST['override_prices'][$option_id]) ? floatval($_POST['override_prices'][$option_id]) : null;
                    $wpdb->insert($option_price_table, [
                        'product_id' => $id,
                        'option_id' => intval($option_id),
                        'override_price' => $override_price
                    ]);
                }
            }

            echo("<script>location.href = '" . admin_url('admin.php?page=ppc-product-edit&id=' . $id) . "'</script>");
            exit;
        }

        // Fetch parameters and their options
        $raw_params = $wpdb->get_results("SELECT p.id AS param_id, p.title AS param_title, p.front_name AS front_name, m.id AS meta_id, m.meta_value
            FROM $param_table p
            LEFT JOIN $meta_table m ON p.id = m.parameter_id
            WHERE p.status = 'active'
            ORDER BY p.id", ARRAY_A);

        $parameters = [];
        foreach ($raw_params as $row) {
            $meta_value = maybe_unserialize($row['meta_value']);
            if (!isset($parameters[$row['param_id']])) {
                $parameters[$row['param_id']] = [
                    'id' => $row['param_id'],
                    'title' => $row['param_title'],
                    'front_name' => $row['front_name'],
                    'options' => [],
                ];
            }
            if (!empty($row['meta_id'])) {
                $parameters[$row['param_id']]['options'][] = [
                    'id' => $row['meta_id'],
                    'title' => $meta_value['option'] ?? '',
                    'image' => $meta_value['image'] ?? '',
                    'cost' => $meta_value['cost'] ?? '',
                    'slug' => $meta_value['slug'] ?? '',
                ];
            }
        }

        include plugin_dir_path(__FILE__) . '/../Templates/Products/form.php';
    }
}
