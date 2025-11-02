<?php
// Fetch all header settings at once
$header_settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'header_%'");
$header_settings = $header_settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function get_header_setting($key, $default = '') {
    global $header_settings;
    return $header_settings[$key] ?? $default;
}

$site_name = get_header_setting('header_site_name', 'Torklub');
$logo_url = get_header_setting('header_logo_url');
$nav_links = json_decode(get_header_setting('header_nav_links', '[{"text":"Home","url":"index.php"},{"text":"Shop","url":"products.php"}]'), true);
$show_admin_icon = get_header_setting('header_show_admin_icon', '1') == '1';
$show_cart_icon = get_header_setting('header_show_cart_icon', '1') == '1';

// Calculate total items in cart
$cart_item_count = 0;
if (!empty($_SESSION['cart'])) {
    $cart_item_count = array_sum($_SESSION['cart']);
}
?>
<header class="bg-white shadow-md sticky top-0 z-40">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="index.php" class="text-2xl font-bold text-gray-800 hover:text-blue-600">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($site_name); ?> Logo" class="h-8 w-auto">
                    <?php else: ?>
                        <?php echo htmlspecialchars($site_name); ?>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Main Navigation -->
            <nav class="hidden md:flex space-x-8">
                <?php foreach ($nav_links as $link): ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" class="text-gray-500 hover:text-gray-900 font-medium"><?php echo htmlspecialchars($link['text']); ?></a>
                <?php endforeach; ?>
            </nav>

            <!-- Right side icons -->
            <div class="flex items-center space-x-6">
                <?php if ($show_admin_icon): ?>
                    <a href="admin/index.php" class="text-gray-500 hover:text-gray-700" title="Admin Login"><svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></a>
                <?php endif; ?>
                <?php if ($show_cart_icon): ?>
                    <a href="checkout.php" class="relative text-gray-500 hover:text-gray-700" title="View Cart">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4z" /></svg>
                        <?php if ($cart_item_count > 0): ?><span class="absolute -top-2 -right-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?php echo $cart_item_count; ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>