<?php
    namespace PPC\Calculator;

    class Quotation
    {
        public function __construct()
        {
          add_action('wp_ajax_ppc_generate_pdf', [__CLASS__, 'generate_pdf']);
          add_action('wp_ajax_nopriv_ppc_generate_pdf', [__CLASS__, 'generate_pdf']);
        }

        public static function generate_pdf()
        {
            // Always validate/sanitize user input!
            $product_title = sanitize_text_field($_POST['product_title'] ?? '');
            $product_image = esc_url_raw($_POST['product_image'] ?? '');
            $params        = json_decode(stripslashes($_POST['params'] ?? '[]'), true);
            $qty           = intval($_POST['qty'] ?? 1);
            $express       = ! empty($_POST['express']);
            $summary_html  = wp_kses_post($_POST['summary_html'] ?? '');
            $total         = sanitize_text_field($_POST['total'] ?? '');
            $note          = wp_kses_post(get_option('ppc_pdf_quotation_note', 15));

            // Use output buffering to build HTML
            ob_start();
        ?>
        <html>
          <head>
              <style>
                  body { font-family: DejaVu Sans, Arial, sans-serif; font-size:13px; }
                  .logo { max-width: 180px; margin-bottom:20px; }
                  .product-img { max-height: 120px; border-radius:8px; }
                  h2 { background: #f1f1f1; padding: 8px 14px; border-radius: 4px; }
                  table { width: 100%; border-collapse: collapse; margin: 18px 0; }
                  th, td { border: 1px solid #ddd; padding: 8px; }
                  th { background: #fafafa; }
                  .total { font-size: 18px; font-weight: bold; }
              </style>
          </head>
          <body>
              <img class="logo" src="https://yourdomain.com/path/to/logo.png" alt="<?php echo esc_attr__( 'Logo', 'printing-pricing-calculator' ); ?>" />
              <h1><?php echo esc_html__( 'Quotation', 'printing-pricing-calculator' ); ?></h1>
              <strong><?php esc_html_e( 'Date:', 'printing-pricing-calculator' ); ?>:</strong><?php echo esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format') ) ); ?><br>
              <br>
              <h2><?php echo esc_html__( 'Product', 'printing-pricing-calculator' ); ?>:<?php echo esc_html($product_title); ?></h2>
              <?php if ($product_image): ?>
                  <img class="product-img" src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr__( 'Product Image', 'printing-pricing-calculator' ); ?>" />
              <?php endif; ?>
              <br><br>
              <table>
                  <thead>
                      <tr>
                          <th><?php esc_html_e( 'Parameter', 'printing-pricing-calculator' ); ?></th>
                          <th><?php esc_html_e( 'Selected Option', 'printing-pricing-calculator' ); ?></th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($params as $p): ?>
                          <tr>
                              <td><?php echo esc_html($p['title']); ?></td>
                              <td><?php echo esc_html($p['text']); ?></td>
                          </tr>
                      <?php endforeach; ?>
                      <tr>
                          <td><?php esc_html_e( 'Quantity', 'printing-pricing-calculator' ); ?></td>
                          <td><?php echo intval($qty); ?></td>
                      </tr>
                      <tr>
                          <td><?php esc_html_e( 'Express Delivery', 'printing-pricing-calculator' ); ?></td>
                          <td><?php echo $express ? 'Yes' : 'No'; ?></td>
                      </tr>
                  </tbody>
              </table>
              <h2><?php esc_html_e( 'Summary', 'printing-pricing-calculator' ); ?></h2>
              <?php echo $summary_html; ?>
              <div class="total"><?php esc_html_e( 'Total', 'printing-pricing-calculator' ); ?>: <strong><?php echo esc_html($total); ?></strong></div>
              <?php if ($note): ?>
                  <hr><div style="margin-top:20px;"><?php echo $note; ?></div>
              <?php endif; ?>
          </body>
        </html>
        <?php
            $html = ob_get_clean();
            // Load dompdf
            require_once WP_PLUGIN_DIR . '/printing-pricing-calculator/vendor/autoload.php';
            $dompdf = new \Dompdf\Dompdf([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true, // Needed for remote images (CDN, etc)
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Output PDF to browser as download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="quotation-' . date('Ymd-His') . '.pdf"');
            echo $dompdf->output();
            exit;
        }
}
