<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'OLT Monitor';
$currentPage = 'olt-monitor';

include __DIR__ . '/views/layouts/header.php';
?>

<!-- OLT Selector -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <label class="input-group-text" for="oltSelector"><i class="bi bi-hdd-rack"></i></label>
            <select class="form-select" id="oltSelector">
                <option value="">-- Select OLT --</option>
            </select>
            <button class="btn btn-outline-secondary" onclick="refreshOLT()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
    <div class="col-md-6 text-end">
        <span id="lastUpdated" class="text-muted small"></span>
    </div>
</div>

<!-- Overview Cards -->
<div id="overviewSection" style="display:none;">
    <div class="stats-grid" id="overviewCards">
        <div class="stat-card primary">
            <div class="stat-info">
                <h3 id="ov-sysname">-</h3>
                <p>System Name</p>
            </div>
            <div class="stat-icon"><i class="bi bi-hdd-rack"></i></div>
        </div>
        <div class="stat-card success">
            <div class="stat-info">
                <h3 id="ov-online">-</h3>
                <p>ONUs Online</p>
            </div>
            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
        </div>
        <div class="stat-card danger">
            <div class="stat-info">
                <h3 id="ov-offline">-</h3>
                <p>ONUs Offline</p>
            </div>
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-info">
                <h3 id="ov-unprovisioned">-</h3>
                <p>Unprovisioned</p>
            </div>
            <div class="stat-icon"><i class="bi bi-question-circle"></i></div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg,#6c757d,#495057);color:#fff;">
            <div class="stat-info">
                <h3 id="ov-auth">-</h3>
                <p>Authorized</p>
            </div>
            <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg,#0dcaf0,#0aa2c0);color:#fff;">
            <div class="stat-info">
                <h3 id="ov-uptime">-</h3>
                <p>Uptime</p>
            </div>
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row mt-3">
        <div class="col-12">
            <ul class="nav nav-tabs" id="oltTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ports-tab" data-bs-toggle="tab" data-bs-target="#ports-pane" type="button" role="tab">
                        <i class="bi bi-diagram-3"></i> PON Ports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="fans-tab" data-bs-toggle="tab" data-bs-target="#fans-pane" type="button" role="tab">
                        <i class="bi bi-wind"></i> Fans
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="oltTabsContent">
                <!-- PON Ports Tab -->
                <div class="tab-pane fade show active" id="ports-pane" role="tabpanel">
                    <div class="card mt-0" style="border-top-left-radius:0;border-top-right-radius:0;">
                        <div class="card-body p-0">
                            <div id="portsLoading" class="text-center py-4" style="display:none;">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="ms-2">Loading PON ports...</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0" id="portsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>PON Port</th>
                                            <th class="text-center">Auth</th>
                                            <th class="text-center">Online</th>
                                            <th class="text-center">Offline</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-end">Bits In</th>
                                            <th class="text-end">Bits Out</th>
                                            <th class="text-center">Tx Power</th>
                                            <th class="text-center">Bias (mA)</th>
                                            <th class="text-center">Laser (dBm)</th>
                                            <th class="text-center">Temp (°C)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="portsTableBody">
                                        <tr><td colspan="11" class="text-center text-muted py-3">Select an OLT to load ports</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fans Tab -->
                <div class="tab-pane fade" id="fans-pane" role="tabpanel">
                    <div class="card mt-0" style="border-top-left-radius:0;border-top-right-radius:0;">
                        <div class="card-body">
                            <div id="fansContainer">
                                <p class="text-muted text-center">Select an OLT to load fan data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Empty state -->
<div id="emptyState" class="text-center py-5">
    <i class="bi bi-hdd-rack" style="font-size:3rem;color:#6c757d;"></i>
    <p class="text-muted mt-2">Select an OLT from the dropdown to start monitoring.</p>
    <a href="/configuration.php#olt-config" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-gear"></i> Configure OLTs
    </a>
</div>

<script src="/assets/js/olt-monitor.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
