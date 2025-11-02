<?php
include '../config.php';
include 'auth_check.php';

$order_id = $_GET['id'] ?? 0;
if (!$order_id) {
    die("No order ID specified.");
}

// --- Fetch Order Details ---
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) {
    die("Order not found.");
}

// --- Handle Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $new_status = $_POST['new_status'];
    $current_status = $order['order_status'];

    // Define statuses that trigger stock deduction.
    $deduction_statuses = ['Processing', 'Shipped', 'Completed'];

    // --- Stock Deduction Logic ---
    if (in_array($new_status, $deduction_statuses) && !in_array($current_status, $deduction_statuses)) {
        $items_stmt = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$order_id]);
        $order_items_for_stock = $items_stmt->fetchAll();

        $pdo->beginTransaction();
        try {
            $update_stock_stmt = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
            foreach ($order_items_for_stock as $item) {
                $update_stock_stmt->execute([(int)$item['quantity'], (int)$item['variant_id']]);
            }
            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$new_status, $order_id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error updating status and stock: " . $e->getMessage());
        }
    } elseif ($new_status === 'Cancelled' && in_array($current_status, $deduction_statuses)) {
        // --- Restock Logic ---
        $items_stmt = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$order_id]);
        $order_items_for_restock = $items_stmt->fetchAll();

        $pdo->beginTransaction();
        try {
            $update_stock_stmt = $pdo->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
            foreach ($order_items_for_restock as $item) {
                $update_stock_stmt->execute([(int)$item['quantity'], (int)$item['variant_id']]);
            }
            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$new_status, $order_id]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error updating status and restocking: " . $e->getMessage());
        }
    } else {
        $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$new_status, $order_id]);
    }
    header("Location: order_view.php?id=" . $order_id);
    exit;
}

// --- Fetch Items (with SKU) ---
$stmt_items = $pdo->prepare("
    SELECT oi.*, p.name as product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Order #<?php echo $order['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div>
            <a href="orders.php" class="text-blue-600 hover:underline">&larr; Back to All Orders</a>
        </div>

        <div class="my-4">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900">Order #<?php echo $order['id']; ?></h2>
            <p class="text-sm text-gray-500 mt-1">Placed on <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Items Ordered</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name'] ?? 'Product Removed'); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['variant_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $item['quantity']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">৳<?php echo number_format($item['price'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Customer & Shipping Details</h3>
                    <p class="text-sm text-gray-600"><strong class="font-medium text-gray-800">Customer Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p class="text-sm text-gray-600 mt-2"><strong class="font-medium text-gray-800">Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    <p class="text-sm text-gray-600 mt-2"><strong class="font-medium text-gray-800">Address:</strong><br><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></p>
                </div>
            </div>

            <div class="lg:col-span-1 space-y-8">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Details</h3>
                    <div class="space-y-2 text-sm">
                        <p class="flex justify-between"><span>Sub-total:</span> <span>৳<?php echo number_format($order['sub_total'], 2); ?></span></p>
                        <p class="flex justify-between"><span>Delivery:</span> <span>৳<?php echo number_format($order['delivery_charge'], 2); ?></span></p>
                        <p class="flex justify-between font-bold text-base border-t pt-2 mt-2"><span>Total:</span> <span>৳<?php echo number_format($order['total_price'], 2); ?></span></p>
                    </div>
                    <hr class="my-4">
                    <p class="text-sm text-gray-600"><strong class="font-medium text-gray-800">Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                    <?php if ($order['payment_method'] === 'Self MFS'): ?>
                        <p class="text-sm text-gray-600 mt-2"><strong class="font-medium text-gray-800">MFS Phone:</strong> <?php echo htmlspecialchars($order['payment_phone']); ?></p>
                        <p class="text-sm text-gray-600 mt-2"><strong class="font-medium text-gray-800">MFS TrxID:</strong> <?php echo htmlspecialchars($order['payment_txid']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Update Status</h3>
                    <form method="POST">
                        <div>
                            <label for="new_status" class="block text-sm font-medium text-gray-700">Order Status</label>
                            <select name="new_status" id="new_status" class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="Pending" <?php if ($order['order_status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Processing" <?php if ($order['order_status'] == 'Processing') echo 'selected'; ?>>Processing</option>
                                <option value="Shipped" <?php if ($order['order_status'] == 'Shipped') echo 'selected'; ?>>Shipped</option>
                                <option value="Completed" <?php if ($order['order_status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                                <option value="Cancelled" <?php if ($order['order_status'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" class="mt-4 w-full inline-flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>