<?php
include '../config.php';
include 'auth_check.php';

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? 0;
$item_to_edit = null;
$error = '';
$message = '';

// --- Handle POST requests (Add/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $id_to_update = $_POST['id'] ?? null;

    if ($id_to_update) {
        $stmt = $pdo->prepare("UPDATE product_categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id_to_update]);
        $message = "Category updated.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO product_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            $message = "Category added.";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "This category already exists.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// --- Handle Delete Action ---
if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM product_categories WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Category deleted.";
}

// --- Load item for editing ---
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM product_categories WHERE id = ?");
    $stmt->execute([$id]);
    $item_to_edit = $stmt->fetch();
}

$items = $pdo->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Manage Product Categories</h2>

        <?php if ($message): ?><div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo $item_to_edit ? 'Edit Category' : 'Add New Category'; ?></h3>
            <form method="POST" action="categories.php" class="flex flex-col sm:flex-row sm:items-end sm:gap-4">
                <?php if ($item_to_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $item_to_edit['id']; ?>">
                <?php endif; ?>
                <div class="flex-grow w-full">
                    <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($item_to_edit['name'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button type="submit" class="mt-2 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 h-10">
                    <?php echo $item_to_edit ? 'Update' : 'Add'; ?>
                </button>
            </form>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="categories.php?action=edit&id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                            <a href="categories.php?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure?');" class="text-red-600 hover:text-red-900 ml-4">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>