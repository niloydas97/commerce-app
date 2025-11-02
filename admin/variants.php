<?php
include '../config.php';
include 'auth_check.php';

$product_id = $_GET['product_id'] ?? 0;
if (!$product_id) {
    die("No product selected.");
}
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) {
    die("Product not found.");
}

$variant_to_edit = null;
$action = $_GET['action'] ?? null;
$variant_id = $_GET['id'] ?? 0;

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid = $_POST['variant_id'] ?? null;
    
    $params = [
        ':product_id' => $product_id,
        ':name' => $_POST['name'],
        ':price' => (float)$_POST['price'],
        ':compare_at_price' => (float)$_POST['compare_at_price'] > 0 ? (float)$_POST['compare_at_price'] : null,
        ':stock' => (int)$_POST['stock'],
        ':sku' => $_POST['sku'],
        ':grams' => (float)$_POST['grams'],
        ':weight_unit' => $_POST['weight_unit'],
        ':barcode' => $_POST['barcode'],
        ':cost' => (float)$_POST['cost'],
        ':requires_shipping' => isset($_POST['requires_shipping']) ? 1 : 0,
        ':taxable' => isset($_POST['taxable']) ? 1 : 0,
        ':option1_name' => $_POST['option1_name'],
        ':option1_value' => $_POST['option1_value'],
        ':option2_name' => $_POST['option2_name'],
        ':option2_value' => $_POST['option2_value'],
        ':option3_name' => $_POST['option3_name'],
        ':option3_value' => $_POST['option3_value']
    ];

    if ($vid) {
        // Update
        $params[':id'] = $vid;
        $sql = "UPDATE product_variants SET 
                    name = :name, price = :price, compare_at_price = :compare_at_price, stock = :stock, sku = :sku, 
                    grams = :grams, weight_unit = :weight_unit, barcode = :barcode, cost = :cost, 
                    requires_shipping = :requires_shipping, taxable = :taxable,
                    option1_name = :option1_name, option1_value = :option1_value,
                    option2_name = :option2_name, option2_value = :option2_value,
                    option3_name = :option3_name, option3_value = :option3_value
                WHERE id = :id AND product_id = :product_id";
    } else {
        // Insert
        $sql = "INSERT INTO product_variants 
                    (product_id, name, price, compare_at_price, stock, sku, grams, weight_unit, barcode, cost, requires_shipping, taxable,
                     option1_name, option1_value, option2_name, option2_value, option3_name, option3_value) 
                VALUES 
                    (:product_id, :name, :price, :compare_at_price, :stock, :sku, :grams, :weight_unit, :barcode, :cost, :requires_shipping, :taxable,
                     :option1_name, :option1_value, :option2_name, :option2_value, :option3_name, :option3_value)";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    header("Location: variants.php?product_id=" . $product_id);
    exit;
}

// --- Handle Delete ---
if ($action === 'delete' && $variant_id) {
    $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt->execute([$variant_id, $product_id]);
    header("Location: variants.php?product_id=" . $product_id);
    exit;
}

// --- Load Variant for Editing ---
if ($action === 'edit' && $variant_id) {
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt->execute([$variant_id, $product_id]);
    $variant_to_edit = $stmt->fetch();
}

