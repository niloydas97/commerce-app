<?php
include '../config.php';
include 'auth_check.php';

header('Content-Type: application/json');

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$variant_id = $data['variant_id'] ?? 0;
$stock = $data['stock'] ?? null;

if ($variant_id > 0 && is_numeric($stock)) {
    try {
        $stmt = $pdo->prepare("UPDATE product_variants SET stock = ? WHERE id = ?");
        $stmt->execute([(int)$stock, $variant_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
}
?>