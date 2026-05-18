<?php
namespace App;

/**
 * HuaweiOLT - SNMP client for Huawei OLT (MA5800/MA5600 series)
 * OIDs sourced from Bee Solutions Zabbix templates
 */
class HuaweiOLT {

    private $host;
    private $community;
    private $timeout;
    private $retries;

    // =========================================================
    // Standard MIB OIDs
    // =========================================================
    const OID_UPTIME        = '1.3.6.1.2.1.1.3.0';
    const OID_SYSNAME       = '1.3.6.1.2.1.1.5.0';
    const OID_SYSDESC       = '1.3.6.1.2.1.1.1.0';

    // Interface MIB
    const OID_IFNAME        = '1.3.6.1.2.1.31.1.1.1.1';   // ifName table
    const OID_IFOPERSTATUS  = '1.3.6.1.2.1.2.2.1.8';       // ifOperStatus
    const OID_IF_IN         = '1.3.6.1.2.1.31.1.1.1.6';    // ifHCInOctets
    const OID_IF_OUT        = '1.3.6.1.2.1.31.1.1.1.10';   // ifHCOutOctets

    // =========================================================
    // Huawei-specific OIDs (OLT GPON)
    // =========================================================
    // ONU states per PON port (1=online, 2=offline)
    const OID_ONU_STATUS    = '1.3.6.1.4.1.2011.6.128.1.1.2.46.1.15';
    // Unprovisioned / undiscovered ONUs
    const OID_UNPROV        = '1.3.6.1.4.1.2011.6.128.1.1.2.48.1.2';
    // PON port link status
    const OID_PON_LINK      = '1.3.6.1.4.1.2011.6.128.1.1.2.21.1.10';

    // PON optical parameters (per-port, ×0.01 for real value)
    const OID_PON_TEMP      = '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.1';  // °C
    const OID_PON_VOLTAGE   = '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.2';  // V
    const OID_PON_BIAS      = '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.3';  // mA
    const OID_PON_TXPOWER   = '1.3.6.1.4.1.2011.6.128.1.1.2.23.1.4';  // dBm
    const OID_PON_LASER     = '1.3.6.1.4.1.2011.6.128.1.1.2.21.1.9';  // 1=Online
    const OID_PON_TYPE      = '1.3.6.1.4.1.2011.6.128.1.1.2.22.1.28'; // GBIC class

    // Slot / board temperature
    const OID_SLOT_TEMP     = '1.3.6.1.4.1.2011.6.3.3.2.1.13.0';      // prefix.{slot}

    // Fan
    const OID_FAN_STATUS    = '1.3.6.1.4.1.2011.6.1.1.5.1.6';
    const OID_FAN_TEMP      = '1.3.6.1.4.1.2011.6.1.1.5.1.7';         // ×0.01
    const OID_FAN_ROTATION  = '1.3.6.1.4.1.2011.6.1.1.5.1.9';         // %

    public function __construct($host, $community = 'public', $timeout = 3000000, $retries = 2) {
        $this->host      = $host;
        $this->community = $community;
        $this->timeout   = $timeout;
        $this->retries   = $retries;

        // Return plain values (no type prefix)
        snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    }

    // =========================================================
    // Low-level helpers
    // =========================================================

