<?php
namespace PPC\Orders;

class OrdersInit
{
    public function __construct()
    {
      add_action('add_meta_boxes', [$this, 'add_order_meta_box'], 99, 2);
      add_action('add_meta_boxes', [$this, 'add_order_sample_meta_box'], 99, 2);
      add_action('admin_post_ppc_download_order_csv', [$this, 'ppc_download_order_csv']);
      add_action('admin_post_nopriv_ppc_download_order_csv', [$this, 'ppc_download_order_csv']);
      add_action('admin_post_ppc_download_order_sample_file', [$this, 'ppc_download_order_sample_file']);
      add_action('admin_post_nopriv_ppc_download_order_sample_file', [$this, 'ppc_download_order_sample_file']);
    }

    public function add_order_meta_box($post_type, $order) {
        // HPOS compatibility (new WooCommerce order edit screen)
        add_meta_box(
            'ppc_order_csv_export',
            __('Order Item CSV Export', 'printing-pricing-calculator'),
            [$this, 'ppc_render_order_csv_metabox'],
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }

    public function add_order_sample_meta_box($post_type, $order) {
        // HPOS compatibility (new WooCommerce order edit screen)
        add_meta_box(
            'ppc_order_sample_file_download',
            __('Order Item Sample File Download', 'printing-pricing-calculator'),
            [$this, 'ppc_render_order_sample_metabox'],
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }

    public function ppc_render_order_csv_metabox($post) {
      $order_id = $post->get_id();
      ?>
        <p><?php _e('Download all selected attributes & cart meta in CSV format.', 'printing-pricing-calculator'); ?></p>
        <a target="_blank" href="<?php echo admin_url('admin-post.php?action=ppc_download_order_csv&order_id=' . $order_id); ?>"
          class="button button-primary">
          <?php _e('Download CSV', 'printing-pricing-calculator'); ?>
        </a>
    <?php
    }

    function ppc_download_order_csv() {
      if (!current_user_can('manage_woocommerce')) {
          wp_die('Not allowed');
      }

      if (empty($_GET['order_id'])) {
          wp_die('Order ID missing');
      }

      $order_id = intval($_GET['order_id']);
      $order = wc_get_order($order_id);

      if (!$order) {
          wp_die('Order not found');
      }

      // -------------------------------
      // Build CSV Data
      // -------------------------------
      $rows = [];
      $rows[] = [
          'key',
          'value'
      ];
      foreach ($order->get_items() as $key => $value) {
        error_log("Order Items: Product Name => " . json_decode($value)->name);
        $rows[] = [
          'Product Name',
          json_decode($value)->name
        ];
        $meta_data = $value->get_formatted_meta_data();
        foreach ($meta_data as $meta) {
          if($meta->display_key == 'PPC Params') {
            $decoded = json_decode($meta->value);
            if (is_array($decoded) || is_object($decoded)) {
              foreach ($decoded as $param) {
                $rows[] = [
                  $param->title,
                  wp_strip_all_tags($param->value),
                ];
              }
            }
          } else {
            $rows[] = [
                $meta->display_key,
                wp_strip_all_tags($meta->display_value),
            ];
          }
        }
      }

      // -------------------------------
      // Output CSV
      // -------------------------------
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=order-' . $order_id . '-export.csv');

      $output = fopen('php://output', 'w');

      foreach ($rows as $row) {
          fputcsv($output, $row);
      }

      fclose($output);
      exit;
    }


    public function ppc_render_order_sample_metabox($post) {
      $order_id = $post->get_id();
      ?>
        <p><?php _e('Download all selected attributes & cart meta in CSV format.', 'printing-pricing-calculator'); ?></p>
        <a target="_blank" href="<?php echo admin_url('admin-post.php?action=ppc_download_order_sample_file&order_id=' . $order_id); ?>"
          class="button button-primary">
          <?php _e('Download Sample File', 'printing-pricing-calculator'); ?>
        </a>
    <?php
    }

    function ppc_download_order_sample_file() {
      if (!current_user_can('manage_woocommerce')) {
          wp_die('Not allowed');
      }

      if (empty($_GET['order_id'])) {
          wp_die('Order ID missing');
      }

      $order_id = intval($_GET['order_id']);
      $order = wc_get_order($order_id);

      if (!$order) {
          wp_die('Order not found');
      }

      // -------------------------------
      // Build CSV Data
      // -------------------------------
      $rows = [];
      $signed_url = "";
      foreach ($order->get_items() as $key => $value) {
        $rows[] = [
          'Product Name',
          json_decode($value)->name
        ];
        $meta_data = $value->get_formatted_meta_data();
        foreach ($meta_data as $meta) {
          if($meta->display_key === "File URL") {
            $key = $this->ppc_r2_extract_key_from_url($meta->value);
            $signed_url = $this->ppc_r2_generate_signed_url($key, 300);
            error_log("Signed URL => " . $signed_url);
          }
        }
      }
      // Extract filename
      $filename = basename(parse_url($signed_url, PHP_URL_PATH));

      // Fetch file from R2
      $file_body = file_get_contents($signed_url);

      if ($file_body === false) {
          wp_die('Could not fetch file.');
      }

      // Send download headers
      header("Content-Type: application/octet-stream");
      header("Content-Disposition: attachment; filename=\"$filename\"");
      header("Content-Length: " . strlen($file_body));

      echo $file_body;
      exit;
    }

  public function ppc_r2_generate_signed_url($key, $expires_in = 300) { // 300 seconds = 5 minutes
    require_once WP_PLUGIN_DIR . '/printing-pricing-calculator/vendor/autoload.php';

    $account_id = get_option('R2_ACCOUNT_ID');
    $access_key = get_option('R2_ACCESS_KEY_ID');
    $secret_key = get_option('R2_SECRET_ACCESS_KEY');
    $bucket     = get_option('R2_BUCKET');

    // S3 Client
    $s3 = new \Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => 'auto',
        'endpoint'    => "https://{$account_id}.r2.cloudflarestorage.com",
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_key,
        ],
    ]);

    // Generate presigned URL
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key'    => $key,
    ]);

    $request = $s3->createPresignedRequest($cmd, "+" . $expires_in . " seconds");

    return (string) $request->getUri();
  }

  public function ppc_r2_extract_key_from_url($url) {
    $parts = parse_url($url);
    return ltrim($parts['path'], '/');  
  }
}
