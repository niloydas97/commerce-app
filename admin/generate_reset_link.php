<?php
include '../config.php';
include 'auth_check.php';

$user_id = $_GET['user_id'] ?? 0;
$link_message = '';
$reset_link = '';

if ($user_id) {
    // 1. Generate a secure, unique token
    $token = bin2hex(random_bytes(32));
    
    // 2. Set an expiry time (e.g., 1 hour from now)
    $expires = date('Y-m-d H:i:s', time() + 3600); 

    // 3. Store the token in the database
    try {
        $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires]);

        // 4. Build the full link to show the admin
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        
        $base_path = str_replace('admin/generate_reset_link.php', '', $_SERVER['SCRIPT_NAME']);
        $base_path = rtrim($base_path, '/');
        
        $reset_link = $protocol . '://' . $host . $base_path . '/reset_password.php?token=' . $token;

        $link_message = "Here is the one-time reset link (valid for 1 hour). Please copy this and send it to the user:";
        
    } catch (Exception $e) {
        $link_message = "Error generating link: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Reset Link</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; }
        .link-box { padding: 20px; background: #f4f4f4; border: 1px solid #ddd; border-radius: 5px; }
        .link-box input { width: 100%; padding: 10px; font-size: 16px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="users.php">&larr; Back to Users</a></p>
        <h2>Generate Password Reset Link</h2>

        <?php if ($link_message): ?>
            <p><?php echo $link_message; ?></p>
            <div class="link-box">
                <input type="text" value="<?php echo htmlspecialchars($reset_link); ?>" readonly onclick="this.select();">
            </div>
        <?php else: ?>
            <p>Invalid user ID.</p>
        <?php endif; ?>
    </div>
</body>
</html>