<?php
namespace PPC\Products;

class ProductList {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            'ppc-calculator',
            __('Products', 'printing-pricing-calculator'),
            __('Products', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-products',
            [$this, 'render']
        );
    }

    public function render() {
        global $wpdb;
        $table = PRODUCT_TABLE;

        // Handle delete
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && current_user_can('manage_options')) {
            $id = intval($_GET['id']);
            check_admin_referer('delete_product_' . $id);
            $wpdb->delete($table, ['id' => $id]);
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Product deleted.', 'printing-pricing-calculator' ) . '</p></div>';
        }

        // Filters
        $search        = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        $page          = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit         = 30;
        $offset        = ($page - 1) * $limit;

        $where = "WHERE 1=1";
        $params = [];

        if ($search) {
            $where .= " AND title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($status_filter) {
            $where .= " AND status = %s";
            $params[] = $status_filter;
        }

        if ($category_filter) {
            $where .= " AND id IN (SELECT product_id FROM " . PRODUCT_CATEGORY_TABLE . " WHERE category_id = " .(int)$category_filter.")";
            $params[] = (int)$category_filter;
        }
        $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        $products    = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", ARRAY_A);

        $base_url   = remove_query_arg(['paged']);
        $pagination = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'current'   => $page,
            'total'     => max(1, (int) ceil($total_items / $limit)),
            'add_args'  => false,
        ]);

        include plugin_dir_path(__FILE__) . '/../Templates/Products/list.php';
    }
}
