<?php
namespace PPC\Parameters;

class ParameterEdit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            null, // hidden from sidebar
            __('Edit Parameter', 'printing-pricing-calculator'),  // page title (localized)
            __('Edit Parameter', 'printing-pricing-calculator'),  // menu title (localized)
            'manage_options',
            'ppc-parameter-edit',
            [$this, 'render_form']
        );
    }

    public function render_form() {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'Unauthorized', 'printing-pricing-calculator' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ppc_parameters';
        $meta_table = $wpdb->prefix . 'ppc_parameter_meta';
        $is_edit = isset($_GET['id']);
        $id = $is_edit ? intval($_GET['id']) : 0;
        $data = ['title' => '', 'content' => ''];

        // Load existing data
        if ($is_edit && $id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                $data = $row;
            }
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('save_parameter')) {
            $title = sanitize_text_field($_POST['title']);
            $front_name = sanitize_text_field($_POST['front_name']);
            $content = wp_kses_post($_POST['content']);
            $slug = isset($_POST['slug']) && trim($_POST['slug']) !== '' ? sanitize_title($_POST['slug']) : sanitize_title($title);
            $original_slug = $slug;
            $i = 2;

            // Ensure slug uniqueness for parameters
            while (true) {
                $query = "SELECT id FROM $table WHERE slug = %s";
                $params = [$slug];
                if ($is_edit && $id) {
                    $query .= " AND id != %d";
                    $params[] = $id;
                }
                $exists = $wpdb->get_var($wpdb->prepare($query, ...$params));
                if (!$exists) break;
                $slug = $original_slug . '-' . $i++;
            }

            if ($is_edit) {
                $wpdb->update($table, [
                    'title' => $title,
                    'front_name' => $front_name,
                    'content' => $content,
                    'slug' => $slug,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $id]);
            } else {
                $wpdb->insert($table, [
                    'title' => $title,
                    'front_name' => $front_name,
                    'content' => $content,
                    'slug' => $slug,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $id = $wpdb->insert_id;
            }

            // Process Options
            error_log(__LINE__.'  '.print_r($_FILES, true)); // debug

            if (!empty($_POST['options']) && is_array($_POST['options'])) {
                $existing_slugs = [];

                // (1) First pass: collect all provided slugs or generate from option titles for uniqueness
                foreach ($_POST['options'] as $key => $opt) {
                    // Always sanitize and generate base slug from option title
                    $base_slug = sanitize_title($opt['option']);
                    $oslug = $base_slug;
                    $i = 2;
                    // Ensure uniqueness within this parameter's options
                    while (in_array($oslug, $existing_slugs, true)) {
                        $oslug = $base_slug . '-' . $i++;
                    }
                    $existing_slugs[] = $oslug;

                    $meta_id = isset($opt['meta_id']) ? intval($opt['meta_id']) : 0;

                    $value = [
                        'option' => sanitize_text_field($opt['option']),
                        'slug'   => $oslug, // store option slug
                        'cost'   => floatval($opt['cost']),
                        'image'  => $opt['existing_image'] ?? '',
                    ];

                    $file_input_name = isset($opt['meta_id']) ? $opt['meta_id'] : $key;
                    if (
                        isset($_FILES['option_files']['name'][$file_input_name]) &&
                        !empty($_FILES['option_files']['name'][$file_input_name])
                    ) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $file_array = [
                            'name'     => $_FILES['option_files']['name'][$file_input_name],
                            'type'     => $_FILES['option_files']['type'][$file_input_name],
                            'tmp_name' => $_FILES['option_files']['tmp_name'][$file_input_name],
                            'error'    => $_FILES['option_files']['error'][$file_input_name],
                            'size'     => $_FILES['option_files']['size'][$file_input_name],
                        ];

                        $upload = wp_handle_upload($file_array, ['test_form' => false]);
                        if (!isset($upload['error'])) {
                            $file_path = $upload['file'];
                            $file_name = basename($file_path);

                            // Prepare attachment data
                            $attachment = [
                                'post_mime_type' => $upload['type'],
                                'post_title'     => sanitize_file_name($file_name),
                                'post_content'   => '',
                                'post_status'    => 'inherit'
                            ];

                            $attach_id = wp_insert_attachment($attachment, $file_path);
                            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                            wp_update_attachment_metadata($attach_id, $attach_data);

                            $value['image'] = wp_get_attachment_url($attach_id);
                        }
                    }

                    if ($meta_id) {
                        // update
                        $wpdb->update($meta_table, [
                            'meta_value' => maybe_serialize($value),
                        ], ['id' => $meta_id]);
                    } else {
                        // insert
                        $wpdb->insert($meta_table, [
                            'parameter_id' => $id,
                            'meta_key'     => 'option',
                            'meta_value'   => maybe_serialize($value),
                        ]);
                    }
                }
            }

            echo "<script>location.href='" . esc_url( admin_url('admin.php?page=ppc-parameter-edit&id=' . $id) ) . "'</script>";
            exit;
        }

        include plugin_dir_path(__FILE__) . '../Templates/Parameters/form.php';
    }
}
