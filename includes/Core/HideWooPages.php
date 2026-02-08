<?php
namespace PPC\Core;


class HideWooPages {
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'hide_woo_pages']);
        add_filter('wp_get_nav_menu_items', [__CLASS__, 'remove_woo_from_menu']);
    }

    public static function hide_woo_pages() {
        if (is_admin() || defined('DOING_AJAX') || defined('REST_REQUEST') || php_sapi_name() === 'cli') return;
        if (is_cart() || is_checkout()) return;
        if (is_shop() || is_product() || is_product_category() || is_product_tag() || is_account_page()) {
            wp_safe_redirect(home_url());
            exit;
        }
    }

    public static function remove_woo_from_menu($items) {
        foreach ($items as $key => $item) {
            if ('product' === $item->object || 'product_cat' === $item->object || 'product_tag' === $item->object) {
                unset($items[$key]);
            }
        }
        return $items;
    }
}

