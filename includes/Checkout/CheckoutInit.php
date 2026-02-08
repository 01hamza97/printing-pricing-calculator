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
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'set_woocommerce_cart_item_thumbnail'], 9999, 3); // high priority
        add_filter('woocommerce_cart_item_name', [$this, 'prepend_image_to_cart_item_name'], 5, 3); // fallback
        add_filter('woocommerce_order_item_thumbnail', [$this, 'set_woocommerce_order_item_thumbnail'], 10, 2);
        add_filter('woocommerce_store_api_cart_item_images', [$this, 'set_woocommerce_store_api_cart_item_images'], 10, 3);
        

        // Attach file to order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);

        // Optionally: Clean up file after order cancelled/trash (if needed)
        add_action('woocommerce_order_status_cancelled', [$this, 'delete_uploaded_files_from_order']);
        add_action('woocommerce_order_status_trash', [$this, 'delete_uploaded_files_from_order']);

        add_filter('woocommerce_cart_item_price', [$this, 'set_woocommerce_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'set_woocommerce_cart_item_subtotal'], 10, 3);

        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_tailwind']);
        add_action( 'wp_head', [ $this, 'conditionally_hide_totals_css' ], 20 );
    }

    public function maybe_enqueue_tailwind()
    {
        if ( is_singular() ) {
            wp_enqueue_script('ppc-admin-script', 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4', ['jquery'], null, true);
        }
    }

    public function ajax_add_to_cart() {
        if (! class_exists('WC_Cart') ) {
            wp_send_json_error( __( 'WooCommerce not active', 'printing-pricing-calculator' ) );
        }

        $product_id = get_option('ppc_wc_stub_product_id');
        if ( ! $product_id || 'publish' !== get_post_status($product_id) ) {
            wp_send_json_error( __( 'Runtime product not found', 'printing-pricing-calculator' ) );
        }

        $qty = max(1, intval($_POST['qty'] ?? 1));

        // Handle file upload (if any)
        $file_url = '';
        $file_name = '';
        if ( ! empty($_FILES['file']['name']) ) {
            require_once WP_PLUGIN_DIR . '/printing-pricing-calculator/vendor/autoload.php';

            $account_id = get_option('R2_ACCOUNT_ID');
            $access_key = get_option('R2_ACCESS_KEY_ID');
            $secret_key = get_option('R2_SECRET_ACCESS_KEY');
            $base_url   = rtrim(get_option('R2_PUBLIC_BASE'), '/');  // e.g. https://example.r2.cloudflarestorage.com/bucket
            $bucket     = get_option('R2_BUCKET');
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            // Prepare file
            $tmpPath   = $_FILES['file']['tmp_name'];
            $file_name = sanitize_file_name($_FILES['file']['name']);

            // Build S3 client for Cloudflare R2
            $s3 = new \Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => 'auto',
                'endpoint'    => "https://{$account_id}.r2.cloudflarestorage.com",
                'credentials' => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            try {
                // Upload to R2
                $result = $s3->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => 'orders/' . time() . '-' . $file_name,
                    'SourceFile'  => $tmpPath,
                    'ACL'         => 'public-read',
                    'ContentType' => mime_content_type($tmpPath),
                ]);

                // Public URL
                $file_url = $base_url . '/' . $result['ObjectURL'];

                // OR if your base URL *already contains bucket*, use:
                // $file_url = $base_url . '/orders/' . time() . '-' . $file_name;

            } catch (Exception $e) {
                wp_send_json_error("R2 Upload Failed: " . $e->getMessage());
            }
        }

        // Gather meta for the cart item
        $item_data = [
            'ppc_product_id'    => sanitize_text_field($_POST['ppc_product_id'] ?? ''), // ID or slug
            'params'            => isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [],
            'express'           => ! empty($_POST['express']),
            'file_check'        => ! empty($_POST['file_check']),
            'file_url'          => $file_url,
            'file_name'         => $file_name,
            'summary'           => isset($_POST['summary_html']) ? wp_kses_post($_POST['summary_html']) : '',
            'calc_total'        => isset($_POST['total']) ? floatval($_POST['total']) : '',
            'discount'          => isset($_POST['discount']) ? floatval($_POST['discount']) : '',
            'tax'               => isset($_POST['tax']) ? floatval($_POST['tax']) : '',
            'qty'               => $qty,
            'ppc_product_title' => isset($_POST['ppc_product_title']) ? sanitize_text_field($_POST['ppc_product_title']) : '',
            'image'             => isset($_POST['image']) ? sanitize_text_field($_POST['image']) : '',
            'customer_note'     => ! empty($_POST['customer_note']) ? sanitize_text_field($_POST['customer_note']) : null,
            'ordered_with_prices'=> ! empty($_POST['ordered_with_prices'])
        ];

        $cart_item_key = WC()->cart->add_to_cart($product_id, $qty, 0, [], $item_data);
        if ( $cart_item_key ) {
            wp_send_json_success(['cart_url' => wc_get_cart_url()]);
        } else {
            wp_send_json_error( __( 'Add to cart failed', 'printing-pricing-calculator' ) );
        }
    }

    public function show_cart_item_data($item_data, $cart_item) {
        if (! empty($cart_item['params']) && is_array($cart_item['params'])) {
            foreach ($cart_item['params'] as $param) {
                if (! empty($param['title']) && ! empty($param['value'])) {
                    $item_data[] = [
                        'key'   => wc_clean($param['title']),
                        'value' => wc_clean($param['value']),
                    ];
                }
            }
        }
        if (! empty($cart_item['express'])) {
            $item_data[] = [
                'key'   => __('Express Delivery', 'printing-pricing-calculator'),
                'value' => __('Yes', 'printing-pricing-calculator'),
            ];
        }
        if (! empty($cart_item['file_check'])) {
            $item_data[] = [
                'key'   => __('File Check', 'printing-pricing-calculator'),
                'value' => __('Yes', 'printing-pricing-calculator'),
            ];
        }
        if (! empty($cart_item['file_url'])) {
            $item_data[] = [
                'key'   => __('Uploaded File', 'printing-pricing-calculator'),
                'value' => '<a href="'. esc_url($cart_item['file_url']) .'" target="_blank">'. esc_html($cart_item['file_name']) .'</a>',
            ];
        }
        if (! empty($cart_item['qty'])) {
            $item_data[] = [
                'key'   => __('Quantity', 'printing-pricing-calculator'),
                'value' => intval($cart_item['qty']),
            ];
        }
        if (! empty($cart_item['discount']) && $cart_item['ordered_with_prices']) {
            $item_data[] = [
                'key'   => __('Discount', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['discount']),
            ];
        }
        if (! empty($cart_item['tax']) && $cart_item['ordered_with_prices']) {
            $item_data[] = [
                'key'   => __('Tax', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['tax']),
            ];
        }
        if (! empty($cart_item['calc_total']) && $cart_item['ordered_with_prices']) {
            $item_data[] = [
                'key'   => __('Calculated Total', 'printing-pricing-calculator'),
                'value' => wc_price($cart_item['calc_total']),
            ];
        }
        if (! empty($cart_item['customer_note'])) {
            $item_data[] = [
                'key'   => __('Note', 'printing-pricing-calculator'),
                'value' => wc_kses_post($cart_item['customer_note']),
            ];
        }

        return $item_data;
    }

    public function delete_uploaded_files_from_order($order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            $file_url = wc_get_order_item_meta($item->get_id(), 'File URL', true);
            if ($file_url) {
                $path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $file_url);
                if ( file_exists($path) ) {
                    @unlink($path);
                }
            }
        }
    }

    public function show_order_item_data($item_id, $item, $product) {
        $label_map = [
            'PPC Params'       => __('Selected Options', 'printing-pricing-calculator'),
            'Express Delivery' => __('Express Delivery', 'printing-pricing-calculator'),
            'File Check'       => __('File Check', 'printing-pricing-calculator'),
            'File URL'         => __('File URL', 'printing-pricing-calculator'),
            'File Name'        => __('File Name', 'printing-pricing-calculator'),
            'Calculated Total' => __('Calculated Total', 'printing-pricing-calculator'),
            'Discount'         => __('Discount', 'printing-pricing-calculator'),
            'Tax'              => __('Tax', 'printing-pricing-calculator'),
            'Quantity'         => __('Quantity', 'printing-pricing-calculator'),
            'customer_note'    => __('Note', 'printing-pricing-calculator'),
        ];
        $meta = wc_get_order_item_meta($item_id, '', false);
        $excluded = [
            '_product_id', '_variation_id', '_qty', '_tax_class',
            '_line_subtotal', '_line_subtotal_tax', '_line_total',
            '_line_tax', '_reduced_stock', '_reduced_stock_later',
            '_restock_refunded_items', '_line_tax_data', 'ordered_with_prices'
        ];
        echo '<div>';
        foreach ($meta as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }
            if ( is_array($value) && count($value) === 1 ) {
                $value = reset($value);
            }
            if ( is_array($value) ) {
                $value = wp_json_encode($value);
            }
            if ($key === 'PPC Params') {
                $decoded = json_decode($value);
                if (is_array($decoded) || is_object($decoded)) {
                    foreach ($decoded as $param) {
                        $title = isset($param->title) ? $param->title : '';
                        $val   = isset($param->value) ? $param->value : '';
                        echo '<b>'. esc_html($title) .'</b>: <span style="color:#0071a1;">'. esc_html($val) .'</span><br>';
                    }
                }
            } else {
                $label = isset($label_map[$key]) ? $label_map[$key] : $key;
                if ( is_string($value) && $value === 'Yes' ) {
                    $value = __( 'Yes', 'printing-pricing-calculator' );
                }
                echo '<b>'. esc_html($label) .'</b>: <span style="color:#0071a1;">'. esc_html((string) $value) .'</span><br>';
            }
        }
        echo '</div>';
    }

    public function hide_order_item_data($hidden_keys) {
        $hidden_keys[] = 'PPC Params';
        $hidden_keys[] = 'Calculated Total';
        $hidden_keys[] = 'Tax';
        $hidden_keys[] = 'Quantity';
        $hidden_keys[] = 'ordered with prices';
        return $hidden_keys;
    }

    public function hide_order_item_data_frontend($formatted_meta, $item) {
        if ( is_admin() ) {
            return $formatted_meta;
        }
        foreach ( $formatted_meta as $key => $meta ) {
            if ( $meta->key === 'PPC Params' ) {
                $data = maybe_unserialize( $meta->value );
                if ( is_string($data) && ( $decoded = json_decode($data, true) ) ) {
                    $html = '';
                    foreach ( $decoded as $paramValue ) {
                        $html .= '<li class="ml-5"><strong>'
                            . esc_html($paramValue['title'])
                            . ': </strong><p>'
                            . esc_html($paramValue['value'])
                            . '</p></li>';
                    }
                    $meta->display_key   = __( 'Selected Options', 'printing-pricing-calculator' );
                    $meta->display_value = $html;
                    $formatted_meta[$key] = $meta;
                }
            }
        }
        return $formatted_meta;
    }

    public function get_cart_item_from_session($cart_item, $values) {
        $custom_fields = [
            'ppc_product_id',
            'params',
            'express',
            'file_check',
            'file_url',
            'file_name',
            'summary',
            'calc_total',
            'discount',
            'tax',
            'qty',
            'ppc_product_title',
            'image',  // This ensures your image value is preserved
            'customer_note',
            'ordered_with_prices'
        ];
        foreach ($custom_fields as $field) {
            if ( isset( $values[$field] ) ) {
                $cart_item[$field] = $values[$field];
            }
        }
        return $cart_item;
    }

    public function set_woocommerce_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key) {
       error_log("set_woocommerce_cart_item_thumbnail fired for cart_item_key: " . $cart_item_key);
        if ( ! empty($cart_item['image']) ) {
            $alt = isset($cart_item['ppc_product_title']) ? $cart_item['ppc_product_title'] : '';
            $thumbnail = sprintf(
                '<img src="%s" alt="%s" style="width:60px;height:auto;border-radius:6px;">',
                esc_url($cart_item['image']),
                esc_attr($alt)
            );
        }
        return $thumbnail;
    }

    public function prepend_image_to_cart_item_name($name, $cart_item, $cart_item_key) {
        if (! empty($cart_item['image'])) {
            $alt = isset($cart_item['ppc_product_title']) ? $cart_item['ppc_product_title'] : '';
            $thumb = sprintf(
                '<img src="%s" alt="%s" style="width:60px;height:auto;border-radius:6px;margin-right:8px;">',
                esc_url($cart_item['image']),
                esc_attr($alt)
            );
            $name = $thumb . $name;
        }
        return $name;
    }

    public function set_woocommerce_store_api_cart_item_images($product_images, $cart_item, $cart_item_key) {
        if (! empty($cart_item['image'])) {
            $image_url = $cart_item['image'];
            return [
                (object)[
                    'id'        => 0,
                    'src'       => $image_url,
                    'thumbnail' => $image_url,
                    'srcset'    => '',
                    'sizes'     => '',
                    'name'      => $cart_item['ppc_product_title'] ?? '',
                    'alt'       => $cart_item['ppc_product_title'] ?? '',
                ]
            ];
        }
        return $product_images;
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (! empty($values['params'])) {
            $item->add_meta_data('PPC Params', json_encode($values['params']));
        }
        if (! empty($values['express'])) {
            $item->add_meta_data('Express Delivery', 'Yes');
        }
        if (! empty($values['file_check'])) {
            $item->add_meta_data('File Check', 'Yes');
        }
        if (! empty($values['file_url'])) {
            $item->add_meta_data('File URL', $values['file_url']);
        }
        if (! empty($values['file_name'])) {
            $item->add_meta_data('File Name', $values['file_name']);
        }
        if (! empty($values['calc_total'])) {
            $item->add_meta_data('Calculated Total', $values['calc_total']);
        }
        if (! empty($values['discount'])) {
            $item->add_meta_data('Discount', $values['discount']);
        }
        if (! empty($values['tax'])) {
            $item->add_meta_data('Tax', $values['tax']);
        }
        if (! empty($values['qty'])) {
            $item->add_meta_data('Quantity', intval($values['qty']));
        }
        if (! empty($values['customer_note'])) {
            $item->add_meta_data('customer_note', $values['customer_note']);
        }
        if (! empty($values['image'])) {
            $item->add_meta_data('image', $values['image']);
        }
        if (! empty($values['ppc_product_title'])) {
            $item->add_meta_data('ppc_product_title', $values['ppc_product_title']);
        }
    }

    public function set_woocommerce_order_item_thumbnail($image_html, $item) {
        $meta_image = $item->get_meta('image');
        if ($meta_image) {
            $alt = $item->get_meta('ppc_product_title') ?: 'Product';
            $image_html = sprintf(
                '<img src="%s" alt="%s" style="width:60px;height:auto;border-radius:6px;">',
                esc_url($meta_image),
                esc_attr($alt)
            );
        }
        return $image_html;
    }

    public function set_woocommerce_cart_item_price($price_html, $cart_item, $cart_item_key) {
        // Example: you could override price HTML if you want; leaving default for now.
        var_dump("Hamza");
        return "";
    }

    public function set_woocommerce_cart_item_subtotal($subtotal_html, $cart_item, $cart_item_key) {
        // Example: you could override subtotal HTML if you want; leaving default for now.
        return "";
    }

    public function conditionally_hide_totals_css() {
        if (!is_checkout()) {
            return;
        }

        $hide = false;
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['ordered_with_prices'] ) ) {
                // item without prices => hide
                $hide = true;
                break;
            }
        }

        if ( $hide ) {
            echo '<style>
                .woocommerce-checkout-review-order-table .cart-subtotal,
                .woocommerce-checkout-review-order-table .order-total,
                .woocommerce-checkout-review-order-table tfoot { 
                    display: none !important; 
                }
                /* Add block‐based checkout alternative if needed */
                .wp-block-woocommerce-checkout-totals-block .wc-block-components-totals-footer-item,
                .wp-block-woocommerce-checkout-totals-block .wp-block-woocommerce-checkout-order-summary-subtotal-block {
                    display: none !important;
                }
                .wc-block-components-totals-wrapper,
                .wp-block-woocommerce-checkout-order-summary-totals-block,
                .wc-block-components-order-summary-item__individual-prices.price.wc-block-components-product-price,
                .wc-block-components-order-summary-item__total-price{
                    display: none !important;
                }
                .wp-block-woocommerce-checkout-order-summary-cart-items-block.wc-block-components-totals-wrapper{
                    display: block !important;
                }
            </style>';
        }
    }
}
