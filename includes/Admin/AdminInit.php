<?php
namespace PPC\Admin;

class AdminInit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_admin_menu() {
        add_menu_page(
            'Print Calculator',
            'Print Calculator',
            'manage_options',
            'ppc-calculator',
            [$this, 'render_admin_page'],
            'dashicons-media-document',
            56
        );
        add_submenu_page(
            'ppc-calculator',
            'Settings',
            'Settings',
            'manage_options',
            'ppc-settings',
            [$this, 'render_settings_page']
    );
    }

    public function render_admin_page() {
        echo '<div class="wrap"><h1>Print Calculator</h1>';
        echo '<p>This plugin uses a custom post type to manage print products with categories and pricing parameters.</p>';
        echo '</div>';
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_ppc-calculator') return;

        wp_enqueue_style('ppc-admin-style', plugin_dir_url(__FILE__) . '../../assets/css/admin.css');
        wp_enqueue_script('ppc-admin-script', plugin_dir_url(__FILE__) . '../../assets/js/admin.js', ['jquery'], null, true);
    }

    public function render_settings_page() {
      global $wpdb;
       if (isset($_POST['ppc_settings_save'])) {
        check_admin_referer('ppc_settings_save');
        update_option('ppc_express_delivery_charges', floatval($_POST['express_delivery_charges']));
        update_option('ppc_minimum_order_quantity', intval($_POST['minimum_order_quantity']));
        update_option('ppc_pdf_quotation_note', wp_kses_post($_POST['pdf_quotation_note']));
        update_option('ppc_express_delivery_type', in_array($_POST['express_delivery_type'], ['percent', 'flat']) ? $_POST['express_delivery_type'] : 'percent');
        update_option('ppc_tax_percentage', floatval($_POST['ppc_tax_percentage']));
        update_option('ppc_default_currency', in_array($_POST['ppc_default_currency'], ['CZK', 'EUR']) ? $_POST['ppc_default_currency'] : 'CZK');
        update_option('ppc_czk_eur_rate', floatval($_POST['ppc_czk_eur_rate']));
        update_option('ppc_file_check_price', floatval($_POST['ppc_file_check_price']));
        update_option('ppc_cart_expiry_days', floatval($_POST['ppc_cart_expiry_days']));
        $discounts = [];
        if (!empty($_POST['discount_qty']) && !empty($_POST['discount_percent'])) {
            $qtys = $_POST['discount_qty'];
            $percents = $_POST['discount_percent'];
            for ($i = 0; $i < count($qtys); $i++) {
                $qty = intval($qtys[$i]);
                $percent = floatval($percents[$i]);
                if ($qty > 0 && $percent > 0) {
                    $discounts[] = ['qty' => $qty, 'percent' => $percent];
                }
            }
            // Sort by qty descending for easy matching
            usort($discounts, function($a, $b) {
                return $b['qty'] - $a['qty'];
            });
        }
        update_option('ppc_discount_rules', $discounts);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
      include plugin_dir_path(__FILE__) . '/../Templates/Settings/form.php';
    }

    public static function import_image_to_media($image_url, $post_id = 0)
    {
        // Only process HTTP(S) URLs
        if (empty($image_url) || !preg_match('#^https?://#', $image_url)) {
            return $image_url;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Suppress output (media_sideload_image normally echoes HTML)
        ob_start();
        $new_url = media_sideload_image($image_url, $post_id, null, 'src');
        ob_end_clean();

        // On failure, return original URL
        if (is_wp_error($new_url) || empty($new_url)) {
            return $image_url;
        }

        return $new_url;
    }
}
