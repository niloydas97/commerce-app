<?php
include 'config.php';
$handle = $_GET['handle'] ?? '';

if (!$handle) {
    die("Product not found.");
}

// 1. Fetch the main product by its handle
$stmt = $pdo->prepare("SELECT * FROM products WHERE handle = ? AND status = 'active'");
$stmt->execute([$handle]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}
$product_id = $product['id'];

// 2. Fetch all images for this product
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY position");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// 3. Fetch all variants
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();

// 4. Fetch tags
$stmt = $pdo->prepare("
    SELECT t.name FROM tags t
    JOIN product_tags pt ON t.id = pt.tag_id
    WHERE pt.product_id = ?
");
$stmt->execute([$product_id]);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

$main_image = $images[0]['image_url'] ?? 'images/placeholder.jpg';
$main_alt = $images[0]['alt_text'] ?? $product['name'];

// 5. Fetch related products (from the same category, excluding the current product)
$related_products = [];
if (!empty($product['product_category'])) {
    $stmt = $pdo->prepare("
        SELECT p.handle, p.name, pi.image_url, pv.price
        FROM products p
        LEFT JOIN (SELECT product_id, image_url FROM product_images WHERE position = 1) pi ON p.id = pi.product_id
        LEFT JOIN (SELECT product_id, MIN(price) as price FROM product_variants GROUP BY product_id) pv ON p.id = pv.product_id
        WHERE p.product_category = ? AND p.id != ? AND p.status = 'active'
        LIMIT 8
    ");
    $stmt->execute([$product['product_category'], $product_id]);
    $related_products = $stmt->fetchAll();
}
// --- Fetch Favicon ---
$favicon_url = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'header_favicon_url'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['seo_title'] ?: $product['name']); ?></title>
    <?php if ($favicon_url): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
    <?php endif; ?>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <meta name="description" content="<?php echo htmlspecialchars($product['seo_description'] ?? ''); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <p class="mb-4"><a href="products.php" class="text-blue-600 hover:underline">&larr; Back to all products</a></p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 bg-white p-6 rounded-lg shadow-sm">
            <div>
                <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($main_alt); ?>" class="w-full rounded-lg shadow-md mb-4" id="main-image">
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach ($images as $index => $image): ?>
                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($image['alt_text'] ?? $product['name']); ?>"
                         class="thumb w-full h-auto aspect-square object-cover rounded-md cursor-pointer border-2 <?php if ($index == 0) echo 'border-blue-500'; else echo 'border-transparent'; ?>" 
                         onclick="changeImage('<?php echo htmlspecialchars($image['image_url']); ?>', this)">
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div>
                <p class="text-sm text-gray-500 mb-1"><?php echo htmlspecialchars($product['vendor']); ?></p>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="my-4">
                    <span class="text-3xl font-bold text-red-600" id="price-display"></span>
                    <span class="ml-2 text-xl text-gray-500 line-through" id="compare-price-display"></span>
                </div>

                <form id="add-to-cart-form" action="cart.php?action=add" method="POST">
                    <div class="space-y-4">
                        <?php if (count($variants) > 1): ?>
                        <div>
                            <label for="variant-select" class="block text-sm font-medium text-gray-700">Select Color</label>
                            <select name="variant_id" id="variant-select" required onchange="updateUI()" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <?php foreach ($variants as $variant): ?>
                                <option value="<?php echo $variant['id']; ?>" 
                                    data-price="<?php echo $variant['price']; ?>"
                                    data-compare-price="<?php echo $variant['compare_at_price']; ?>"
                                    data-stock="<?php echo $variant['stock']; ?>"
                                    data-sku="<?php echo htmlspecialchars($variant['sku']); ?>" 
                                    data-image="<?php echo htmlspecialchars($variant['image_url'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($variant['name']); ?> (Stock: <?php echo $variant['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="variant_id" value="<?php echo $variants[0]['id']; ?>" 
                                data-price="<?php echo $variants[0]['price']; ?>"
                                data-compare-price="<?php echo $variants[0]['compare_at_price']; ?>"
                                data-stock="<?php echo $variants[0]['stock']; ?>"
                                data-sku="<?php echo htmlspecialchars($variants[0]['sku']); ?>"
                                id="variant-select"
                            >
                        <?php endif; ?>

                        <div id="sku-display" class="text-xs text-gray-500"></div>

                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                            <div class="mt-1 flex items-center border border-gray-300 rounded-md w-32">
                                <button type="button" onclick="updateQuantity(-1)" class="px-3 py-1 text-lg text-gray-600 hover:bg-gray-100 rounded-l-md">-</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" required onchange="updateUI()" class="w-full text-center border-l border-r focus:outline-none focus:ring-0">
                                <button type="button" onclick="updateQuantity(1)" class="px-3 py-1 text-lg text-gray-600 hover:bg-gray-100 rounded-r-md">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-4">
                        <button type="submit" id="add-to-cart-btn" class="flex-1 inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed">Add to Cart</button>
                        <button type="submit" id="buy-now-btn" formaction="cart.php?action=buy_now" class="flex-1 inline-flex items-center justify-center rounded-md border border-transparent bg-orange-500 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-orange-600 disabled:bg-gray-400 disabled:cursor-not-allowed">Buy Now</button>
                    </div>
                </form>

                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900">Description</h3>
                    <div class="mt-4 prose prose-sm max-w-none text-gray-600">
                    <?php echo $product['description']; // Allow HTML from Shopify ?>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($related_products)): ?>
        <div class="mt-12">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 mb-6">Related Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($related_products as $related): ?>
                    <a href="product.php?handle=<?php echo $related['handle']; ?>" class="group block bg-white rounded-lg shadow-sm overflow-hidden transition-shadow duration-300 hover:shadow-md">
                        <div class="relative overflow-hidden aspect-square">
                            <img src="<?php echo htmlspecialchars($related['image_url'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <h3 class="text-sm font-medium text-gray-800 truncate group-hover:text-blue-600"><?php echo htmlspecialchars($related['name']); ?></h3>
                            <p class="mt-2 text-lg font-semibold text-gray-900">৳<?php echo htmlspecialchars(number_format($related['price'] ?? 0, 2)); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function changeImage(src, thumbElement) {
        document.getElementById('main-image').src = src;
        document.querySelectorAll('.thumb').forEach(t => t.classList.remove('border-blue-500', 'border-2'));
        if (thumbElement) {
            thumbElement.classList.add('border-blue-500', 'border-2');
        }
    }

    function updateQuantity(change) {
        const quantityInput = document.getElementById('quantity');
        const variantElement = document.getElementById('variant-select');
        
        let option;
        if (variantElement.tagName === 'SELECT') {
            option = variantElement.options[variantElement.selectedIndex];
        } else {
            option = variantElement; // It's the hidden input
        }
        const stock = parseInt(option.dataset.stock, 10);

        let currentQuantity = parseInt(quantityInput.value, 10);
        currentQuantity += change;

        if (currentQuantity < 1) {
            currentQuantity = 1;
        }
        if (currentQuantity > stock) {
            currentQuantity = stock;
        }
        quantityInput.value = currentQuantity;
        updateUI();
    }

    function updateUI() {
        const variantElement = document.getElementById('variant-select');
        const quantity = parseInt(document.getElementById('quantity').value, 10) || 1;
        
        let option;
        if (variantElement.tagName === 'SELECT') {
            option = variantElement.options[variantElement.selectedIndex];
        } else {
            option = variantElement; // It's the hidden input
        }

        const stock = parseInt(option.dataset.stock, 10);
        const price = parseFloat(option.dataset.price);
        const comparePrice = parseFloat(option.dataset.comparePrice);
        const sku = option.dataset.sku;
        const variantImage = option.dataset.image;

        // Update price display
        const priceDisplay = document.getElementById('price-display');
        const comparePriceDisplay = document.getElementById('compare-price-display');

        // Get buttons
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        const buyNowBtn = document.getElementById('buy-now-btn');

        // Also cap quantity here in case user types it in
        if (quantity > stock) {
            document.getElementById('quantity').value = stock;
            quantity = stock;
        }
        
        if (!isNaN(price)) {
            priceDisplay.textContent = `৳${(price * quantity).toFixed(2)}`;
        } else {
            priceDisplay.textContent = '';
        }

        if (!isNaN(comparePrice) && comparePrice > price) {
            comparePriceDisplay.textContent = `৳${(comparePrice * quantity).toFixed(2)}`;
            comparePriceDisplay.style.display = 'inline';
        } else {
            comparePriceDisplay.style.display = 'none';
        }

        // Update SKU
        document.getElementById('sku-display').textContent = sku ? `SKU: ${sku}` : '';

        // Update image if variant has one
        if (variantImage && variantImage !== 'null' && variantImage.length > 0) {
            changeImage(variantImage, null);
        }

        // Enable or disable buttons based on stock
        if (stock > 0) {
            addToCartBtn.disabled = false;
            buyNowBtn.disabled = false;
        } else {
            addToCartBtn.disabled = true;
            buyNowBtn.disabled = true;
        }
    }

    // Initial UI update on page load
    document.addEventListener('DOMContentLoaded', updateUI);
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>