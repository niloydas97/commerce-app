<?php
/**
 * Expects a $product variable to be available in the scope.
 * $product = [
 *   'handle' => 'product-handle',
 *   'name' => 'Product Name',
 *   'image_url' => 'path/to/image.jpg',
 *   'price' => 99.99,
 *   'compare_at_price' => 120.00, (optional)
 *   'total_stock' => 10
 * ]
 */
?>
<a href="product.php?handle=<?php echo $product['handle']; ?>" class="group block bg-white rounded-lg shadow-sm overflow-hidden transition-shadow duration-300 hover:shadow-md">
    <div class="relative overflow-hidden aspect-square">
        <?php if (($product['total_stock'] ?? 0) <= 0): ?>
            <div class="absolute bottom-2 left-2 z-10"><span class="bg-black text-white font-semibold text-xs px-2 py-1 rounded-sm uppercase tracking-wider">Sold Out</span></div>
        <?php endif; ?>
        <img data-src="<?php echo htmlspecialchars($product['image_url'] ?? 'images/placeholder.jpg'); ?>" src="images/placeholder.svg" alt="<?php echo htmlspecialchars($product['name']); ?>" class="lazy absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-500">
    </div>
    <div class="p-4">
        <h3 class="text-sm font-medium text-gray-800 truncate group-hover:text-blue-600"><?php echo htmlspecialchars($product['name']); ?></h3>
        <p class="mt-2 text-lg font-semibold text-gray-900">৳<?php echo htmlspecialchars(number_format($product['price'] ?? 0, 2)); ?></p>
    </div>
</a>