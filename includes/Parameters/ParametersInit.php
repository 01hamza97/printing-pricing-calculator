<?php
namespace PPC\Parameters;

class ParametersInit
{
    public function __construct()
    {}

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
}
