<?php
include '../config.php';
include 'auth_check.php';

$action = $_GET['action'] ?? null;
$user_id = $_GET['id'] ?? 0;
$user_to_edit = null;
$error = '';
$message = '';

// --- Handle Delete Action ---
if ($action === 'delete' && $user_id) {
    // Prevent deleting the last user or yourself
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();

    if ($user_id == $_SESSION['admin_user_id']) {
        $error = "You cannot delete yourself.";
    } elseif ($count <= 1) {
        $error = "You cannot delete the last user.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User deleted successfully.";
    }
}

// --- Handle Add/Edit POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $id_to_update = $_POST['user_id'] ?? null;

    if ($id_to_update) {
        // --- Update User ---
        if (empty($password)) {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $id_to_update]);
            $message = "User updated.";
        } else {
            // Update AND change password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hash, $id_to_update]);
            $message = "User and password updated.";
        }
    } else {
        // --- Add New User ---
        if (empty($password)) {
            $error = "Password is required for new users.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            try {
                $stmt->execute([$name, $email, $hash]);
                $message = "User added successfully.";
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $error = "An account with this email already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// --- Handle Edit Action (Load user into form) ---
if ($action === 'edit' && $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_to_edit = $stmt->fetch();
}

// --- Fetch all users for the list ---
$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 font-sans">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        <p class="mb-4"><a href="dashboard.php" class="text-blue-600 hover:underline">&larr; Back to Dashboard</a></p>
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-6">Manage Admin Users</h2>

        <?php if ($message): ?><div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg"><?php echo $error; ?></div><?php endif; ?>

        <div class="bg-white p-6 rounded-lg shadow-sm mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo $user_to_edit ? 'Edit User' : 'Add New User'; ?></h3>
            <form method="POST" action="users.php" class="space-y-4">
                <?php if ($user_to_edit): ?>
                    <input type="hidden" name="user_id" value="<?php echo $user_to_edit['id']; ?>">
                <?php endif; ?>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user_to_edit['name'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user_to_edit['email'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" <?php echo $user_to_edit ? '' : 'required'; ?> class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <?php if ($user_to_edit): ?><p class="mt-1 text-xs text-gray-500">Leave blank to keep current password.</p><?php endif; ?>
                </div>
                <div>
                    <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                        <?php echo $user_to_edit ? 'Update User' : 'Add User'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto bg-white rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                            <a href="generate_reset_link.php?user_id=<?php echo $user['id']; ?>" class="text-green-600 hover:text-green-900 ml-4">Get Reset Link</a>
                            <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure?');" class="text-red-600 hover:text-red-900 ml-4">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>