    private function get($oid) {
        try {
            $val = @snmp2_get($this->host, $this->community, $oid, $this->timeout, $this->retries);
            return $val !== false ? trim($val, '"') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function walk($oid) {
        try {
            $result = @snmp2_walk($this->host, $this->community, $oid, $this->timeout, $this->retries);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Walk OID and return associative array [last_oid_segment => value]
     */
    private function walkIndexed($oid) {
        $raw = $this->walk($oid);
        $result = [];
        foreach ($raw as $fullOid => $value) {
            // Extract the last numeric segment as index
            if (preg_match('/\.(\d+)$/', $fullOid, $m)) {
                $result[$m[1]] = trim($value, '"');
            }
        }
        return $result;
    }

    // =========================================================
    // Test connection
    // =========================================================

    public function testConnection() {
        $val = $this->get(self::OID_SYSNAME);
        return $val !== null;
    }

    // =========================================================
    // Overview / General Stats
    // =========================================================

    public function getOverview() {
        $uptime  = $this->get(self::OID_UPTIME);
        $sysname = $this->get(self::OID_SYSNAME);
        $sysdesc = $this->get(self::OID_SYSDESC);

        // Uptime is in hundredths of a second
        $uptimeSeconds = $uptime ? intval($uptime) / 100 : 0;

        return [
            'sysname'        => $sysname ?? 'N/A',
            'sysdesc'        => $sysdesc ?? 'N/A',
            'uptime_seconds' => $uptimeSeconds,
            'uptime_human'   => $this->formatUptime($uptimeSeconds),
        ];
    }

    // =========================================================
    // GPON Interface Discovery
    // =========================================================

    /**
     * Discover all GPON interfaces and return [ifIndex => ifName]
     */
    public function getGPONInterfaces() {
        $ifNames = $this->walkIndexed(self::OID_IFNAME);
        $gpon = [];
        foreach ($ifNames as $idx => $name) {
            if (stripos($name, 'GPON') !== false) {
                $gpon[$idx] = $name;
            }
        }
        return $gpon; // [ifIndex => 'GPON 0/1/0']
    }

    // =========================================================
    // ONU Counts per PON port
    // =========================================================

    /**
     * Count ONUs by status for a given PON index
     * Status: 1 = online, 2 = offline
     */
    public function getONUCounts($ponIndex) {
        $oid    = self::OID_ONU_STATUS . '.' . $ponIndex;
        $states = $this->walk($oid);

        $online  = 0;
        $offline = 0;
        foreach ($states as $val) {
            $v = intval(trim($val, '"'));
            if ($v === 1) $online++;
            elseif ($v === 2) $offline++;
        }

        return [
            'authorized' => $online + $offline,
            'online'     => $online,
            'offline'    => $offline,
        ];
    }

    /**
     * Count total unprovisioned ONUs on the OLT
     */
    public function getUnprovisionedCount() {
        $result = $this->walk(self::OID_UNPROV);
        return count(array_filter($result, fn($v) => trim($v, '"') !== ''));
    }

    // =========================================================
    // PON Port Optical Stats
    // =========================================================

    public function getPONOptical($ponIndex) {
        $temp     = $this->get(self::OID_PON_TEMP    . '.' . $ponIndex);
        $voltage  = $this->get(self::OID_PON_VOLTAGE . '.' . $ponIndex);
        $bias     = $this->get(self::OID_PON_BIAS    . '.' . $ponIndex);
        $txpower  = $this->get(self::OID_PON_TXPOWER . '.' . $ponIndex);
        $laser    = $this->get(self::OID_PON_LASER   . '.' . $ponIndex);
        $type     = $this->get(self::OID_PON_TYPE    . '.' . $ponIndex);
        $link     = $this->get(self::OID_PON_LINK    . '.' . $ponIndex);

        return [
            'temperature' => $temp    !== null ? round(intval($temp) * 0.01, 1)    : null,
            'voltage'     => $voltage !== null ? round(intval($voltage) * 0.01, 2) : null,
            'bias'        => $bias    !== null ? round(intval($bias) * 0.01, 3)    : null,
            'tx_power'    => $txpower !== null ? round(intval($txpower) * 0.01, 2) : null,
            'laser'       => $laser   !== null ? (intval($laser) === 1 ? 'Online' : 'Offline') : 'N/A',
            'type'        => $this->gbicClass(intval($type ?? 0)),
            'link_status' => $link    !== null ? (intval($link) === 1 ? 'Up' : 'Down') : 'N/A',
        ];
    }

    // =========================================================
    // Interface Traffic
    // =========================================================

    public function getInterfaceTraffic($ifIndex) {
        $in  = $this->get(self::OID_IF_IN  . '.' . $ifIndex);
        $out = $this->get(self::OID_IF_OUT . '.' . $ifIndex);
        return [
            'bytes_in'  => $in  !== null ? intval($in)  : 0,
            'bytes_out' => $out !== null ? intval($out) : 0,
        ];
    }

    // =========================================================
    // Fan Stats
    // =========================================================

    public function getFans() {
        $statuses   = $this->walkIndexed(self::OID_FAN_STATUS);
        $temps      = $this->walkIndexed(self::OID_FAN_TEMP);
        $rotations  = $this->walkIndexed(self::OID_FAN_ROTATION);

        $fans = [];
        foreach ($statuses as $idx => $status) {
            $fans[] = [
                'index'    => $idx,
                'status'   => intval($status) === 1 ? 'OK' : 'Fault',
                'temp'     => isset($temps[$idx]) ? round(intval($temps[$idx]) * 0.01, 1) : null,
                'rotation' => isset($rotations[$idx]) ? intval($rotations[$idx]) : null,
            ];
        }
        return $fans;
    }

    // =========================================================
    // Full PON ports list
    // =========================================================

    public function getAllPONPorts() {
        $interfaces = $this->getGPONInterfaces();
        $ports = [];

        foreach ($interfaces as $ifIndex => $ifName) {
            $counts  = $this->getONUCounts($ifIndex);
            $optical = $this->getPONOptical($ifIndex);
            $traffic = $this->getInterfaceTraffic($ifIndex);

            $ports[] = [
                'if_index'    => $ifIndex,
                'name'        => $ifName,
                'authorized'  => $counts['authorized'],
                'online'      => $counts['online'],
                'offline'     => $counts['offline'],
                'link_status' => $optical['link_status'],
                'bits_in'     => $traffic['bytes_in'],
                'bits_out'    => $traffic['bytes_out'],
                'tx_power'    => $optical['tx_power'],
                'bias'        => $optical['bias'],
                'laser'       => $optical['laser'],
                'temperature' => $optical['temperature'],
                'voltage'     => $optical['voltage'],
                'type'        => $optical['type'],
            ];
        }

        // Sort by PON name
        usort($ports, fn($a, $b) => strnatcmp($a['name'], $b['name']));
        return $ports;
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function formatUptime($seconds) {
        $days    = floor($seconds / 86400);
        $hours   = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$days}d {$hours}h {$minutes}m";
    }

    private function gbicClass($code) {
        $map = [
            1 => 'C+', 2 => 'B+', 3 => 'A', 4 => 'N/A',
            5 => 'C',  6 => 'D',
        ];
        return $map[$code] ?? 'N/A';
    }
}
