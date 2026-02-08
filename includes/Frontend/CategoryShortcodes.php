<?php
namespace PPC\Frontend;

class CategoryShortcodes {

    public function __construct() {
        add_shortcode('ppc_category_archive', [$this, 'render_category_archive']);
        add_shortcode('ppc_categories_grid',  [$this, 'render_categories_grid']);
        add_shortcode('ppc_child_categories_grid',  [$this, 'render_child_categories_grid']);
        add_shortcode('ppc_parent_categories_grid',  [$this, 'render_parent_categories_grid']);
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
            return '<div class="w-11/12 mx-auto my-8 p-4 bg-yellow-50 border border-yellow-200 rounded">' .
                   esc_html__( 'No category specified.', 'printing-pricing-calculator' ) .
                   '</div>';
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
            return '<div class="w-11/12 mx-auto my-8 p-4 bg-red-50 border border-red-200 rounded">' .
                   esc_html__( 'Category not found.', 'printing-pricing-calculator' ) .
                   '</div>';
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
            return home_url( 'produkt/' . $slug );
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
                                <h3 class="mt-3 text-base font-semibold group-hover:[color:rgb(0,163,202)] line-clamp-2">
                                    <?php echo esc_html($title); ?>
                                </h3>
                            </a>
                            <!--
                            <div class="text-sm text-gray-600 mt-1">
                                <?php // printf( esc_html__( 'From %s', 'printing-pricing-calculator' ), number_format_i18n( (float) $p['base_price'], 2 ) ); ?>
                            </div>
                            <div class="mt-3">
                                <a href="<?php // echo esc_url($url); ?>"
                                   class="inline-flex items-center justify-center px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                                    <?php // echo esc_html__( 'Configure & Price', 'printing-pricing-calculator' ); ?>
                                </a>
                            </div>
                            -->
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 rounded bg-gray-50 border border-gray-200 mb-8">
                    <?php echo esc_html__( 'No products in this category.', 'printing-pricing-calculator' ); ?>
                </div>
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
     * - 5 boxes per row, each shows category image + name
     * - Clickable to category archive page (we’ll send to a generic landing using the slug)
     */
    public function render_categories_grid($atts = []) {
        $this->ensure_tailwind();

        global $wpdb;

        $atts = shortcode_atts([
            'columns' => 5,     // 4 per row
            'limit'   => 100,   // safety cap
            'order'   => 'ASC',
            // Optional: base page for category archives, default is /category/<slug>
            'base'    => 'portfolio',    // e.g., '/produkty/' if you have a pretty URL page
            'ids'     => '',
            'slugs' => ''
        ], $atts, 'ppc_categories_grid');

        $columns = (int) $atts['columns'];
        $columns = max(1, min(6, $columns));
        $limit   = max(1, (int)$atts['limit']);
        $order   = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $base    = trim((string)$atts['base']);
        $ids_raw = trim($atts['ids']);
        $slugs_raw = trim($atts['slugs']);
        $cats = [];

        /* ---------------------------------------------------------
        1. If comma-separated IDs are provided → fetch ONLY these
        --------------------------------------------------------- */
        if (!empty($ids_raw)) {

            // Convert: "1, 4, 6" → [1,4,6]
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));

                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND id IN ($placeholders)",
                    $ids
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        } elseif (!empty($slugs_raw)) {
            $slugs = array_filter(array_map('sanitize_title', explode(',', $slugs_raw)));

            if (!empty($slugs)) {
                $placeholders = implode(',', array_fill(0, count($slugs), '%d'));

                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND sllug IN ($placeholders)",
                    $slugs
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        }

        /* ---------------------------------------------------------
        2. Otherwise → fallback to default full list logic
        --------------------------------------------------------- */
        if (empty($cats)) {
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
        }

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
                            $img_html = '';
                            if (!empty($c['image_id'])) {
                                $img_html = wp_get_attachment_image( (int)$c['image_id'], 'medium', false, [
                                    'class' => 'block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105'
                                ]);
                            } else {
                                $img_html = '<img class="block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105" src="https://www.repress.cz/wp-content/uploads/logo-R-1.webp" alt="default category picture" />';
                            }
                            $cat_link = $base !== ''
                                ? trailingslashit( home_url( '/' . ltrim( $base, '/\\' ) . '/' . $c['slug'] ) )
                                : home_url( 'portfolio' . $c['slug'] );
                        ?>
                        <a href="<?php echo esc_url( $cat_link ); ?>" class="block group !no-underline">
                            <div class="bg-white shadow hover:shadow-md transition overflow-hidden border border-transparent hover:border-black">
                                <div class="aspect-[4/3] bg-gray-100">
                                    <?php if ($img_html) : ?>
                                        <?php echo $img_html; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full grid place-items-center text-gray-400 text-sm">
                                            <?php echo esc_html__( 'No image', 'printing-pricing-calculator' ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="text-base font-semibold group-hover:[color:rgb(0,163,202)] transition">
                                        <?php echo esc_html($c['name']); ?>
                                    </h3>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-4 rounded bg-gray-50 border border-gray-200">
                        <?php echo esc_html__( 'No categories found.', 'printing-pricing-calculator' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_child_categories_grid($atts = []) {
        global $wpdb;
        // Shortcode attributes
        $atts = shortcode_atts([
            'columns' => '',
            'slug' => '',       // parent category slug (optional)
            'id'   => '',       // parent category ID (optional)
            'limit' => 48,
            'order' => 'ASC',
            'base' => 'portfolio',
            'child_ids' => '',
            'child_slugs' => ''
        ], $atts, 'ppc_category_archive');

        $limit = max(1, (int)$atts['limit']);
        $order = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $base    = trim((string)$atts['base']);

        // ↓↓↓ 1. DETERMINE PARENT CATEGORY ID ↓↓↓

        $parent_id = 0;

        /** ✔ A. If shortcode has ID directly */
        if (!empty($atts['id'])) {
            $parent_id = (int)$atts['id'];
        }

        /** ✔ B. If shortcode has slug explicitly */
        elseif (!empty($atts['slug'])) {
            $parent_id = (int)$wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM " . CATEGORY_TABLE . " WHERE slug = %s AND status = 'active' LIMIT 1",
                    sanitize_title($atts['slug'])
                )
            );
        }

        /** ✔ C. If neither slug nor ID is provided → use resolve_category_slug() */
        else {
            $resolved_slug = $this->resolve_category_slug($atts);

            if ($resolved_slug) {
                $parent_id = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM " . CATEGORY_TABLE . " WHERE slug = %s AND status = 'active' LIMIT 1",
                        sanitize_title($resolved_slug)
                    )
                );
            }
        }

