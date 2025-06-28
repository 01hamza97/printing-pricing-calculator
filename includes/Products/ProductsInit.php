<?php
namespace PPC\Products;

class ProductsInit
{
    public function __construct()
    {}

    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        if ($wpdb->get_var("SHOW TABLES LIKE '". PRODUCT_TABLE ."'") !== PRODUCT_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS ". PRODUCT_TABLE ." (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NULL,
                base_price DECIMAL(10,2) DEFAULT 0.00,
                status ENUM('active', 'inactive') DEFAULT 'active',
                image_url TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;
        ) $charset_collate;");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '" . PRODUCT_PARAMETERS_TABLE ."'") !== PRODUCT_PARAMETERS_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS " . PRODUCT_PARAMETERS_TABLE ." (
              id INT AUTO_INCREMENT PRIMARY KEY,
              product_id INT NOT NULL,
              is_required TINYINT(1) NOT NULL DEFAULT 0,
              parameter_id BIGINT UNSIGNED NOT NULL,
              FOREIGN KEY (product_id) REFERENCES " . PRODUCT_TABLE . "(id) ON DELETE CASCADE,
              FOREIGN KEY (parameter_id) REFERENCES ". PARAM_TABLE ."(id) ON DELETE CASCADE
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
    }
}
