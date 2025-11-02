<?php
include 'config.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_SESSION['cart'])) {

    $pdo->beginTransaction();

    try {
        $customer_name = $_POST['full_name'] ?? 'N/A';
        $customer_phone = $_POST['phone'] ?? 'N/A';
        $customer_address = $_POST['address'] ?? 'N/A';
        $customer_email = $_POST['email'] ?? null;
        $delivery_zone = $_POST['delivery_zone'] ?? 'Inside Dhaka';
        $order_notes = $_POST['order_notes'] ?? null;
        
        $payment_method = $_POST['payment_method'] ?? 'cod';
        $payment_phone = ($payment_method === 'Self MFS') ? $_POST['payment_phone'] : null;
        $payment_txid = ($payment_method === 'Self MFS') ? $_POST['payment_txid'] : null;

        $sub_total = 0;
        $cart_items = []; 

        foreach ($_SESSION['cart'] as $variant_id => $quantity) {
            $stmt = $pdo->prepare("
                SELECT v.product_id, v.name, v.price, v.sku 
                FROM product_variants v 
                WHERE v.id = ?
            ");
            $stmt->execute([$variant_id]);
            $item = $stmt->fetch();

            if ($item) {
                $sub_total += $item['price'] * $quantity;
                $cart_items[] = [
                    'product_id' => $item['product_id'],
                    'variant_id' => $variant_id,
                    'variant_name' => $item['name'],
                    'sku' => $item['sku'], // Get SKU
                    'quantity' => $quantity,
                    'price' => $item['price']
                ];
            }
        }

        $delivery_charge = ($delivery_zone === 'Inside Dhaka') ? 70 : 120;
        $total_price = $sub_total + $delivery_charge;

        $sql = "INSERT INTO orders (
                    customer_name, customer_phone, customer_address, customer_email,
                    delivery_zone, order_notes, sub_total, delivery_charge, 
                    total_price, payment_method, payment_phone, payment_txid
                ) VALUES (
                    :name, :phone, :address, :email,
                    :zone, :notes, :subtotal, :delivery,
                    :total, :payment, :pphone, :ptxid
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $customer_name,
            ':phone' => $customer_phone,
            ':address' => $customer_address,
            ':email' => $customer_email,
            ':zone' => $delivery_zone,
            ':notes' => $order_notes,
            ':subtotal' => $sub_total,
            ':delivery' => $delivery_charge,
            ':total' => $total_price,
            ':payment' => $payment_method,
            ':pphone' => $payment_phone,
            ':ptxid' => $payment_txid
        ]);

        $order_id = $pdo->lastInsertId();

        // UPDATED to include SKU
        $sql_items = "INSERT INTO order_items 
                        (order_id, product_id, variant_id, variant_name, sku, quantity, price) 
                      VALUES 
                        (:order_id, :product_id, :variant_id, :variant_name, :sku, :quantity, :price)";
        
        $stmt_items = $pdo->prepare($sql_items);

        foreach ($cart_items as $item) {
            $stmt_items->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['product_id'],
                ':variant_id' => $item['variant_id'],
                ':variant_name' => $item['variant_name'],
                ':sku' => $item['sku'], // Save SKU
                ':quantity' => $item['quantity'],
                ':price' => $item['price']
            ]);
        }

        $pdo->commit();
        unset($_SESSION['cart']);
        
        header("Location: thank_you.php?order_id=" . $order_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: Could not place order. " . $e->getMessage());
    }

} else {
    header("Location: index.php");
    exit;
}
?>