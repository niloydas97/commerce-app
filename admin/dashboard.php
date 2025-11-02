<?php
include '../config.php';
include 'auth_check.php';

// --- Fetch Dashboard Metrics ---

// 1. Key Stats
$total_sales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE order_status NOT IN ('Cancelled')")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(id) FROM orders")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(id) FROM products WHERE status = 'active'")->fetchColumn();
$total_customers = $pdo->query("SELECT COUNT(DISTINCT customer_phone) FROM orders")->fetchColumn();

// 2. Sales data for the last 7 days
$sales_by_day_stmt = $pdo->query("
    SELECT 
        DATE(created_at) as sale_date, 
        SUM(total_price) as daily_sales
    FROM orders 
    WHERE created_at >= CURDATE() - INTERVAL 7 DAY AND order_status NOT IN ('Cancelled')
    GROUP BY sale_date
    ORDER BY sale_date ASC
");
$sales_by_day = $sales_by_day_stmt->fetchAll();

// Format for Chart.js
$chart_labels = [];
$chart_data = [];
$period = new DatePeriod(
     new DateTime('-7 days'),
     new DateInterval('P1D'),
     new DateTime('+1 day')
);
foreach ($period as $date) {
    $chart_labels[] = $date->format('M d');
    $sales_for_day = 0;
    foreach ($sales_by_day as $sale) {
        if ($sale['sale_date'] == $date->format('Y-m-d')) {
            $sales_for_day = $sale['daily_sales'];
            break;
        }
    }
    $chart_data[] = $sales_for_day;
}

// 3. Recent Orders
$recent_orders_stmt = $pdo->query("SELECT id, customer_name, total_price, order_status, created_at FROM orders ORDER BY id DESC LIMIT 5");
$recent_orders = $recent_orders_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Dashboard</h1>
                <p class="mt-1 text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_user_name']); ?>!</p>
            </div>
            <div>
                <a href="logout.php" class="text-sm font-medium text-red-600 hover:text-red-800">Logout</a>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-sm font-medium text-gray-500">Total Revenue</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900">৳<?php echo number_format($total_sales ?? 0, 2); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-sm font-medium text-gray-500">Total Orders</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo number_format($total_orders ?? 0); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-sm font-medium text-gray-500">Active Products</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo number_format($total_products ?? 0); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-sm font-medium text-gray-500">Total Customers</h3>
                <p class="mt-1 text-3xl font-semibold text-gray-900"><?php echo number_format($total_customers ?? 0); ?></p>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Sales Chart and Recent Orders -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Sales (Last 7 Days)</h3>
                    <canvas id="salesChart"></canvas>
                </div>

                <div class="bg-white rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 p-6">Recent Orders</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600"><a href="order_view.php?id=<?php echo $order['id']; ?>">#<?php echo $order['id']; ?></a></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">৳<?php echo number_format($order['total_price'], 2); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo strtolower($order['order_status']) === 'completed' ? 'bg-green-100 text-green-800' : (strtolower($order['order_status']) === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                            <?php echo htmlspecialchars($order['order_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow-sm space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Quick Links</h3>
                    <a href="homepage_editor.php" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors font-semibold text-blue-700">Homepage Editor</a>
                    <a href="header_editor.php" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors font-semibold text-blue-700">Header Editor</a>
                    <a href="inventory.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Inventory</a>
                    <a href="media.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Media Library</a>
                    <a href="products.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Products</a>
                    <a href="orders.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Orders</a>
                    <a href="categories.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Categories</a>
                    <a href="migrate_categories.php" class="flex items-center p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors text-yellow-800">Migrate Categories</a>
                    <a href="vendors.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Vendors</a>
                    <a href="vendors.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Vendors</a>
                    <a href="tags.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Tags</a>
                    <a href="import.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Import from Shopify</a>
                    <a href="users.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">Manage Users</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return '৳' + value;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
    </script>
</body>
</html>