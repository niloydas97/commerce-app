<?php
include '../config.php';
include 'auth_check.php';

// Fetch all orders
$stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .status { padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="dashboard.php">&larr; Back to Dashboard</a></p>
        <h2>Manage Orders</h2>

        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                    <td>৳<?php echo htmlspecialchars($order['total_price']); ?></td>
                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower(htmlspecialchars($order['order_status'])); ?>">
                            <?php echo htmlspecialchars($order['order_status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y, g:ia', strtotime($order['created_at'])); ?></td>
                    <td>
                        <a href="order_view.php?id=<?php echo $order['id']; ?>" class="confirm-button" style="padding: 5px 10px; font-size: 14px; background: #007bff;">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8">No orders found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>