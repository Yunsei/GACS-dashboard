'use strict';

// Load OLT list when OLT tab is clicked
document.addEventListener('DOMContentLoaded', () => {
    const oltTab = document.getElementById('olt-tab');
    if (oltTab) {
        oltTab.addEventListener('shown.bs.tab', loadOLTConfigs);
        // Auto-open OLT tab if URL hash matches
        if (window.location.hash === '#olt-config') {
            oltTab.click();
        }
    }
});

function loadOLTConfigs() {
    fetch('/api/olt-get-configs.php')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('olt-table-body');
            if (!data.success || !data.olts.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No OLTs configured yet</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            data.olts.forEach(olt => tbody.appendChild(buildOLTRow(olt)));
        })
        .catch(() => {
            document.getElementById('olt-table-body').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger">Failed to load OLTs</td></tr>';
        });
}

function buildOLTRow(olt) {
    const tr = document.createElement('tr');
    const statusBadge = olt.is_active == 1
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';
    tr.innerHTML = `
        <td><strong>${escHtml(olt.name)}</strong></td>
        <td>${escHtml(olt.ip_address)}</td>
        <td><code>${escHtml(olt.snmp_community)}</code></td>
        <td>${olt.snmp_port}</td>
        <td>${statusBadge}</td>
        <td>
            <button class="btn btn-sm btn-outline-primary me-1" onclick="editOLT(${olt.id},'${escAttr(olt.name)}','${escAttr(olt.ip_address)}','${escAttr(olt.snmp_community)}',${olt.snmp_port})">
                <i class="bi bi-pencil"></i>
            </button>
            <a class="btn btn-sm btn-outline-info me-1" href="/olt-monitor.php" title="Monitor">
                <i class="bi bi-activity"></i>
            </a>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteOLT(${olt.id},'${escAttr(olt.name)}')">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;
    return tr;
}

function showOLTForm() {
    document.getElementById('olt-form-container').style.display = '';
    document.getElementById('olt-form-title').textContent = 'Add OLT';
    document.getElementById('olt-id').value = '0';
    document.getElementById('olt-name').value = '';
    document.getElementById('olt-ip').value = '';
    document.getElementById('olt-community').value = 'public';
    document.getElementById('olt-port').value = '161';
    document.getElementById('olt-test-result').innerHTML = '';
}

function hideOLTForm() {
    document.getElementById('olt-form-container').style.display = 'none';
}

function editOLT(id, name, ip, community, port) {
    showOLTForm();
    document.getElementById('olt-form-title').textContent = 'Edit OLT';
    document.getElementById('olt-id').value = id;
    document.getElementById('olt-name').value = name;
    document.getElementById('olt-ip').value = ip;
    document.getElementById('olt-community').value = community;
    document.getElementById('olt-port').value = port;
}

function saveOLT() {
    const payload = {
        id:             parseInt(document.getElementById('olt-id').value),
        name:           document.getElementById('olt-name').value.trim(),
        ip_address:     document.getElementById('olt-ip').value.trim(),
        snmp_community: document.getElementById('olt-community').value.trim(),
        snmp_port:      parseInt(document.getElementById('olt-port').value),
    };
    if (!payload.name || !payload.ip_address) {
        alert('Name and IP address are required');
        return;
    }
    fetch('/api/olt-save-config.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                hideOLTForm();
                loadOLTConfigs();
                showToast('OLT saved successfully', 'success');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(e => alert('Request failed: ' + e.message));
}

function testOLTConnection() {
    const resultEl = document.getElementById('olt-test-result');
    resultEl.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm"></span> Testing…</span>';
    const payload = {
        ip_address:     document.getElementById('olt-ip').value.trim(),
        snmp_community: document.getElementById('olt-community').value.trim(),
    };
    fetch('/api/olt-test-config.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                resultEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> Connected — ${escHtml(data.sysname)} (up ${escHtml(data.uptime)})</span>`;
            } else {
                resultEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> ${escHtml(data.message)}</span>`;
            }
        })
        .catch(e => {
            resultEl.innerHTML = `<span class="text-danger">Request failed: ${escHtml(e.message)}</span>`;
        });
}

function deleteOLT(id, name) {
    if (!confirm(`Delete OLT "${name}"? This cannot be undone.`)) return;
    fetch('/api/olt-delete-config.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id}),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadOLTConfigs();
                showToast('OLT deleted', 'warning');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(e => alert('Request failed: ' + e.message));
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
    return String(str).replace(/'/g, "\\'");
}

function showToast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} alert-dismissible fade show position-fixed bottom-0 end-0 m-3`;
    el.style.zIndex = 9999;
    el.innerHTML = `${escHtml(msg)} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}
