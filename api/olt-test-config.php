<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$ip        = trim($data['ip_address'] ?? '');
$community = trim($data['snmp_community'] ?? 'public');

if (empty($ip)) {
    jsonResponse(['success' => false, 'message' => 'IP address required']);
}

if (!class_exists('\\App\\HuaweiOLT')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use App\HuaweiOLT;

$olt = new HuaweiOLT($ip, $community);

if ($olt->testConnection()) {
    $overview = $olt->getOverview();
    jsonResponse([
        'success' => true,
        'message' => 'Connection successful',
        'sysname' => $overview['sysname'],
        'uptime'  => $overview['uptime_human'],
    ]);
} else {
    jsonResponse(['success' => false, 'message' => 'Connection failed - check IP and SNMP community']);
}
