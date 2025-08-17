<?php
namespace PPC\Categories;

class CategoriesInit
{
    public function __construct()
    {}

    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Check and create ppc_parameters table
        if ($wpdb->get_var("SHOW TABLES LIKE '". CATEGORY_TABLE  ."'") !== CATEGORY_TABLE ) {
            dbDelta("CREATE TABLE IF NOT EXISTS ". CATEGORY_TABLE  ." (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            image_id BIGINT(20) UNSIGNED NULL,
            description TEXT DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;");
        }

        // Check and create ppc_parameter_meta table
        if ($wpdb->get_var("SHOW TABLES LIKE '". PRODUCT_CATEGORY_TABLE ."'") !== PRODUCT_CATEGORY_TABLE) {
            dbDelta("CREATE TABLE IF NOT EXISTS ". PRODUCT_CATEGORY_TABLE ." (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES ". PRODUCT_TABLE ."(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES ". CATEGORY_TABLE ."(id) ON DELETE CASCADE
        ) $charset_collate;");
        }
    }
}
