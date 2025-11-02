<?php
// Fetch all footer settings at once
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'footer_%'");
$raw_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Helper to get a setting with a default value
function get_setting($key, $default = '') {
    global $raw_settings;
    return $raw_settings[$key] ?? $default;
}

$about_us = get_setting('footer_about_us', 'Torklub is your one-stop shop for premium diecast models and collectibles. We are passionate about cars and dedicated to bringing you the best.');
$address = get_setting('footer_address', '123 Diecast Lane, Dhaka, Bangladesh');
$phone = get_setting('footer_phone', '+880 123 456 789');
$email = get_setting('footer_email', 'contact@torklub.com');
$copyright = get_setting('footer_copyright', '© ' . date('Y') . ' Torklub. All Rights Reserved.');

$links_1_title = get_setting('footer_links_1_title', 'Quick Links');
$links_1 = json_decode(get_setting('footer_links_1_content', '[{"text":"Home","url":"index.php"},{"text":"Shop","url":"products.php"}]'), true);

$links_2_title = get_setting('footer_links_2_title', 'Information');
$links_2 = json_decode(get_setting('footer_links_2_content', '[{"text":"About Us","url":"#"},{"text":"Contact Us","url":"#"}]'), true);

?>
<footer class="bg-gray-800 text-gray-300">
    <div class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- About Section -->
            <div class="md:col-span-2">
                <h3 class="text-lg font-semibold text-white">About Torklub</h3>
                <p class="mt-4 text-sm"><?php echo htmlspecialchars($about_us); ?></p>
                <div class="mt-4 space-y-2 text-sm">
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($address); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>

            <!-- Links Column 1 -->
            <div>
                <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($links_1_title); ?></h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($links_1 as $link): ?>
                        <li><a href="<?php echo htmlspecialchars($link['url']); ?>" class="hover:text-white"><?php echo htmlspecialchars($link['text']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Links Column 2 -->
            <div>
                <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($links_2_title); ?></h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($links_2 as $link): ?>
                        <li><a href="<?php echo htmlspecialchars($link['url']); ?>" class="hover:text-white"><?php echo htmlspecialchars($link['text']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="mt-8 border-t border-gray-700 pt-8 text-center text-sm">
            <p><?php echo htmlspecialchars($copyright); ?></p>
        </div>
    </div>
</footer>