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
        // Works only on single post/page (not archive)
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

        // Express Delivery Calculation Settings (product-specific, fallback to global)
        $express_delivery_value = null;
        $express_delivery_type = null;
        // Check if the product has specific express settings
        if (isset($product['express_delivery_value']) && $product['express_delivery_value'] !== null && $product['express_delivery_value'] !== '') {
            $express_delivery_value = $product['express_delivery_value'];
            $express_delivery_type = $product['express_delivery_type'] ?? 'percent';
        } else {
            // Fall back to global settings
            $express_delivery_value = get_option('ppc_express_delivery_charges', 15);
            $express_delivery_type = get_option('ppc_express_delivery_type', 'percent');
        }

        // Pass these values to the template
        // ... right before ob_start();
        $express_delivery = [
            'value' => $express_delivery_value,
            'type'  => $express_delivery_type,
        ];

        $min_order_qty = isset($product['min_order_qty']) && $product['min_order_qty'] !== null && $product['min_order_qty'] !== ''
            ? intval($product['min_order_qty'])
            : intval(get_option('ppc_minimum_order_quantity', 100));

        $tax = floatval(get_option('ppc_tax_percentage', 0));

        $discount_rules = get_option('ppc_discount_rules', []);

        // Fetch parameters related to this product
        $parameter_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT parameter_id FROM " . PRODUCT_PARAMETERS_TABLE . " WHERE product_id = %d", $product_id)
        );

        if ($parameter_ids) {
            // Fetch parameter records
            $in_placeholder = implode(',', array_fill(0, count($parameter_ids), '%d'));
            $parameters     = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM " . PARAM_TABLE . " WHERE id IN ($in_placeholder) AND status = 'active'",
                    ...$parameter_ids
                ),
                ARRAY_A
            );

            // For each parameter, fetch options
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

        ob_start();
        include plugin_dir_path(__FILE__) . '../Templates/Frontend/calculator-ui.php';
        return ob_get_clean();
    }

}
