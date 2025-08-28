<?php
namespace PPC\Parameters;

class ParametersList
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu()
    {
        add_submenu_page(
            'ppc-calculator',
            __('Parameter List', 'printing-pricing-calculator'),
            __('Parameters', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-parameters',
            [$this, 'render_parameters_list']
        );
    }

    public function render_parameters_list()
    {
        global $wpdb;
        $table      = $wpdb->prefix . 'ppc_parameters';
        $parameters = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");

        include plugin_dir_path(__FILE__) . '../Templates/Parameters/list.php';

        // Deletion logic (after the include)
        if (isset($_GET['delete']) && current_user_can('manage_options')) {
            $id = intval($_GET['delete']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_param_' . $id)) {
                $wpdb->delete($table, ['id' => $id]);
                $wpdb->delete($wpdb->prefix . 'ppc_parameter_meta', ['parameter_id' => $id]);
                echo("<script>location.href = '".admin_url('admin.php?page=ppc-parameters&deleted=1')."'</script>");
                exit;
            }
        }

        if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['activate', 'deactivate'])) {
            $status = $_GET['action'] === 'activate' ? 'active' : 'inactive';
            $id = intval($_GET['id']);
            $wpdb->update($wpdb->prefix . 'ppc_parameters', ['status' => $status], ['id' => $id]);
            echo("<script>location.href = '".admin_url('admin.php?page=ppc-parameters&status_updated=1')."'</script>");
            exit;
        }

    }
}
