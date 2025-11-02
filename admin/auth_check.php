<?php
// This script checks if the admin user is logged in.
// If not, it redirects them to the login page.
if (!isset($_SESSION['admin_user_id'])) {
    header("Location: index.php");
    exit;
}
?>