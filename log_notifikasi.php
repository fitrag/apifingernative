<?php
require_once 'layout.php';
renderHeader('Log Notifikasi', 'log');
?>

<style>
.terminal-container {
    background: #1e1e1e;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.terminal-header {
    background: #323232;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.terminal-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.terminal-dot.red { background: #ff5f56; }
.terminal-dot.yellow { background: #ffbd2e; }
.terminal-dot.green { background: #27ca40; }
.terminal-title {
    color: #888;
    font-size: 13px;
    margin-left: 12px;
    font-family: 'SF Mono', 'Consolas', monospace;
}
.terminal-status {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #888;
    font-size: 12px;
    font-family: 'SF Mono', 'Consolas', monospace;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #27ca40;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.terminal-body {
    padding: 16px;
    max-height: 600px;
    overflow-y: auto;
    font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
    font-size: 13px;
    line-height: 1.6;
}
.terminal-body::-webkit-scrollbar { width: 8px; }
.terminal-body::-webkit-scrollbar-track { background: #2d2d2d; }
.terminal-body::-webkit-scrollbar-thumb { background: #555; border-radius: 4px; }

.log-line {
    display: flex;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid #2d2d2d;
    align-items: flex-start;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
.log-line:hover { background: rgba(255,255,255,0.03); }
.log-line.new { background: rgba(59, 130, 246, 0.1); }

.log-badge {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
    min-width: 70px;
    text-align: center;
}
.log-timestamp {
    color: #6b7280;
    font-size: 12px;
    white-space: nowrap;
    min-width: 140px;
}
.log-message {
    color: #d4d4d4;
    word-break: break-word;
    flex: 1;
}
.log-message.success { color: #4ade80; }
.log-message.error { color: #f87171; }
.log-message.warning { color: #fbbf24; }
.log-message.skip { color: #9ca3af; }

.log-icon { width: 20px; text-align: center; }
.log-icon.success { color: #4ade80; }
.log-icon.error { color: #f87171; }
.log-icon.warning { color: #fbbf24; }
.log-icon.skip { color: #6b7280; }
.log-icon.info { color: #60a5fa; }

.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-tab {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-tab:hover { border-color: #3b82f6; color: #3b82f6; }
.filter-tab.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}
.filter-tab.active .count { background: rgba(255,255,255,0.2); }

.stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.stat-mini {
    background: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.3s;
}
.stat-mini .lnr { font-size: 20px; }
.stat-mini.success .lnr { color: #22c55e; }
.stat-mini.error .lnr { color: #ef4444; }
.stat-mini.warning .lnr { color: #f59e0b; }
.stat-mini .stat-value { font-size: 20px; font-weight: 700; color: #1e293b; }
.stat-mini .stat-label { font-size: 12px; color: #64748b; }

.control-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-control {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-control:hover { border-color: #3b82f6; color: #3b82f6; }
.btn-control.active { background: #22c55e; color: #fff; border-color: #22c55e; }
.btn-control.paused { background: #ef4444; color: #fff; border-color: #ef4444; }

.last-update {
    margin-left: auto;
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}
.empty-state .lnr {
    font-size: 48px;
    margin-bottom: 16px;
    color: #d1d5db;
}

.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="stats-row">
    <div class="stat-mini success">
        <span class="lnr lnr-checkmark-circle"></span>
        <div>
            <div class="stat-value" id="statSuccess">0</div>
            <div class="stat-label">Berhasil</div>
        </div>
    </div>
    <div class="stat-mini error">
        <span class="lnr lnr-cross-circle"></span>
        <div>
            <div class="stat-value" id="statError">0</div>
            <div class="stat-label">Gagal</div>
        </div>
    </div>
    <div class="stat-mini warning">
        <span class="lnr lnr-warning"></span>
        <div>
            <div class="stat-value" id="statWarning">0</div>
            <div class="stat-label">Peringatan</div>
        </div>
    </div>
</div>

<div class="control-bar">
    <button class="btn-control active" id="btnAutoRefresh" onclick="toggleAutoRefresh()">
        <span class="lnr lnr-sync"></span> <span id="btnText">Auto Refresh ON</span>
    </button>
    <button class="btn-control" onclick="fetchLogs()">
        <span class="lnr lnr-redo"></span> Refresh Manual
    </button>
    <button class="btn-control" onclick="clearFilter()">
        <span class="lnr lnr-layers"></span> Reset Filter
    </button>
    <div class="last-update">
        <span class="lnr lnr-clock"></span>
        <span>Update: <span id="lastUpdate">-</span></span>
    </div>
</div>

<div class="filter-tabs">
    <button class="filter-tab active" data-filter="all" onclick="setFilter('all', this)">
        <span class="lnr lnr-layers"></span> Semua <span class="count" id="countAll">0</span>
    </button>
    <button class="filter-tab" data-filter="success" onclick="setFilter('success', this)">
        <span class="lnr lnr-checkmark-circle"></span> Berhasil
    </button>
    <button class="filter-tab" data-filter="error" onclick="setFilter('error', this)">
        <span class="lnr lnr-cross-circle"></span> Gagal
    </button>
    <button class="filter-tab" data-filter="warning" onclick="setFilter('warning', this)">
        <span class="lnr lnr-warning"></span> Peringatan
    </button>
</div>

<div class="terminal-container">
    <div class="terminal-header">
        <div class="terminal-dot red"></div>
        <div class="terminal-dot yellow"></div>
        <div class="terminal-dot green"></div>
        <span class="terminal-title">Log Notifikasi WhatsApp — <span id="logCount">0</span> entries</span>
        <div class="terminal-status">
            <div class="status-dot" id="statusDot"></div>
            <span id="statusText">Live</span>
        </div>
    </div>
    <div class="terminal-body" id="logContainer">
        <div class="empty-state">
            <div class="loading-spinner"></div>
            <div style="margin-top: 16px;">Memuat log...</div>
        </div>
    </div>
</div>

<script>
let autoRefresh = true;
let refreshInterval = null;
let currentFilter = 'all';
let lastLogHash = '';

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderLogs(logs) {
    const container = document.getElementById('logContainer');
    
    if (logs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="lnr lnr-inbox"></span>
                <div>Belum ada log notifikasi</div>
            </div>
        `;
        return;
    }
    
    let html = '';
    logs.forEach((log, index) => {
        const display = (currentFilter === 'all' || log.status === currentFilter) ? 'flex' : 'none';
        html += `
            <div class="log-line" data-status="${log.status}" style="display: ${display};">
                <span class="log-icon ${log.status}"><span class="lnr ${log.icon}"></span></span>
                <span class="log-badge" style="background: ${log.color}20; color: ${log.color};">${escapeHtml(log.type)}</span>
                <span class="log-timestamp">${escapeHtml(log.timestamp)}</span>
                <span class="log-message ${log.status}">${escapeHtml(log.message)}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateStats(stats) {
    document.getElementById('statSuccess').textContent = stats.success;
    document.getElementById('statError').textContent = stats.error;
    document.getElementById('statWarning').textContent = stats.warning;
    document.getElementById('countAll').textContent = stats.total;
    document.getElementById('logCount').textContent = stats.total;
}

function fetchLogs() {
    fetch('api_log.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if logs changed
                const newHash = JSON.stringify(data.logs.slice(0, 5));
                if (newHash !== lastLogHash) {
                    renderLogs(data.logs);
                    lastLogHash = newHash;
                }
                
                updateStats(data.stats);
                document.getElementById('lastUpdate').textContent = data.lastUpdate;
            }
        })
        .catch(error => {
            console.error('Error fetching logs:', error);
        });
}

function setFilter(filter, btn) {
    currentFilter = filter;
    
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    
    // Filter logs
    document.querySelectorAll('.log-line').forEach(line => {
        if (filter === 'all' || line.dataset.status === filter) {
            line.style.display = 'flex';
        } else {
            line.style.display = 'none';
        }
    });
}

function clearFilter() {
    currentFilter = 'all';
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.filter-tab[data-filter="all"]').classList.add('active');
    document.querySelectorAll('.log-line').forEach(line => {
        line.style.display = 'flex';
    });
}

function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    const btn = document.getElementById('btnAutoRefresh');
    const btnText = document.getElementById('btnText');
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    
    if (autoRefresh) {
        btn.classList.remove('paused');
        btn.classList.add('active');
        btnText.textContent = 'Auto Refresh ON';
        statusDot.style.background = '#27ca40';
        statusDot.style.animation = 'pulse 2s infinite';
        statusText.textContent = 'Live';
        startAutoRefresh();
    } else {
        btn.classList.remove('active');
        btn.classList.add('paused');
        btnText.textContent = 'Auto Refresh OFF';
        statusDot.style.background = '#ef4444';
        statusDot.style.animation = 'none';
        statusText.textContent = 'Paused';
        stopAutoRefresh();
    }
}

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(fetchLogs, 3000); // Refresh every 3 seconds
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// Initial load
fetchLogs();
startAutoRefresh();

// Cleanup on page unload
window.addEventListener('beforeunload', stopAutoRefresh);
</script>

<?php renderFooter(); ?>
