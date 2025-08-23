<?php
namespace PPC\Checkout;

class CheckoutInit {
    public function __construct() {
        add_action('wp_ajax_ppc_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_ppc_add_to_cart', [$this, 'ajax_add_to_cart']);

        // Display custom meta in cart/checkout/order
        add_filter('woocommerce_get_item_data', [$this, 'show_cart_item_data'], 10, 2);
        add_filter('woocommerce_after_order_itemmeta', [$this, 'show_order_item_data'], 10, 3);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_order_item_data'], 10, 1);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'hide_order_item_data_frontend'], 10, 2);

        // Attach file to order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);

        // Optionally: Clean up file after order cancelled/trash (if needed)
        add_action('woocommerce_order_status_cancelled', [$this, 'delete_uploaded_files_from_order']);
        add_action('woocommerce_order_status_trash', [$this, 'delete_uploaded_files_from_order']);

        add_filter('woocommerce_cart_item_name', [$this, 'set_woocommerce_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'set_woocommerce_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'set_woocommerce_cart_item_subtotal'], 10, 3);

        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_tailwind']);
    }

    public function maybe_enqueue_tailwind()
    {
        if (is_singular()) {
            global $post;
            wp_enqueue_script('ppc-admin-script', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', ['jquery'], null, true);
        }
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
            'customer_note' => !empty($_POST['customer_note']) ? $_POST['customer_note'] : null
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
        // Customer Note
        if (!empty($cart_item['customer_note'])) {
            $item_data[] = [
                'key'   => __('Note', 'printing-pricing-calculator'),
                'value' => $cart_item['customer_note'],
            ];
        }
        return $item_data;
    }

    // Attach cart meta to order items for order details (WooCommerce 3.0+)
    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        // $item is WC_Order_Item_Product
        // $values is your $cart_item
        // Add meta using $item->add_meta_data()
        if (!empty($values['params'])) {
            $item->add_meta_data('PPC Params', json_encode($values['params']));
        }
        if (!empty($values['express'])) {
            $item->add_meta_data('Express Delivery', 'Yes');
        }
        if (!empty($values['file_check'])) {
            $item->add_meta_data('File Check', 'Yes');
        }
        if (!empty($values['file_url'])) {
            $item->add_meta_data('File URL', $values['file_url']);
        }
        if (!empty($values['file_name'])) {
            $item->add_meta_data('File Name', $values['file_name']);
        }
        if (!empty($values['calc_total'])) {
            $item->add_meta_data('Calculated Total', $values['calc_total']);
        }
        if (!empty($values['discount'])) {
            $item->add_meta_data('Discount', $values['discount']);
        }
        if (!empty($values['tax'])) {
            $item->add_meta_data('Tax', $values['tax']);
        }
        if (!empty($values['qty'])) {
            $item->add_meta_data('Quantity', intval($values['qty']));
        }
        if (!empty($values['customer_note'])) {
            $item->add_meta_data('customer_note', $values['customer_note']);
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

    public function show_order_item_data($item_id, $item, $product) {
            // Get ALL meta for this item
        $meta = wc_get_order_item_meta($item_id, '', false);

        // Only show custom meta fields (skip default like _product_id etc.), optional
        $excluded = [
            '_product_id', '_variation_id', '_qty', '_tax_class', '_line_subtotal', '_line_subtotal_tax',
            '_line_total', '_line_tax', '_reduced_stock', '_reduced_stock_later', '_restock_refunded_items', '_line_tax_data'
        ];

        echo '<div>';
        foreach($meta as $key => $value) {
            if(in_array($key, $excluded)) continue; // skip Woo default meta
            // Value might be array
            if(is_array($value) && count($value) === 1) $value = reset($value);
            if(is_array($value)) $value = json_encode($value);
            if($key == "PPC Params") {
                $value = json_decode($value);
                foreach ($value as $param) {
                    echo '<b>' . esc_html($param->title) . '</b>: <span style="color:#0071a1;">' . esc_html($param->value) . '</span><br>';
                } 
            } else {
                echo '<b>' . esc_html($key) . '</b>: <span style="color:#0071a1;">' . esc_html($value) . '</span><br>';
            } 
        }
        echo '</div>';
    }

    public function hide_order_item_data($hidden_keys) {
        $hidden_keys[] = 'PPC Params';
        $hidden_keys[] = 'Calculated Total';
        $hidden_keys[] = 'Tax';
        $hidden_keys[] = 'Quantity';
        return $hidden_keys;
    }

    public function hide_order_item_data_frontend($formatted_meta, $item) {
        if (is_admin()) return $formatted_meta; // don't filter in admin
        foreach ($formatted_meta as $key => $meta) {
            if ($meta->key === 'PPC Params') {
                // Example: decode value (maybe JSON) and replace with a nice format
                $data = maybe_unserialize($meta->value);
                if (is_string($data) && $decoded = json_decode($data, true)) {
                    // Build custom HTML
                    $html = '';
                    foreach ($decoded as $paramValue) {
                        $html .= '<li class="ml-5"><strong>' . esc_html($paramValue["title"]) . ': </strong><p>' . esc_html($paramValue["value"]) . '</p></li>';
                    }

                    // Hide the meta label (the "PPC Params:" part)
                    $meta->display_key = 'Selected Options';
                    // Replace Woo meta value output
                    $meta->display_value = $html;
                    $formatted_meta[$key] = $meta;
                }
            }
        }
        return $formatted_meta;
    }
}
