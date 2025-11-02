<?php
include 'config.php';

// Get the search term from the query string, default to empty
$term = $_GET['q'] ?? '';

// Only search if the term is at least 2 characters long to avoid excessive queries
if (strlen($term) < 2) {
    // Return nothing to hide the container
    exit;
}

// Prepare a statement to find products matching the term
$stmt = $pdo->prepare("
    SELECT p.handle, p.name, pi.image_url 
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.position = 1
    WHERE p.status = 'active' AND p.name LIKE ?
    ORDER BY p.name
    LIMIT 6
");

$stmt->execute(['%' . $term . '%']);
$products = $stmt->fetchAll();

if (empty($products)) {
    echo '<p class="p-4 text-sm text-gray-500">No products found.</p>';
    exit;
}

echo '<ul>';
foreach ($products as $product) {
    $imageUrl = htmlspecialchars($product['image_url'] ?? 'images/placeholder.jpg');
    $productName = htmlspecialchars($product['name']);
    $productHandle = htmlspecialchars($product['handle']);
    echo <<<HTML
        <li class="border-b border-gray-100 last:border-b-0">
            <a href="product.php?handle={$productHandle}" class="flex items-center p-3 gap-3 hover:bg-gray-50">
                <img src="{$imageUrl}" alt="{$productName}" class="h-10 w-10 rounded-md object-cover">
                <span class="text-sm font-medium text-gray-800">{$productName}</span>
            </a>
        </li>
    HTML;
}
echo '</ul>';
?>