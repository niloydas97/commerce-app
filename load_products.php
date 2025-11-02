<?php
include 'config.php';

// --- Configuration ---
$limit = 12; // Number of products to load per page

// --- Get parameters from the request ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_term = $_GET['search'] ?? '';
$selected_category = $_GET['category'] ?? '';
$selected_vendor = $_GET['vendor'] ?? '';

// --- Build the product query dynamically ---
$sql = "
    SELECT
        p.handle,
        p.name,
        pi.image_url, 
        pv.price,
        vs.total_stock
    FROM products p
    LEFT JOIN (
        SELECT product_id, image_url FROM product_images WHERE position = 1
    ) pi ON p.id = pi.product_id
    LEFT JOIN (
        SELECT product_id, MIN(price) as price FROM product_variants GROUP BY product_id
    ) pv ON p.id = pv.product_id
    LEFT JOIN (
        SELECT product_id, SUM(stock) as total_stock FROM product_variants GROUP BY product_id
    ) vs ON p.id = vs.product_id
    WHERE p.status = 'active'
";
$params = [];

if (!empty($search_term)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = '%' . $search_term . '%';
}
if (!empty($selected_category)) {
    $sql .= " AND p.product_category = ?";
    $params[] = $selected_category;
}
if (!empty($selected_vendor)) {
    $sql .= " AND p.vendor = ?";
    $params[] = $selected_vendor;
}

$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);

// Bind the string parameters first
$param_index = 1;
foreach ($params as $value) {
    // bindValue is 1-indexed
    $stmt->bindValue($param_index, $value, PDO::PARAM_STR);
    $param_index++;
}

// Now, bind the integer parameters for LIMIT and OFFSET
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll();

// --- Output the HTML for the product cards & load more button ---
if ($page === 1 && empty($products)) {
    echo '<p class="col-span-full text-center text-gray-500">No products found matching your criteria.</p>';
    exit;
}

foreach ($products as $product) {
    include 'product_card.php';
}

// --- Add "Load More" button if there might be more products ---
$next_page = $page + 1;
$query_params = http_build_query([
    'search' => $search_term,
    'category' => $selected_category,
    'vendor' => $selected_vendor,
    'page' => $next_page
]);

if (count($products) === $limit) {
    echo '<div id="load-more-trigger" hx-get="load_products.php?' . $query_params . '" hx-trigger="intersect once" hx-swap="outerHTML" class="col-span-full text-center p-4">';
    echo '    <button class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Loading more...</button>';
    echo '</div>';
}
?>
