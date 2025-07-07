<?php
namespace PPC\Checkout;

class CheckoutInit {
    public function __construct() {
        add_action('wp_ajax_ppc_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_ppc_add_to_cart', [$this, 'ajax_add_to_cart']);

        // Display custom meta in cart/checkout/order
        add_filter('woocommerce_get_item_data', [$this, 'show_cart_item_data'], 10, 2);

        // Attach file to order meta
        add_action('woocommerce_new_order_item', [$this, 'add_order_item_meta'], 10, 3);

        // Optionally: Clean up file after order cancelled/trash (if needed)
        add_action('woocommerce_order_status_cancelled', [$this, 'delete_uploaded_files_from_order']);
        add_action('woocommerce_order_status_trash', [$this, 'delete_uploaded_files_from_order']);

        add_filter('woocommerce_cart_item_name', [$this, 'set_woocommerce_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'set_woocommerce_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'set_woocommerce_cart_item_subtotal'], 10, 3);
    }

    public function ajax_add_to_cart() {
        if (!class_exists('WC_Cart')) wp_send_json_error('WooCommerce not active');

        $product_id = get_option('ppc_wc_stub_product_id');
        if (!$product_id || 'publish' !== get_post_status($product_id)) {
            wp_send_json_error('Runtime product not found');
        }

        $qty = max(1, intval($_POST['qty'] ?? 1));

        // Handle file upload (if any)
        $file_url = '';
        $file_name = '';
        if (!empty($_FILES['file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                $file_url = esc_url_raw($upload['url']);
                $file_name = basename($file_url);
            }
        }

        // Gather meta for the cart item
        $item_data = [
            'ppc_product_id' => sanitize_text_field($_POST['ppc_product_id'] ?? ''), // ID or slug
            'params'         => isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [],
            'express'        => !empty($_POST['express']),
            'file_check'     => !empty($_POST['file_check']),
            'file_url'       => $file_url,
            'file_name'      => $file_name,
            'summary'        => isset($_POST['summary_html']) ? wp_kses_post($_POST['summary_html']) : '',
            'calc_total'     => isset($_POST['total']) ? floatval($_POST['total']) : '',
            'discount'       => isset($_POST['discount']) ? floatval($_POST['discount']) : '',
            'tax'            => isset($_POST['tax']) ? floatval($_POST['tax']) : '',
            'qty'            => $qty,
            'ppc_product_title' => isset($_POST['ppc_product_title']) ? sanitize_text_field($_POST['ppc_product_title']) : '',
            'image' => isset($_POST['image']) ? sanitize_text_field($_POST['image']) : '',
        ];

        $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, 0, [], $item_data);
        if ($cart_item_key) {
            wp_send_json_success(['cart_url' => wc_get_cart_url()]);
        } else {
            wp_send_json_error('Add to cart failed');
        }
    }

    public function show_cart_item_data($item_data, $cart_item) {
        // Params (as before)
        if (!empty($cart_item['params']) && is_array($cart_item['params'])) {
            foreach ($cart_item['params'] as $param) {
                if (!empty($param['title']) && !empty($param['value'])) {
                    $item_data[] = [
                        'key'   => wc_clean($param['title']),
                        'value' => wc_clean($param['value']),
                    ];
                }
            }
        }
        // Express
        if (!empty($cart_item['express'])) {
            $item_data[] = [
                'key'   => __('Express Delivery', 'printing-pricing-calculator'),
                'value' => __('Yes', 'printing-pricing-calculator')
            ];
        }
        // File check
        if (!empty($cart_item['file_check'])) {
            $item_data[] = [
                'key'   => __('File Check', 'printing-pricing-calculator'),
                'value' => __('Yes', 'printing-pricing-calculator')
            ];
        }
        // Uploaded file link
        if (!empty($cart_item['file_url'])) {
            $item_data[] = [
                'key'   => __('Uploaded File', 'printing-pricing-calculator'),
                'value' => '<a href="' . esc_url($cart_item['file_url']) . '" target="_blank">' . esc_html($cart_item['file_name']) . '</a>'
            ];
        }
        // Quantity (displayed explicitly)
        if (!empty($cart_item['qty'])) {
            $item_data[] = [
                'key'   => __('Quantity', 'printing-pricing-calculator'),
                'value' => intval($cart_item['qty']),
            ];
        }
        // Discount
        if (!empty($cart_item['discount'])) {
            $item_data[] = [
                'key'   => __('Discount', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['discount']),
            ];
        }
        // Tax
        if (!empty($cart_item['tax'])) {
            $item_data[] = [
                'key'   => __('Tax', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['tax']),
            ];
        }
        // Total from calculator
        if (!empty($cart_item['calc_total'])) {
            $item_data[] = [
                'key'   => __('Calculated Total', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['calc_total']),
            ];
        }
        return $item_data;
    }

    // Attach cart meta to order items for order details (WooCommerce 3.0+)
    public function add_order_item_meta($item_id, $values, $cart_item_key) {
        if (!empty($values['params'])) {
            wc_add_order_item_meta($item_id, 'PPC Params', json_encode($values['params']));
        }
        if (!empty($values['express'])) {
            wc_add_order_item_meta($item_id, 'Express Delivery', 'Yes');
        }
        if (!empty($values['file_check'])) {
            wc_add_order_item_meta($item_id, 'File Check', 'Yes');
        }
        if (!empty($values['file_url'])) {
            wc_add_order_item_meta($item_id, 'File URL', $values['file_url']);
        }
        if (!empty($values['file_name'])) {
            wc_add_order_item_meta($item_id, 'File Name', $values['file_name']);
        }
        if (!empty($values['calc_total'])) {
            wc_add_order_item_meta($item_id, 'Calculated Total', $values['calc_total']);
        }
        if (!empty($values['discount'])) {
            wc_add_order_item_meta($item_id, 'Discount', $values['discount']);
        }
        if (!empty($values['tax'])) {
            wc_add_order_item_meta($item_id, 'Tax', $values['tax']);
        }
        if (!empty($values['qty'])) {
            wc_add_order_item_meta($item_id, 'Quantity', intval($values['qty']));
        }
    }

    // Optionally: Delete file from server if order is cancelled or trashed (cleanup)
    public function delete_uploaded_files_from_order($order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            $file_url = wc_get_order_item_meta($item->get_id(), 'File URL', true);
            if ($file_url) {
                $path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $file_url);
                if (file_exists($path)) @unlink($path);
            }
        }
    }

}
