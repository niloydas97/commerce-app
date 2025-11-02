<?php
include '../config.php';
include 'auth_check.php';

// --- Configuration ---
$limit = 50; // Show 50 variants per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$vendor = $_GET['vendor'] ?? '';

// --- Build WHERE clause for filtering ---
$where_clauses = ["p.status = 'active'"];
$params = [];

if (!empty($search)) {
    // Search by product name OR variant SKU
    $where_clauses[] = "(p.name LIKE ? OR v.sku LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($vendor)) {
    $where_clauses[] = "p.vendor = ?";
    $params[] = $vendor;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- Get Total Count for Pagination ---
$count_sql = "SELECT COUNT(v.id) 
              FROM product_variants v 
              JOIN products p ON v.product_id = p.id 
              $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_variants = $count_stmt->fetchColumn();
$total_pages = ceil($total_variants / $limit);

// --- Get Variants for This Page ---
$sql = "SELECT 
            p.name as product_name,
            v.id as variant_id,
            v.name as variant_name,
            v.sku,
            v.stock
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        $where_sql
        ORDER BY p.name, v.name
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll();

// Get all vendors for the filter dropdown
$vendors = $pdo->query("SELECT DISTINCT vendor FROM products WHERE vendor IS NOT NULL AND vendor != '' ORDER BY vendor")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Inventory Management</h2>

        <!-- Filter Bar -->
        <form method="GET" class="bg-white p-4 rounded-lg shadow-sm mb-6 flex flex-col sm:flex-row gap-4 items-center">
            <input type="search" name="search" placeholder="Search by Product Name or SKU..." value="<?php echo htmlspecialchars($search); ?>" class="w-full sm:flex-grow rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select name="vendor" class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Vendors</option>
                <?php foreach ($vendors as $v): ?>
                <option value="<?php echo htmlspecialchars($v); ?>" <?php if ($v === $vendor) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($v); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700">Filter</button>
        </form>

        <!-- Inventory Table -->
        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($variants as $v): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($v['product_name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($v['variant_name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($v['sku']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <input type="number" class="stock-input w-20 text-center rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" id="stock-<?php echo $v['variant_id']; ?>" value="<?php echo $v['stock']; ?>">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button class="update-btn inline-flex justify-center rounded-md border border-transparent bg-green-600 py-1 px-3 text-sm font-medium text-white shadow-sm hover:bg-green-700 transition-colors duration-300" data-variant-id="<?php echo $v['variant_id']; ?>">Update</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($variants)): ?>
                    <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No products found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6 flex justify-center items-center space-x-1">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&vendor=<?php echo htmlspecialchars($vendor); ?>"
                   class="px-3 py-1 text-sm font-medium rounded-md <?php if ($i == $page) echo 'bg-blue-600 text-white'; else echo 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.update-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const variantId = this.getAttribute('data-variant-id');
            const stock = document.getElementById('stock-' + variantId).value;
            
            this.innerText = 'Saving...';
            this.disabled = true;

            try {
                const response = await fetch('update_stock.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: variantId, stock: stock })
                });
                const result = await response.json();

                if (result.success) {
                    this.innerText = 'Saved!';
                    this.classList.remove('bg-green-600', 'hover:bg-green-700');
                    this.classList.add('bg-blue-600');
                    setTimeout(() => {
                        this.innerText = 'Update';
                        this.classList.remove('bg-blue-600');
                        this.classList.add('bg-green-600', 'hover:bg-green-700');
                        this.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Unknown error');
                }
            } catch (error) {
                console.error('Error updating stock:', error);
                this.innerText = 'Error!';
                this.classList.remove('bg-green-600', 'hover:bg-green-700');
                this.classList.add('bg-red-600');
                setTimeout(() => {
                    this.innerText = 'Update';
                    this.classList.remove('bg-red-600');
                    this.classList.add('bg-green-600', 'hover:bg-green-700');
                    this.disabled = false;
                }, 3000);
            }
        });
    });
});
</script>
</body>
</html>