<?php
include '../config.php';
include 'auth_check.php';

$message = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_save = [
        'header_site_name' => $_POST['header_site_name'] ?? 'Torklub',
        'header_logo_url' => $_POST['header_logo_url'] ?? '',
        'header_favicon_url' => $_POST['header_favicon_url'] ?? '',
        'header_nav_links' => json_encode(array_values($_POST['nav_links'] ?? [])),
        'header_show_admin_icon' => isset($_POST['header_show_admin_icon']) ? '1' : '0',
        'header_show_cart_icon' => isset($_POST['header_show_cart_icon']) ? '1' : '0',
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");

    foreach ($settings_to_save as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    $message = "Header settings have been updated successfully!";
}

// --- Fetch current settings ---
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'header_%'");
$raw_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function get_setting($key, $default = '') {
    global $raw_settings;
    return $raw_settings[$key] ?? $default;
}

$nav_links = json_decode(get_setting('header_nav_links', '[{"text":"Home","url":"index.php"},{"text":"Shop","url":"products.php"}]'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Header Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>    
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold mb-6">Header Editor</h1>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <!-- Branding -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Branding & Icons</h2>
                <div class="space-y-4">
                    <div>
                        <label for="header_site_name" class="block text-sm font-medium text-gray-700">Site Name</label>
                        <input type="text" name="header_site_name" id="header_site_name" value="<?php echo htmlspecialchars(get_setting('header_site_name', 'Torklub')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="header_logo_url" class="block text-sm font-medium text-gray-700">Logo URL (e.g., /images/logo.png). If empty, Site Name is shown.</label>
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <input type="text" name="header_logo_url" id="header_logo_url" value="<?php echo htmlspecialchars(get_setting('header_logo_url')); ?>" class="block w-full rounded-none rounded-l-md border-gray-300">
                            <button type="button" onclick="openMediaLibrary('header_logo_url')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Select</button>
                        </div>
                    </div>
                    <div>
                        <label for="header_favicon_url" class="block text-sm font-medium text-gray-700">Favicon URL (e.g., /images/favicon.ico)</label>
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <input type="text" name="header_favicon_url" id="header_favicon_url" value="<?php echo htmlspecialchars(get_setting('header_favicon_url')); ?>" class="mt-1 block w-full rounded-none rounded-l-md border-gray-300">
                            <button type="button" onclick="openMediaLibrary('header_favicon_url')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Select</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="bg-white p-6 rounded-lg shadow-md" x-data='{ links: <?php echo json_encode(array_values($nav_links ?: [['text' => '', 'url' => '']])); ?> }'>
                <h2 class="text-xl font-semibold mb-4">Navigation Menu</h2>
                <div class="space-y-4">
                    <template x-for="(link, index) in links" :key="index">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="`nav_links[${index}][text]`" x-model="link.text" placeholder="Link Text" class="w-1/2 rounded-md border-gray-300">
                            <input type="text" :name="`nav_links[${index}][url]`" x-model="link.url" placeholder="URL (e.g., /products.php)" class="w-1/2 rounded-md border-gray-300">
                            <button type="button" @click="links.splice(index, 1)" class="text-red-500 hover:text-red-700">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="links.push({ text: '', url: '' })" class="text-sm font-medium text-blue-600 hover:text-blue-800">+ Add Link</button>
                </div>
            </div>

            <!-- Icons -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Header Icons</h2>
                <div class="space-y-2">
                    <label class="flex items-center"><input type="checkbox" name="header_show_admin_icon" value="1" <?php if(get_setting('header_show_admin_icon', '1') == '1') echo 'checked'; ?> class="h-4 w-4 rounded border-gray-300"> <span class="ml-2">Show Admin/User Icon</span></label>
                    <label class="flex items-center"><input type="checkbox" name="header_show_cart_icon" value="1" <?php if(get_setting('header_show_cart_icon', '1') == '1') echo 'checked'; ?> class="h-4 w-4 rounded border-gray-300"> <span class="ml-2">Show Cart Icon</span></label>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Header Settings</button>
            </div>
        </form>
    </div>
</body>
</html>