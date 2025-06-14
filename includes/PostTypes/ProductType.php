<?php
namespace PPC\PostTypes;

class ProductType {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type() {
        register_post_type('ppc_product', [
            'labels' => [
                'name' => 'Print Products',
                'singular_name' => 'Print Product'
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-media-default',
        ]);
    }
}
