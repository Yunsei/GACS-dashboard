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
    jsonResponse(['success' => false, 'message' => 'OLT not found or inactive']);
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\HuaweiOLT;

set_time_limit(60);

$olt = new HuaweiOLT($config['ip_address'], $config['snmp_community']);

$overview     = $olt->getOverview();
$fans         = $olt->getFans();
$unprov       = $olt->getUnprovisionedCount();

// Quick ONU totals - walk all PON ports
$interfaces = $olt->getGPONInterfaces();
$totalOnline  = 0;
$totalOffline = 0;
$totalAuth    = 0;

foreach ($interfaces as $idx => $name) {
    $counts = $olt->getONUCounts($idx);
    $totalOnline  += $counts['online'];
    $totalOffline += $counts['offline'];
    $totalAuth    += $counts['authorized'];
}

jsonResponse([
    'success'       => true,
    'olt_name'      => $config['name'],
    'sysname'       => $overview['sysname'],
    'uptime'        => $overview['uptime_human'],
    'total_auth'    => $totalAuth,
    'total_online'  => $totalOnline,
    'total_offline' => $totalOffline,
    'unprovisioned' => $unprov,
    'pon_count'     => count($interfaces),
    'fans'          => $fans,
]);
