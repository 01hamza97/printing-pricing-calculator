<?php
namespace PPC\Frontend;

class CalculatorWithPriceShortcodeHandler
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_tailwind']);
        add_shortcode('ppc_calculator_with_price', [$this, 'render_calculator']);
    }

    /**
     * Only enqueue Tailwind CSS when the shortcode is present in content.
     */
    public function maybe_enqueue_tailwind()
    {
        if (is_singular()) {
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ppc_calculator_with_price')) {
                wp_enqueue_script('ppc-admin-script', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', ['jquery'], null, true);
                wp_enqueue_style( 'ppc-client-style', "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css", [], null );
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
            return '<div class="ppc-calc-error">' . esc_html__( 'Product not found.', 'printing-pricing-calculator' ) . '</div>';
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

        if(!isset($product['instructions_file_id']) || $product['instructions_file_id'] == null || empty($product['instructions_file_id'])) {
            $product['instructions_file_id'] = get_option('ppc_instructions_pdf_id', 0);
        }

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

        $conditions = ['option' => [], 'parameter' => []];

        if (defined('PRODUCT_OPTION_CONDITIONS_TABLE')) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM " . PRODUCT_OPTION_CONDITIONS_TABLE . " WHERE product_id = %d ORDER BY logic_group, id",
                    $product_id
                ),
                ARRAY_A
            );

            if ($rows) {
                // group rows by a key (option_id or source_param_id), then by logic_group
                $byKey = ['option' => [], 'parameter' => []];

                foreach ($rows as $r) {
                    $is_param = !empty($r['source_type']) && $r['source_type'] === 'parameter';
                    $bucket   = $is_param ? 'parameter' : 'option';
                    $key      = $is_param ? intval($r['source_param_id'] ?? 0) : intval($r['option_id'] ?? 0);
                    if ($key <= 0) { continue; }

                    if (!isset($byKey[$bucket][$key])) {
                        $byKey[$bucket][$key] = [];
                    }
                    $g = intval($r['logic_group'] ?? 1);
                    if (!isset($byKey[$bucket][$key][$g])) {
                        $byKey[$bucket][$key][$g] = [
                            'operator' => ($r['operator'] ?? 'AND'),
                            'rows'     => []
                        ];
                    }
                    $byKey[$bucket][$key][$g]['rows'][] = [
                        'target_param_id'  => intval($r['target_param_id'] ?? 0),
                        // 0 or empty means ANY option
                        'target_option_id' => isset($r['target_option_id']) && $r['target_option_id'] !== '' ? intval($r['target_option_id']) : 0,
                        'action'           => ($r['action'] === 'hide' ? 'hide' : 'show'),
                    ];
                }

                // normalize to arrays: groups sorted by logic_group index
                foreach (['option','parameter'] as $bk) {
                    foreach ($byKey[$bk] as $k => $groups) {
                        ksort($groups);
                        $conditions[$bk][$k] = array_values($groups); // [{operator, rows:[...]}...]
                    }
                }
            }
        }
        $ppc_currency = array(
          'symbol'       => get_woocommerce_currency_symbol(),
          'position'     => get_option('woocommerce_currency_pos', 'right_space'), // left, right, left_space, right_space
          'thousand_sep' => wc_get_price_thousand_separator(),
          'decimal_sep'  => wc_get_price_decimal_separator(),
          'num_decimals' => wc_get_price_decimals(),
        );
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
            global_discount_rules: <?php echo json_encode($global_discount_rules); ?>,
            conditions: <?php echo wp_json_encode($conditions); ?>,
            ppcCurrency: <?php echo wp_json_encode( $ppc_currency ); ?>
        };
        </script>
        <?php

        // Pass PHP vars as well for PHP-side template rendering if needed:
        include plugin_dir_path(__FILE__) . '../Templates/Frontend/calculator-ui-with-price.php';
        return ob_get_clean();
    }

}
