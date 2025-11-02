<?php
include '../config.php';
include 'auth_check.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$product_to_edit = null;
$product_tags = '';
$product_variants = [];
$product_images = [];

// --- Handle Form Submission (Create or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['id'] ?? null;
    
    // Sanitize handle or create from name
    $handle = !empty($_POST['handle']) ? $_POST['handle'] : $_POST['name'];
    $handle = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $handle)));
    
    $params = [
        ':handle' => $handle,
        ':name' => $_POST['name'],
        ':desc' => $_POST['description'],
        ':vendor' => $_POST['vendor'],
        ':cat' => $_POST['product_category'],
        ':type' => $_POST['product_type'],
        ':status' => $_POST['status'],
        ':seo_title' => $_POST['seo_title'],
        ':seo_desc' => $_POST['seo_description'],
        ':video_url' => $_POST['video_url'],
        ':g_cat' => $_POST['google_product_category'],
        ':g_gender' => $_POST['google_gender'],
        ':g_age' => $_POST['google_age_group'],
        ':g_mpn' => $_POST['google_mpn'],
        ':g_cond' => $_POST['google_condition']
    ];
    
    if ($product_id) {
        // --- Update Existing Product ---
        $params[':id'] = $product_id;
        $stmt = $pdo->prepare("
            UPDATE products SET 
                handle = :handle, name = :name, description = :desc, vendor = :vendor, 
                product_category = :cat, product_type = :type, status = :status, video_url = :video_url,
                seo_title = :seo_title, seo_description = :seo_desc, 
                google_product_category = :g_cat, google_gender = :g_gender, 
                google_age_group = :g_age, google_mpn = :g_mpn, google_condition = :g_cond
            WHERE id = :id
        ");
    } else {
        // --- Create New Product ---
        $stmt = $pdo->prepare("
            INSERT INTO products (
                handle, name, description, vendor, product_category, product_type, status, video_url,
                seo_title, seo_description, google_product_category, google_gender, 
                google_age_group, google_mpn, google_condition
            ) VALUES (
                :handle, :name, :desc, :vendor, :cat, :type, :status, :video_url,
                :seo_title, :seo_desc, :g_cat, :g_gender, :g_age, :g_mpn, :g_cond
            )
        ");
    }

    $stmt->execute($params);

    if (!$product_id) {
        $product_id = $pdo->lastInsertId();
    }

    // --- Handle Tags ---
    $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$product_id]);
    if (!empty($_POST['tags'])) {
        $tags = array_map('trim', explode(',', $_POST['tags']));
        foreach ($tags as $tag_name) {
            $tag_name = trim($tag_name);
            if (empty($tag_name)) continue;
            
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tag_name]);
            $tag_id = $stmt->fetchColumn();
            
            if (!$tag_id) {
                $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                $stmt->execute([$tag_name]);
                $tag_id = $pdo->lastInsertId();
            }
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$product_id, $tag_id]);
        }
    }
    
    // --- Handle Variants ---
    $variant_ids_to_keep = [];
    if (isset($_POST['variants'])) {
        foreach ($_POST['variants'] as $v_data) {
            $variant_id = $v_data['id'] ?? null;
            $variant_params = [
                ':product_id' => $product_id,
                ':name' => $v_data['color'], // Simplified name
                ':option1_name' => 'Color',
                ':option1_value' => $v_data['color'],
                ':compare_at_price' => (float)$v_data['compare_at_price'] > 0 ? (float)$v_data['compare_at_price'] : null,
                ':price' => (float)$v_data['price'],
                ':stock' => (int)$v_data['stock'],
                ':sku' => $v_data['sku'],
                ':image_url' => $v_data['image_url'] ?? null
            ];

            if ($variant_id && $variant_id !== 'new') {
                // Update existing variant
                $variant_params[':id'] = $variant_id;
                $v_stmt = $pdo->prepare("UPDATE product_variants SET name=:name, option1_name=:option1_name, option1_value=:option1_value, price=:price, compare_at_price=:compare_at_price, stock=:stock, sku=:sku, image_url=:image_url WHERE id=:id AND product_id=:product_id");
                $v_stmt->execute($variant_params);
                $variant_ids_to_keep[] = $variant_id;
            } else {
                // Add new variant
                $v_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, name, option1_name, option1_value, price, compare_at_price, stock, sku, image_url) VALUES (:product_id, :name, :option1_name, :option1_value, :price, :compare_at_price, :stock, :sku, :image_url)");
                $v_stmt->execute($variant_params);
                $variant_ids_to_keep[] = $pdo->lastInsertId();
            }
        }
    }
    // Delete variants that were removed from the form
    if ($product_id) {
        $v_delete_stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN (" . implode(',', array_fill(0, count($variant_ids_to_keep), '?')) . ")");
        if (!empty($variant_ids_to_keep)) {
            $v_delete_stmt->execute(array_merge([$product_id], $variant_ids_to_keep));
        } else {
             $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$product_id]);
        }
    }

    // --- Handle Image Uploads ---
    if (isset($_FILES['images'])) {
        $upload_dir = '../images/';
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = 'prod_' . $product_id . '_' . uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $dest_path = $upload_dir . $file_name;
                if (move_uploaded_file($tmp_name, $dest_path)) {
                    $image_url = 'images/' . $file_name;
                    // Get max position and add 1
                    $pos_stmt = $pdo->prepare("SELECT MAX(position) FROM product_images WHERE product_id = ?");
                    $pos_stmt->execute([$product_id]);
                    $position = ($pos_stmt->fetchColumn() ?? 0) + 1;

                    $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, position) VALUES (?, ?, ?)");
                    $img_stmt->execute([$product_id, $image_url, $position]);
                }
            }
        }
    }

    // --- Handle Images from Media Library ---
    if (!empty($_POST['media_images'])) {
        $media_images = json_decode($_POST['media_images'], true);
        if (is_array($media_images)) {
            foreach ($media_images as $image_url) {
                $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, position) SELECT ?, ?, COALESCE(MAX(position), 0) + 1 FROM product_images WHERE product_id = ?");
                $img_stmt->execute([$product_id, $image_url, $product_id]);
            }
        }
    }

    // --- Handle Image Deletion ---
    if (isset($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $image_id_to_delete) {
            // Optional: also delete file from server
            $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")->execute([$image_id_to_delete, $product_id]);
        }
    }

    header('Location: products.php');
    exit;
}

