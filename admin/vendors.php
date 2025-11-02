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
    $logo_url = $_POST['logo_url'];
    $id_to_update = $_POST['id'] ?? null;

    if (empty($name)) {
        $error = "Vendor name cannot be empty.";
    } else {
        if ($id_to_update) {
            $stmt = $pdo->prepare("UPDATE vendors SET name = ?, logo_url = ? WHERE id = ?");
            $stmt->execute([$name, $logo_url, $id_to_update]);
            $message = "Vendor updated.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO vendors (name, logo_url) VALUES (?, ?)");
                $stmt->execute([$name, $logo_url]);
                $message = "Vendor added.";
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $error = "A vendor with this name already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// --- Handle Delete Action ---
if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: vendors.php");
    exit;
}

// --- Load item for editing ---
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $item_to_edit = $stmt->fetch();
}

$items = [];
try {
    $items = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $error = "The 'vendors' table does not exist. Please run the SQL command from the documentation to create it.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Vendors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function openMediaLibrary(targetInputId) {
            const mediaUrl = `media.php?select=1&target=${targetInputId}`;
            window.open(mediaUrl, 'MediaLibrary', 'width=1200,height=800,scrollbars=yes');
        }
    </script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8 max-w-4xl">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h1 class="text-3xl font-bold mb-6">Manage Vendors</h1>

        <?php if ($message): ?><div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo $item_to_edit ? 'Edit Vendor' : 'Add New Vendor'; ?></h3>
            <form method="POST" action="vendors.php" class="space-y-4">
                <?php if ($item_to_edit): ?><input type="hidden" name="id" value="<?php echo $item_to_edit['id']; ?>"><?php endif; ?>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Vendor Name</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($item_to_edit['name'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <label for="logo_url" class="block text-sm font-medium text-gray-700">Logo URL</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <input type="text" name="logo_url" id="logo_url" value="<?php echo htmlspecialchars($item_to_edit['logo_url'] ?? ''); ?>" class="block w-full rounded-none rounded-l-md border-gray-300">
                        <button type="button" onclick="openMediaLibrary('logo_url')" class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Select</button>
                    </div>
                </div>
                <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700"><?php echo $item_to_edit ? 'Update Vendor' : 'Add Vendor'; ?></button>
            </form>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th><th class="relative px-6 py-3"></th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-6 py-4"><img src="../<?php echo htmlspecialchars($item['logo_url'] ?? 'images/placeholder.svg'); ?>" class="h-10 w-20 object-contain bg-gray-100 p-1 rounded-md"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="?action=edit&id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                            <a href="?action=delete&id=<?php echo $item['id']; ?>" onclick="return confirm('Are you sure?');" class="text-red-600 hover:text-red-900 ml-4">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>