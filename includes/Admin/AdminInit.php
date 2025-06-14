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
}
