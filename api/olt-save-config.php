<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

$name      = trim($data['name'] ?? '');
$ip        = trim($data['ip_address'] ?? '');
$community = trim($data['snmp_community'] ?? 'public');
$port      = intval($data['snmp_port'] ?? 161);
$id        = intval($data['id'] ?? 0);

if (empty($name) || empty($ip)) {
    jsonResponse(['success' => false, 'message' => 'Name and IP address are required']);
}

$conn = getDBConnection();

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE olt_configs SET name=?, ip_address=?, snmp_community=?, snmp_port=? WHERE id=?");
    $stmt->bind_param("sssii", $name, $ip, $community, $port, $id);
} else {
    $stmt = $conn->prepare("INSERT INTO olt_configs (name, ip_address, snmp_community, snmp_port, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("sssi", $name, $ip, $community, $port);
}

if ($stmt->execute()) {
    $newId = $id > 0 ? $id : $conn->insert_id;
    jsonResponse(['success' => true, 'message' => 'OLT saved successfully', 'id' => $newId]);
} else {
    jsonResponse(['success' => false, 'message' => 'Failed to save OLT: ' . $conn->error]);
}
