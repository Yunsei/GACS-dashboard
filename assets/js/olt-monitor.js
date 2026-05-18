'use strict';

let currentOltId = null;

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadOLTList();
    document.getElementById('oltSelector').addEventListener('change', function () {
        currentOltId = this.value;
        if (currentOltId) {
            document.getElementById('overviewSection').style.display = '';
            document.getElementById('emptyState').style.display = 'none';
            loadOLTOverview(currentOltId);
            loadPONPorts(currentOltId);
        } else {
            document.getElementById('overviewSection').style.display = 'none';
            document.getElementById('emptyState').style.display = '';
        }
    });
});

function refreshOLT() {
    if (!currentOltId) return;
    loadOLTOverview(currentOltId);
    loadPONPorts(currentOltId);
}

// ── OLT List ──────────────────────────────────────────────────────────────────
function loadOLTList() {
    fetch('/api/olt-get-configs.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('oltSelector');
            sel.innerHTML = '<option value="">-- Select OLT --</option>';
            if (data.success && data.olts.length) {
                data.olts.forEach(olt => {
                    const opt = document.createElement('option');
                    opt.value = olt.id;
                    opt.textContent = `${olt.name} (${olt.ip_address})`;
                    sel.appendChild(opt);
                });
            }
        })
        .catch(() => {});
}

// ── Overview ──────────────────────────────────────────────────────────────────
function loadOLTOverview(oltId) {
    resetOverviewCards();
    fetch(`/api/olt-get-overview.php?olt_id=${oltId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showAlert('danger', data.message); return; }
            document.getElementById('ov-sysname').textContent       = data.sysname || data.olt_name;
            document.getElementById('ov-online').textContent         = data.total_online.toLocaleString();
            document.getElementById('ov-offline').textContent        = data.total_offline.toLocaleString();
            document.getElementById('ov-unprovisioned').textContent  = data.unprovisioned;
            document.getElementById('ov-auth').textContent           = data.total_auth.toLocaleString();
            document.getElementById('ov-uptime').textContent         = data.uptime;
            document.getElementById('lastUpdated').textContent       = 'Updated: ' + new Date().toLocaleTimeString();
            renderFans(data.fans);
        })
        .catch(e => showAlert('danger', 'Failed to fetch overview: ' + e.message));
}

function resetOverviewCards() {
    ['ov-sysname','ov-online','ov-offline','ov-unprovisioned','ov-auth','ov-uptime'].forEach(id => {
        document.getElementById(id).textContent = '…';
    });
}

// ── PON Ports ─────────────────────────────────────────────────────────────────
function loadPONPorts(oltId) {
    const tbody = document.getElementById('portsTableBody');
    const loading = document.getElementById('portsLoading');
    tbody.innerHTML = '';
    loading.style.display = '';

    fetch(`/api/olt-get-ports.php?olt_id=${oltId}`)
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            if (!data.success) { tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">${data.message}</td></tr>`; return; }
            if (!data.ports.length) { tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No PON ports found</td></tr>'; return; }
            data.ports.forEach(port => tbody.appendChild(buildPortRow(port)));
        })
        .catch(e => {
            loading.style.display = 'none';
            tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">Error: ${e.message}</td></tr>`;
        });
}

function buildPortRow(port) {
    const tr = document.createElement('tr');

    const onlineRatio = port.onu_authorized > 0
        ? Math.round((port.onu_online / port.onu_authorized) * 100)
        : 0;
    const statusBadge = port.link_status == 1
        ? '<span class="badge bg-success">Up</span>'
        : '<span class="badge bg-secondary">Down</span>';

    const txPower = port.tx_power !== null ? parseFloat(port.tx_power).toFixed(2) + ' dBm' : '-';
    const bias    = port.bias_current !== null ? parseFloat(port.bias_current).toFixed(2) : '-';
    const laser   = port.laser_power !== null  ? parseFloat(port.laser_power).toFixed(2) + ' dBm' : '-';
    const temp    = port.temperature !== null  ? parseFloat(port.temperature).toFixed(1) : '-';

    const inBps  = port.if_in_bps  !== null ? formatBits(port.if_in_bps)  : '-';
    const outBps = port.if_out_bps !== null ? formatBits(port.if_out_bps) : '-';

    tr.innerHTML = `
        <td><strong>${escHtml(port.name)}</strong></td>
        <td class="text-center">${port.onu_authorized}</td>
        <td class="text-center"><span class="text-success fw-bold">${port.onu_online}</span></td>
        <td class="text-center"><span class="${port.onu_offline > 0 ? 'text-danger fw-bold' : 'text-muted'}">${port.onu_offline}</span></td>
        <td class="text-center">${statusBadge}</td>
        <td class="text-end text-info">${inBps}</td>
        <td class="text-end text-warning">${outBps}</td>
        <td class="text-center">${txPower}</td>
        <td class="text-center">${bias}</td>
        <td class="text-center">${laser}</td>
        <td class="text-center">${colorTemp(temp)}</td>
    `;
    return tr;
}

// ── Fans ──────────────────────────────────────────────────────────────────────
function renderFans(fans) {
    const container = document.getElementById('fansContainer');
    if (!fans || !fans.length) {
        container.innerHTML = '<p class="text-muted text-center">No fan data available</p>';
        return;
    }
    let html = '<div class="row g-3">';
    fans.forEach((fan, i) => {
        const statusClass = fan.status == 1 ? 'success' : 'danger';
        const statusText  = fan.status == 1 ? 'Running' : 'Fault';
        const rotation    = fan.rotation ? fan.rotation.toLocaleString() + ' RPM' : '-';
        const temp        = fan.temperature !== null ? parseFloat(fan.temperature).toFixed(1) + ' °C' : '-';
        html += `
            <div class="col-md-3">
                <div class="card border-${statusClass}">
                    <div class="card-body text-center">
                        <i class="bi bi-fan text-${statusClass}" style="font-size:2rem;"></i>
                        <h6 class="mt-2 mb-0">Fan ${i + 1}</h6>
                        <span class="badge bg-${statusClass} mt-1">${statusText}</span>
                        <div class="mt-2 small text-muted">
                            <div>${rotation}</div>
                            <div>${temp}</div>
                        </div>
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatBits(bps) {
    if (bps === null || bps === undefined) return '-';
    bps = parseFloat(bps);
    if (bps >= 1e9)  return (bps / 1e9).toFixed(2) + ' Gbps';
    if (bps >= 1e6)  return (bps / 1e6).toFixed(2) + ' Mbps';
    if (bps >= 1e3)  return (bps / 1e3).toFixed(2) + ' Kbps';
    return bps.toFixed(0) + ' bps';
}

function colorTemp(t) {
    if (t === '-') return '-';
    const v = parseFloat(t);
    if (v >= 70) return `<span class="text-danger fw-bold">${t} °C</span>`;
    if (v >= 55) return `<span class="text-warning fw-bold">${t} °C</span>`;
    return `<span class="text-success">${t} °C</span>`;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showAlert(type, msg) {
    const el = document.createElement('div');
    el.className = `alert alert-${type} alert-dismissible fade show`;
    el.innerHTML = `${escHtml(msg)} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.content-wrapper').prepend(el);
    setTimeout(() => el.remove(), 6000);
}
