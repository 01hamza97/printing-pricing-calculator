<?php
namespace PPC\Admin;

class AdminInit {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Print Calculator', 'printing-pricing-calculator'),
            __('Print Calculator', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-calculator',
            [$this, 'render_admin_page'],
            'dashicons-media-document',
            56
        );
        add_submenu_page(
            'ppc-calculator',
            __('Settings', 'printing-pricing-calculator'),
            __('Settings', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Print Calculator', 'printing-pricing-calculator' ) . '</h1>';
        echo '<p>' . esc_html__( 'This plugin uses a custom post type to manage print products with categories and pricing parameters.', 'printing-pricing-calculator' ) . '</p>';
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

        // --- GLOBAL INSTRUCTIONS PDF UPLOAD/REMOVE ---
        $existing_id = (int) get_option('ppc_instructions_pdf_id', 0);

        // If user asked to remove the current PDF
        if (!empty($_POST['ppc_instructions_pdf_remove']) && $existing_id) {
            // delete the attachment (and file) from media library
            wp_delete_attachment($existing_id, true);
            update_option('ppc_instructions_pdf_id', 0);
        }

        // If a new PDF was uploaded
        if (!empty($_FILES['ppc_instructions_pdf']) && !empty($_FILES['ppc_instructions_pdf']['name'])) {
            // Limit to PDFs only
            $filename = $_FILES['ppc_instructions_pdf']['name'];
            $finfo    = wp_check_filetype_and_ext(
                $_FILES['ppc_instructions_pdf']['tmp_name'],
                $filename,
                ['pdf' => 'application/pdf']
            );

            if ($finfo['ext'] !== 'pdf') {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please upload a valid PDF file.', 'printing-pricing-calculator') . '</p></div>';
            } else {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // Upload to Media Library and create attachment
                $attach_id = media_handle_upload('ppc_instructions_pdf', 0, [], ['test_form' => false]);
                if ( is_wp_error( $attach_id ) ) {
                    echo '<div class="notice notice-error"><p>' .
                        sprintf(
                            esc_html__( 'Upload failed: %s', 'printing-pricing-calculator' ),
                            esc_html( $attach_id->get_error_message() )
                        ) .
                    '</p></div>';
                } else {
                    // Replace existing (optional: delete old attachment)
                    if ($existing_id) {
                        wp_delete_attachment($existing_id, true);
                    }
                    update_option('ppc_instructions_pdf_id', (int) $attach_id);
                }
            }
        }

        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'printing-pricing-calculator') . '</p></div>';
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
