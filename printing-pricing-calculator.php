<?php
/**
 * Plugin Name: Printing Pricing Calculator
 * Description: A WooCommerce plugin for calculating product prices based on dynamic formulas.
 * Version: 1.0
 * Author: Hamza Samad
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/Core/Loader.php';

spl_autoload_register(function ($class) {
    // Only autoload PPC classes
    if (strpos($class, 'PPC\\') !== 0) return;

    $path = plugin_dir_path(__FILE__) . 'includes/' . str_replace('\\', '/', substr($class, 4)) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});
global $wpdb;

define('PARAM_TABLE', $wpdb->prefix . 'ppc_parameters');
define('META_TABLE', $wpdb->prefix . 'ppc_parameter_meta');
define('PRODUCT_TABLE', $wpdb->prefix . 'ppc_products');
define('PRODUCT_PARAMETERS_TABLE', $wpdb->prefix . 'ppc_product_parameters');
define('PRODUCT_PARAM_META_TABLE', $wpdb->prefix . 'ppc_product_option_prices');
define('PRODUCT_OPTION_CONDITIONS_TABLE', $wpdb->prefix . 'ppc_option_conditions');
define('CATEGORY_TABLE', $wpdb->prefix . 'ppc_categories');
define('PRODUCT_CATEGORY_TABLE', $wpdb->prefix . 'ppc_product_categories');

// On activation, call the table-creation method
register_activation_hook(
    __FILE__,
    ['\PPC\Parameters\ParametersInit', 'create_tables']
);

register_activation_hook(
    __FILE__,
    ['\PPC\Products\ProductsInit', 'create_tables']
);

register_activation_hook(
    __FILE__,
    ['\PPC\Categories\CategoriesInit', 'create_tables']
);

register_activation_hook(__FILE__, function() {
    // ... any other install tasks ...
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Register activation hook for stub product
register_activation_hook(__FILE__, function() {
    if (!function_exists('wc_get_product')) {
        // Optional: admin notice for missing WC
        // deactivate_plugins(plugin_basename(__FILE__));
        return;
    }
    ppc_ensure_stub_product();
});

// Your function as you wrote it
function ppc_ensure_stub_product() {
    $existing = get_option('ppc_wc_stub_product_id');
    if ($existing && get_post_status($existing) == 'publish') return $existing;

    $pid = wp_insert_post([
        'post_title'    => 'PPC Runtime Product',
        'post_content'  => 'This is a stub for custom print orders.',
        'post_status'   => 'publish',
        'post_type'     => 'product',
        'post_author'   => 1,
        'meta_input'    => [
            '_price' => 1,
            '_regular_price' => 1,
            '_virtual' => 'yes',
        ]
    ]);
    update_option('ppc_wc_stub_product_id', $pid);
    return $pid;
}

function ppc_bootstrap_plugin() {
    $loader = new \PPC\Core\Loader();
    $loader->init();
}

add_action('plugins_loaded', 'ppc_bootstrap_plugin');

// Register the custom query var for pretty calculator URLs
add_filter('query_vars', function($vars) {
    $vars[] = 'ppc_slug';
    return $vars;
});

add_action('init', function() {
    add_rewrite_rule(
        '^product/([^/]+)/?',
        'index.php?pagename=product&ppc_slug=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^category/([^/]+)/?$',
        'index.php?pagename=category&ppc_category=$matches[1]',
        'top'
    );
});

// Check if WooCommerce is active
function ppc_check_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        // Deactivate this plugin
        deactivate_plugins(plugin_basename(__FILE__));
        // Show admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>PPC Pricing Calculator</strong> requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> to be installed and active.</p></div>';
        });
    }
}
add_action('admin_init', 'ppc_check_woocommerce_active');


register_activation_hook(__FILE__, function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'PPC Pricing Calculator requires WooCommerce to be installed and active.',
            'Plugin dependency check',
            ['back_link' => true]
        );
    } else {
        // Safe to call your stub product creator here
        ppc_ensure_stub_product();
    }
});

add_filter('wpseo_sitemap_exclude_post_type', function($value, $post_type) {
    if (in_array($post_type, ['product', 'product_cat', 'product_tag'])) return true;
    return $value;
}, 10, 2);


add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (!empty($cart_item['calc_total']) && !empty($cart_item['quantity']) && $cart_item['data']) {
            $unit_price = floatval($cart_item['calc_total']) / intval($cart_item['quantity']);
            $cart_item['data']->set_price($unit_price);
            // Optional: make sure it's tax exempt
            $cart_item['data']->set_tax_status('none');
            $cart_item['data']->set_name($cart_item['ppc_product_title']);
            $cart_item['data']->set_description("");
            $cart_item['data']->set_image_id(attachment_url_to_postid($cart_item['image']));
        }
    }
});

add_filter('woocommerce_cart_item_permalink', '__return_false');

function custom_remove_all_quantity_fields( $return, $product ) {
    return true;
}
add_filter( 'woocommerce_is_sold_individually', 'custom_remove_all_quantity_fields', 10, 2 );