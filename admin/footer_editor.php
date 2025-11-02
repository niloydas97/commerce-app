<?php
include '../config.php';
include 'auth_check.php';

$message = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_save = [
        'footer_about_us' => $_POST['footer_about_us'] ?? '',
        'footer_address' => $_POST['footer_address'] ?? '',
        'footer_phone' => $_POST['footer_phone'] ?? '',
        'footer_email' => $_POST['footer_email'] ?? '',
        'footer_copyright' => $_POST['footer_copyright'] ?? '',
        'footer_links_1_title' => $_POST['footer_links_1_title'] ?? '',
        'footer_links_1_content' => json_encode(array_values($_POST['links_1'] ?? [])),
        'footer_links_2_title' => $_POST['footer_links_2_title'] ?? '',
        'footer_links_2_content' => json_encode(array_values($_POST['links_2'] ?? [])),
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");

    foreach ($settings_to_save as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    $message = "Footer settings have been updated successfully!";
}

// --- Fetch current settings ---
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'footer_%'");
$raw_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function get_setting($key, $default = '') {
    global $raw_settings;
    return $raw_settings[$key] ?? $default;
}

$links_1 = json_decode(get_setting('footer_links_1_content', '[]'), true);
$links_2 = json_decode(get_setting('footer_links_2_content', '[]'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Footer Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold mb-6">Footer Editor</h1>

        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <!-- General Info -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">General Information</h2>
                <div class="space-y-4">
                    <div>
                        <label for="footer_about_us" class="block text-sm font-medium text-gray-700">About Us Text</label>
                        <textarea name="footer_about_us" id="footer_about_us" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?php echo htmlspecialchars(get_setting('footer_about_us')); ?></textarea>
                    </div>
                    <div>
                        <label for="footer_address" class="block text-sm font-medium text-gray-700">Address</label>
                        <input type="text" name="footer_address" id="footer_address" value="<?php echo htmlspecialchars(get_setting('footer_address')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="footer_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="footer_phone" id="footer_phone" value="<?php echo htmlspecialchars(get_setting('footer_phone')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="footer_email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="footer_email" id="footer_email" value="<?php echo htmlspecialchars(get_setting('footer_email')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="footer_copyright" class="block text-sm font-medium text-gray-700">Copyright Text</label>
                        <input type="text" name="footer_copyright" id="footer_copyright" value="<?php echo htmlspecialchars(get_setting('footer_copyright', '© ' . date('Y') . ' Torklub. All Rights Reserved.')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Link Columns -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Links Column 1 -->
                <div class="bg-white p-6 rounded-lg shadow-md" x-data='{ links: <?php echo json_encode(array_values($links_1 ?: [['text' => '', 'url' => '']])); ?> }'>
                    <h2 class="text-xl font-semibold mb-4">Link Column 1</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Column Title</label>
                            <input type="text" name="footer_links_1_title" value="<?php echo htmlspecialchars(get_setting('footer_links_1_title')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <template x-for="(link, index) in links" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`links_1[${index}][text]`" x-model="link.text" placeholder="Link Text" class="w-1/2 rounded-md border-gray-300">
                                <input type="text" :name="`links_1[${index}][url]`" x-model="link.url" placeholder="URL" class="w-1/2 rounded-md border-gray-300">
                                <button type="button" @click="links.splice(index, 1)" class="text-red-500 hover:text-red-700">&times;</button>
                            </div>
                        </template>
                        <button type="button" @click="links.push({ text: '', url: '' })" class="text-sm font-medium text-blue-600 hover:text-blue-800">+ Add Link</button>
                    </div>
                </div>

                <!-- Links Column 2 -->
                <div class="bg-white p-6 rounded-lg shadow-md" x-data='{ links: <?php echo json_encode(array_values($links_2 ?: [['text' => '', 'url' => '']])); ?> }'>
                    <h2 class="text-xl font-semibold mb-4">Link Column 2</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Column Title</label>
                            <input type="text" name="footer_links_2_title" value="<?php echo htmlspecialchars(get_setting('footer_links_2_title')); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <template x-for="(link, index) in links" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="`links_2[${index}][text]`" x-model="link.text" placeholder="Link Text" class="w-1/2 rounded-md border-gray-300">
                                <input type="text" :name="`links_2[${index}][url]`" x-model="link.url" placeholder="URL" class="w-1/2 rounded-md border-gray-300">
                                <button type="button" @click="links.splice(index, 1)" class="text-red-500 hover:text-red-700">&times;</button>
                            </div>
                        </template>
                        <button type="button" @click="links.push({ text: '', url: '' })" class="text-sm font-medium text-blue-600 hover:text-blue-800">+ Add Link</button>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Footer Settings</button>
            </div>
        </form>
    </div>
</body>
</html>