<?php
namespace PPC\Parameters;

use PPC\Admin\AdminInit;

class ParametersInit
{
    public function __construct()
    {
        add_action('admin_post_ppc_export_parameters_zip', [__CLASS__, 'export_zip_csv']);
        add_action('admin_post_ppc_import_parameters_zip', [__CLASS__, 'import_zip_csv']);
    }

    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Check and create ppc_parameters table
        if ($wpdb->get_var("SHOW TABLES LIKE '". PARAM_TABLE ."'") !== PARAM_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS ". PARAM_TABLE ." (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            front_name VARCHAR(255) DEFAULT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            content TEXT DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;");
        }

        // Check and create ppc_parameter_meta table
        if ($wpdb->get_var("SHOW TABLES LIKE '". META_TABLE ."'") !== META_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS ". META_TABLE ." (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parameter_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value TEXT,
            FOREIGN KEY (parameter_id) REFERENCES ". PARAM_TABLE ."(id) ON DELETE CASCADE
        ) $charset_collate;");
        }
    }

    public static function export_zip_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '403 Forbidden');
        }
        global $wpdb;

        $param_table = PARAM_TABLE;
        $meta_table = META_TABLE;

        // 1. Prepare each CSV as a string (slugs for linking)
        $csvs = [];

        // --- parameters.csv ---
        $parameters = $wpdb->get_results("SELECT * FROM $param_table", ARRAY_A);
        $csvs['parameters.csv'] = self::array_to_csv($parameters);

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

        // Read CSV files from ZIP into memory
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
        $param_table = PARAM_TABLE;
        $meta_table  = META_TABLE;

        /*
        --------------------------------------------------------------------
        # 1. IMPORT PARAMETERS
        --------------------------------------------------------------------
        */
        if (!isset($csvs['parameters.csv'])) {
            wp_die('parameters.csv missing in ZIP file.');
        }

        $param_rows = self::csv_to_array($csvs['parameters.csv']);

        foreach ($param_rows as $row) {
            if (!isset($row['slug'])) continue;

            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $param_table WHERE slug = %s",
                $row['slug']
            ));

            if ($existing_id) {
                // Update existing
                $wpdb->update($param_table, $row, ['id' => $existing_id]);
            } else {
                // Insert new
                $wpdb->insert($param_table, $row);
            }
        }

        // Rebuild slug → ID map after import
        $param_map = $wpdb->get_results("SELECT id, slug FROM $param_table", OBJECT_K);
        $slug_to_paramid = [];
        foreach ($param_map as $obj) {
            $slug_to_paramid[$obj->slug] = $obj->id;
        }

        /*
        --------------------------------------------------------------------
        # 2. IMPORT PARAMETER OPTIONS (META)
        --------------------------------------------------------------------
        */
        if (!isset($csvs['parameter_options.csv'])) {
            wp_die('parameter_options.csv missing in ZIP file.');
        }

        $opt_rows = self::csv_to_array($csvs['parameter_options.csv']);

        // Clean old meta before full import
        $wpdb->query("DELETE FROM $meta_table");

        foreach ($opt_rows as $row) {

            $param_id = $slug_to_paramid[$row['parameter_id'] ?? $row['parameter_slug']] 
                        ?? $slug_to_paramid[$row['parameter_slug']] 
                        ?? 0;

            if (!$param_id) {
                continue;
            }

            // Import image if provided
            $new_image = AdminInit::import_image_to_media($row['option_image']);

            $option_array = [
                'option' => $row['option_title'],
                'slug'   => $row['option_slug'],
                'cost'   => $row['option_cost'],
                'image'  => $new_image,
            ];

            $meta_value = maybe_serialize($option_array);

            $wpdb->insert($meta_table, [
                'parameter_id' => $param_id,
                'meta_key'     => 'option',
                'meta_value'   => $meta_value,
            ]);
        }

        /*
        --------------------------------------------------------------------
        # FINAL REDIRECT
        --------------------------------------------------------------------
        */
        wp_redirect(admin_url('admin.php?page=ppc-parameters&imported=1'));
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

        $fp = fopen('php://memory', 'r+');
        fwrite($fp, $csv);
        rewind($fp);

        $header = null;

        while (($cols = fgetcsv($fp)) !== false) {

            // Skip empty lines
            if ($cols === [null] || $cols === false) {
                continue;
            }

            if (!$header) {
                $header = $cols;
                continue;
            }

            // pad/truncate
            if (count($cols) < count($header)) {
                $cols = array_pad($cols, count($header), '');
            } elseif (count($cols) > count($header)) {
                $cols = array_slice($cols, 0, count($header));
            }

            $rows[] = array_combine($header, $cols);
        }

        fclose($fp);

        return $rows;
    }

}