// --- Handle Delete ---
if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM product_metafields WHERE product_id = ?")->execute([$id]);
    header('Location: products.php');
    exit;
}

// --- Load Product for Editing ---
if (($action === 'edit' && $id) || $action === 'add') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product_to_edit = $stmt->fetch();
    
    // Load related data
    $stmt = $pdo->prepare("SELECT t.name FROM tags t JOIN product_tags pt ON t.id = pt.tag_id WHERE pt.product_id = ?");
    $stmt->execute([$id]);
    $tags_array = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $product_tags = implode(', ', $tags_array);

    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
    $stmt->execute([$id]);
    $product_variants = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY position");
    $stmt->execute([$id]);
    $product_images = $stmt->fetchAll();
}

// --- Fetch data for dropdowns ---
$all_vendors = [];
try {
    $all_vendors = $pdo->query("SELECT name FROM vendors ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // vendors table might not exist yet.
}
$all_categories = [];
try {
    $all_categories = $pdo->query("SELECT name FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Table might not exist yet, so we can ignore this error and show an empty list.
    // The user can create categories from the "Manage Categories" page.
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Products</title>
    <script>
        function openMediaLibrary(targetInputId, isMultiSelect = false) {
            let mediaUrl = `media.php?select=1`;
            if (isMultiSelect) {
                mediaUrl += `&multi=1`;
            } else {
                mediaUrl += `&target=${targetInputId}`;
            }
            window.open(mediaUrl, 'MediaLibrary', 'width=1200,height=800,scrollbars=yes');
        }
    </script>
    <script>
        // This function will be called by the media library popup
        function handleMediaSelection(imageUrls) {
            const container = document.getElementById('new-media-images');
            const hiddenInput = document.getElementById('media-images-input');
            let currentImages = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];

            imageUrls.forEach(url => {
                if (!currentImages.includes(url)) {
                    currentImages.push(url);
                    container.innerHTML += `<div class="flex items-center gap-2 text-sm"><img src="../${url}" class="h-12 w-12 rounded-md object-cover"><span class="truncate">${url.split('/').pop()}</span></div>`;
                }
            });
            hiddenInput.value = JSON.stringify(currentImages);
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Manage Products</h1>

        <?php if ($action === 'edit' || $action === 'add'): ?>
        <h2 class="text-2xl font-semibold text-gray-900 mb-4"><?php echo $action === 'edit' ? 'Edit Product' : 'Add New Product'; ?></h2>
        
        <form action="products.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-sm mb-8">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $product_to_edit['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div>
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 p-6 rounded-lg">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Main Details</h3>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product_to_edit['name'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div class="form-group">
                                    <label for="handle" class="block text-sm font-medium text-gray-700">Handle (URL Slug)</label>
                                    <input type="text" name="handle" id="handle" value="<?php echo htmlspecialchars($product_to_edit['handle'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea name="description" id="description" rows="10" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($product_to_edit['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 p-6 rounded-lg">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Images & Video</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="images" class="block text-sm font-medium text-gray-700">Upload New Images</label>
                                    <input type="file" name="images[]" id="images" multiple accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                                <div>
                                    <button type="button" onclick="openMediaLibrary(null, true)" class="w-full inline-flex justify-center rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">Select from Media Library</button>
                                    <input type="hidden" name="media_images" id="media-images-input">
                                    <div id="new-media-images" class="mt-2 space-y-2"></div>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                            <?php foreach($product_images as $image): ?>
                                <div class="flex items-center gap-4 text-sm">
                                    <img src="../<?php echo htmlspecialchars($image['image_url']); ?>" class="h-12 w-12 rounded-md object-cover">
                                    <span class="flex-grow truncate"><?php echo basename($image['image_url']); ?></span>
                                    <label class="flex items-center gap-1 text-red-600 cursor-pointer"><input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500"> Delete</label>
                                </div>
                            <?php endforeach; ?>
                            </div>
                            <hr class="my-6 border-gray-200">
                            <div>
                                <label for="video_url" class="block text-sm font-medium text-gray-700">Video URL (e.g., YouTube)</label>
                                <input type="text" name="video_url" id="video_url" value="<?php echo htmlspecialchars($product_to_edit['video_url'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 p-6 rounded-lg">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Variants (Color-based)</h3>
                            <div id="variants-list" class="space-y-3">
                            <?php foreach($product_variants as $index => $variant): ?>
                                <div class="variant-row grid grid-cols-1 md:grid-cols-6 gap-3 items-center">
                                    <input type="hidden" name="variants[<?php echo $index; ?>][id]" value="<?php echo $variant['id']; ?>">
                                    <div class="form-group">
                                        <label for="variant_image_<?php echo $index; ?>" class="text-xs text-gray-500 md:hidden">Image</label>
                                        <div class="flex rounded-md shadow-sm">
                                            <input type="text" 
                                                   name="variants[<?php echo $index; ?>][image_url]" 
                                                   id="variant_image_<?php echo $index; ?>" 
                                                   value="<?php echo htmlspecialchars($variant['image_url'] ?? ''); ?>" 
                                                   placeholder="Variant Image URL"
                                                   class="block w-full rounded-none rounded-l-md border-gray-300 text-sm">
                                            <button type="button" onclick="openMediaLibrary('variant_image_<?php echo $index; ?>')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                                Select
                                            </button>
                                        </div>
                                    </div>
                                    <div class="md:col-span-4 grid grid-cols-1 sm:grid-cols-5 gap-3">
                                        <input type="text" name="variants[<?php echo $index; ?>][color]" placeholder="Color" value="<?php echo htmlspecialchars($variant['option1_value'] ?? ''); ?>" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="number" name="variants[<?php echo $index; ?>][price]" placeholder="Price" step="0.01" value="<?php echo htmlspecialchars($variant['price'] ?? ''); ?>" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="number" name="variants[<?php echo $index; ?>][compare_at_price]" placeholder="Compare Price" step="0.01" value="<?php echo htmlspecialchars($variant['compare_at_price'] ?? ''); ?>" class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="number" name="variants[<?php echo $index; ?>][stock]" placeholder="Stock" value="<?php echo htmlspecialchars($variant['stock'] ?? '0'); ?>" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="text" name="variants[<?php echo $index; ?>][sku]" placeholder="SKU" value="<?php echo htmlspecialchars($variant['sku'] ?? ''); ?>" class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <button type="button" onclick="this.parentElement.remove()" class="text-sm text-red-600 hover:text-red-800 justify-self-start md:justify-self-center">Remove</button>
                                </div>
                            <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-variant-btn" class="mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">+ Add Variant</button>
                        </div>

                        <div class="bg-white border border-gray-200 p-6 rounded-lg">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Search Engine Optimization</h3>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="seo_title" class="block text-sm font-medium text-gray-700">SEO Title</label>
                                    <input type="text" name="seo_title" id="seo_title" value="<?php echo htmlspecialchars($product_to_edit['seo_title'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div class="form-group">
                                    <label for="seo_description" class="block text-sm font-medium text-gray-700">SEO Description</label>
                                    <textarea name="seo_description" id="seo_description" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?php echo htmlspecialchars($product_to_edit['seo_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="bg-white border border-gray-200 p-6 rounded-lg">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Organization</h3>
                        <div class="space-y-4">
                            <div class="form-group">
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="active" <?php echo ($product_to_edit['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="draft" <?php echo ($product_to_edit['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vendor" class="block text-sm font-medium text-gray-700">Vendor</label>
                                <input list="vendor-list" name="vendor" id="vendor" value="<?php echo htmlspecialchars($product_to_edit['vendor'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <datalist id="vendor-list">
                                    <?php foreach ($all_vendors as $vendor_name): ?>
                                        <option value="<?php echo htmlspecialchars($vendor_name); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="product_category" class="block text-sm font-medium text-gray-700">Product Category</label>
                                <input list="category-list" name="product_category" id="product_category" value="<?php echo htmlspecialchars($product_to_edit['product_category'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <datalist id="category-list">
                                    <?php foreach ($all_categories as $category_name): ?>
                                        <option value="<?php echo htmlspecialchars($category_name); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="product_type" class="block text-sm font-medium text-gray-700">Product Type</label>
                                <input type="text" name="product_type" id="product_type" value="<?php echo htmlspecialchars($product_to_edit['product_type'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label for="tags" class="block text-sm font-medium text-gray-700">Tags (comma-separated)</label>
                                <input type="text" name="tags" id="tags" value="<?php echo htmlspecialchars($product_tags); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 p-6 rounded-lg">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Google Shopping</h3>
                        <div class="space-y-4 form-group">
                            <div>
                                <label for="google_product_category" class="block text-sm font-medium text-gray-700">Google Product Category</label>
                                <input type="text" name="google_product_category" id="google_product_category" value="<?php echo htmlspecialchars($product_to_edit['google_product_category'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="google_gender" class="block text-sm font-medium text-gray-700">Gender</label>
                                <input type="text" name="google_gender" id="google_gender" value="<?php echo htmlspecialchars($product_to_edit['google_gender'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="google_age_group" class="block text-sm font-medium text-gray-700">Age Group</label>
                                <input type="text" name="google_age_group" id="google_age_group" value="<?php echo htmlspecialchars($product_to_edit['google_age_group'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="google_mpn" class="block text-sm font-medium text-gray-700">MPN</label>
                                <input type="text" name="google_mpn" id="google_mpn" value="<?php echo htmlspecialchars($product_to_edit['google_mpn'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="google_condition" class="block text-sm font-medium text-gray-700">Condition</label>
                                <input type="text" name="google_condition" id="google_condition" value="<?php echo htmlspecialchars($product_to_edit['google_condition'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4 mt-6">
            <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <?php echo $action === 'edit' ? 'Update Product' : 'Save Product'; ?>
            </button>
            <a href="products.php" class="text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

        <div class="mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-gray-900">All Products</h2>
                <a href="products.php?action=add" class="inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">+ Add New Product</a>
            </div>
            <div class="overflow-x-auto bg-white rounded-lg shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variants</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $stmt = $pdo->query("
                            SELECT p.id, p.name, p.vendor, p.status, pi.image_url, (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variant_count
                            FROM products p
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.position = 1
                            ORDER BY p.id DESC
                        ");
                        while ($product = $stmt->fetch()) {
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="../<?php echo htmlspecialchars($product['image_url'] ?? 'images/placeholder.svg'); ?>" alt="" class="h-10 w-10 rounded-md object-cover">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['vendor']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $product['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo htmlspecialchars($product['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['variant_count']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="products.php?action=edit&id=<?php echo $product['id']; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                    <a href="products.php?action=delete&id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure? This will delete the product and all its variants and images.');" class="text-red-600 hover:text-red-900 ml-4">Delete</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('add-variant-btn').addEventListener('click', function() {
        const list = document.getElementById('variants-list');
        const index = Date.now(); // Use a unique index to prevent collisions
        const row = document.createElement('div');
        row.className = 'variant-row grid grid-cols-1 md:grid-cols-6 gap-3 items-center';
        row.innerHTML = `
            <input type="hidden" name="variants[${index}][id]" value="new">
            <div class="form-group">
                <label for="variant_image_${index}" class="text-xs text-gray-500 md:hidden">Image URL</label>
                <div class="flex rounded-md shadow-sm">
                    <input type="text" 
                           name="variants[${index}][image_url]" 
                           id="variant_image_${index}" 
                           placeholder="Variant Image URL"
                           class="block w-full rounded-none rounded-l-md border-gray-300 text-sm">
                    <button type="button" onclick="openMediaLibrary('variant_image_${index}')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100">
                        Select
                    </button>
                </div>
            </div>
            <div class="md:col-span-4 grid grid-cols-1 sm:grid-cols-5 gap-3">
                <input type="text" name="variants[${index}][color]" placeholder="Color" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="number" name="variants[${index}][price]" placeholder="Price" step="0.01" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="number" name="variants[${index}][compare_at_price]" placeholder="Compare Price" step="0.01" class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="number" name="variants[${index}][stock]" placeholder="Stock" value="0" required class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <input type="text" name="variants[${index}][sku]" placeholder="SKU" class="block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-sm text-red-600 hover:text-red-800 justify-self-start md:justify-self-center">Remove</button>
        `;
        list.appendChild(row);
    });
    </script>
</body>
</html>