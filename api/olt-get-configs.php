<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireLogin();

$conn = getDBConnection();
$result = $conn->query("SELECT id, name, ip_address, snmp_community, snmp_port, is_active, created_at FROM olt_configs ORDER BY id ASC");

$configs = [];
while ($row = $result->fetch_assoc()) {
    $configs[] = $row;
}

jsonResponse(['success' => true, 'configs' => $configs]);
