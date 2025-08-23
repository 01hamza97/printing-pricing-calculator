<?php
namespace PPC\Frontend;

class CategoryShortcodes {

    public function __construct() {
        add_shortcode('ppc_category_archive', [$this, 'render_category_archive']);
        add_shortcode('ppc_categories_grid',  [$this, 'render_categories_grid']);
    }

    /** Enqueue Tailwind only when needed */
    private function ensure_tailwind() {
        // Use same handle site-wide to avoid duplicate loads
        if ( ! wp_script_is('ppc-tailwind', 'enqueued') ) {
            wp_enqueue_script(
                'ppc-tailwind',
                'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
                [],
                null,
                true
            );
        }
    }

    /**
     * Resolve category slug:
     * - 1) explicit [ppc_category_archive slug="..."]
     * - 2) ?ppc_category=<slug> (query var; add a rewrite for production)
     * - 3) last path segment (best-effort fallback)
     */
    private function resolve_category_slug($atts) : string {
        $slug = '';
        if (!empty($atts['slug'])) {
            $slug = sanitize_title($atts['slug']);
        } else {
            $qv = get_query_var('ppc_category');
            if (!empty($qv)) {
                $slug = sanitize_title($qv);
            } else {
                global $wp;
                if (isset($wp->request) && is_string($wp->request) && $wp->request !== '') {
                    $parts = array_values(array_filter(explode('/', $wp->request)));
                    if (!empty($parts)) {
                        $slug = sanitize_title(end($parts));
                    }
                }
            }
        }
        return $slug;
    }

    /**
     * Shortcode 1:
     * [ppc_category_archive slug="letaky"]
     * - If slug omitted, pulled from URL as described above.
     * - Renders: Category Title, products (4 per row), Category description (HTML)
     */
    public function render_category_archive($atts = []) {
        $this->ensure_tailwind();

        global $wpdb;

        $atts = shortcode_atts([
            'slug'    => '',
            'limit'   => 48,   // safety cap
            'order'   => 'ASC' // product title order
        ], $atts, 'ppc_category_archive');

        $slug  = $this->resolve_category_slug($atts);
        $limit = max(1, (int)$atts['limit']);
        $order = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';

        if ($slug === '') {
            return '<div class="w-11/12 mx-auto my-8 p-4 bg-yellow-50 border border-yellow-200 rounded">No category specified.</div>';
        }

        // Fetch category by slug
        $cat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . CATEGORY_TABLE . " WHERE slug = %s AND status = 'active' LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        if (! $cat) {
            return '<div class="w-11/12 mx-auto my-8 p-4 bg-red-50 border border-red-200 rounded">Category not found.</div>';
        }

