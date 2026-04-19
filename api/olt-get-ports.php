<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireLogin();

$oltId = intval($_GET['olt_id'] ?? 0);
if ($oltId <= 0) {
    jsonResponse(['success' => false, 'message' => 'OLT ID required']);
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM olt_configs WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $oltId);
$stmt->execute();
$config = $stmt->get_result()->fetch_assoc();

if (!$config) {
    jsonResponse(['success' => false, 'message' => 'OLT not found']);
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\HuaweiOLT;

set_time_limit(120);

$olt   = new HuaweiOLT($config['ip_address'], $config['snmp_community']);
$ports = $olt->getAllPONPorts();

jsonResponse([
    'success' => true,
    'ports'   => $ports,
    'count'   => count($ports),
]);
