<?php
include '../config.php';
include 'auth_check.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$section_to_edit = null;

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_id = $_POST['id'] ?? null;
    $existing_section = null;

    if ($section_id) {
        $stmt = $pdo->prepare("SELECT * FROM homepage_sections WHERE id = ?");
        $stmt->execute([$section_id]);
        $existing_section = $stmt->fetch();
    }

    // Get the section type from the hidden form input, or from the database if it's an existing section.
    $section_type = $_POST['section_type'] ?? $existing_section['section_type'] ?? null;
    $title = $_POST['title'] ?? $existing_section['title'] ?? null;
    // These columns were added in a previous step
    $caption = $_POST['caption'] ?? $existing_section['caption'] ?? null; 
    $button_text = $_POST['button_text'] ?? $existing_section['button_text'] ?? null; 

    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Process content based on type
    $content = $existing_section['content'] ?? null;
    $settings = $existing_section['settings'] ?? null;

    if ($section_type === 'hero_slider') {
        $content = json_encode($_POST['slides'] ?? []);
    } elseif ($section_type === 'brand_logos') {
        $content = null;
        $settings = null;
    } elseif ($section_type === 'product_grid') {
        $settings = json_encode([
            'grid_type' => $_POST['grid_type'] ?? 'new_arrivals',
            'limit' => (int) ($_POST['limit'] ?? 4)
        ]);
    } elseif ($section_type === 'two_column_layout') {
        $content = json_encode([
            'left_section_id' => $_POST['left_section_id'] ?? null,
            'right_section_id' => $_POST['right_section_id'] ?? null,
        ]);
    } elseif ($section_type === 'image_with_content') {
        $content = json_encode([
            'image_url' => $_POST['image_url'] ?? '',
            'button_link' => $_POST['button_link'] ?? ''
        ]);
        $settings = json_encode([
            'text_alignment' => $_POST['text_alignment'] ?? 'left',
            'background_color' => $_POST['background_color'] ?? 'white',
            'text_color' => $_POST['text_color'] ?? 'gray-800'
        ]);
    } elseif ($section_type === 'promo_banner_double') {
        $content = json_encode([
            'block_1' => [
                'image_url' => $_POST['image_url_1'] ?? '',
                'title' => $_POST['title_1'] ?? '',
                'caption' => $_POST['caption_1'] ?? '',
                'button_text' => $_POST['button_text_1'] ?? '',
                'button_link' => $_POST['button_link_1'] ?? '',
            ],
            'block_2' => [
                'image_url' => $_POST['image_url_2'] ?? '',
                'title' => $_POST['title_2'] ?? '',
                'caption' => $_POST['caption_2'] ?? '',
                'button_text' => $_POST['button_text_2'] ?? '',
                'button_link' => $_POST['button_link_2'] ?? '',
            ]
        ]);
        $settings = json_encode([
             'text_color_1' => $_POST['text_color_1'] ?? 'gray-800',
             'text_color_2' => $_POST['text_color_2'] ?? 'gray-800'
        ]);
        $title = $_POST['section_title'] ?? 'Promo Banners';
        $caption = null;
        $button_text = null;
    }

    if ($section_id) {
        // --- Update Existing Section ---
        $stmt = $pdo->prepare("UPDATE homepage_sections SET title = ?, caption = ?, button_text = ?, content = ?, settings = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$title, $caption, $button_text, $content, $settings, $is_active, $section_id]);
    } else {
        // --- Create New Section ---
        $stmt = $pdo->query("SELECT MAX(sort_order) FROM homepage_sections");
        $max_sort = $stmt->fetchColumn() ?? 0;
        $stmt = $pdo->prepare("INSERT INTO homepage_sections (section_type, title, caption, button_text, content, settings, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$section_type, $title, $caption, $button_text, $content, $settings, $is_active, $max_sort + 1]);
    }
    header("Location: homepage_editor.php");
    exit;
}

// --- Handle Actions (Delete, Move) ---
if ($action && $id) {
    // The 'move' action is now handled by update_section_order.php via an AJAX call from SortableJS.
    // We only need to handle delete here.
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM homepage_sections WHERE id = ?")->execute([$id]);
    }
    header("Location: homepage_editor.php");
    exit;
}

// --- Load section for editing ---
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM homepage_sections WHERE id = ?");
    $stmt->execute([$id]);
    $section_to_edit = $stmt->fetch();
}

