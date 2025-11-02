<?php
include '../config.php';
include 'auth_check.php';

$message = '';
$error = '';
$upload_dir = '../images/';

// --- Handle Image Deletion ---
if (isset($_GET['delete'])) {
    $file_to_delete = realpath($upload_dir . basename($_GET['delete']));
    // Security check: ensure the file is within the upload directory
    if ($file_to_delete && strpos($file_to_delete, realpath($upload_dir)) === 0) {
        if (unlink($file_to_delete)) {
            $message = "Image deleted successfully.";
        } else {
            $error = "Could not delete image.";
        }
    } else {
        $error = "Invalid file path.";
    }
}

// --- Handle Image Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
            if (in_array($_FILES['images']['type'][$key], $allowed_types)) {
                $file_name = 'media_' . uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $dest_path = $upload_dir . $file_name;
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $message = "Image(s) uploaded successfully.";
                } else {
                    $error = "Failed to move uploaded file.";
                }
            } else {
                $error = "Invalid file type: " . $_FILES['images']['type'][$key];
            }
        }
    }
}

// --- Scan for images ---
$files = scandir($upload_dir, SCANDIR_SORT_DESCENDING);
$images = array_filter($files, function($file) {
    return !is_dir('../images/' . $file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
});

$is_select_mode = isset($_GET['select']);
$target_input_id = htmlspecialchars($_GET['target'] ?? '');
$multi_select = isset($_GET['multi']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Media Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    <script>
        function selectImage(targetInputId, imageUrl, isMultiSelect = false) {
            if (window.opener && !window.opener.closed) {
                if (isMultiSelect) {
                    // For product page multi-select
                    if (typeof window.opener.handleMediaSelection === 'function') {
                        const selectedUrls = Array.from(document.querySelectorAll('input[name="selected_images[]"]:checked')).map(cb => cb.value);
                        if (selectedUrls.length > 0) {
                            window.opener.handleMediaSelection(selectedUrls);
                        }
                        window.close();
                    }
                } else {
                    // For single-select fields (header, variants, etc.)
                    const targetInput = window.opener.document.getElementById(targetInputId);
                    targetInput.value = imageUrl;
                    // Trigger 'input' event for frameworks like Alpine.js
                    targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                window.close();
            } else {
                alert("Could not find the original window.");
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8" x-data="{ selectedCount: 0 }">
        <?php if (!$is_select_mode): ?>
            <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
            <h1 class="text-3xl font-bold mb-6">Media Library</h1>
        <?php else: ?>
            <h1 class="text-3xl font-bold mb-6">Select an Image</h1>
        <?php endif; ?>

        <?php if ($message): ?><div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"><?php echo $error; ?></div><?php endif; ?>

        <!-- Upload Form -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-8">
            <h2 class="text-xl font-semibold mb-4">Upload New Images</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="images[]" multiple required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <button type="submit" class="mt-4 inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700">Upload & Refresh</button>
            </form>
        </div>

        <!-- Image Grid -->
        <?php if ($multi_select): ?>
        <div class="sticky top-0 bg-white/80 backdrop-blur-sm p-4 mb-4 rounded-lg shadow-md z-10 flex justify-between items-center">
            <span class="font-semibold text-gray-700"><span x-text="selectedCount">0</span> image(s) selected</span>
            <button @click="selectImage(null, null, true)" :disabled="selectedCount === 0" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg disabled:bg-gray-400">Add Selected Images</button>
        </div>
        <?php endif; ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
            <?php if (empty($images)): ?>
                <p class="col-span-full text-center text-gray-500">No images found. Upload some!</p>
            <?php endif; ?>
            <?php foreach ($images as $image): 
                $image_url = 'images/' . $image;
                $full_image_url = '/' . $image_url;
            ?>
                <div class="relative group bg-white rounded-lg shadow-sm overflow-hidden">
                    <label class="aspect-square w-full block cursor-pointer">
                        <img src="../<?php echo $image_url; ?>" alt="<?php echo $image; ?>" class="w-full h-full object-cover peer-checked:ring-4 ring-blue-500">
                        <?php if ($multi_select): ?>
                            <input type="checkbox" name="selected_images[]" value="<?php echo $full_image_url; ?>" @change="selectedCount = $event.target.checked ? selectedCount + 1 : selectedCount - 1" class="absolute top-2 left-2 h-5 w-5 rounded text-blue-600 peer">
                        <?php endif; ?>
                    </label>
                    <?php if ($is_select_mode && !$multi_select): ?>
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="selectImage('<?php echo $target_input_id; ?>', '<?php echo $full_image_url; ?>')" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg text-sm">Select</button>
                        </div>
                    <?php else: ?>
                        <div class="absolute top-1 right-1">
                            <a href="?delete=<?php echo urlencode($image); ?>" onclick="return confirm('Are you sure you want to delete this image?')" class="bg-red-600 text-white rounded-full p-1.5 inline-flex items-center justify-center hover:bg-red-700 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>