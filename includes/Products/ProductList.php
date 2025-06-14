<?php
namespace PPC\Products;

class ProductList {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }
    public function register_menu() {
        add_submenu_page(
            'ppc-calculator',
            'Products',
            'Products',
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
          echo '<div class="notice notice-success"><p>Product deleted.</p></div>';
      }

      // Filters
      $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
      $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
      $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
      $limit = 10;
      $offset = ($page - 1) * $limit;
      $where = "WHERE 1=1";
      if ($search) $where .= $wpdb->prepare(" AND title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
      if (in_array($status_filter, ['active', 'inactive'])) $where .= $wpdb->prepare(" AND status = %s", $status_filter);
      $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
      $products = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset", ARRAY_A);
      $base_url = remove_query_arg(['paged']);
      $pagination = paginate_links([
          'base' => add_query_arg('paged', '%#%'),
          'format' => '',
          'current' => $page,
          'total' => ceil($total_items / $limit),
          'add_args' => false,
      ]);
      include plugin_dir_path(__FILE__) . '/../Templates/Products/list.php';
    }
}
