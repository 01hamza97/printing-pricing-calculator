<?php
namespace PPC\Products;

use PPC\Admin\AdminInit;

class ProductsInit
{
    public function __construct()
    {
        add_action('admin_post_ppc_export_products_flat', [__CLASS__, 'export_flat_csv']);
        add_action('admin_post_ppc_export_products_zip', [__CLASS__, 'export_zip_csv']);
        add_action('admin_post_ppc_import_products_zip', [__CLASS__, 'import_zip_csv']);
    }

    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if ($wpdb->get_var("SHOW TABLES LIKE '" . PRODUCT_TABLE . "'") !== PRODUCT_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS " . PRODUCT_TABLE . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                content TEXT NULL,
                min_order_qty INT DEFAULT NULL,
                discount_rules text default NULL,
                file_check_price decimal(10,2) default NULL,
                file_check_required tinyint(1) default 0, 
                base_price DECIMAL(10,2) DEFAULT 0.00,
                express_delivery_value FLOAT DEFAULT NULL,
                express_delivery_type VARCHAR(10) DEFAULT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                image_url TEXT NULL,
                instructions_file_id BIGINT(20) UNSIGNED NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;
        ) $charset_collate;");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '" . PRODUCT_PARAMETERS_TABLE . "'") !== PRODUCT_PARAMETERS_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS " . PRODUCT_PARAMETERS_TABLE . " (
              id INT AUTO_INCREMENT PRIMARY KEY,
              product_id INT NOT NULL,
              is_required TINYINT(1) NOT NULL DEFAULT 0,
              position INT(1) NOT NULL DEFAULT 0,
              parameter_id BIGINT UNSIGNED NOT NULL,
              FOREIGN KEY (product_id) REFERENCES " . PRODUCT_TABLE . "(id) ON DELETE CASCADE,
              FOREIGN KEY (parameter_id) REFERENCES " . PARAM_TABLE . "(id) ON DELETE CASCADE
        ) $charset_collate;");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '" . PRODUCT_PARAM_META_TABLE . "'") !== PRODUCT_PARAM_META_TABLE) {
            dbDelta("CREATE TABLE " . PRODUCT_PARAM_META_TABLE . " (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              product_id INT NOT NULL,
              option_id BIGINT UNSIGNED NOT NULL,
              override_price DECIMAL(10,2) DEFAULT NULL,
              FOREIGN KEY (product_id) REFERENCES " . PRODUCT_TABLE . "(id) ON DELETE CASCADE,
              FOREIGN KEY (option_id) REFERENCES " . META_TABLE . "(id) ON DELETE CASCADE
            ) $charset_collate;");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE 'ppc_option_conditions'") !== 'ppc_option_conditions') {
            dbDelta("CREATE TABLE " . PRODUCT_OPTION_CONDITIONS_TABLE . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                source_type ENUM('option','parameter') NOT NULL DEFAULT 'option',
                option_id BIGINT UNSIGNED NULL,
                source_param_id BIGINT UNSIGNED NULL,
                target_param_id BIGINT UNSIGNED NULL,
                target_option_id BIGINT UNSIGNED NULL,
                action ENUM('show','hide') NOT NULL DEFAULT 'show',
                logic_group INT DEFAULT 1,
                operator ENUM('AND','OR') NOT NULL DEFAULT 'AND',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES " . PRODUCT_TABLE . "(id) ON DELETE CASCADE,
                FOREIGN KEY (option_id) REFERENCES " . META_TABLE . "(id) ON DELETE CASCADE,
                FOREIGN KEY (source_param_id) REFERENCES " . PARAM_TABLE . "(id) ON DELETE CASCADE,
                FOREIGN KEY (target_param_id) REFERENCES " . PARAM_TABLE . "(id) ON DELETE CASCADE,
                FOREIGN KEY (target_option_id) REFERENCES " . META_TABLE . "(id) ON DELETE CASCADE
            ) $charset_collate;");
        }
    }

    public static function export_flat_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '403 Forbidden');
        }
        global $wpdb;

        $product_table = PRODUCT_TABLE;
        $param_table = PARAM_TABLE;
        $pivot_table = PRODUCT_PARAMETERS_TABLE;
        $meta_table = META_TABLE;
        $option_price_table = PRODUCT_PARAM_META_TABLE;

        $header = [
            'product_id', 'product_title', 'product_slug', 'base_price', 'status',
            'parameter_id', 'parameter_title', 'parameter_slug', 'is_required',
            'option_id', 'option_title', 'option_slug', 'option_cost', 'option_image', 'override_price'
        ];

        $rows = [];

        // Fetch all products
        $products = $wpdb->get_results("SELECT * FROM $product_table", ARRAY_A);

        foreach ($products as $prod) {
            // Fetch all linked parameters for this product
            $product_params = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, param.title AS parameter_title, param.slug AS parameter_slug
                FROM $pivot_table p
                JOIN $param_table param ON p.parameter_id = param.id
                WHERE p.product_id = %d", $prod['id']), ARRAY_A);

            if ($product_params) {
                foreach ($product_params as $pp) {
                    // Fetch all options for this parameter
                    $options = $wpdb->get_results($wpdb->prepare(
                        "SELECT m.id as option_id, m.meta_value
                        FROM $meta_table m
                        WHERE m.parameter_id = %d", $pp['parameter_id']), ARRAY_A);

                    foreach ($options as $opt) {
                        $meta_value = maybe_unserialize($opt['meta_value']);
                        $override = $wpdb->get_var($wpdb->prepare(
                            "SELECT override_price FROM $option_price_table WHERE product_id = %d AND option_id = %d",
                            $prod['id'], $opt['option_id']
                        ));

                        $rows[] = [
                            $prod['id'],
                            $prod['title'],
                            $prod['slug'],
                            $prod['base_price'],
                            $prod['status'],
                            $pp['parameter_id'],
                            $pp['parameter_title'],
                            $pp['parameter_slug'],
                            $pp['is_required'],
                            $opt['option_id'],
                            $meta_value['option'] ?? '',
                            $meta_value['slug'] ?? '', // <-- Option slug!
                            $meta_value['cost'] ?? '',
                            $meta_value['image'] ?? '',
                            $override !== null ? $override : '',
                        ];
                    }
                }
            } else {
                // Product with no parameters/options
                $rows[] = [
                    $prod['id'],
                    $prod['title'],
                    $prod['slug'],
                    $prod['base_price'],
                    $prod['status'],
                    '', '', '', '', '', '', '', '', '', ''
                ];
            }
        }

        // Output as CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=products-flat-export-' . date('Ymd-His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, $header);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    public static function export_zip_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '403 Forbidden');
        }
        global $wpdb;

        $product_table = PRODUCT_TABLE;
        $param_table = PARAM_TABLE;
        $pivot_table = PRODUCT_PARAMETERS_TABLE;
        $meta_table = META_TABLE;
        $option_price_table = PRODUCT_PARAM_META_TABLE;

        // 1. Prepare each CSV as a string (slugs for linking)
        $csvs = [];

        // --- products.csv ---
        $products = $wpdb->get_results("SELECT * FROM $product_table", ARRAY_A);
        $csvs['products.csv'] = self::array_to_csv($products);

        // --- parameters.csv ---
        $parameters = $wpdb->get_results("SELECT * FROM $param_table", ARRAY_A);
        $csvs['parameters.csv'] = self::array_to_csv($parameters);

        // --- product_parameters.csv (using slugs) ---
        $pp_links = $wpdb->get_results("
            SELECT 
                pp.product_id, p.slug AS product_slug, 
                pp.parameter_id, param.slug AS parameter_slug, 
                pp.is_required
            FROM $pivot_table pp
            JOIN $product_table p ON pp.product_id = p.id
            JOIN $param_table param ON pp.parameter_id = param.id
        ", ARRAY_A);
        $csvs['product_parameters.csv'] = self::array_to_csv($pp_links);

        // --- parameter_options.csv (with option slugs) ---
        $param_options = [];
        $all_params = $wpdb->get_results("SELECT id, slug FROM $param_table", ARRAY_A);
        $param_slug_map = [];
        foreach ($all_params as $par) $param_slug_map[$par['id']] = $par['slug'];

        $option_rows = $wpdb->get_results("SELECT * FROM $meta_table", ARRAY_A);
        foreach ($option_rows as $row) {
            $options = maybe_unserialize($row['meta_value']);
            if (isset($options['option'])) $options = [$options]; // handle single option case
            foreach ($options as $opt) {
                $param_id = $row['parameter_id'];
                $param_options[] = [
                    'parameter_id'   => $param_id,
                    'parameter_slug' => $param_slug_map[$param_id] ?? '',
                    'option_title'   => $opt['option'] ?? '',
                    'option_slug'    => $opt['slug'] ?? '',
                    'option_cost'    => $opt['cost'] ?? '',
                    'option_image'   => $opt['image'] ?? ''
                ];
            }
        }
        $csvs['parameter_options.csv'] = self::array_to_csv($param_options);

        // --- option_overrides.csv (using slugs) ---
        $overrides = $wpdb->get_results("SELECT * FROM $option_price_table", ARRAY_A);
        $option_id_map = [];
        // Build: option_id => [parameter_id, option_slug]
        foreach ($option_rows as $row) {
            $options = maybe_unserialize($row['meta_value']);
            if (isset($options['option'])) $options = [$options];
            foreach ($options as $opt) {
                $option_id_map[$row['id']] = [
                    'parameter_id' => $row['parameter_id'],
                    'option_slug' => $opt['slug'] ?? '',
                ];
            }
        }
        // parameter_id => slug
        foreach ($overrides as &$orow) {
            $product = $wpdb->get_row($wpdb->prepare("SELECT slug FROM $product_table WHERE id = %d", $orow['product_id']), ARRAY_A);
            $option_info = $option_id_map[$orow['option_id']] ?? ['parameter_id' => '', 'option_slug' => ''];
            $parameter_slug = $param_slug_map[$option_info['parameter_id']] ?? '';
            $orow['product_slug'] = $product['slug'] ?? '';
            $orow['parameter_slug'] = $parameter_slug;
            $orow['option_slug'] = $option_info['option_slug'];
            unset($orow['product_id'], $orow['option_id']);
        }
        unset($orow);

        $csvs['option_overrides.csv'] = self::array_to_csv($overrides);

        // 2. Build ZIP in memory
        $zip = new \ZipArchive();
        $tmp_file = tempnam(sys_get_temp_dir(), 'ppc_zip_');
        @unlink($tmp_file);
        $zip->open($tmp_file, \ZipArchive::CREATE);

        foreach ($csvs as $filename => $content) {
            $zip->addFromString($filename, $content);
        }
        $zip->close();

        // 3. Output headers
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=ppc-export-' . date('Ymd-His') . '.zip');
        header('Content-Length: ' . filesize($tmp_file));
        readfile($tmp_file);
        unlink($tmp_file);
        exit;
    }

    public static function import_zip_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '403 Forbidden');
        }
        check_admin_referer('ppc_import_zip');

        if (empty($_FILES['ppc_import_zip']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $zip_file = $_FILES['ppc_import_zip']['tmp_name'];
        $zip = new \ZipArchive();
        if ($zip->open($zip_file) !== true) {
            wp_die('Could not open ZIP file.');
        }

        // Extract all CSVs to memory
        $csvs = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            if (substr($name, -4) === '.csv') {
                $csvs[$name] = $zip->getFromIndex($i);
            }
        }
        $zip->close();

        global $wpdb;
        $product_table = PRODUCT_TABLE;
        $param_table = PARAM_TABLE;
        $pivot_table = PRODUCT_PARAMETERS_TABLE;
        $meta_table = META_TABLE;
        $option_price_table = PRODUCT_PARAM_META_TABLE;

        // 1. Import Products
        if (isset($csvs['products.csv'])) {
            $rows = self::csv_to_array($csvs['products.csv']);
            foreach ($rows as $row) {
                // Update or insert by slug
                $new_url = AdminInit::import_image_to_media($row['image_url']);
                $row['image_url'] = $new_url;
                $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $product_table WHERE slug = %s", $row['slug']));
                if ($existing_id) {
                    $wpdb->update($product_table, $row, ['id' => $existing_id]);
                } else {
                    $wpdb->insert($product_table, $row);
                }
            }
        }

        // 2. Import Parameters
        if (isset($csvs['parameters.csv'])) {
            $rows = self::csv_to_array($csvs['parameters.csv']);
            foreach ($rows as $row) {
                $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $param_table WHERE slug = %s", $row['slug']));
                if ($existing_id) {
                    $wpdb->update($param_table, $row, ['id' => $existing_id]);
                } else {
                    $wpdb->insert($param_table, $row);
                }
            }
        }

        // Build map for fast ID lookup after upserts
        $product_map = $wpdb->get_results("SELECT id, slug FROM $product_table", OBJECT_K);
        $param_map   = $wpdb->get_results("SELECT id, slug FROM $param_table", OBJECT_K);

        $slug_to_pid = [];
        $slug_to_paramid = [];
        foreach ($product_map as $obj)   $slug_to_pid[$obj->slug]   = $obj->id;
        foreach ($param_map as $obj)     $slug_to_paramid[$obj->slug] = $obj->id;

        // 3. Import Product-Parameter links
        if (isset($csvs['product_parameters.csv'])) {
            $rows = self::csv_to_array($csvs['product_parameters.csv']);
            $wpdb->query("DELETE FROM $pivot_table"); // Remove existing for clean import
            foreach ($rows as $row) {
                $pid = $slug_to_pid[$row['product_slug']] ?? 0;
                $paramid = $slug_to_paramid[$row['parameter_slug']] ?? 0;
                if ($pid && $paramid) {
                    $wpdb->insert($pivot_table, [
                        'product_id' => $pid,
                        'parameter_id' => $paramid,
                        'is_required' => $row['is_required'],
                    ]);
                }
            }
        }

        // 4. Import Parameter Options
        // We will build a [parameter_slug][option_slug] => meta_id map
        $option_ids = [];
        if (isset($csvs['parameter_options.csv'])) {
            $rows = self::csv_to_array($csvs['parameter_options.csv']);
            $wpdb->query("DELETE FROM $meta_table"); // Clean import
            foreach ($rows as $row) {
                $parameter_id = $slug_to_paramid[$row['parameter_slug']] ?? 0;
                if (!$parameter_id) continue;
                $new_opt_image = AdminInit::import_image_to_media($row['option_image']);
                $option_array = [
                    'option' => $row['option_title'],
                    'slug'   => $row['option_slug'],
                    'cost'   => $row['option_cost'],
                    'image'  => $new_opt_image,
                ];
                $meta_value = maybe_serialize($option_array);
                $wpdb->insert($meta_table, [
                    'parameter_id' => $parameter_id,
                    'meta_key'     => 'option',
                    'meta_value'   => $meta_value,
                ]);
                $meta_id = $wpdb->insert_id;
                $option_ids[$row['parameter_slug']][$row['option_slug']] = $meta_id;
            }
        }

        // 5. Import Option Overrides
        if (isset($csvs['option_overrides.csv'])) {
            $rows = self::csv_to_array($csvs['option_overrides.csv']);
            $wpdb->query("DELETE FROM $option_price_table"); // Clean import
            foreach ($rows as $row) {
                $pid = $slug_to_pid[$row['product_slug']] ?? 0;
                $param_slug = $row['parameter_slug'];
                $option_slug = $row['option_slug'];
                $option_id = $option_ids[$param_slug][$option_slug] ?? 0;
                if ($pid && $option_id) {
                    $wpdb->insert($option_price_table, [
                        'product_id' => $pid,
                        'option_id' => $option_id,
                        'override_price' => $row['override_price'],
                    ]);
                }
            }
        }

        // Redirect back with success
        wp_redirect(admin_url('admin.php?page=ppc-products&imported=1'));
        exit;
    }



    /**
     * Helper: Convert array of associative arrays to CSV string.
     */
    public static function array_to_csv($array)
    {
        if (empty($array)) return '';
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($array[0]));
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
    
    // Helper: CSV to array
    public static function csv_to_array($csv)
    {
        $rows = [];
        $lines = explode("\n", $csv);
        $header = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $cols = str_getcsv($line);
            if (!$header) {
                $header = $cols;
            } else {
                $rows[] = array_combine($header, $cols);
            }
        }
        return $rows;
    }
}
