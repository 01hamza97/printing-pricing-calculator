<?php
namespace PPC\Frontend;

defined('ABSPATH') || exit;

class ProductShortcodes
{
    public function __construct()
    {
        add_shortcode('ppc_products_list', [$this, 'render_products_list']);
    }

    /**
     * Enqueue Tailwind and FontAwesome styles when needed.
     */
    private function ensure_assets()
    {
        if (!wp_script_is('ppc-tailwind', 'enqueued')) {
            wp_enqueue_script(
                'ppc-tailwind',
                'https://cdn.tailwindcss.com',
                [],
                null,
                true
            );
        }

        if (!wp_style_is('ppc-fontawesome', 'enqueued')) {
            wp_enqueue_style(
                'ppc-fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                [],
                null
            );
        }
    }

    /**
     * Renders products listed alphabetically.
     *
     * Shortcode: [ppc_products_list]
     */
    public function render_products_list($atts = [])
    {
        global $wpdb;

        $this->ensure_assets();

        // Parse shortcode attributes
        $atts = shortcode_atts([
            'order'       => 'ASC',
            'limit'       => 100,
            'columns'     => 5,
            'category'    => '',
            'category_id' => '',
            'show_price'  => 0,
            'show_button' => 0,
            'paginate'    => 0,
            'per_page'    => 10,
            'option'      => 1,
        ], $atts, 'ppc_products_list');

        $order       = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $limit       = max(1, intval($atts['limit']));
        $columns     = max(1, min(6, intval($atts['columns'])));
        $show_price  = filter_var($atts['show_price'], FILTER_VALIDATE_BOOLEAN) || intval($atts['show_price']) === 1;
        $show_button = filter_var($atts['show_button'], FILTER_VALIDATE_BOOLEAN) || intval($atts['show_button']) === 1;
        $paginate    = filter_var($atts['paginate'], FILTER_VALIDATE_BOOLEAN) || intval($atts['paginate']) === 1;
        $per_page    = max(1, intval($atts['per_page']));
        $option      = max(1, min(2, intval($atts['option'])));

        $category_id = 0;
        if (!empty($atts['category_id'])) {
            $category_id = intval($atts['category_id']);
        } elseif (!empty($atts['category'])) {
            $category_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM " . CATEGORY_TABLE . " WHERE slug = %s AND status = 'active' LIMIT 1",
                    sanitize_title($atts['category'])
                )
            );
        }

        // Get total count for pagination
        $total_products = 0;
        if ($paginate) {
            if ($category_id > 0) {
                $total_products = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT pc.product_id)
                         FROM " . PRODUCT_CATEGORY_TABLE . " pc
                         INNER JOIN " . PRODUCT_TABLE . " p ON p.id = pc.product_id AND p.status = 'active'
                         WHERE pc.category_id = %d",
                        $category_id
                    )
                );
            } else {
                $total_products = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM " . PRODUCT_TABLE . " WHERE status = 'active'"
                );
            }
        }

        $total_pages  = $paginate ? max(1, ceil($total_products / $per_page)) : 1;
        $current_page = max(1, isset($_GET['ppc_page']) ? intval($_GET['ppc_page']) : 1);
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }
        $offset = ($current_page - 1) * $per_page;

        // Fetch products
        if ($category_id > 0) {
            if ($paginate) {
                $product_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT pc.product_id
                         FROM " . PRODUCT_CATEGORY_TABLE . " pc
                         INNER JOIN " . PRODUCT_TABLE . " p ON p.id = pc.product_id AND p.status = 'active'
                         WHERE pc.category_id = %d
                         ORDER BY p.title {$order}
                         LIMIT %d OFFSET %d",
                        $category_id,
                        $per_page,
                        $offset
                    )
                );
            } else {
                $product_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT pc.product_id
                         FROM " . PRODUCT_CATEGORY_TABLE . " pc
                         INNER JOIN " . PRODUCT_TABLE . " p ON p.id = pc.product_id AND p.status = 'active'
                         WHERE pc.category_id = %d
                         ORDER BY p.title {$order}
                         LIMIT %d",
                        $category_id,
                        $limit
                    )
                );
            }

            $products = [];
            if (!empty($product_ids)) {
                $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, title, slug, image_url, base_price, popis_1, popis_2, with_price
                         FROM " . PRODUCT_TABLE . "
                         WHERE id IN ($placeholders) AND status = 'active'
                         ORDER BY title {$order}",
                        ...array_map('intval', $product_ids)
                    ),
                    ARRAY_A
                );
            }
        } else {
            if ($paginate) {
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, title, slug, image_url, base_price, popis_1, popis_2, with_price
                         FROM " . PRODUCT_TABLE . "
                         WHERE status = 'active'
                         ORDER BY title {$order}
                         LIMIT %d OFFSET %d",
                        $per_page,
                        $offset
                    ),
                    ARRAY_A
                );
            } else {
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, title, slug, image_url, base_price, popis_1, popis_2, with_price
                         FROM " . PRODUCT_TABLE . "
                         WHERE status = 'active'
                         ORDER BY title {$order}
                         LIMIT %d",
                        $limit
                    ),
                    ARRAY_A
                );
            }
        }

        // Column grid Tailwind classes mapping
        $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6';
        if ($columns === 1) {
            $colClass = 'grid grid-cols-1 gap-6';
        } elseif ($columns === 2) {
            $colClass = 'grid grid-cols-1 sm:grid-cols-2 gap-6';
        } elseif ($columns === 3) {
            $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
        } elseif ($columns === 4) {
            $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
        } elseif ($columns === 6) {
            $colClass = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6';
        }

        $product_url = function($slug) {
            return home_url('produkt/' . ltrim((string)$slug, '/'));
        };

        $base_url = remove_query_arg('ppc_page');

        ob_start();
        ?>
        <div class="w-11/12 mx-auto my-10 font-sans">
            <?php if (!empty($products)): ?>
                <?php if($option == 1): ?>
                    <div class="<?php echo esc_attr($colClass); ?>">
                        <?php foreach ($products as $p): ?>
                            <?php
                            $title = $p['title'] ?? '';
                            $pslug = $p['slug'] ?? '';
                            $img   = $p['image_url'] ?? '';
                            $url   = $product_url($pslug);
                            ?>
                            <article class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 hover:border-sky-500 transition-all duration-300 flex flex-col h-full transform hover:-translate-y-1">
                                <a href="<?php echo esc_url($url); ?>" class="block flex flex-col h-full !no-underline">
                                    <!-- Image Wrapper -->
                                    <div class="aspect-[4/3] w-full bg-gray-50 overflow-hidden relative">
                                        <?php if ($img): ?>
                                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="block w-full h-full object-cover transition-transform duration-500 ease-out group-hover:scale-105">
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 text-sm">
                                                <i class="fa-regular fa-image text-3xl mb-2 text-gray-300"></i>
                                                <span><?php echo esc_html__('No image', 'printing-pricing-calculator'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Content Container -->
                                    <div class="p-5 flex flex-col justify-between flex-grow">
                                        <div>
                                            <!-- Product Title -->
                                            <h3 class="font-semibold text-gray-800 group-hover:text-sky-600 transition-colors duration-200 text-base leading-snug line-clamp-2 mb-2">
                                                <?php echo esc_html($title); ?>
                                            </h3>

                                            <!-- Optional description snippet -->
                                            <?php if (!empty($p['popis_1'])): ?>
                                                <p class="text-xs text-gray-500 line-clamp-2 mb-3">
                                                    <?php echo esc_html(wp_strip_all_tags($p['popis_1'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                                            <!-- Price Display -->
                                            <?php if ($show_price && isset($p['base_price'])): ?>
                                                <div class="text-xs text-gray-500 flex flex-col">
                                                    <span class="text-[9px] uppercase tracking-wider text-gray-400 font-semibold"><?php echo esc_html__('From', 'printing-pricing-calculator'); ?></span>
                                                    <span class="text-base font-bold text-gray-900"><?php echo wc_price((float)$p['base_price']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Button or Arrow Link -->
                                            <?php if ($show_button): ?>
                                                <span class="inline-flex items-center justify-center px-3.5 py-1.5 text-xs font-semibold text-white bg-sky-600 group-hover:bg-sky-700 transition rounded-lg shadow-sm">
                                                    <?php echo esc_html__('Configure', 'printing-pricing-calculator'); ?>
                                                    <i class="fa-solid fa-chevron-right ml-1.5 text-[9px]"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-sky-500 group-hover:translate-x-1.5 transition-transform duration-300 ml-auto">
                                                    <i class="fa-solid fa-arrow-right-long text-lg"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($option == 2): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        <?php foreach ($products as $p): ?>
                            <?php
                                $title = $p['title'] ?? '';
                                $pslug = $p['slug']  ?? '';
                                $img   = $p['image_url'] ?? '';
                                $url   = $product_url($pslug);
                            ?>
                            <article class="bg-white shadow hover:shadow-md transition p-3 flex flex-col border border-transparent hover:border-black">
                                <a href="<?php echo esc_url($url); ?>" class="block !no-underline">
                                    <div class="bg-white shadow hover:shadow-md transition overflow-hidden">
                                        <?php if ($img): ?>
                                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105">
                                        <?php else: ?>
                                            <div class="w-full h-full grid place-items-center text-gray-400 text-sm">
                                                <?php echo esc_html__( 'No image', 'printing-pricing-calculator' ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-3 font-semibold group-hover:[color:rgb(0,163,202)] line-clamp-2">
                                        <?php echo esc_html($title); ?>
                                    </p>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif ?>


                <!-- Pagination -->
                <?php if ($paginate && $total_pages > 1): ?>
                    <nav class="flex items-center justify-center space-x-2 mt-12 font-sans" aria-label="Pagination">
                        <!-- Previous Page -->
                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('ppc_page', $current_page - 1, $base_url)); ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 text-gray-500 hover:border-sky-500 hover:text-sky-600 transition-colors duration-200 bg-white">
                                <i class="fa-solid fa-chevron-left text-sm"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $range = 2;
                        $last_printed = 0;
                        for ($i = 1; $i <= $total_pages; $i++):
                            $is_current = $i === $current_page;
                            $in_range = $i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range);
                            
                            if ($in_range):
                                if ($last_printed && $i - $last_printed > 1):
                                    ?>
                                    <span class="px-1 text-gray-400 select-none">...</span>
                                    <?php
                                endif;
                                $class = $is_current 
                                    ? 'inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-600 text-white font-semibold shadow-sm cursor-default' 
                                    : 'inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 text-gray-600 hover:border-sky-500 hover:text-sky-600 transition-colors duration-200 bg-white';
                                ?>
                                <a href="<?php echo $is_current ? '#' : esc_url(add_query_arg('ppc_page', $i, $base_url)); ?>" class="<?php echo esc_attr($class); ?>" <?php echo $is_current ? 'onclick="return false;"' : ''; ?>>
                                    <?php echo $i; ?>
                                </a>
                                <?php
                                $last_printed = $i;
                            endif;
                        endfor;
                        ?>

                        <!-- Next Page -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg('ppc_page', $current_page + 1, $base_url)); ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 text-gray-500 hover:border-sky-500 hover:text-sky-600 transition-colors duration-200 bg-white">
                                <i class="fa-solid fa-chevron-right text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 text-gray-500 text-center">
                    <?php echo esc_html__('No products found.', 'printing-pricing-calculator'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
