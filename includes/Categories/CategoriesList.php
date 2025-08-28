<?php
namespace PPC\Categories;

if ( ! class_exists('\WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CategoriesList extends \WP_List_Table {
    public function register_menu() {
        add_submenu_page(
            'ppc-calculator',
            __('Categories', 'printing-pricing-calculator'),
            __('Categories', 'printing-pricing-calculator'),
            'manage_options',
            'ppc-categories',
            [$this, 'render']
        );
    }

    public function render() {
      if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__( 'Unauthorized', 'printing-pricing-calculator' ) );
        }

        // Prepare the table before including the template
        $this->prepare_items();

        // Optional message from actions
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

        // Include template (use plugin root constant/path)
        // Replace PPC_PLUGIN_DIR with your plugin dir constant if different.
        require_once plugin_dir_path(__FILE__) . 'includes/Templates/Categories/list.php';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'printing-pricing-calculator' ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg(['page'=>'ppc-categories-edit'], admin_url('admin.php')) ); ?>" class="page-title-action">
                <?php echo esc_html__( 'Add New', 'printing-pricing-calculator' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( $message ) : ?>
                <div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <form method="post">
                <?php
                    $this->search_box( esc_html__( 'Search Categories', 'printing-pricing-calculator' ), 'ppc-cat' );
                    $this->display();
                ?>
            </form>
        </div>
        <?php
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        parent::__construct([
            'singular' => 'ppc_category',
            'plural'   => 'ppc_categories',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'image'       => esc_html__( 'Image', 'printing-pricing-calculator' ),
            'name'        => esc_html__( 'Name', 'printing-pricing-calculator' ),
            'slug'        => esc_html__( 'Slug', 'printing-pricing-calculator' ),
            'status'      => esc_html__( 'Status', 'printing-pricing-calculator' ),
            'description' => esc_html__( 'Description', 'printing-pricing-calculator' ),
            'date'        => esc_html__( 'Created', 'printing-pricing-calculator' ),
        ];
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item['id']);
    }

    protected function column_name( $item ) {
        $edit_url = add_query_arg(
            [
                'page' => 'ppc-categories-edit',
                'id'   => $item['id'],
            ],
            admin_url('admin.php')
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                [
                    'page'   => 'ppc-categories',
                    'action' => 'delete',
                    'id'     => $item['id'],
                ],
                admin_url('admin.php')
            ),
            'ppc_cat_delete_' . $item['id']
        );

        $actions = [
            'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'printing-pricing-calculator' ) . '</a>',
            'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . wp_json_encode( __( 'Delete this category?', 'printing-pricing-calculator' ) ) . ')">' . esc_html__( 'Delete', 'printing-pricing-calculator' ) . '</a>',
        ];

        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            esc_url( $edit_url ),
            esc_html( $item['name'] ),
            $this->row_actions( $actions )
        );
    }

    protected function column_slug($item) {
        return esc_html($item['slug']);
    }

    protected function column_status( $item ) {
        return esc_html( $item['status'] === 'active'
            ? __( 'Active', 'printing-pricing-calculator' )
            : __( 'Inactive', 'printing-pricing-calculator' )
        );
    }

    protected function column_description($item) {
        return esc_html( wp_trim_words( (string) $item['description'], 20 ) );
    }

    protected function column_date($item) {
        return esc_html( mysql2date( 'Y-m-d', $item['created_at'] ) );
    }

    protected function get_sortable_columns() {
        return [
            'name'   => ['name', false],
            'slug'   => ['slug', false],
            'status' => ['status', false],
            'date'   => ['created_at', false],
        ];
    }

    public function get_bulk_actions() {
        return [
            'bulk-delete' => esc_html__( 'Delete', 'printing-pricing-calculator' ),
        ];
    }

    public function process_bulk_action() {
        if ( 'bulk-delete' === $this->current_action() && ! empty($_POST['ids']) && is_array($_POST['ids']) ) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            global $wpdb;
            $ids = array_map('intval', $_POST['ids']);
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query( $wpdb->prepare("DELETE FROM " . CATEGORY_TABLE . " WHERE id IN ($in)", ...$ids) );
            }
        }
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $orderby = (!empty($_GET['orderby']) && in_array($_GET['orderby'], ['name','slug','status','created_at'], true)) ? $_GET['orderby'] : 'created_at';
        $order   = (!empty($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

        $search  = isset($_REQUEST['s']) ? trim(wp_unslash($_REQUEST['s'])) : '';

        $where = "WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $where .= " AND (name LIKE %s OR slug LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like; $params[] = $like;
        }

        $total_items = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . CATEGORY_TABLE . " $where",
            ...$params
        ) );

        $offset = ($current_page - 1) * $per_page;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . CATEGORY_TABLE . " $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        ), ARRAY_A );

        $this->items = $rows ?: [];

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->process_bulk_action();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}
