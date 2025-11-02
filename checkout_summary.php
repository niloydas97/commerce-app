<?php
include_once 'config.php';

$sub_total = 0;
$cart_empty = empty($_SESSION['cart']);
$delivery_zone = $_GET['delivery_zone'] ?? 'Inside Dhaka';

?>
<h3 class="text-lg font-medium text-gray-900">Your Order</h3>
<div class="mt-4 space-y-4">
    <?php
    if ($cart_empty) {
        echo "<p>Your cart is empty.</p>";
    } else {
        foreach ($_SESSION['cart'] as $variant_id => $quantity) {
            $stmt = $pdo->prepare("
                SELECT v.name, v.price, v.sku, v.stock, p.name as product_name, pi.image_url
                FROM product_variants v
                JOIN products p ON v.product_id = p.id
                LEFT JOIN (SELECT product_id, image_url FROM product_images WHERE position = 1) pi ON p.id = pi.product_id
                WHERE v.id = ?
            ");
            $stmt->execute([$variant_id]);
            $item = $stmt->fetch();

            if ($item) {
                $item_total = $item['price'] * $quantity;
                $sub_total += $item_total;
                $stock = $item['stock'];
    ?>
        <div class="flex justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <img src="<?php echo htmlspecialchars($item['image_url'] ?? 'images/placeholder.svg'); ?>" alt="" class="h-16 w-16 rounded-md object-cover">
                <div>
                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($item['name']); ?></p>
                    <div class="mt-1 flex items-center border border-gray-200 rounded-md w-24">
                        <button type="button" hx-post="cart.php?action=update" hx-vals='{"variant_id": "<?php echo $variant_id; ?>", "quantity": "<?php echo $quantity - 1; ?>"}' hx-target="#order-summary" class="px-2 py-0.5 text-lg text-gray-500 hover:bg-gray-100 rounded-l-md">-</button>
                        <input type="number" value="<?php echo $quantity; ?>" min="1" max="<?php echo $stock; ?>" class="w-full text-center border-l border-r focus:outline-none focus:ring-0 text-sm"
                               hx-post="cart.php?action=update" hx-trigger="change" hx-vals='{"variant_id": "<?php echo $variant_id; ?>"}' name="quantity" hx-target="#order-summary">
                        <button type="button" hx-post="cart.php?action=update" hx-vals='{"variant_id": "<?php echo $variant_id; ?>", "quantity": "<?php echo $quantity + 1; ?>"}' hx-target="#order-summary" class="px-2 py-0.5 text-lg text-gray-500 hover:bg-gray-100 rounded-r-md">+</button>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-gray-900 mb-1">৳<?php echo number_format($item_total, 2); ?></p>
                <button hx-post="cart.php?action=remove" hx-vals='{"variant_id": "<?php echo $variant_id; ?>"}' hx-target="#order-summary" hx-confirm="Are you sure you want to remove this item?" class="text-xs text-red-500 hover:underline">Remove</button>
            </div>
        </div>
    <?php
            }
        }
    }

    $delivery_charge = ($delivery_zone === 'Inside Dhaka') ? 70 : 120;
    $total = $sub_total + $delivery_charge;
    ?>
</div>
<div class="mt-6 pt-6 border-t border-gray-200 space-y-2">
    <p class="flex justify-between text-sm text-gray-600">Sub-Total: <span>৳<?php echo number_format($sub_total, 2); ?></span></p>
    <p class="flex justify-between text-sm text-gray-600">Delivery charge: <span id="delivery-charge-display">৳<?php echo number_format($delivery_charge, 2); ?></span></p>
    <p class="flex justify-between text-base font-semibold text-gray-900">Total: <span id="total-price-display">৳<?php echo number_format($total, 2); ?></span></p>
</div>
<?php if ($cart_empty): ?>
<script>document.querySelector('form[action="place_order.php"] button[type="submit"]').disabled = true;</script>
<?php endif; ?>