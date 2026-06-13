<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid ID']);
}

$conn = getDBConnection();
$stmt = $conn->prepare("DELETE FROM olt_configs WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    jsonResponse(['success' => true, 'message' => 'OLT deleted']);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to delete']);
}
