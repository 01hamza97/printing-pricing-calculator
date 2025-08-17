<?php
namespace PPC\Categories;

class CategoriesAdmin {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        // adjust 'ppc' to your real top-level menu slug
        add_submenu_page(
            'ppc-calculator',
            'Categories',
            'Categories',
            'manage_options',
            'ppc-categories',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if ( ! current_user_can('manage_options') ) {
            wp_die('Unauthorized');
        }

        // Load the List Table class now (admin screen is ready)
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once plugin_dir_path(__FILE__) . 'CategoriesListTable.php';
        wp_enqueue_media(); // enables wp.media modal
        $table = new CategoriesListTable();

        // Handle bulk actions before fetching totals
        $table->process_bulk_action();
        $table->prepare_items();

        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

        // require_once plugin_dir_path(__FILE__) . '../Templates/Categories/list.php';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Categories</h1>
            <a href="<?php echo esc_url( add_query_arg(['page'=>'ppc-categories-edit'], admin_url('admin.php')) ); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">

            <?php if ($message): ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php
                    $table->search_box('Search Categories', 'ppc-cat');
                    $table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
