<?php
include '../config.php';
include 'auth_check.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order']) && is_array($data['order'])) {
    $order = $data['order'];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE homepage_sections SET sort_order = ? WHERE id = ?");
        foreach ($order as $position => $id) {
            $stmt->execute([$position + 1, $id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Section order updated.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
}