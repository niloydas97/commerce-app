<?php
include '../config.php';
include 'auth_check.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Get all distinct categories from the products table
        $stmt = $pdo->query("SELECT DISTINCT product_category FROM products WHERE product_category IS NOT NULL AND product_category != ''");
        $source_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($source_categories)) {
            $message = "No categories found in the products table to migrate.";
        } else {
            $inserted_count = 0;
            // 2. Use INSERT IGNORE to add only new categories, preventing errors on duplicates
            $insert_stmt = $pdo->prepare("INSERT IGNORE INTO product_categories (name) VALUES (?)");

            // 3. Loop through the source categories and execute the insert
            foreach ($source_categories as $category_name) {
                $insert_stmt->execute([$category_name]);
                if ($insert_stmt->rowCount() > 0) {
                    $inserted_count++;
                }
            }
            $message = "Migration complete. Found " . count($source_categories) . " unique categories in the products table. Added " . $inserted_count . " new categories to the categories table.";
        }
    } catch (PDOException $e) {
        $error = "Database error during migration: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate Product Categories</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8 max-w-2xl">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold mb-6">Migrate Product Categories</h1>

        <?php if ($message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm">
            <p class="mb-4">This tool will find all unique category names from your `products` table and add them to the main `product_categories` table. This helps keep your category data consistent.</p>
            <form method="POST">
                <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700">Start Migration</button>
            </form>
        </div>
    </div>
</body>
</html>