$sections = $pdo->query("SELECT * FROM homepage_sections ORDER BY sort_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Homepage Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function openMediaLibrary(targetInputId) {
            const mediaUrl = `media.php?select=1&target=${targetInputId}`; // Note: Alpine might need a different approach for dynamic IDs
            window.open(mediaUrl, 'MediaLibrary', 'width=1200,height=800,scrollbars=yes');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold mb-6">Homepage Editor</h1>

        <?php if ($action === 'add' || $action === 'edit') :
            $type = $section_to_edit['section_type'] ?? $_GET['type'] ?? 'product_grid';
            $content = $section_to_edit ? json_decode($section_to_edit['content'], true) : [];
            $settings = $section_to_edit ? json_decode($section_to_edit['settings'], true) : [];

            // Helper function to render promo block fields to reduce repetition
            function render_promo_block_fields($block_num, $content, $settings) {
                $image_url = htmlspecialchars($content["block_{$block_num}"]['image_url'] ?? '');
                $title = htmlspecialchars($content["block_{$block_num}"]['title'] ?? '');
                $caption = htmlspecialchars($content["block_{$block_num}"]['caption'] ?? '');
                $button_text = htmlspecialchars($content["block_{$block_num}"]['button_text'] ?? '');
                $button_link = htmlspecialchars($content["block_{$block_num}"]['button_link'] ?? '');
                $text_color = htmlspecialchars($settings["text_color_{$block_num}"] ?? 'gray-800');
                echo "<div><label for='image_url_{$block_num}' class='block text-sm font-medium text-gray-700'>Image URL</label><div class='flex rounded-md shadow-sm'><input type='text' name='image_url_{$block_num}' id='image_url_{$block_num}' value='{$image_url}' class='block w-full rounded-none rounded-l-md border-gray-300'><button type='button' onclick=\"openMediaLibrary('image_url_{$block_num}')\" class='relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100'>Select</button></div></div>";
                echo "<div><label for='title_{$block_num}' class='block text-sm font-medium text-gray-700'>Title</label><input type='text' name='title_{$block_num}' id='title_{$block_num}' value='{$title}' class='mt-1 block w-full rounded-md border-gray-300'></div>";
                echo "<div><label for='caption_{$block_num}' class='block text-sm font-medium text-gray-700'>Caption/Subtitle</label><input type='text' name='caption_{$block_num}' id='caption_{$block_num}' value='{$caption}' class='mt-1 block w-full rounded-md border-gray-300'></div>";
                echo "<div><label for='button_text_{$block_num}' class='block text-sm font-medium text-gray-700'>Button Text</label><input type='text' name='button_text_{$block_num}' id='button_text_{$block_num}' value='{$button_text}' class='mt-1 block w-full rounded-md border-gray-300'></div>";
                echo "<div><label for='button_link_{$block_num}' class='block text-sm font-medium text-gray-700'>Button Link (URL)</label><input type='text' name='button_link_{$block_num}' id='button_link_{$block_num}' value='{$button_link}' class='mt-1 block w-full rounded-md border-gray-300'></div>";
                echo "<div><label for='text_color_{$block_num}' class='block text-sm font-medium text-gray-700'>Text Color</label><input type='text' name='text_color_{$block_num}' id='text_color_{$block_num}' value='{$text_color}' class='mt-1 block w-full rounded-md border-gray-300' placeholder='e.g., white, gray-800'></div>";
            }

        ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold mb-4"><?= $action === 'edit' ? 'Edit Section' : 'Add New Section' ?> - <span class="text-blue-600"><?= ucwords(str_replace('_', ' ', $type)) ?></span></h2>
            <form method="POST">
                <?php if ($action === 'edit') : ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                <input type="hidden" name="section_type" value="<?= $type ?>">

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-medium text-gray-700">Type: <strong class="text-blue-600"><?= ucwords(str_replace('_', ' ', $type)) ?></strong></span>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= ($section_to_edit['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <span class="ml-2 text-sm text-gray-600">Active</span>
                        </label>
                    </div>

                    <?php if ($type === 'hero_slider') : ?>
                        <div x-data='{ slides: <?= json_encode(array_values($content ?: [['image_url' => '', 'link_url' => '']])) ?> }' class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Slides</label>
                            <template x-for="(slide, index) in slides" :key="index">
                                <div class="flex items-center gap-2 p-2 border rounded-md bg-gray-50">
                                    <div class="cursor-move">☰</div>
                                    <div class="flex-grow space-y-2">
                                        <div class="flex rounded-md shadow-sm">
                                            <input type="text" :name="`slides[${index}][image_url]`" :id="`slide_image_${index}`" x-model="slide.image_url" placeholder="Image URL" class="block w-full rounded-none rounded-l-md border-gray-300">
                                            <button type="button" @click="openMediaLibrary(`slide_image_${index}`)" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Select</button>
                                        </div>
                                        <div>
                                            <input type="text" :name="`slides[${index}][link_url]`" x-model="slide.link_url" placeholder="Link URL (optional)" class="w-full rounded-md border-gray-300">
                                        </div>
                                    </div>
                                    <button type="button" @click="slides.splice(index, 1)" class="text-red-500 hover:text-red-700 font-semibold p-2">&times;</button>
                                </div>
                            </template>
                            <button type="button" @click="slides.push({ image_url: '', link_url: '' })" class="text-sm font-medium text-blue-600 hover:text-blue-800">+ Add Slide</button>
                        </div>

                    <?php elseif ($type === 'product_grid') : ?>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title (e.g., "New Arrivals")</label>
                            <input type="text" name="title" id="title" value="<?= htmlspecialchars($section_to_edit['title'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="grid_type" class="block text-sm font-medium text-gray-700">Product Grid Type</label>
                            <select name="grid_type" id="grid_type" class="mt-1 block w-full rounded-md border-gray-300">
                                <option value="new_arrivals" <?= ($settings['grid_type'] ?? '') === 'new_arrivals' ? 'selected' : '' ?>>New Arrivals</option>
                                <option value="on_sale" <?= ($settings['grid_type'] ?? '') === 'on_sale' ? 'selected' : '' ?>>On Sale</option>
                            </select>
                        </div>
                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700">Number of Products</label>
                            <input type="number" name="limit" id="limit" value="<?= htmlspecialchars($settings['limit'] ?? 4) ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                    <?php elseif ($type === 'brand_logos') : ?>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title (e.g., "Shop By Brand")</label>
                        <input type="text" name="title" id="title" value="<?= htmlspecialchars($section_to_edit['title'] ?? 'Shop By Brand') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                    <?php elseif ($type === 'two_column_layout'): ?>
                        <?php
                            // Fetch other sections to choose from, excluding other two-column layouts
                            $available_sections_stmt = $pdo->prepare("SELECT id, title, section_type FROM homepage_sections WHERE section_type != 'two_column_layout' AND id != ? ORDER BY sort_order");
                            $available_sections_stmt->execute([$id ?? 0]);
                            $available_sections = $available_sections_stmt->fetchAll();
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="left_section_id" class="block text-sm font-medium text-gray-700">Left Column Content</label>
                                <select name="left_section_id" id="left_section_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">-- None --</option><?php foreach($available_sections as $s): ?><option value="<?= $s['id'] ?>" <?= ($content['left_section_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title'] ?: ucwords(str_replace('_', ' ', $s['section_type']))) ?></option><?php endforeach; ?></select>
                            </div>
                            <div>
                                <label for="right_section_id" class="block text-sm font-medium text-gray-700">Right Column Content</label>
                                <select name="right_section_id" id="right_section_id" class="mt-1 block w-full rounded-md border-gray-300"><option value="">-- None --</option><?php foreach($available_sections as $s): ?><option value="<?= $s['id'] ?>" <?= ($content['right_section_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title'] ?: ucwords(str_replace('_', ' ', $s['section_type']))) ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                    <?php elseif ($type === 'image_with_content') : ?>
                        <div>
                            <label for="image_url" class="block text-sm font-medium text-gray-700">Image URL</label>
                            <div class="flex rounded-md shadow-sm">
                                <input type="text" name="image_url" id="image_url" value="<?= htmlspecialchars($content['image_url'] ?? '') ?>" class="block w-full rounded-none rounded-l-md border-gray-300">
                                <button type="button" onclick="openMediaLibrary('image_url')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Select</button>
                            </div>
                        </div>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title (e.g., "Co-Ord Set")</label>
                            <input type="text" name="title" id="title" value="<?= htmlspecialchars($section_to_edit['title'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="caption" class="block text-sm font-medium text-gray-700">Caption/Subtitle (e.g., "SAVE 30-50% ON")</label>
                            <input type="text" name="caption" id="caption" value="<?= htmlspecialchars($section_to_edit['caption'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="button_text" class="block text-sm font-medium text-gray-700">Button Text (e.g., "Shop Now")</label>
                            <input type="text" name="button_text" id="button_text" value="<?= htmlspecialchars($section_to_edit['button_text'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="button_link" class="block text-sm font-medium text-gray-700">Button Link (URL)</label>
                            <input type="text" name="button_link" id="button_link" value="<?= htmlspecialchars($content['button_link'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div>
                            <label for="text_alignment" class="block text-sm font-medium text-gray-700">Text Alignment</label>
                            <select name="text_alignment" id="text_alignment" class="mt-1 block w-full rounded-md border-gray-300">
                                <option value="left" <?= ($settings['text_alignment'] ?? 'left') === 'left' ? 'selected' : '' ?>>Left</option>
                                <option value="center" <?= ($settings['text_alignment'] ?? 'left') === 'center' ? 'selected' : '' ?>>Center</option>
                                <option value="right" <?= ($settings['text_alignment'] ?? 'left') === 'right' ? 'selected' : '' ?>>Right</option>
                            </select>
                        </div>
                        <div>
                            <label for="text_color" class="block text-sm font-medium text-gray-700">Text Color (Tailwind class, e.g., 'white', 'gray-800')</label>
                            <input type="text" name="text_color" id="text_color" value="<?= htmlspecialchars($settings['text_color'] ?? 'gray-800') ?>" class="mt-1 block w-full rounded-md border-gray-300" placeholder="e.g., white, gray-800">
                        </div>
                        <div>
                            <label for="background_color" class="block text-sm font-medium text-gray-700">Background Color (Tailwind class, e.g., 'white', 'gray-100')</label>
                            <input type="text" name="background_color" id="background_color" value="<?= htmlspecialchars($settings['background_color'] ?? 'white') ?>" class="mt-1 block w-full rounded-md border-gray-300" placeholder="e.g., white, gray-100">
                        </div>
                    <?php elseif ($type === 'promo_banner_double') : ?>
                        <div>
                            <label for="section_title" class="block text-sm font-medium text-gray-700">Section Title (for Admin)</label>
                            <input type="text" name="section_title" id="section_title" value="<?= htmlspecialchars($section_to_edit['title'] ?? 'Promo Banners') ?>" class="mt-1 block w-full rounded-md border-gray-300">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4 p-4 border rounded-md bg-gray-50">
                                <h3 class="font-semibold text-lg">Block 1</h3>
                                <?php render_promo_block_fields(1, $content, $settings); ?>
                            </div>
                            <div class="space-y-4 p-4 border rounded-md bg-gray-50">
                                <h3 class="font-semibold text-lg">Block 2</h3>
                                <?php render_promo_block_fields(2, $content, $settings); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex gap-4">
                    <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Save Section</button>
                    <a href="homepage_editor.php" class="bg-gray-200 text-gray-800 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold">Homepage Sections</h2>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open" class="bg-green-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-green-700">+ Add Section</button>
                    <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg z-10 border" style="display: none;">
                        <a href="?action=add&type=hero_slider" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Hero Slider</a>
                        <a href="?action=add&type=product_grid" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Product Grid</a>
                        <a href="?action=add&type=brand_logos" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Brand Logos</a>
                        <a href="?action=add&type=two_column_layout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Two Column Layout</a>
                        <a href="?action=add&type=image_with_content" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Image with Content (Single)</a>
                        <a href="?action=add&type=promo_banner_double" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Promo Banner (2-Column)</a>
                    </div>
                </div>
            </div>

            <div id="sections-list-container" class="space-y-3">
                <?php if (empty($sections)) : ?>
                    <p class="text-gray-500">No sections yet. Click "Add Section" to get started.</p>
                <?php endif; ?>
                <?php foreach ($sections as $section) : ?>
                    <div class="section-item flex items-center justify-between p-4 border rounded-lg <?= $section['is_active'] ? 'bg-white' : 'bg-gray-100' ?>" data-id="<?= $section['id'] ?>">
                        <div class="flex items-center gap-4">
                            <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600" title="Drag to reorder">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-lg"><?= htmlspecialchars($section['title'] ?: ucwords(str_replace('_', ' ', $section['section_type']))) ?></p>
                                <p class="text-sm text-gray-500">Type: <?= $section['section_type'] ?> | Status: <?= $section['is_active'] ? 'Active' : 'Inactive' ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="?action=edit&id=<?= $section['id'] ?>" class="text-blue-600 font-semibold">Edit</a>
                            <a href="?action=delete&id=<?= $section['id'] ?>" onclick="return confirm('Are you sure?')" class="text-red-600 font-semibold">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('sections-list-container');
    if (container) {
        new Sortable(container, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function () {
                const order = Array.from(container.children).map(item => item.dataset.id);
                
                fetch('update_section_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: order })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Order saved:', data.message);
                })
                .catch(error => console.error('Error saving order:', error));
            }
        });
    }
});
</script>
</body>

</html>