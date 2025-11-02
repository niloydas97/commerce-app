<?php
include '../config.php';
include 'auth_check.php';
header('Content-Type: text/html; charset=utf-8');

function check($title, $condition, $error_msg = "") {
    if ($condition) {
        echo "<li style='color: green;'>✅ <strong>PASS:</strong> $title</li>";
    } else {
        echo "<li style='color: red;'>❌ <strong>FAIL:</strong> $title. $error_msg</li>";
    }
    return $condition;
}

function get_columns($pdo, $table_name) {
    try {
        $stmt = $pdo->query("DESCRIBE `$table_name`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return $e; // Return the error
    }
}

// --- The CSV columns your script needs ---
$required_product_cols = [
    'handle', 'name', 'description', 'vendor', 'product_category', 
    'product_type', 'status', 'published', 'gift_card', 'seo_title', 'seo_description'
];
$required_variant_cols = [
    'product_id', 'name', 'price', 'compare_at_price', 'stock', 'sku', 
    'grams', 'weight_unit', 'barcode', 'cost', 'image_url', 'requires_shipping', 'taxable'
];
$required_image_cols = [
    'product_id', 'image_url', 'position', 'alt_text'
];
$required_tag_cols = [
    'id', 'name'
];
$required_product_tag_cols = [
    'product_id', 'tag_id'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Debugger</title>
    <style>
        body { font-family: sans-serif; margin: 30px; }
        ul { list-style-type: none; padding: 0; font-size: 16px; line-height: 1.8; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Import Debugger &amp; System Check</h1>
    <p>This tool will check your server and database configuration to find the import error.</p>
    <ul>
        <?php
        // --- Test 1: File Checks ---
        echo "<h3>File System:</h3>";
        $csv_folder = '../images';
        check("Folder `images/` exists", file_exists($csv_folder), "The folder to store the CSV and product images is missing.");
        check("Folder `images/` is writable", is_writable($csv_folder), "PHP cannot write files to this folder. Check server permissions (set to 755 or 777).");
        check("File `import_batch.php` exists", file_exists('import_batch.php'), "The importer engine file is missing. This causes a 404 error.");
        
        // --- Test 2: Database Table Checks ---
        echo "<h3 style='margin-top: 20px;'>Database Tables:</h3>";
        $products_cols = get_columns($pdo, 'products');
        $variants_cols = get_columns($pdo, 'product_variants');
        $images_cols = get_columns($pdo, 'product_images');
        $tags_cols = get_columns($pdo, 'tags');
        $product_tags_cols = get_columns($pdo, 'product_tags');

        if ($products_cols instanceof Exception) {
            check("Table `products` exists", false, "Error: " . $products_cols->getMessage());
        } else {
            foreach ($required_product_cols as $col) {
                check("`products` table has column: <strong>$col</strong>", in_array($col, $products_cols));
            }
        }
        
        if ($variants_cols instanceof Exception) {
            check("Table `product_variants` exists", false, "Error: " . $variants_cols->getMessage());
        } else {
            foreach ($required_variant_cols as $col) {
                check("`product_variants` table has column: <strong>$col</strong>", in_array($col, $variants_cols));
            }
        }

        if ($images_cols instanceof Exception) {
            check("Table `product_images` exists", false, "Error: " . $images_cols->getMessage());
        } else {
            foreach ($required_image_cols as $col) {
                check("`product_images` table has column: <strong>$col</strong>", in_array($col, $images_cols));
            }
        }

        if ($tags_cols instanceof Exception) {
            check("Table `tags` exists", false, "Error: " . $tags_cols->getMessage());
        } else {
            foreach ($required_tag_cols as $col) {
                check("`tags` table has column: <strong>$col</strong>", in_array($col, $tags_cols));
            }
        }

        if ($product_tags_cols instanceof Exception) {
            check("Table `product_tags` exists", false, "Error: " . $product_tags_cols->getMessage());
        } else {
            foreach ($required_product_tag_cols as $col) {
                check("`product_tags` table has column: <strong>$col</strong>", in_array($col, $product_tags_cols));
            }
        }
        ?>
    </ul>
</body>
</html>