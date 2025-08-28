<?php
namespace PPC\Categories;

class CategoriesAdmin {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_submenu_page(
            'ppc-calculator',
            __('Categories', 'printing-pricing-calculator'),
            __('Categories', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-categories',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'Unauthorized', 'printing-pricing-calculator' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once plugin_dir_path(__FILE__) . 'CategoriesListTable.php';
        wp_enqueue_media();
        $table = new CategoriesListTable();

        $table->process_bulk_action();
        $table->prepare_items();

        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Categories', 'printing-pricing-calculator' ); ?>
            </h1>
            <a href="<?php echo esc_url( add_query_arg(['page'=>'ppc-categories-edit'], admin_url('admin.php')) ); ?>" class="page-title-action">
                <?php echo esc_html__( 'Add New', 'printing-pricing-calculator' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ($message): ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php
                    $table->search_box( esc_html__( 'Search Categories', 'printing-pricing-calculator' ), 'ppc-cat' );
                    $table->display();
                ?>
            </form>
        </div>
        <?php
    }
}