        // Fetch product IDs in this category
        $product_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pc.product_id
                 FROM " . PRODUCT_CATEGORY_TABLE . " pc
                 INNER JOIN " . PRODUCT_TABLE . " p ON p.id = pc.product_id AND p.status = 'active'
                 WHERE pc.category_id = %d
                 ORDER BY p.title {$order}
                 LIMIT %d",
                (int)$cat['id'],
                $limit
            )
        );

        $products = [];
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
            $products = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, slug, image_url, base_price
                     FROM " . PRODUCT_TABLE . "
                     WHERE id IN ($placeholders) AND status = 'active'
                     ORDER BY FIELD(id, " . implode(',', array_map('intval', $product_ids)) . ")",
                    ...array_map('intval', $product_ids)
                ),
                ARRAY_A
            );
        }

        // Build URL for product card links
        $product_url = function($slug) {
            $slug = ltrim((string)$slug, '/');
            return home_url( 'product/' . $slug );
        };

        ob_start();
        ?>
        <div class="w-11/12 mx-auto my-10 font-sans">
            <!-- 1) Category Title -->
            <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mb-6">
                <?php echo esc_html($cat['name']); ?>
            </h1>

            <!-- 2) Products Grid -->
            <?php if (!empty($products)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <?php foreach ($products as $p): ?>
                        <?php
                            $title = $p['title'] ?? '';
                            $slug  = $p['slug']  ?? '';
                            $img   = $p['image_url'] ?? '';
                            $url   = $product_url($slug);
                        ?>
                        <article class="bg-white rounded-xl shadow hover:shadow-md transition p-3 flex flex-col">
                            <a href="<?php echo esc_url($url); ?>" class="block">
                                <div class="aspect-[4/3] overflow-hidden rounded-lg bg-gray-100">
                                    <?php if ($img): ?>
                                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full grid place-items-center text-gray-400 text-sm">No image</div>
                                    <?php endif; ?>
                                </div>
                                <h3 class="mt-3 text-base font-semibold line-clamp-2">
                                    <?php echo esc_html($title); ?>
                                </h3>
                            </a>
                            <!-- <?php // if (isset($p['base_price']) && $p['base_price'] !== ''): ?>
                                <div class="text-sm text-gray-600 mt-1">
                                    From <?php // echo number_format((float)$p['base_price'], 2); ?>
                                </div>
                            <?php // endif; ?>
                            <div class="mt-3">
                                <a href="<?php // echo esc_url($url); ?>"
                                   class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                                    Configure &amp; Price
                                </a>
                            </div> -->
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 rounded bg-gray-50 border border-gray-200 mb-8">No products in this category.</div>
            <?php endif; ?>

            <!-- 3) Category Description (HTML) -->
            <?php if (!empty($cat['description'])): ?>
                <div class="prose max-w-none">
                    <?php echo wp_kses_post($cat['description']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode 2:
     * [ppc_categories_grid]
     * - 4 boxes per row, each shows category image + name
     * - Clickable to category archive page (we’ll send to a generic landing using the slug)
     */
    public function render_categories_grid($atts = []) {
        $this->ensure_tailwind();

        global $wpdb;

        $atts = shortcode_atts([
            'columns' => 4,     // 4 per row
            'limit'   => 100,   // safety cap
            'order'   => 'ASC',
            // Optional: base page for category archives, default is current page with ?ppc_category=slug
            'base'    => '',    // e.g., '/produkty/' if you have a pretty URL page
        ], $atts, 'ppc_categories_grid');

        $columns = (int) $atts['columns'];
        $columns = max(1, min(6, $columns));
        $limit   = max(1, (int)$atts['limit']);
        $order   = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $base    = trim((string)$atts['base']);

        // Load categories
        $cats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, slug, image_id, description
                 FROM " . CATEGORY_TABLE . "
                 WHERE status = 'active'
                 ORDER BY name {$order}
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        // // Build category URL
        // $cat_url = function($slug) use ($base) {
        //     $slug = sanitize_title($slug);
        //     if ($base !== '') {
        //         // Use provided base (accepts absolute or relative)
        //         $base_url = home_url( '/' . ltrim($base, '/\\') . '/' );
        //         return trailingslashit( $base_url . $slug );
        //     }
        //     // Default: add query var to current page
        //     return add_query_arg('ppc_category', $slug, get_permalink());
        // };

        // Tailwind grid col classes for 4 per row default
        $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
        if ($columns === 3)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
        if ($columns === 5)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6';
        if ($columns === 6)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-6';

        ob_start();
        ?>
        <div class="w-11/12 mx-auto my-10 font-sans">
            <div class="<?php echo esc_attr($colClass); ?>">
                <?php if (!empty($cats)): ?>
                    <?php foreach ($cats as $c): ?>
                        <?php
                            $img = '';
                            if (!empty($c['image_id'])) {
                                $img = wp_get_attachment_image( (int)$c['image_id'], 'medium', false, [
                                    'class' => 'w-full h-full object-cover'
                                ]);
                            }
                        ?>
                        <a href="/category/<?php echo $c['slug'] ?>" class="block group">
                            <div class="bg-white rounded-xl shadow hover:shadow-md transition overflow-hidden">
                                <div class="aspect-[4/3] bg-gray-100">
                                    <?php if ($img) : ?>
                                        <?php echo $img; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full grid place-items-center text-gray-400 text-sm">No image</div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="text-base font-semibold group-hover:text-blue-600 transition">
                                        <?php echo esc_html($c['name']); ?>
                                    </h3>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-4 rounded bg-gray-50 border border-gray-200">No categories found.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
