<?php
include '../config.php';
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session
header("Location: index.php"); // Redirect to login page
exit;
?>