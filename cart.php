<?php
include_once 'config.php';

$action = $_GET['action'] ?? null;

// --- ADD TO CART ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)$_POST['variant_id'];
    $quantity_to_add = (int)$_POST['quantity'];

    if ($variant_id > 0 && $quantity_to_add > 0) {
        // Get current stock for the variant
        $stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
        $stmt->execute([$variant_id]);
        $variant = $stmt->fetch();
        $stock = $variant ? (int)$variant['stock'] : 0;

        // Get current quantity in cart and calculate new total
        $current_quantity_in_cart = $_SESSION['cart'][$variant_id] ?? 0;
        $new_total_quantity = $current_quantity_in_cart + $quantity_to_add;

        // Cap the quantity at the stock level
        $_SESSION['cart'][$variant_id] = min($new_total_quantity, $stock);
    }

    // Redirect to checkout page after adding
    header('Location: checkout.php');
    exit;
}

// --- BUY NOW ---
if ($action === 'buy_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // The 'add' action already handles adding to cart and redirecting.
    // We just need to call it.
    $_GET['action'] = 'add';
    require __FILE__; // Re-process this same file with the action changed to 'add'
    exit;
}

// --- REMOVE FROM CART ---
if ($action === 'remove') {
    // Use POST for state-changing actions
    $variant_id = (int)($_POST['variant_id'] ?? $_GET['id'] ?? 0);
    if (isset($_SESSION['cart'][$variant_id])) {
        unset($_SESSION['cart'][$variant_id]);
    }

    // If it's an HTMX request, return the updated summary fragment
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        include_once 'checkout_summary.php';
        exit;
    } else {
        // Otherwise, redirect
        header('Location: checkout.php');
        exit;
    }
}

// --- UPDATE CART QUANTITY ---
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)$_POST['variant_id'];
    $quantity = (int)$_POST['quantity'];

    if ($quantity > 0) {
        // Get current stock for the variant
        $stmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
        $stmt->execute([$variant_id]);
        $variant = $stmt->fetch();
        $stock = $variant ? (int)$variant['stock'] : 0;

        // Cap the quantity at the stock level, but only if the item is in the cart
        if (isset($_SESSION['cart'][$variant_id])) {
            $_SESSION['cart'][$variant_id] = min($quantity, $stock);
        } 
    } else { // If quantity is 0 or less, remove the item
        unset($_SESSION['cart'][$variant_id]);
    }
    
    // If it's an HTMX request, return the updated summary fragment
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        include_once 'checkout_summary.php';
        exit;
    } else {
        header('Location: checkout.php');
        exit;
    }
}

// If no action, just go to checkout
header('Location: checkout.php');
exit;
?>