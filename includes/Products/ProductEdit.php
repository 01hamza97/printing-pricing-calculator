<?php
namespace PPC\Products;

class ProductEdit
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_ppc_param_search', [$this, 'ajax_param_search']);
        add_action('wp_ajax_ppc_param_row_markup', [$this, 'ppc_param_row_markup_handler']);
        add_action('wp_ajax_ppc_save_param_order', [$this, 'ppc_save_param_order']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

    }

    public function enqueue_admin_scripts($hook) {
        // Only enqueue on your product edit page
        if ($hook !== 'admin_page_ppc-product-edit') return;
        wp_enqueue_script('jquery-ui-sortable');
    }

    public function register_menu()
    {
        add_submenu_page(
            null,
            'Edit Product',
            'Edit Product',
            'manage_options',
            'ppc-product-edit',
            [$this, 'render']
        );
    }

    public function ppc_generate_unique_slug($title, $wpdb, $product_table, $current_id = null)
    {
        $slug          = sanitize_title($title);
        $original_slug = $slug;
        $i             = 2;
        while (true) {
            $query  = "SELECT id FROM $product_table WHERE slug = %s";
            $params = [$slug];
            if ($current_id) {
                $query .= " AND id != %d";
                $params[] = $current_id;
            }
            $exists = $wpdb->get_var($wpdb->prepare($query, ...$params));
            if (! $exists) {
                break;
            }

            $slug = $original_slug . '-' . $i++;
        }
        return $slug;
    }

    public function render()
    {
        global $wpdb;

        $product_table      = PRODUCT_TABLE;
        $pivot_table        = PRODUCT_PARAMETERS_TABLE;
        $option_price_table = PRODUCT_PARAM_META_TABLE;
        $param_table        = PARAM_TABLE;
        $meta_table         = META_TABLE;

        $is_edit = isset($_GET['id']);
        $id      = $is_edit ? intval($_GET['id']) : 0;
        $data    = [
            'title'               => '',
            'slug'                => '',
            'content'             => '',
            'base_price'          => '',
            'status'              => 'active',
            'image_url'           => '',
            'params'              => [],
            'option_prices'       => [],
            'min_order_qty'       => null,
            // New fields for requirements:
            'discount_rules'      => [],
            'file_check_price'    => '',
            'file_check_required' => 0,
        ];

        // ----- LOAD EXISTING DATA -----
        if ($is_edit && $id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $product_table WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                $data = array_merge($data, $row);

                // Parameters (get all attached)
                $data['params'] = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT parameter_id, is_required, position FROM $pivot_table WHERE product_id = %d ORDER BY position ASC, id ASC", 
                        $id
                    )
                );

                // Option prices
                $existing_prices = $wpdb->get_results(
                    $wpdb->prepare("SELECT option_id, override_price FROM $option_price_table WHERE product_id = %d", $id),
                    OBJECT_K
                );
                foreach ($existing_prices as $opt_id => $obj) {
                    $data['option_prices'][$opt_id] = $obj->override_price;
                }
                // Product-level discount rules
                $data['discount_rules'] = ! empty($data['discount_rules'])
                ? maybe_unserialize($data['discount_rules'])
                : [];
                if (! is_array($data['discount_rules'])) {
                    $data['discount_rules'] = [];
                }

            }
        }

        // Check for duplication request
    if (isset($_GET['duplicate_id'])) {
        $duplicate_id = intval($_GET['duplicate_id']);
        $orig = $wpdb->get_row($wpdb->prepare("SELECT * FROM $product_table WHERE id = %d", $duplicate_id), ARRAY_A);
        if ($orig) {
            // Remove id, slug, created/updated dates
            unset($orig['id'], $orig['slug'], $orig['created_at'], $orig['updated_at']);
            // Add (Copy) to title
            $orig['title'] .= ' (Copy)';
            // Insert new product
            $wpdb->insert($product_table, $orig);
            $new_id = $wpdb->insert_id;
            // Generate unique slug
            $new_slug = $this->ppc_generate_unique_slug($orig['title'], $wpdb, $product_table, $new_id);
            $wpdb->update($product_table, ['slug' => $new_slug], ['id' => $new_id]);
            // Copy parameter relations
            $param_rels = $wpdb->get_results($wpdb->prepare("SELECT * FROM $pivot_table WHERE product_id = %d", $duplicate_id), ARRAY_A);
            foreach ($param_rels as $rel) {
                unset($rel['id']);
                $rel['product_id'] = $new_id;
                $wpdb->insert($pivot_table, $rel);
            }
            // Copy option prices
            $opt_prices = $wpdb->get_results($wpdb->prepare("SELECT * FROM $option_price_table WHERE product_id = %d", $duplicate_id), ARRAY_A);
            foreach ($opt_prices as $price) {
                unset($price['id']);
                $price['product_id'] = $new_id;
                $wpdb->insert($option_price_table, $price);
            }
            // Redirect to edit page for new product
            echo("<script>location.href = '" . admin_url('admin.php?page=ppc-product-edit&id=' . $new_id . '&duplicated=1') . "'</script>");
            exit;
        }
    }

        // ----- HANDLE FORM SUBMISSION (unchanged from your previous code) -----
        // ----- HANDLE FORM SUBMISSION -----
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

            // ----- NEW: Product discount rules -----
            $discount_rules = [];
            if (!empty($_POST['discount_qty']) && !empty($_POST['discount_percent'])) {
                $qtys = $_POST['discount_qty'];
                $percents = $_POST['discount_percent'];
                for ($i = 0; $i < count($qtys); $i++) {
                    $qty = intval($qtys[$i]);
                    $percent = floatval($percents[$i]);
                    if ($qty > 0 && $percent > 0) {
                        $discount_rules[] = ['qty' => $qty, 'percent' => $percent];
                    }
                }
                // Sort by qty descending for easy matching
                usort($discount_rules, function($a, $b) {
                    return $b['qty'] - $a['qty'];
                });
            }
            $discount_rules_serialized = !empty($discount_rules) ? maybe_serialize($discount_rules) : null;

            // ----- NEW: File check -----
            $file_check_price = isset($_POST['file_check_price']) && $_POST['file_check_price'] !== ''
                ? floatval($_POST['file_check_price'])
                : null;
            $file_check_required = !empty($_POST['file_check_required']) ? intval($_POST['file_check_required']) : 0;

            // Slug logic (unchanged)
            $slug = null;
            if ($is_edit) {
                $slug = $this->ppc_generate_unique_slug($title, $wpdb, $product_table, $id);
            } else {
                $slug = $this->ppc_generate_unique_slug($title, $wpdb, $product_table);
            }

            // ----- IMAGE LOGIC (as before, now with media library attachment) -----
            $image_url = $data['image_url'] ?? '';
            if (!empty($_FILES['image_file']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                // Remove old file if exists
                if ($is_edit && !empty($data['image_url'])) {
                    $upload_dir = wp_upload_dir();
                    $old_file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $data['image_url']);
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                $uploaded = wp_handle_upload($_FILES['image_file'], ['test_form' => false]);
                if (!isset($uploaded['error'])) {
                    $file_path = $uploaded['file'];
                    $file_name = basename($file_path);
                    $attachment = [
                        'post_mime_type' => $uploaded['type'],
                        'post_title'     => sanitize_file_name($file_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ];
                    $attach_id = wp_insert_attachment($attachment, $file_path);
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    $image_url = wp_get_attachment_url($attach_id);
                }
            }

            $fields = [
                'title' => $title,
                'content' => $content,
                'base_price' => $base_price,
                'slug' => $slug,
                'express_delivery_value' => $express_delivery_value,
                'express_delivery_type' => $express_delivery_type,
                'min_order_qty' => $min_order_qty,
                'status' => $status,
                'image_url' => $image_url,
                'updated_at' => current_time('mysql'),
                // New fields
                'discount_rules' => $discount_rules_serialized,
                'file_check_price' => $file_check_price,
                'file_check_required' => $file_check_required,
            ];

            if ($is_edit) {
                $wpdb->update($product_table, $fields, ['id' => $id]);
            } else {
                $fields['created_at'] = current_time('mysql');
                $wpdb->insert($product_table, $fields);
                $id = $wpdb->insert_id;
            }

            // Sync parameters (unchanged)
            $wpdb->delete($pivot_table, ['product_id' => $id]);
            if (!empty($_POST['parameters'])) {
                foreach ($_POST['parameters'] as $position => $param_id) {
                    $is_required = (!empty($_POST['is_required'][$param_id]) && $_POST['is_required'][$param_id] == 1) ? 1 : 0;
                    $wpdb->insert($pivot_table, [
                        'product_id' => $id,
                        'parameter_id' => intval($param_id),
                        'is_required' => $is_required,
                        'position' => $_POST['param_positions'][$position]
                    ]);
                }
            }

            // Sync option pricing (unchanged)
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
        // ... Keep your form submission code as it is ...

        // ----- FETCH PARAMETERS (split to selected/unselected for the UI filter) -----
        // First get IDs of selected parameters
        $selected_ids = array_map(function ($p) {return $p->parameter_id;}, $data['params'] ?? []);

        // Fetch all parameters with their options
        $raw_params = $wpdb->get_results("SELECT p.id AS param_id, p.title AS param_title, p.front_name AS front_name, m.id AS meta_id, m.meta_value
        FROM $param_table p
        LEFT JOIN $meta_table m ON p.id = m.parameter_id
        WHERE p.status = 'active'
        ORDER BY p.id", ARRAY_A);

        // Build associative arrays
        $parameters = [];
        foreach ($raw_params as $row) {
            $meta_value = maybe_unserialize($row['meta_value']);
            if (! isset($parameters[$row['param_id']])) {
                $parameters[$row['param_id']] = [
                    'id'         => $row['param_id'],
                    'title'      => $row['param_title'],
                    'front_name' => $row['front_name'],
                    'options'    => [],
                ];
            }
            if (! empty($row['meta_id'])) {
                $parameters[$row['param_id']]['options'][] = [
                    'id'    => $row['meta_id'],
                    'title' => $meta_value['option'] ?? '',
                    'image' => $meta_value['image'] ?? '',
                    'cost'  => $meta_value['cost'] ?? '',
                    'slug'  => $meta_value['slug'] ?? '',
                ];
            }
        }

        $param_positions = [];
        foreach ($data['params'] as $p) {
            $param_positions[$p->parameter_id] = isset($p->position) ? intval($p->position) : 0;
        }

        // Step 2: Separate selected and available parameters, attach position
        $selectedParameters = [];
        $availableParameters = [];

        foreach ($parameters as $param) {
            if (in_array($param['id'], $selected_ids)) {
                $param['position'] = $param_positions[$param['id']] ?? 0;
                $selectedParameters[] = $param;
            } else {
                $availableParameters[] = $param;
            }
        }

        // Step 3: Sort $selectedParameters by position ASC
        usort($selectedParameters, function($a, $b){
            return $a['position'] <=> $b['position'];
        });

        // Pass both to the template
        include plugin_dir_path(__FILE__) . '/../Templates/Products/form.php';
    }


    public function ajax_param_search() {
        // Check capability
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        global $wpdb;

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        $param_table = PARAM_TABLE;
        $meta_table = META_TABLE;
        $pivot_table = PRODUCT_PARAMETERS_TABLE;
        // Build WHERE
        $where = "status = 'active'";
        if ($search) {
            $where .= $wpdb->prepare(" AND (title LIKE %s OR front_name LIKE %s OR slug LIKE %s)", "%$search%", "%$search%", "%$search%");
        }
        if (!empty($_POST['exclude'])) {
            $where .= " AND id NOT IN (" . implode(',', array_map('intval', $_POST['exclude'])) . ")";
        }

        // wp_send_json_success(['params' => $params]);
        $params = $wpdb->get_results("SELECT id, title, front_name FROM $param_table WHERE $where ORDER BY title ASC", ARRAY_A);

        // Optionally, fetch options for these params as well (for full UI preview)
        foreach ($params as &$param) {
            $param['options'] = [];
            $option_rows = $wpdb->get_results($wpdb->prepare("SELECT id, meta_value FROM $meta_table WHERE parameter_id = %d", $param['id']), ARRAY_A);
            foreach ($option_rows as $opt_row) {
                $meta = maybe_unserialize($opt_row['meta_value']);
                $param['options'][] = [
                    'id' => $opt_row['id'],
                    'title' => $meta['option'] ?? '',
                    'image' => $meta['image'] ?? '',
                    'cost' => $meta['cost'] ?? '',
                    'slug' => $meta['slug'] ?? '',
                ];
            }
        }

        wp_send_json_success(['params' => $params]);
    }

    function ppc_param_row_markup_handler() {
        global $wpdb;
        $param_id = intval($_POST['param_id']);
        // Fetch param and its options as in your edit logic
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, title, front_name FROM " . PARAM_TABLE . " WHERE id = %d", $param_id), ARRAY_A);
        if (!$row) wp_send_json_error();

        $meta_rows = $wpdb->get_results($wpdb->prepare("SELECT id, meta_value FROM " . META_TABLE . " WHERE parameter_id = %d", $param_id), ARRAY_A);
        $row['options'] = [];
        foreach ($meta_rows as $m) {
            $mv = maybe_unserialize($m['meta_value']);
            $row['options'][] = [
                'id' => $m['id'],
                'title' => $mv['option'] ?? '',
                'image' => $mv['image'] ?? '',
                'cost' => $mv['cost'] ?? '',
                'slug' => $mv['slug'] ?? '',
            ];
        }
        $param = $row;
        $data = ['params' => []]; // empty for new
        ob_start();
        include plugin_dir_path(__FILE__) . '/../Templates/Products/param-row.php';
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }   

    public function ppc_save_param_order() {
        global $wpdb;
        $product_id = intval($_POST['product_id']);
        $param_ids = $_POST['param_ids'] ?? [];
        $positions = $_POST['positions'] ?? [];

        // Save new positions to pivot table
        foreach ($param_ids as $i => $param_id) {
            $position = isset($positions[$i]) ? intval($positions[$i]) : $i;
            $wpdb->update(
                PRODUCT_PARAMETERS_TABLE,
                ['position' => $position],
                [
                    'product_id' => $product_id,
                    'parameter_id' => $param_id
                ]
            );
        }
        wp_send_json_success();
    }
}
