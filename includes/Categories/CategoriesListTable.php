<?php
namespace PPC\Categories;

if ( ! class_exists('\WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CategoriesListTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'ppc_category',
            'plural'   => 'ppc_categories',
            'ajax'     => false,
        ]);
        add_action('admin_head', [$this, 'print_column_styles']);
    }

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'id'          => __('Id', 'printing-pricing-calculator'),
            'image'       => __('Image', 'printing-pricing-calculator'),
            'name'        => __('Name', 'printing-pricing-calculator'),
            'slug'        => __('Slug', 'printing-pricing-calculator'),
            'parent'      => __('Parent', 'printing-pricing-calculator'),
            'status'      => __('Status', 'printing-pricing-calculator'),
            'description' => __('Description', 'printing-pricing-calculator'),
            'date'        => __('Created', 'printing-pricing-calculator'),
        ];
    }

    /**
     * Output custom styles for this list table.
     * The table will have the class "ppc_categories" (from the plural),
     * and the ID column cells have "column-id".
     */
    public function print_column_styles() {
        ?>
        <style>
            /* Limit the width of the ID column just for this table */
            .wp-list-table.ppc_categories .column-id {
                width: 70px;
                max-width: 70px;
                text-align: center;
            }
        </style>
        <?php
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', (int)$item['id']);
    }

    protected function column_id($item) {
        return esc_html($item['id']);
    }

    protected function column_image($item) {
        $img = '';
        if (!empty($item['image_id'])) {
            $img = wp_get_attachment_image((int)$item['image_id'], [40, 40], true, ['style' => 'border-radius:4px;object-fit:cover;']);
        }
        return $img ?: '<span class="dashicons dashicons-format-image" style="opacity:.3"></span>';
    }

    protected function column_name($item) {
        $edit_url = add_query_arg([
            'page' => 'ppc-categories-edit',
            'id'   => (int)$item['id'],
        ], admin_url('admin.php'));

        $delete_url = wp_nonce_url(
            add_query_arg([
                'page'   => 'ppc-categories',
                'action' => 'delete',
                'id'     => (int)$item['id'],
            ], admin_url('admin.php')),
            'ppc_cat_delete_' . (int)$item['id']
        );

        $actions = [
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'printing-pricing-calculator')),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url($delete_url),
                esc_js(__('Delete this category?', 'printing-pricing-calculator')),
                esc_html__('Delete', 'printing-pricing-calculator')
            ),
        ];

        // Add visual hierarchy prefix
        $depth  = $this->get_category_depth($item['id']);
        $prefix = str_repeat('— ', $depth);

        return sprintf(
            '<strong><a href="%s">%s%s</a></strong> %s',
            esc_url($edit_url),
            esc_html($prefix),
            esc_html($item['name']),
            $this->row_actions($actions)
        );
    }

    protected function column_parent($item) {
        global $wpdb;
        if (!empty($item['parent_id'])) {
            $parent_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . CATEGORY_TABLE . " WHERE id = %d", (int)$item['parent_id']));
            return esc_html($parent_name ?: '—');
        }
        return '<span style="color:#999;">—</span>';
    }

    protected function column_slug($item)        { return esc_html($item['slug']); }
    protected function column_status($item)      { return esc_html($item['status'] === 'active' ? __('Active', 'printing-pricing-calculator') : __('Inactive', 'printing-pricing-calculator')); }
    protected function column_description($item) { return esc_html(wp_trim_words((string)$item['description'], 20)); }
    protected function column_date($item)        { return esc_html(mysql2date('Y-m-d', $item['created_at'])); }

    protected function get_sortable_columns() {
        return [
            'id'     => ['id', true],
            'name'   => ['name', false],
            'slug'   => ['slug', false],
            'status' => ['status', false],
            'date'   => ['created_at', false],
        ];
    }

    public function get_bulk_actions() {
        return ['bulk-delete' => __('Delete', 'printing-pricing-calculator')];
    }

    public function process_bulk_action() {
        if ('bulk-delete' === $this->current_action() && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            global $wpdb;
            $ids = array_map('intval', $_POST['ids']);
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM " . CATEGORY_TABLE . " WHERE id IN ($in)", ...$ids));
            }
        }
    }

    public function prepare_items() {
        global $wpdb;

        $per_page     = 20;
        $current_page = $this->get_pagenum();

        $orderby = (!empty($_GET['orderby']) && in_array($_GET['orderby'], ['name','slug','status','created_at'], true)) ? $_GET['orderby'] : 'created_at';
        $order   = (!empty($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
        $search  = isset($_REQUEST['s']) ? trim(wp_unslash($_REQUEST['s'])) : '';

        $where  = "WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $where .= " AND (name LIKE %s OR slug LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like; 
            $params[] = $like;
        }

        // Count
        $count_sql = "SELECT COUNT(*) FROM " . CATEGORY_TABLE . " $where";
        $total_items = !empty($params)
            ? (int)$wpdb->get_var($wpdb->prepare($count_sql, ...$params))
            : (int)$wpdb->get_var($count_sql);

        $offset = ($current_page - 1) * $per_page;

        // Fetch rows with parent hierarchy
        $rows_sql = "SELECT * FROM " . CATEGORY_TABLE . " $where ORDER BY parent_id ASC, $orderby $order LIMIT %d OFFSET %d";
        $rows_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, ...$rows_params), ARRAY_A);

        $this->items = $rows ?: [];

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int)ceil($total_items / $per_page),
        ]);
    }

    private function get_category_depth($cat_id, $depth = 0) {
        global $wpdb;
        $parent = $wpdb->get_var($wpdb->prepare("SELECT parent_id FROM " . CATEGORY_TABLE . " WHERE id = %d", (int)$cat_id));
        if ($parent) {
            return $this->get_category_depth($parent, $depth + 1);
        }
        return $depth;
    }
}
