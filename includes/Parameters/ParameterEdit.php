<?php
namespace PPC\Parameters;

class ParameterEdit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            null, // hidden from sidebar
            'Edit Parameter',
            'Edit Parameter',
            'manage_options',
            'ppc-parameter-edit',
            [$this, 'render_form']
        );
    }

    public function render_form() {
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
            $content = wp_kses_post($_POST['content']);

            if ($is_edit) {
                $wpdb->update($table, [
                    'title' => $title,
                    'content' => $content,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $id]);
            } else {
                $wpdb->insert($table, [
                    'title' => $title,
                    'content' => $content,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $id = $wpdb->insert_id;
            }

            // Process Options
            error_log(__LINE__.'  '.print_r($_FILES, true)); // always use print_r with true for readable output

            if (!empty($_POST['options']) && is_array($_POST['options'])) {
                foreach ($_POST['options'] as $key => $opt) {
                    $meta_id = isset($opt['meta_id']) ? intval($opt['meta_id']) : 0;

                    $value = [
                        'option' => sanitize_text_field($opt['option']),
                        'cost'   => floatval($opt['cost']),
                        'image'  => $opt['existing_image'] ?? '',
                    ];



                    // Handle file upload
                    $file_input_name = isset($opt['meta_id']) ? $opt['meta_id'] : $key;
                    error_log(__LINE__.'  '.$file_input_name);
                    if (isset($_FILES['option_files']['name'][$file_input_name]) && !empty($_FILES['option_files']['name'][$file_input_name])) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';

                        $file_array = [
                            'name'     => $_FILES['option_files']['name'][$file_input_name],
                            'type'     => $_FILES['option_files']['type'][$file_input_name],
                            'tmp_name' => $_FILES['option_files']['tmp_name'][$file_input_name],
                            'error'    => $_FILES['option_files']['error'][$file_input_name],
                            'size'     => $_FILES['option_files']['size'][$file_input_name],
                        ];
                        error_log(__LINE__.'  '.print_r($file_array, true));
                        $upload = wp_handle_upload($file_array, ['test_form' => false]);

                        if (!isset($upload['error'])) {
                            $value['image'] = esc_url_raw($upload['url']);
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
              echo("<script>location.href = '".admin_url('admin.php?page=ppc-parameter-edit&id='.$id)."'</script>");
              exit;
        }


        include plugin_dir_path(__FILE__) . '../Templates/Parameters/form.php';
    }
}
