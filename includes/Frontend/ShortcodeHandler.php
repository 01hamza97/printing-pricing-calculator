<?php
namespace PPC\Frontend;

class ShortcodeHandler
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_tailwind']);
        add_shortcode('ppc_calculator', [$this, 'render_calculator']);
    }

    /**
     * Only enqueue Tailwind CSS when the shortcode is present in content.
     */
    public function maybe_enqueue_tailwind()
    {
        if (is_singular()) {
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ppc_calculator')) {
                wp_enqueue_script('ppc-admin-script', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', ['jquery'], null, true);
            }
        }
    }

    /**
     * Renders the calculator UI via template.
     */
    public function render_calculator($atts = [])
    {
        global $wpdb;

        $atts = shortcode_atts([
            'id'   => 0,
            'slug' => '',
        ], $atts);

        if(empty($atts['slug'])) {
            $atts['slug'] = get_query_var('ppc_slug', '');
        }

        $product = null;
        if (!empty($atts['slug'])) {
            $product = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM " . PRODUCT_TABLE . " WHERE slug = %s AND status = 'active'", $atts['slug']),
                ARRAY_A
            );
        } elseif (!empty($atts['id'])) {
            $product = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM " . PRODUCT_TABLE . " WHERE id = %d AND status = 'active'", $atts['id']),
                ARRAY_A
            );
        }

        if (! $product) {
            return '<div class="ppc-calc-error">Product not found.</div>';
        }
        $product_id = $product['id'];

        // ---- Express Delivery Calculation Settings (product-specific, fallback to global) ----
        if (isset($product['express_delivery_value']) && $product['express_delivery_value'] !== null && $product['express_delivery_value'] !== '') {
            $express_delivery_value = $product['express_delivery_value'];
            $express_delivery_type = $product['express_delivery_type'] ?? 'percent';
        } else {
            $express_delivery_value = get_option('ppc_express_delivery_charges', 15);
            $express_delivery_type = get_option('ppc_express_delivery_type', 'percent');
        }
        $express_delivery = [
            'value' => $express_delivery_value,
            'type'  => $express_delivery_type,
        ];

        // ---- Minimum Order Quantity ----
        $min_order_qty = isset($product['min_order_qty']) && $product['min_order_qty'] !== null && $product['min_order_qty'] !== ''
            ? intval($product['min_order_qty'])
            : intval(get_option('ppc_minimum_order_quantity', 100));

        // ---- Tax Percentage ----
        $tax = floatval(get_option('ppc_tax_percentage', 0));

        // ---- Discount Rules (product-level first, fallback to global) ----
        $product_discount_rules = [];
        if (!empty($product['discount_rules'])) {
            $product_discount_rules = maybe_unserialize($product['discount_rules']);
        }
        if (!is_array($product_discount_rules)) $product_discount_rules = [];

        $global_discount_rules = get_option('ppc_discount_rules', []);
        if (!is_array($global_discount_rules)) $global_discount_rules = [];

        // ---- File Check Service ----
        $file_check_price = isset($product['file_check_price']) && $product['file_check_price'] !== '' && $product['file_check_price'] !== null
            ? floatval($product['file_check_price'])
            : floatval(get_option('ppc_file_check_price', 0));
        $file_check_required = isset($product['file_check_required']) ? (int)$product['file_check_required'] : 0;

        // ---- PDF Quotation Note ----
        $pdf_quotation_note = get_option('ppc_pdf_quotation_note', '');

        // ---- Fetch parameters and options ----
        $parameter_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT parameter_id FROM " . PRODUCT_PARAMETERS_TABLE . " WHERE product_id = %d", $product_id)
        );

        if ($parameter_ids) {
            $in_placeholder = implode(',', array_fill(0, count($parameter_ids), '%d'));
            $parameters = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM " . PARAM_TABLE . " WHERE id IN ($in_placeholder) AND status = 'active'",
                    ...$parameter_ids
                ),
                ARRAY_A
            );

            // --- Sort parameters according to $parameter_ids order ---
            // Build a map id => parameter
            $param_map = [];
            foreach ($parameters as $param) {
                $param_map[$param['id']] = $param;
            }
            // Now order the parameters by the order in $parameter_ids
            $parameters = [];
            foreach ($parameter_ids as $pid) {
                if (isset($param_map[$pid])) {
                    $parameters[] = $param_map[$pid];
                }
            }

            // Fetch options as before
            foreach ($parameters as &$param) {
                $param['options'] = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM " . PRODUCT_PARAM_META_TABLE . " AS param_product_price LEFT JOIN ". META_TABLE ." AS meta ON param_product_price.option_id = meta.id WHERE product_id = %d AND parameter_id = %d",
                        $product_id, $param['id']
                    ),
                    ARRAY_A
                );
                foreach ($param['options'] as &$opt) {
                    $opt['meta_value'] = maybe_unserialize($opt['meta_value']);
                }
            }
        } else {
            $parameters = [];
        }

        // ---- Make variables available to template ----
        ob_start();

        // All JS-available settings for calculator:
        ?>
        <script>
        window.ppc_settings = {
            min_order_qty: <?php echo json_encode($min_order_qty); ?>,
            express_delivery_value: <?php echo json_encode($express_delivery_value); ?>,
            express_delivery_type: <?php echo json_encode($express_delivery_type); ?>,
            tax: <?php echo json_encode($tax); ?>,
            pdf_quotation_note: <?php echo json_encode($pdf_quotation_note); ?>,
            file_check_price: <?php echo json_encode($file_check_price); ?>,
            file_check_required: <?php echo json_encode($file_check_required); ?>,
            product_discount_rules: <?php echo json_encode($product_discount_rules); ?>,
            global_discount_rules: <?php echo json_encode($global_discount_rules); ?>
        };
        </script>
        <?php

        // Pass PHP vars as well for PHP-side template rendering if needed:
        include plugin_dir_path(__FILE__) . '../Templates/Frontend/calculator-ui.php';
        return ob_get_clean();
    }

}
