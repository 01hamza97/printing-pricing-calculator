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
            // Force site/frontend locale for this AJAX call
            if ( function_exists( 'switch_to_locale' ) ) {
                switch_to_locale( get_locale() ); // site language from Settings → General
            }
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
                  body { font-family: DejaVu Sans, Arial, sans-serif; font-size:8px; }
                  .logo { max-width: 180px; margin-bottom:20px; }
                  .product-img { max-height: 120px; border-radius:8px; }
                  h2 { background: #f1f1f1; padding: 4px 7px; border-radius: 4px; }
                  table { width: 100%; border-collapse: collapse; margin: 14px 0; }
                  th, td { border: 1px solid #ddd; padding: 3px; }
                  th { background: #fafafa; }
                  .total { font-size: 12px; font-weight: bold; text-align: left; margin-left: 0px; width: 300px; left: 0px;}
                  .product-info {display: flex; justify-content: space-between; align-items: center; align-content: center; vertical-align: middle;}
                  @page { margin: 0; /* bottom margin for footer */ }
                  .footer { position: fixed; bottom: 10px; left: 0; right: 0; width: 100%; text-align: center; font-size: 8px; color: #333; }
                  .footer-line { width: 100%; height: 1px; background: #000; margin-bottom: 6px; }
                  .footer-content { margin: 0 auto; text-align: center; display: inline-block; width: 80%; }
                  .footer-content div{ float: left; margin-right: 25px; }
                  .footer-content img { height: 12px; width: auto; vertical-align: middle; margin-right: 2px; margin-top: 5px; }
                  .footer-content span { margin: 0 5px; vertical-align: middle; }
                  .header { position: fixed; top: 0; left: 0; right: 0; height: 50px; background-color: #00a3ca; margin: 0 !important; padding: 20px 0 0 !important; display: flex; align-items: center; justify-content: flex-start; }
                  .header img.logo { height: 30px; margin-left: 20px; }
                  body { margin-top: 60px; margin-bottom: 40px; }
                  .body { margin: 0 30px; }
              </style>
          </head>
          <body>
              <div class="header">
                  <img class="logo" src="https://www.repress.cz/wp-content/themes/repress/assets/img/logo-white.png" alt="<?php echo esc_attr__( 'Logo', 'printing-pricing-calculator' ); ?>" />
              </div>
              <div class="body">
                  <h1><?php echo esc_html__( 'Quotation', 'printing-pricing-calculator' ); ?></h1>
                  <strong><?php esc_html_e( 'Date:', 'printing-pricing-calculator' ); ?> </strong><?php echo esc_html( wp_date( get_option('date_format') . ' ' . get_option('time_format') ) ); ?><br>
                  <br>
                  <div class="product-info">
                      <h2><?php echo esc_html__( 'Product', 'printing-pricing-calculator' ); ?>: <?php echo esc_html($product_title); ?></h2>
                      <?php if ($product_image): ?>
                          <img class="product-img" src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr__( 'Product Image', 'printing-pricing-calculator' ); ?>" />
                      <?php endif; ?>
                  </div>
                  <br>
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
                              <td><?php echo $express ? esc_html_e( 'Yes', 'printing-pricing-calculator' ) : esc_html_e( 'No', 'printing-pricing-calculator' ); ?></td>
                          </tr>
                      </tbody>
                  </table>
                  <h2><?php esc_html_e( 'Summary', 'printing-pricing-calculator' ); ?></h2>
                  <?php echo $summary_html; ?>
                  <?php 
                        $cfg = [
                            'symbol'       => get_woocommerce_currency_symbol(),
                            'position'     => 'right_space', // or pull from a plugin option if you like
                            'thousand_sep' => wc_get_price_thousand_separator(),
                            'decimal_sep'  => wc_get_price_decimal_separator(),
                            'num_decimals' => wc_get_price_decimals(),
                        ];
                        $formatted_total = Quotation::ppc_format_money( (float) $total, $cfg );
                  ?>
                  <div class="total"><?php esc_html_e( 'Total', 'printing-pricing-calculator' ); ?>: <strong><?php echo esc_html( $formatted_total ); ?></strong></div>
                  <?php if ($note): ?>
                      <hr><div style="margin-top:20px;"><?php echo $note; ?></div>
                  <?php endif; ?>
              </div>
              <div class="footer">
                <div class="footer-line"></div>
                <div class="footer-content">
                    <div>
                        <img src="https://img.icons8.com/ios-filled/50/000000/globe.png" alt="globe" />
                        <span>https://repress.cz</span>
                    </div>
                    <div>
                        <img src="https://img.icons8.com/ios-glyphs/30/000000/new-post.png" alt="email" />
                        <span>repress@repress.cz</span>
                    </div>
                    <div>
                        <img src="https://img.icons8.com/ios-filled/50/000000/phone.png" alt="phone" />
                        <span>(+420) 777 341 300</span>
                    </div>
                    <div>
                        <img src="https://img.icons8.com/ios-filled/50/000000/phone.png" alt="phone" />
                        <span>(+420) 518 341 700</span>
                    </div>
                </div>
            </div>
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

    /**
     * Format money similar to ppcFormatMoney JS helper.
     *
     * @param float|int $amount
     * @param array     $cfg {
     *      @type string $symbol       Currency symbol (e.g. "Kč").
     *      @type string $position     'left', 'left_space', 'right', 'right_space'.
     *      @type string $thousand_sep Thousands separator (e.g. ' ').
     *      @type string $decimal_sep  Decimal separator (e.g. ',').
     *      @type int    $num_decimals Number of decimal places.
     * }
     *
     * @return string
     */
    protected static function ppc_format_money( $amount, array $cfg = [] ) {
        $defaults = [
            'symbol'       => 'Kč',
            'position'     => 'right_space',
            'thousand_sep' => ' ',
            'decimal_sep'  => ',',
            'num_decimals' => 2,
        ];

        $cfg = array_merge( $defaults, $cfg );

        // Non-breaking space (same as JS \u00A0).
        $nbspace = "\xc2\xa0";

        $sepTh = ( $cfg['thousand_sep'] === ' ' ) ? $nbspace : $cfg['thousand_sep'];

        // Normalize and format absolute value with fixed decimals.
        $n = number_format( abs( (float) $amount ), (int) $cfg['num_decimals'], '.', '' );
        $parts = explode( '.', $n );

        $intPart = $parts[0];
        $decPart = isset( $parts[1] ) ? $parts[1] : '';

        // Insert thousands separator (like the JS regex).
        $intPart = preg_replace( '/\B(?=(\d{3})+(?!\d))/', $sepTh, $intPart );

        if ( $cfg['num_decimals'] > 0 ) {
            $number = $intPart . $cfg['decimal_sep'] . $decPart;
        } else {
            $number = $intPart;
        }

        $neg      = ( $amount < 0 ) ? '-' : '';
        $hasSpace = ( $cfg['position'] === 'left_space' || $cfg['position'] === 'right_space' );
        $space    = $hasSpace ? $nbspace : '';

        switch ( $cfg['position'] ) {
            case 'left':
                return $neg . $cfg['symbol'] . $number;
            case 'left_space':
                return $neg . $cfg['symbol'] . $space . $number;
            case 'right':
                return $neg . $number . $cfg['symbol'];
            case 'right_space':
            default:
                return $neg . $number . $space . $cfg['symbol'];
        }
    }
}