<?php 
include_once 'config.php'; 
$page_title = "Torklub - Your Diecast Heaven";

// --- Fetch data for filters ---
try {
    $categories_stmt = $pdo->query("SELECT name FROM product_categories ORDER BY name ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

$vendors_with_logos = [];
try {
    $vendors_stmt = $pdo->query("SELECT * FROM vendors WHERE logo_url IS NOT NULL AND logo_url != '' ORDER BY name ASC");
    $vendors_with_logos = $vendors_stmt->fetchAll();
} catch (PDOException $e) {
    $vendors_with_logos = [];
}

// --- Fetch Homepage Sections from Database ---
try {
    $sections_stmt = $pdo->query("SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order ASC");
    $sections = $sections_stmt->fetchAll();
} catch (PDOException $e) {
    $sections = [];
}

// --- Fetch Favicon ---
try {
    $favicon_url = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'header_favicon_url'")->fetchColumn();
} catch (PDOException $e) {
    $favicon_url = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php if ($favicon_url): ?>
    <link rel="icon" href="<?php echo htmlspecialchars(ltrim($favicon_url, '/')); ?>">
    <?php endif; ?>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="style.css"> <!-- For custom animations -->
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <?php include 'header.php'; ?>

    <?php
    // --- Render Homepage Sections Dynamically ---
    foreach ($sections as $section) {
        $type = $section['section_type'];
        $title = htmlspecialchars($section['title'] ?? '');
        $content = json_decode($section['content'] ?? '[]', true);
        $caption = htmlspecialchars($section['caption'] ?? '');
        $settings = json_decode($section['settings'] ?? '[]', true);

        if ($type === 'hero_slider' && !empty($content)) {
            $active_slides = array_filter($content, fn($s) => !empty($s['image_url']));
            if (!empty($active_slides)) {
    ?> 
            <div class="relative w-full overflow-hidden" 
                 x-data="{ current: 0, slides: <?php echo count($active_slides); ?>, progress: 0 }" 
                 x-init="setInterval(() => { current = (current + 1) % slides }, 5000)">
                <!-- Progress Bar -->
                <div class="absolute top-0 left-0 w-full h-1 bg-gray-300/75 z-10">
                    <div :key="current" class="h-full bg-orange-500 animate-progress"></div>
                </div>
                <!-- Slides -->
                <div class="flex transition-transform duration-700 ease-in-out" :style="`transform: translateX(-${current * 100}%)`">
                    <?php foreach ($active_slides as $slide): ?>
                        <div class="w-full flex-shrink-0 overflow-hidden">
                            <?php $image_path = ltrim($slide['image_url'], '/'); ?>
                            <a href="<?php echo htmlspecialchars($slide['link_url']); ?>">
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Promotional slide" class="w-full h-auto ken-burns-image">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Dot Navigation -->
                <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-2 z-10">
                    <template x-for="i in slides" :key="i">
                        <button @click="current = i - 1" class="h-2 w-2 rounded-full transition-colors" :class="{'bg-white': current === i - 1, 'bg-white/50 hover:bg-white': current !== i - 1}"></button>
                    </template>
                </div>
            </div>
    <?php
            }
        } elseif ($type === 'brand_logos' && !empty($vendors_with_logos)) {
            echo '<div class="bg-white py-8"><div class="container mx-auto px-4">';
            echo '<h2 class="text-center text-2xl font-bold text-gray-800 mb-6">' . $title . '</h2>';
            echo '<div class="flex flex-wrap justify-center items-center gap-x-12 gap-y-6">';
            foreach (array_slice($vendors_with_logos, 0, 8) as $vendor) {
                // Remove leading slash from the URL to make it a relative path
                $logo_path = ltrim($vendor['logo_url'], '/');
                echo '<img src="' . htmlspecialchars($logo_path) . '" alt="' . htmlspecialchars($vendor['name']) . '" class="h-12 object-contain">';
            }
            echo '</div></div></div>';
        } elseif ($type === 'product_grid') {
            $grid_type = $settings['grid_type'] ?? 'new_arrivals';
            $limit = (int)($settings['limit'] ?? 4);
            $products = [];
            $product_query = "";

            // Updated queries to use the new fast-loading logic
            $base_sql = "
                SELECT p.handle, p.name, 
                    MAX(CASE WHEN pi.position = 1 THEN pi.image_url END) as image_url,
                    MAX(CASE WHEN pi.position = 2 THEN pi.image_url END) as hover_image_url,
                    MIN(pv.price) as price,
                    MAX(pv.compare_at_price) as compare_at_price,
                       SUM(pv.stock) as total_stock
                FROM products p
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.position IN (1, 2)
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE p.status = 'active'
                ";

            if ($grid_type === 'new_arrivals') {
                $product_query = $base_sql . " GROUP BY p.id ORDER BY p.id DESC LIMIT " . $limit;
            } elseif ($grid_type === 'on_sale') {
                $product_query = $base_sql . " AND pv.compare_at_price IS NOT NULL AND pv.compare_at_price > pv.price GROUP BY p.id HAVING MAX(pv.compare_at_price) > MIN(pv.price) LIMIT " . $limit;
            }

            if ($product_query) {
                $products = $pdo->query($product_query)->fetchAll();
            }

            if (!empty($products)) {
                echo '<div class="container mx-auto p-4 sm:p-6 lg:p-8">';
                echo '<h2 class="text-2xl font-bold tracking-tight text-gray-900 mb-6">' . $title . '</h2>';
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">';
                foreach ($products as $product) {
                    include 'product_card.php';
                }
                echo '</div></div>';
            }
        } elseif ($type === 'image_with_content') {
            $image_url = $content['image_url'] ?? '';
            $button_link = $content['button_link'] ?? '#';
            $button_text = htmlspecialchars($section['button_text'] ?? '');
            $text_align = $settings['text_alignment'] ?? 'left';
            $bg_color = $settings['background_color'] ?? 'white';
            $text_color = $settings['text_color'] ?? 'gray-800';

            $align_class = 'items-start text-left';
            if ($text_align === 'center') $align_class = 'items-center text-center';
            if ($text_align === 'right') $align_class = 'items-end text-right';

            if ($image_url) {
            ?>
                <div class="relative bg-<?php echo $bg_color; ?> text-<?php echo $text_color; ?>">
                    <div class="absolute inset-0">
                        <img class="w-full h-full object-cover" src="<?php echo htmlspecialchars(ltrim($image_url, '/')); ?>" alt="<?php echo $title; ?>">
                        <div class="absolute inset-0 bg-black opacity-30"></div>
                    </div>
                    <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32">
                        <div class="flex flex-col <?php echo $align_class; ?> max-w-xl">
                            <?php if ($caption): ?><p class="text-lg font-semibold uppercase tracking-wider"><?php echo $caption; ?></p><?php endif; ?>
                            <?php if ($title): ?><h2 class="text-4xl sm:text-5xl font-extrabold mt-2"><?php echo $title; ?></h2><?php endif; ?>
                            <?php if ($button_text && $button_link): ?>
                                <a href="<?php echo htmlspecialchars($button_link); ?>" class="mt-8 inline-block bg-white text-gray-900 font-semibold py-3 px-8 rounded-md hover:bg-gray-200 transition-colors">
                                    <?php echo $button_text; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php
            }
        } elseif ($type === 'promo_banner_double') {
            $block1 = $content['block_1'] ?? [];
            $block2 = $content['block_2'] ?? [];
            $text_color1 = $settings['text_color_1'] ?? 'gray-800';
            $text_color2 = $settings['text_color_2'] ?? 'gray-800';

            if (!empty($block1['image_url']) && !empty($block2['image_url'])) {
            ?>
                <div class="container mx-auto my-8 px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ([$block1, $block2] as $index => $block): ?>
                            <a href="<?php echo htmlspecialchars($block['button_link'] ?? '#'); ?>" class="group relative block overflow-hidden rounded-lg">
                                <img src="<?php echo htmlspecialchars(ltrim($block['image_url'], '/')); ?>" alt="<?php echo htmlspecialchars($block['title'] ?? ''); ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                                <div class="absolute inset-0 p-8 flex flex-col justify-end text-<?php echo ($index === 0 ? $text_color1 : $text_color2); ?>">
                                    <?php if (!empty($block['caption'])): ?><p class="text-sm font-semibold uppercase"><?php echo htmlspecialchars($block['caption']); ?></p><?php endif; ?>
                                    <?php if (!empty($block['title'])): ?><h3 class="text-2xl font-bold mt-1"><?php echo htmlspecialchars($block['title']); ?></h3><?php endif; ?>
                                    <?php if (!empty($block['button_text'])): ?><span class="mt-4 inline-block font-semibold border-b-2 border-current pb-1 group-hover:border-transparent transition"><?php echo htmlspecialchars($block['button_text']); ?></span><?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php
            }
        }
    }
    ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Lazy Loading ---
    const lazyLoad = (target) => {
        const io = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('opacity-100');
                    observer.unobserve(img);
                }
            });
        });
        io.observe(target);
    };

    const lazyImages = document.querySelectorAll('.lazy');
    lazyImages.forEach(lazyLoad);
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
