<?php
include 'config.php'; // Use config from the root

$token = $_GET['token'] ?? '';
$error = '';
$message = '';
$valid_token = false;
$user_id = null;
$reset_id = null;

if (empty($token)) {
    $error = "No token provided.";
} else {
    // Check if the token is valid and not expired
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();

    if ($reset_data) {
        $valid_token = true;
        $user_id = $reset_data['user_id'];
        $reset_id = $reset_data['id']; // ID of the reset token itself
    } else {
        $error = "This reset link is invalid or has expired.";
    }
}

// Handle the form submission
if ($valid_token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // --- Success! Update the password ---
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        try {
            // 1. Update user's password
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);

            // 2. Delete the used token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
            $stmt->execute([$reset_id]);

            $pdo->commit();
            
            $message = "Password reset successfully! You can now log in with your new password.";
            $valid_token = false; // Hide the form

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: grid; place-items: center; min-height: 100vh; background-color: #f4f7f6; }
        .reset-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 350px; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .error { padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="reset-box">
        <h2>Set New Password</h2>

        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
            <a href="admin/index.php">Go to Login</a>
        <?php elseif ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="password_confirm" required>
            </div>
            <button type="submit" class="confirm-button" style="width: 100%;">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>