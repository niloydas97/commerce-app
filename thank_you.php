<?php
include 'config.php'; // Include config to initialize session for the header
$order_id = $_GET['order_id'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank You for Your Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Minimal styles for this simple page */
        .container { max-width: 600px; margin: auto; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>

    <div class="container text-center py-16">
        <div class="bg-white p-8 rounded-lg shadow-md inline-block">
            <h1 class="text-3xl font-bold text-green-600">Thank You!</h1>
            <p class="mt-2 text-gray-700">Your order has been placed successfully.</p>
            <p class="mt-4 text-gray-600">Your Order ID is: <strong class="text-gray-800">#<?php echo htmlspecialchars($order_id); ?></strong></p>
            <p class="mt-2 text-gray-600">We will contact you shortly to confirm your order.</p>
            <a href="index.php" class="mt-6 inline-block bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700">Continue Shopping</a>
        </div>
    </div>
</body>
</html>