        /** If still no parent category found */
        if (!$parent_id) {
            return '<div class="w-11/12 mx-auto my-8 p-4 bg-yellow-50 border border-yellow-200 rounded">'
                . esc_html__('Parent category not found.', 'printing-pricing-calculator')
                . '</div>';
        }
        /* ------------------------------------------------------------
        2. NEW FEATURE: FETCH ONLY SPECIFIC CHILD IDs (if provided)
        ------------------------------------------------------------ */

        $child_ids_raw = trim($atts['child_ids']);
        $child_slugs_raw = trim($atts['child_slugs']);
        $cats = [];

        if (!empty($child_ids_raw)) {

            // Convert "2, 5, 9" → [2, 5, 9]
            $child_ids = array_filter(array_map('intval', explode(',', $child_ids_raw)));

            if (!empty($child_ids)) {

                // Prepare placeholders for IN clause
                $placeholders = implode(',', array_fill(0, count($child_ids), '%d'));

                // Fetch only children that:
                // → match provided IDs
                // → AND belong to this parent
                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND parent_id = %d
                    AND id IN ($placeholders)",
                    array_merge([$parent_id], $child_ids)
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        } elseif (!empty($child_slugs_raw)) {
            // Convert "2, 5, 9" → [2, 5, 9]
            $child_slugs = array_filter(array_map('sanitize_title', explode(',', $child_slugs_raw)));

            if (!empty($child_slugs)) {

                // Prepare placeholders for IN clause
                $placeholders = implode(',', array_fill(0, count($child_slugs), '%s'));
                // Fetch only children that:
                // → match provided IDs
                // → AND belong to this parent
                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND parent_id = %d
                    AND slug  IN ($placeholders)",
                    array_merge([$parent_id], $child_slugs)
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        }

        /* ------------------------------------------------------------
        3. FALLBACK: If no child_ids OR no matching results → fetch all children
        ------------------------------------------------------------ */
        if (empty($cats)) {
            $cats = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND parent_id = %d
                    ORDER BY name {$order}
                    LIMIT %d",
                    $parent_id,
                    $limit
                ),
                ARRAY_A
            );
        }

        $columns = 5;
        if (!empty($atts['columns'])) {
            $columns = (int)$atts['columns'];
        }

        $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
        if ($columns === 3)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
        if ($columns === 5)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6';
        if ($columns === 6)  $colClass = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-6';

