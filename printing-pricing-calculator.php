<?php
/**
 * Plugin Name: Printing Pricing Calculator
 * Description: A WooCommerce plugin for calculating product prices based on dynamic formulas.
 * Version: 1.0
 * Author: Hamza Samad
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

// On activation, call the table-creation method
register_activation_hook(
    __FILE__,
    ['\PPC\Parameters\ParametersInit', 'create_tables']
);

register_activation_hook(
    __FILE__,
    ['\PPC\Products\ProductsInit', 'create_tables']
);

function ppc_bootstrap_plugin() {
    $loader = new \PPC\Core\Loader();
    $loader->init();
}

add_action('plugins_loaded', 'ppc_bootstrap_plugin');
