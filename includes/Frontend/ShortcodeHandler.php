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
                // wp_enqueue_style(
                //     'ppc-tailwind',
                //     'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
                //     [],
                //     '3.4.3'
                // );
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
            'id' => 0,
        ], $atts);

        $product_id = intval($atts['id']);

        // Fetch product
        $product = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . PRODUCT_TABLE . " WHERE id = %d AND status = 'active'", $product_id),
            ARRAY_A
        );

        if (! $product) {
            return '<div class="ppc-calc-error">Product not found.</div>';
        }

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
                        "SELECT * FROM " . PRODUCT_PARAM_META_TABLE . " AS param_product_price LEFT JOIN ". META_TABLE ." AS meta ON param_product_price.option_id = meta.id WHERE product_id = %d",
                        $product_id
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

        var_dump($parameters);

        ob_start();
        include plugin_dir_path(__FILE__) . '../Templates/Frontend/calculator-ui.php';
        return ob_get_clean();
    }

}