        // ↓↓↓ 3. Return raw data (you will handle frontend) ↓↓↓
        ob_start();
        ?>
        <div class="w-11/12 mx-auto my-10 font-sans">
            <div class="<?php echo esc_attr($colClass); ?>">
                <?php if (!empty($cats)): ?>
                    <?php foreach ($cats as $c): ?>
                        <?php
                            $img_html = '';
                            if (!empty($c['image_id'])) {
                                $img_html = wp_get_attachment_image( (int)$c['image_id'], 'medium', false, [
                                    'class' => 'block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105'
                                ]);
                            } else {
                                $img_html = '<img class="block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105" src="https://www.repress.cz/wp-content/uploads/logo-R-1.webp" alt="default category picture" />';
                            }
                            $cat_link = $base !== ''
                                ? trailingslashit( home_url( '/' . ltrim( $base, '/\\' ) . '/' . $c['slug'] ) )
                                : home_url( 'portfolio' . $c['slug'] );
                        ?>
                        <a href="<?php echo esc_url( $cat_link ); ?>" class="block group !no-underline">
                            <div class="bg-white shadow hover:shadow-md transition overflow-hidden border border-transparent hover:border-black">
                                <div class="aspect-[4/3] bg-gray-100">
                                    <?php if ($img_html) : ?>
                                        <?php echo $img_html; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full grid place-items-center text-gray-400 text-sm">
                                            <?php echo esc_html__( 'No image', 'printing-pricing-calculator' ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="text-base font-semibold group-hover:[color:rgb(0,163,202)] transition">
                                        <?php echo esc_html($c['name']); ?>
                                    </h3>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-4 rounded bg-gray-50 border border-gray-200">
                        <?php echo esc_html__( 'No categories found.', 'printing-pricing-calculator' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_parent_categories_grid($atts = []) {
        $this->ensure_tailwind();

        global $wpdb;

        $atts = shortcode_atts([
            'columns' => 5,     // 4 per row
            'limit'   => 100,   // safety cap
            'order'   => 'ASC',
            // Optional: base page for category archives, default is /category/<slug>
            'base'    => 'portfolio',    // e.g., '/produkty/' if you have a pretty URL page
            'ids'     => '',
            'slugs' => ''
        ], $atts, 'ppc_categories_grid');

        $columns = (int) $atts['columns'];
        $columns = max(1, min(6, $columns));
        $limit   = max(1, (int)$atts['limit']);
        $order   = strtoupper($atts['order']) === 'DESC' ? 'DESC' : 'ASC';
        $base    = trim((string)$atts['base']);
        $ids_raw = trim($atts['ids']);
        $slugs_raw = trim($atts['slugs']);
        $cats = [];

        /* ---------------------------------------------------------
        1. If comma-separated IDs are provided → fetch ONLY these
        --------------------------------------------------------- */
        if (!empty($ids_raw)) {

            // Convert: "1, 4, 6" → [1,4,6]
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));

                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND id IN ($placeholders)
                    AND parent_id IS NULL",
                    $ids
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        } elseif (!empty($slugs_raw)) {
            $slugs = array_filter(array_map('sanitize_title', explode(',', $slugs_raw)));

            if (!empty($slugs)) {
                $placeholders = implode(',', array_fill(0, count($slugs), '%d'));

                $query = $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND sllug IN ($placeholders)
                    AND parent_id IS NULL",
                    $slugs
                );

                $cats = $wpdb->get_results($query, ARRAY_A);
            }
        }

        /* ---------------------------------------------------------
        2. Otherwise → fallback to default full list logic
        --------------------------------------------------------- */
        if (empty($cats)) {
            $cats = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name, slug, image_id, description
                    FROM " . CATEGORY_TABLE . "
                    WHERE status = 'active'
                    AND parent_id IS NULL
                    ORDER BY name {$order}
                    LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }

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
                            $img_html = '';
                            if (!empty($c['image_id'])) {
                                $img_html = wp_get_attachment_image( (int)$c['image_id'], 'medium', false, [
                                    'class' => 'block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105'
                                ]);
                            } else {
                                $img_html = '<img class="block w-full h-full object-cover transform-gpu will-change-transform transition-transform duration-500 ease-out group-hover:scale-105" src="https://www.repress.cz/wp-content/uploads/logo-R-1.webp" alt="default category picture" />';
                            }
                            $cat_link = $base !== ''
                                ? trailingslashit( home_url( '/' . ltrim( $base, '/\\' ) . '/' . $c['slug'] ) )
                                : home_url( 'portfolio' . $c['slug'] );
                        ?>
                        <a href="<?php echo esc_url( $cat_link ); ?>" class="block group !no-underline">
                            <div class="bg-white shadow hover:shadow-md transition overflow-hidden border border-transparent hover:border-black">
                                <div class="aspect-[4/3] bg-gray-100">
                                    <?php if ($img_html) : ?>
                                        <?php echo $img_html; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full grid place-items-center text-gray-400 text-sm">
                                            <?php echo esc_html__( 'No image', 'printing-pricing-calculator' ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="text-base font-semibold group-hover:[color:rgb(0,163,202)] transition">
                                        <?php echo esc_html($c['name']); ?>
                                    </h3>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-4 rounded bg-gray-50 border border-gray-200">
                        <?php echo esc_html__( 'No categories found.', 'printing-pricing-calculator' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