// Fetch all variants for this product
$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
$variants->execute([$product_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Variants</title>
    <link rel="stylesheet" href="../style.css"> 
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        form { border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; background: #f9f9f9; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .form-grid .full-width { grid-column: 1 / -1; }
        .form-grid label { display: block; font-weight: bold; }
        .form-grid input { width: 100%; padding: 8px; box-sizing: border-box; }
        .checkbox-group { display: flex; align-items: center; }
        .checkbox-group input { width: auto; margin-right: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="products.php">&larr; Back to Products</a></p>
        <h2>Manage Variants for: <?php echo htmlspecialchars($product['name']); ?></h2>

        <h3><?php echo $variant_to_edit ? 'Edit Variant' : 'Add New Variant'; ?></h3>
        <form method="POST">
            <?php if ($variant_to_edit): ?>
                <input type="hidden" name="variant_id" value="<?php echo $variant_to_edit['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="full-width">
                    <label>Variant Name * (e.g., "Default Title" or "Blue / Large")</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($variant_to_edit['name'] ?? ''); ?>" required>
                </div>

                <div>
                    <label>Price *</label>
                    <input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($variant_to_edit['price'] ?? '0.00'); ?>" required>
                </div>
                <div>
                    <label>Compare At Price</label>
                    <input type="number" name="compare_at_price" step="0.01" value="<?php echo htmlspecialchars($variant_to_edit['compare_at_price'] ?? '0.00'); ?>">
                </div>
                <div>
                    <label>Cost</label>
                    <input type="number" name="cost" step="0.01" value="<?php echo htmlspecialchars($variant_to_edit['cost'] ?? '0.00'); ?>">
                </div>

                <div class="full-width"><hr></div>

                <div>
                    <label>SKU</label>
                    <input type="text" name="sku" value="<?php echo htmlspecialchars($variant_to_edit['sku'] ?? ''); ?>">
                </div>
                <div>
                    <label>Barcode</label>
                    <input type="text" name="barcode" value="<?php echo htmlspecialchars($variant_to_edit['barcode'] ?? ''); ?>">
                </div>
                <div>
                    <label>Stock *</label>
                    <input type="number" name="stock" value="<?php echo htmlspecialchars($variant_to_edit['stock'] ?? '0'); ?>" required>
                </div>
                <div>
                    <label>Grams</label>
                    <input type="number" name="grams" step="0.01" value="<?php echo htmlspecialchars($variant_to_edit['grams'] ?? '0'); ?>">
                </div>
                <div>
                    <label>Weight Unit</label>
                    <input type="text" name="weight_unit" value="<?php echo htmlspecialchars($variant_to_edit['weight_unit'] ?? 'kg'); ?>">
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="requires_shipping" id="requires_shipping" value="1" <?php echo ($variant_to_edit['requires_shipping'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="requires_shipping">Requires Shipping</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="taxable" id="taxable" value="1" <?php echo ($variant_to_edit['taxable'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="taxable">Taxable</label>
                </div>

                <div class="full-width"><hr><h3>Options</h3></div>
                
                <div>
                    <label>Option1 Name</label>
                    <input type="text" name="option1_name" value="<?php echo htmlspecialchars($variant_to_edit['option1_name'] ?? ''); ?>">
                </div>
                <div>
                    <label>Option1 Value</label>
                    <input type="text" name="option1_value" value="<?php echo htmlspecialchars($variant_to_edit['option1_value'] ?? ''); ?>">
                </div>
                <div class="full-width"></div>
                <div>
                    <label>Option2 Name</label>
                    <input type="text" name="option2_name" value="<?php echo htmlspecialchars($variant_to_edit['option2_name'] ?? ''); ?>">
                </div>
                <div>
                    <label>Option2 Value</label>
                    <input type="text" name="option2_value" value="<?php echo htmlspecialchars($variant_to_edit['option2_value'] ?? ''); ?>">
                </div>
                <div class="full-width"></div>
                <div>
                    <label>Option3 Name</label>
                    <input type="text" name="option3_name" value="<?php echo htmlspecialchars($variant_to_edit['option3_name'] ?? ''); ?>">
                </div>
                <div>
                    <label>Option3 Value</label>
                    <input type="text" name="option3_value" value="<?php echo htmlspecialchars($variant_to_edit['option3_value'] ?? ''); ?>">
                </div>
            </div>
            
            <button type="submit" class="confirm-button" style="width: auto; padding: 10px 15px; margin-top: 15px;">
                <?php echo $variant_to_edit ? 'Update Variant' : 'Save Variant'; ?>
            </button>
            <?php if ($variant_to_edit): ?>
                <a href="variants.php?product_id=<?php echo $product_id; ?>">Cancel Edit</a>
            <?php endif; ?>
        </form>

        <h3>Existing Variants</h3>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($variants as $variant): ?>
                <tr>
                    <td><?php echo htmlspecialchars($variant['name']); ?></td>
                    <td><?php echo htmlspecialchars($variant['sku']); ?></td>
                    <td>৳<?php echo htmlspecialchars($variant['price']); ?></td>
                    <td><?php echo htmlspecialchars($variant['stock']); ?></td>
                    <td>
                        <a href="variants.php?product_id=<?php echo $product_id; ?>&action=edit&id=<?php echo $variant['id']; ?>">Edit</a> |
                        <a href="variants.php?product_id=<?php echo $product_id; ?>&action=delete&id=<?php echo $variant['id']; ?>" onclick="return confirm('Are you sure?');" style="color: red;">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>