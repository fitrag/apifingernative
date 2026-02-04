<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi</title>
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/vue-router@4/dist/vue-router.global.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f8fafc; min-height: 100vh; }
        .sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 260px; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); z-index: 100; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h1 { color: #fff; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .sidebar-nav { padding: 16px 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; margin-bottom: 4px; color: #94a3b8; text-decoration: none; border-radius: 8px; transition: all 0.2s; font-size: 14px; cursor: pointer; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: #3b82f6; color: #fff; }
        .nav-item .lnr { font-size: 18px; width: 24px; text-align: center; }
        .nav-section { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 16px 16px 8px; margin-top: 8px; }
        .main-content { margin-left: 260px; min-height: 100vh; }
        .topbar { background: #fff; padding: 16px 32px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .topbar h2 { font-size: 24px; color: #1e293b; font-weight: 600; }
        .topbar-time { color: #64748b; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .content { padding: 32px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); padding: 24px; margin-bottom: 24px; }
        .card-header { margin-bottom: 20px; }
        .card-title { font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .card-subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-card .lnr { font-size: 32px; margin-bottom: 12px; display: block; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .stat-card .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stat-card.blue { border-left: 4px solid #3b82f6; } .stat-card.blue .lnr { color: #3b82f6; }
        .stat-card.green { border-left: 4px solid #22c55e; } .stat-card.green .lnr { color: #22c55e; }
        .stat-card.yellow { border-left: 4px solid #eab308; } .stat-card.yellow .lnr { color: #eab308; }
        .stat-card.red { border-left: 4px solid #ef4444; } .stat-card.red .lnr { color: #ef4444; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; }
        td { color: #334155; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .form-hint { font-size: 12px; color: #6b7280; margin-top: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-primary { background: #3b82f6; color: #fff; } .btn-primary:hover { background: #2563eb; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .terminal-container { background: #1e1e1e; border-radius: 12px; overflow: hidden; }
        .terminal-header { background: #323232; padding: 12px 16px; display: flex; align-items: center; gap: 8px; }
        .terminal-dot { width: 12px; height: 12px; border-radius: 50%; }
        .terminal-dot.red { background: #ff5f56; } .terminal-dot.yellow { background: #ffbd2e; } .terminal-dot.green { background: #27ca40; }
        .terminal-title { color: #888; font-size: 13px; margin-left: 12px; font-family: monospace; }
        .terminal-status { margin-left: auto; display: flex; align-items: center; gap: 8px; color: #888; font-size: 12px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #27ca40; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .terminal-body { padding: 16px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 13px; }
        .log-line { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px solid #2d2d2d; }
        .log-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; text-transform: uppercase; min-width: 70px; text-align: center; }
        .log-timestamp { color: #6b7280; font-size: 12px; min-width: 140px; }
        .log-message { color: #d4d4d4; word-break: break-word; flex: 1; }
        .log-message.success { color: #4ade80; } .log-message.error { color: #f87171; } .log-message.warning { color: #fbbf24; }
        .log-icon { width: 20px; text-align: center; }
        .log-icon.success { color: #4ade80; } .log-icon.error { color: #f87171; } .log-icon.warning { color: #fbbf24; } .log-icon.info { color: #60a5fa; }
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab { padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; border: 1px solid #e2e8f0; background: #fff; color: #64748b; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .filter-tab:hover { border-color: #3b82f6; color: #3b82f6; }
        .filter-tab.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #f1f5f9; border-radius: 8px; cursor: pointer; border: 2px solid transparent; }
        .checkbox-item.checked { background: #fee2e2; border-color: #ef4444; }
        .checkbox-item input { width: 18px; height: 18px; accent-color: #ef4444; }
        .time-display { background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
        .time-display .lnr { font-size: 24px; color: #3b82f6; }
        .time-value { font-size: 18px; font-weight: 600; color: #1e293b; }
        .time-label { font-size: 12px; color: #64748b; }
        /* Button Icon & Selection */
        .btn-icon { width: 32px; height: 32px; border: none; background: #f1f5f9; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s; margin-right: 4px; }
        .btn-icon:hover { background: #3b82f6; color: #fff; }
        .btn-icon.danger:hover { background: #ef4444; }
        .selected-row { background: #eff6ff !important; }
        tr input[type="checkbox"] { width: 16px; height: 16px; accent-color: #3b82f6; cursor: pointer; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; }
        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .empty-state .lnr { font-size: 48px; margin-bottom: 16px; color: #d1d5db; display: block; }
        /* Loading State Styles */
        .loading-container { text-align: center; padding: 60px 20px; }
        .loading-spinner { width: 48px; height: 48px; border: 4px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: #64748b; font-size: 14px; }
        .skeleton { background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .skeleton-stat { height: 100px; }
        .skeleton-row { height: 48px; margin-bottom: 8px; }
        .skeleton-text { height: 20px; width: 60%; }
        .btn-loading { position: relative; color: transparent !important; }
        .btn-loading::after { content: ''; position: absolute; width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
        .fade-enter-from, .fade-leave-to { opacity: 0; }
    </style>
</head>
<body>
<div id="app">
    <aside class="sidebar">
        <div class="sidebar-header"><h1><span class="lnr lnr-calendar-full"></span> Absensi</h1></div>
        <nav class="sidebar-nav">
            <div class="nav-section">Menu Utama</div>
            <router-link to="/" class="nav-item" active-class="active" exact><span class="lnr lnr-chart-bars"></span> Dashboard</router-link>
            <router-link to="/absensi" class="nav-item" active-class="active"><span class="lnr lnr-list"></span> Data Absensi</router-link>
            <router-link to="/karyawan" class="nav-item" active-class="active"><span class="lnr lnr-users"></span> Data Karyawan</router-link>
            <div class="nav-section">Laporan</div>
            <router-link to="/laporan/terlambat" class="nav-item" active-class="active"><span class="lnr lnr-clock"></span> Keterlambatan</router-link>
            <router-link to="/laporan/tidak-hadir" class="nav-item" active-class="active"><span class="lnr lnr-cross-circle"></span> Tidak Hadir</router-link>
            <router-link to="/laporan/bolos" class="nav-item" active-class="active"><span class="lnr lnr-warning"></span> Bolos</router-link>
            <div class="nav-section">Pengaturan</div>
            <router-link to="/pengaturan" class="nav-item" active-class="active"><span class="lnr lnr-cog"></span> Pengaturan</router-link>
            <router-link to="/log" class="nav-item" active-class="active"><span class="lnr lnr-envelope"></span> Log Notifikasi</router-link>
        </nav>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <h2>{{ pageTitle }}</h2>
            <div class="topbar-time"><span class="lnr lnr-calendar-full"></span> {{ currentDate }}</div>
        </header>
        <div class="content"><router-view v-slot="{ Component }"><transition name="fade" mode="out-in"><component :is="Component" /></transition></router-view></div>
    </main>
</div>
<script>
const { createApp, ref, computed, onMounted, onUnmounted, watch } = Vue;
const { createRouter, createWebHashHistory } = VueRouter;

// Utility: Debounce untuk mengurangi request berlebihan
const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
};

// Utility: Cache sederhana untuk API responses
const apiCache = new Map();
const cachedFetch = async (url, ttl = 30000) => {
    const cached = apiCache.get(url);
    if (cached && Date.now() - cached.time < ttl) return cached.data;
    const res = await fetch(url);
    const data = await res.json();
    apiCache.set(url, { data, time: Date.now() });
    return data;
};

// Loading Component
const LoadingState = `<div class="loading-container"><div class="loading-spinner"></div><div class="loading-text">Memuat data...</div></div>`;
const SkeletonStats = `<div class="stats-grid"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>`;
const SkeletonTable = `<div class="card"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>`;

// Dashboard Component
const Dashboard = {
    template: `
        <div>
            <div v-if="loading" class="stats-grid"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>
            <div v-else class="stats-grid">
                <div class="stat-card blue"><span class="lnr lnr-users"></span><div class="value">{{ stats.total_karyawan || 0 }}</div><div class="label">Total Karyawan</div></div>
                <div class="stat-card green"><span class="lnr lnr-checkmark-circle"></span><div class="value">{{ stats.hadir || 0 }}</div><div class="label">Hadir Hari Ini</div></div>
                <div class="stat-card yellow"><span class="lnr lnr-clock"></span><div class="value">{{ stats.terlambat || 0 }}</div><div class="label">Terlambat Hari Ini</div></div>
                <div class="stat-card red"><span class="lnr lnr-cross-circle"></span><div class="value">{{ tidakHadir }}</div><div class="label">Tidak Hadir</div></div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><span class="lnr lnr-list"></span> Absensi Terbaru</div><div class="card-subtitle">10 data absensi terakhir</div></div>
                <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
                <div v-else class="table-container">
                    <table><thead><tr><th>Nama</th><th>Badge</th><th>Waktu</th><th>Tipe</th><th>Status</th></tr></thead>
                    <tbody><tr v-for="row in absensi" :key="row.id">
                        <td>{{ row.name }}</td><td>{{ row.badgenumber }}</td><td>{{ row.tanggal }} {{ row.jam }}</td>
                        <td>{{ row.checktype == '0' ? 'Check In' : 'Check Out' }}</td>
                        <td><span :class="getStatusClass(row)"><span :class="getStatusIcon(row)"></span> {{ getStatusText(row) }}</span></td>
                    </tr></tbody></table>
                </div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const stats = ref({}); const absensi = ref([]); const settings = ref({});
        const tidakHadir = computed(() => (stats.value.total_karyawan || 0) - (stats.value.hadir || 0));
        const load = async () => {
            loading.value = true;
            const res = await fetch('api.php?action=dashboard'); const data = await res.json();
            stats.value = data.stats; absensi.value = data.absensi; settings.value = data.settings;
            loading.value = false;
        };
        const getStatusClass = (row) => { if (row.checktype != '0') return 'badge badge-info'; if (row.jam <= settings.value.jam_masuk) return 'badge badge-success'; if (row.jam <= settings.value.jam_terlambat) return 'badge badge-warning'; return 'badge badge-danger'; };
        const getStatusIcon = (row) => { if (row.checktype != '0') return 'lnr lnr-exit'; if (row.jam <= settings.value.jam_masuk) return 'lnr lnr-checkmark-circle'; if (row.jam <= settings.value.jam_terlambat) return 'lnr lnr-clock'; return 'lnr lnr-cross-circle'; };
        const getStatusText = (row) => { if (row.checktype != '0') return 'Pulang'; if (row.jam <= settings.value.jam_masuk) return 'Tepat Waktu'; if (row.jam <= settings.value.jam_terlambat) return 'Terlambat'; return 'Tidak Hadir'; };
        onMounted(load);
        return { loading, stats, absensi, tidakHadir, getStatusClass, getStatusIcon, getStatusText };
    }
};

// Absensi Component
const Absensi = {
    template: `
        <div class="card">
            <div class="filter-bar">
                <label class="form-label" style="margin:0">Tanggal:</label>
                <input type="date" v-model="tanggal" class="form-input" style="width:auto">
                <button class="btn btn-primary" :class="{' btn-loading': loading}" :disabled="loading" @click="load"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
            </div>
            <div class="card-header"><div class="card-title"><span class="lnr lnr-list"></span> Data Absensi</div><div class="card-subtitle">Total: {{ data.length }} data</div></div>
            <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
            <div v-else class="table-container">
                <table><thead><tr><th>No</th><th>Badge</th><th>Nama</th><th>Waktu</th><th>Tipe</th></tr></thead>
                <tbody><tr v-for="(row, i) in data" :key="row.id"><td>{{ i+1 }}</td><td>{{ row.badgenumber }}</td><td>{{ row.name }}</td><td>{{ row.jam }}</td><td>{{ row.checktype == '0' ? 'Check In' : 'Check Out' }}</td></tr></tbody></table>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const tanggal = ref(new Date().toISOString().split('T')[0]); const data = ref([]);
        const load = async () => { loading.value = true; const res = await fetch('api.php?action=absensi&tanggal=' + tanggal.value); data.value = (await res.json()).data; loading.value = false; };
        onMounted(load);
        return { loading, tanggal, data, load };
    }
};

// Karyawan Component
const Karyawan = {
    template: `
        <div>
            <div v-if="message" :class="'alert alert-' + messageType"><span :class="'lnr lnr-' + (messageType=='success'?'checkmark-circle':'warning')"></span> {{ message }}</div>
            
            <!-- Import Modal -->
            <div v-if="showImport" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000" @click.self="showImport=false">
                <div class="card" style="width:500px;max-width:90%;margin:0">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-upload"></span> Import Data Karyawan</div></div>
                    <div class="form-group">
                        <label class="form-label">File Excel/CSV</label>
                        <input type="file" ref="fileInput" accept=".csv,.xlsx,.xls" class="form-input" @change="handleFile">
                        <div class="form-hint">Format: CSV atau Excel (.xlsx). <a href="import_karyawan.php?action=template" style="color:#3b82f6">Download Template</a></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mode Import</label>
                        <select v-model="importMode" class="form-input">
                            <option value="skip">Skip jika sudah ada</option>
                            <option value="update">Update jika sudah ada</option>
                        </select>
                    </div>
                    <div v-if="importResult" style="background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:16px;font-size:13px">
                        <div><strong>Hasil Import:</strong></div>
                        <div style="color:#22c55e">✓ {{ importResult.imported }} data baru</div>
                        <div style="color:#3b82f6">↻ {{ importResult.updated }} data diupdate</div>
                        <div style="color:#64748b">○ {{ importResult.skipped }} data dilewati</div>
                        <div v-if="importResult.errors.length" style="color:#ef4444;margin-top:8px">
                            <div v-for="err in importResult.errors.slice(0,5)">{{ err }}</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px">
                        <button class="btn btn-primary" :class="{'btn-loading':importing}" :disabled="!selectedFile||importing" @click="doImport" style="flex:1"><span class="lnr lnr-upload"></span> Import</button>
                        <button class="btn" style="background:#e2e8f0;color:#475569" @click="showImport=false">Batal</button>
                    </div>
                </div>
            </div>
            
            <!-- Edit Modal -->
            <div v-if="showEdit" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000" @click.self="showEdit=false">
                <div class="card" style="width:500px;max-width:90%;margin:0">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-pencil"></span> {{ editForm.userid ? 'Edit' : 'Tambah' }} Karyawan</div></div>
                    <div class="form-group">
                        <label class="form-label">Badge Number *</label>
                        <input type="text" v-model="editForm.badgenumber" class="form-input" placeholder="001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIS (Title) *</label>
                        <input type="text" v-model="editForm.title" class="form-input" placeholder="NIS / Nomor Induk">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama *</label>
                        <input type="text" v-model="editForm.name" class="form-input" placeholder="Nama Karyawan">
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. WhatsApp</label>
                        <input type="text" v-model="editForm.Card" class="form-input" placeholder="628123456789">
                    </div>
                    <div class="form-group">
                        <label class="form-label">FPHONE</label>
                        <input type="text" v-model="editForm.FPHONE" class="form-input" placeholder="Nomor telepon lain">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dept ID</label>
                        <input type="number" v-model="editForm.defaultdeptid" class="form-input" placeholder="1">
                    </div>
                    <div style="display:flex;gap:12px">
                        <button class="btn btn-primary" :class="{'btn-loading':saving}" :disabled="saving" @click="saveKaryawan" style="flex:1"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                        <button class="btn" style="background:#e2e8f0;color:#475569" @click="showEdit=false">Batal</button>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirm Modal -->
            <div v-if="showDeleteConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000">
                <div class="card" style="width:400px;max-width:90%;margin:0;text-align:center">
                    <div style="font-size:48px;color:#ef4444;margin-bottom:16px"><span class="lnr lnr-warning"></span></div>
                    <h3 style="margin-bottom:8px">Konfirmasi Hapus</h3>
                    <p style="color:#64748b;margin-bottom:24px">{{ deleteMessage }}</p>
                    <div style="display:flex;gap:12px;justify-content:center">
                        <button class="btn" style="background:#ef4444;color:#fff" :class="{'btn-loading':deleting}" :disabled="deleting" @click="confirmDelete"><span class="lnr lnr-trash"></span> Hapus</button>
                        <button class="btn" style="background:#e2e8f0;color:#475569" @click="showDeleteConfirm=false">Batal</button>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="filter-bar">
                    <button class="btn btn-primary" @click="openAdd"><span class="lnr lnr-plus-circle"></span> Tambah</button>
                    <button class="btn btn-primary" @click="showImport=true"><span class="lnr lnr-upload"></span> Import</button>
                    <a href="import_karyawan.php?action=template" class="btn" style="background:#e2e8f0;color:#475569;text-decoration:none"><span class="lnr lnr-download"></span> Template</a>
                    <button v-if="selected.length" class="btn" style="background:#ef4444;color:#fff" @click="openBulkDelete"><span class="lnr lnr-trash"></span> Hapus ({{ selected.length }})</button>
                </div>
                <div class="card-header"><div class="card-title"><span class="lnr lnr-users"></span> Daftar Karyawan</div><div class="card-subtitle">Total: {{ data.length }} karyawan</div></div>
                <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
                <div v-else class="table-container">
                    <table><thead><tr>
                        <th style="width:40px"><input type="checkbox" @change="toggleAll" :checked="selected.length === data.length && data.length > 0"></th>
                        <th>No</th><th>Badge</th><th>NIS</th><th>Nama</th><th>No. WhatsApp</th><th>FPHONE</th><th>Status</th><th style="width:100px">Aksi</th>
                    </tr></thead>
                    <tbody><tr v-for="(row, i) in data" :key="row.userid" :class="{'selected-row': selected.includes(row.userid)}">
                        <td><input type="checkbox" :value="row.userid" v-model="selected"></td>
                        <td>{{ i+1 }}</td><td>{{ row.badgenumber }}</td><td>{{ row.title || '-' }}</td><td>{{ row.name }}</td><td>{{ row.Card || '-' }}</td><td>{{ row.FPHONE || '-' }}</td>
                        <td><span :class="row.Card ? 'badge badge-success' : 'badge badge-danger'"><span :class="row.Card ? 'lnr lnr-checkmark-circle' : 'lnr lnr-cross-circle'"></span> {{ row.Card ? 'Aktif' : 'Tidak Ada' }}</span></td>
                        <td>
                            <button class="btn-icon" @click="openEdit(row)" title="Edit"><span class="lnr lnr-pencil"></span></button>
                            <button class="btn-icon danger" @click="openDelete(row)" title="Hapus"><span class="lnr lnr-trash"></span></button>
                        </td>
                    </tr></tbody></table>
                </div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const data = ref([]);
        const showImport = ref(false); const importing = ref(false);
        const selectedFile = ref(null); const importMode = ref('skip');
        const importResult = ref(null);
        const message = ref(''); const messageType = ref('success');
        const fileInput = ref(null);
        
        // Edit state
        const showEdit = ref(false); const saving = ref(false);
        const editForm = ref({ userid: 0, badgenumber: '', title: '', name: '', Card: '', FPHONE: '', defaultdeptid: 1 });
        
        // Delete state
        const showDeleteConfirm = ref(false); const deleting = ref(false);
        const deleteMessage = ref(''); const deleteTarget = ref(null); const deleteBulk = ref(false);
        
        // Selection state
        const selected = ref([]);
        
        const load = async () => { loading.value = true; const res = await fetch('api.php?action=karyawan'); data.value = (await res.json()).data; loading.value = false; selected.value = []; };
        const handleFile = (e) => { selectedFile.value = e.target.files[0]; importResult.value = null; };
        const showMsg = (msg, type='success') => { message.value = msg; messageType.value = type; setTimeout(() => message.value = '', 4000); };
        
        const doImport = async () => {
            if (!selectedFile.value) return;
            importing.value = true;
            const formData = new FormData();
            formData.append('file', selectedFile.value);
            formData.append('action', 'import');
            formData.append('mode', importMode.value);
            try {
                const res = await fetch('import_karyawan.php', { method: 'POST', body: formData });
                const result = await res.json();
                importResult.value = result;
                if (result.success) {
                    showMsg(result.message, 'success');
                    load();
                } else {
                    showMsg(result.message, 'error');
                }
            } catch (e) {
                showMsg('Gagal import: ' + e.message, 'error');
            }
            importing.value = false;
        };
        
        // Edit functions
        const openAdd = () => { editForm.value = { userid: 0, badgenumber: '', title: '', name: '', Card: '', FPHONE: '', defaultdeptid: 1 }; showEdit.value = true; };
        const openEdit = (row) => { editForm.value = { ...row }; showEdit.value = true; };
        const saveKaryawan = async () => {
            if (!editForm.value.badgenumber || !editForm.value.name) { showMsg('Badge dan nama wajib diisi', 'error'); return; }
            saving.value = true;
            const res = await fetch('api.php?action=karyawan_save', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(editForm.value) });
            const result = await res.json();
            showMsg(result.message, result.success ? 'success' : 'error');
            if (result.success) { showEdit.value = false; load(); }
            saving.value = false;
        };
        
        // Delete functions
        const openDelete = (row) => { deleteTarget.value = row; deleteBulk.value = false; deleteMessage.value = `Hapus karyawan "${row.name}"?`; showDeleteConfirm.value = true; };
        const openBulkDelete = () => { deleteBulk.value = true; deleteMessage.value = `Hapus ${selected.value.length} karyawan yang dipilih?`; showDeleteConfirm.value = true; };
        const confirmDelete = async () => {
            deleting.value = true;
            let res;
            if (deleteBulk.value) {
                res = await fetch('api.php?action=karyawan_delete_bulk', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ userids: selected.value }) });
            } else {
                res = await fetch('api.php?action=karyawan_delete', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ userid: deleteTarget.value.userid }) });
            }
            const result = await res.json();
            showMsg(result.message, result.success ? 'success' : 'error');
            showDeleteConfirm.value = false;
            deleting.value = false;
            if (result.success) load();
        };
        
        // Selection functions
        const toggleAll = (e) => { selected.value = e.target.checked ? data.value.map(d => d.userid) : []; };
        
        onMounted(load);
        return { loading, data, showImport, importing, selectedFile, importMode, importResult, message, messageType, fileInput, handleFile, doImport,
                 showEdit, saving, editForm, openAdd, openEdit, saveKaryawan,
                 showDeleteConfirm, deleting, deleteMessage, openDelete, openBulkDelete, confirmDelete,
                 selected, toggleAll };
    }
};

// Laporan Terlambat Component
const LaporanTerlambat = {
    template: `
        <div>
            <div v-if="loading" class="stats-grid"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>
            <div v-else class="stats-grid">
                <div class="stat-card yellow"><span class="lnr lnr-clock"></span><div class="value">{{ data.length }}</div><div class="label">Total Keterlambatan</div></div>
                <div class="stat-card red"><span class="lnr lnr-users"></span><div class="value">{{ uniqueUsers }}</div><div class="label">Karyawan Terlambat</div></div>
            </div>
            <div class="card">
                <div class="filter-bar">
                    <label class="form-label" style="margin:0">Periode:</label>
                    <input type="date" v-model="mulai" class="form-input" style="width:auto">
                    <span style="color:#64748b">s/d</span>
                    <input type="date" v-model="selesai" class="form-input" style="width:auto">
                    <button class="btn btn-primary" :class="{'btn-loading': loading}" :disabled="loading" @click="load"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
                </div>
                <div class="card-header"><div class="card-title"><span class="lnr lnr-clock"></span> Laporan Keterlambatan</div></div>
                <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
                <div v-else-if="data.length" class="table-container">
                    <table><thead><tr><th>No</th><th>Badge</th><th>Nama</th><th>Tanggal</th><th>Jam Masuk</th><th>Keterlambatan</th></tr></thead>
                    <tbody><tr v-for="(row, i) in data" :key="i"><td>{{ i+1 }}</td><td>{{ row.badgenumber }}</td><td>{{ row.name }}</td><td>{{ formatDate(row.tanggal) }}</td><td>{{ row.jam_masuk_only }}</td><td>{{ calcLate(row.jam_masuk_only) }} menit</td></tr></tbody></table>
                </div>
                <div v-else class="empty-state"><span class="lnr lnr-checkmark-circle"></span><p>Tidak ada keterlambatan pada periode ini</p></div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const mulai = ref(new Date().toISOString().slice(0,8) + '01'); const selesai = ref(new Date().toISOString().split('T')[0]);
        const data = ref([]); const jamMasuk = ref('07:00:00');
        const uniqueUsers = computed(() => [...new Set(data.value.map(d => d.userid))].length);
        const load = async () => { loading.value = true; const res = await fetch(`api.php?action=laporan_terlambat&mulai=${mulai.value}&selesai=${selesai.value}`); const r = await res.json(); data.value = r.data; jamMasuk.value = r.jam_masuk; loading.value = false; };
        const formatDate = (d) => new Date(d).toLocaleDateString('id-ID');
        const calcLate = (jam) => { const [h,m] = jam.split(':').map(Number); const [h2,m2] = jamMasuk.value.split(':').map(Number); return (h*60+m) - (h2*60+m2); };
        onMounted(load);
        return { loading, mulai, selesai, data, uniqueUsers, load, formatDate, calcLate };
    }
};

// Laporan Tidak Hadir Component
const LaporanTidakHadir = {
    template: `
        <div>
            <div v-if="loading" class="stats-grid"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>
            <div v-else class="stats-grid">
                <div class="stat-card red"><span class="lnr lnr-cross-circle"></span><div class="value">{{ data.length }}</div><div class="label">Total Tidak Hadir</div></div>
                <div class="stat-card yellow"><span class="lnr lnr-users"></span><div class="value">{{ uniqueUsers }}</div><div class="label">Karyawan Tidak Hadir</div></div>
            </div>
            <div class="card">
                <div class="filter-bar">
                    <label class="form-label" style="margin:0">Periode:</label>
                    <input type="date" v-model="mulai" class="form-input" style="width:auto">
                    <span style="color:#64748b">s/d</span>
                    <input type="date" v-model="selesai" class="form-input" style="width:auto">
                    <button class="btn btn-primary" :class="{'btn-loading': loading}" :disabled="loading" @click="load"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
                </div>
                <div class="card-header"><div class="card-title"><span class="lnr lnr-cross-circle"></span> Laporan Tidak Hadir</div></div>
                <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
                <div v-else-if="data.length" class="table-container">
                    <table><thead><tr><th>No</th><th>Badge</th><th>Nama</th><th>Tanggal</th><th>Jam Masuk</th><th>Keterangan</th></tr></thead>
                    <tbody><tr v-for="(row, i) in data" :key="i"><td>{{ i+1 }}</td><td>{{ row.badgenumber }}</td><td>{{ row.name }}</td><td>{{ formatDate(row.tanggal) }}</td><td>{{ row.jam_masuk || '-' }}</td><td>{{ row.status }}</td></tr></tbody></table>
                </div>
                <div v-else class="empty-state"><span class="lnr lnr-checkmark-circle"></span><p>Tidak ada ketidakhadiran pada periode ini</p></div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const mulai = ref(new Date().toISOString().slice(0,8) + '01'); const selesai = ref(new Date().toISOString().split('T')[0]); const data = ref([]);
        const uniqueUsers = computed(() => [...new Set(data.value.map(d => d.userid))].length);
        const load = async () => { loading.value = true; const res = await fetch(`api.php?action=laporan_tidak_hadir&mulai=${mulai.value}&selesai=${selesai.value}`); data.value = (await res.json()).data; loading.value = false; };
        const formatDate = (d) => new Date(d).toLocaleDateString('id-ID');
        onMounted(load);
        return { loading, mulai, selesai, data, uniqueUsers, load, formatDate };
    }
};

// Laporan Bolos Component
const LaporanBolos = {
    template: `
        <div>
            <div v-if="loading" class="stats-grid"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>
            <div v-else class="stats-grid">
                <div class="stat-card red"><span class="lnr lnr-warning"></span><div class="value">{{ data.length }}</div><div class="label">Total Bolos</div></div>
                <div class="stat-card yellow"><span class="lnr lnr-users"></span><div class="value">{{ uniqueUsers }}</div><div class="label">Karyawan Bolos</div></div>
            </div>
            <div class="card">
                <div class="filter-bar">
                    <label class="form-label" style="margin:0">Periode:</label>
                    <input type="date" v-model="mulai" class="form-input" style="width:auto">
                    <span style="color:#64748b">s/d</span>
                    <input type="date" v-model="selesai" class="form-input" style="width:auto">
                    <button class="btn btn-primary" :class="{'btn-loading': loading}" :disabled="loading" @click="load"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
                </div>
                <div class="card-header"><div class="card-title"><span class="lnr lnr-warning"></span> Laporan Bolos</div></div>
                <div v-if="loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>
                <div v-else-if="data.length" class="table-container">
                    <table><thead><tr><th>No</th><th>Badge</th><th>Nama</th><th>Tanggal</th><th>Jam Masuk</th><th>Keterangan</th></tr></thead>
                    <tbody><tr v-for="(row, i) in data" :key="i"><td>{{ i+1 }}</td><td>{{ row.badgenumber }}</td><td>{{ row.name }}</td><td>{{ formatDate(row.tanggal) }}</td><td>{{ row.jam_masuk }}</td><td>Tidak check out setelah {{ jamPulang }}</td></tr></tbody></table>
                </div>
                <div v-else class="empty-state"><span class="lnr lnr-checkmark-circle"></span><p>Tidak ada kasus bolos pada periode ini</p></div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const mulai = ref(new Date().toISOString().slice(0,8) + '01'); const selesai = ref(new Date().toISOString().split('T')[0]);
        const data = ref([]); const jamPulang = ref('17:00');
        const uniqueUsers = computed(() => [...new Set(data.value.map(d => d.userid))].length);
        const load = async () => { loading.value = true; const res = await fetch(`api.php?action=laporan_bolos&mulai=${mulai.value}&selesai=${selesai.value}`); const r = await res.json(); data.value = r.data; jamPulang.value = r.jam_pulang?.slice(0,5) || '17:00'; loading.value = false; };
        const formatDate = (d) => new Date(d).toLocaleDateString('id-ID');
        onMounted(load);
        return { loading, mulai, selesai, data, uniqueUsers, jamPulang, load, formatDate };
    }
};

// Pengaturan Component
const Pengaturan = {
    template: `
        <div>
            <div v-if="message" :class="'alert alert-' + messageType"><span :class="'lnr lnr-' + (messageType=='success'?'checkmark-circle':'warning')"></span> {{ message }}</div>
            <div v-if="loading" class="loading-container"><div class="loading-spinner"></div><div class="loading-text">Memuat pengaturan...</div></div>
            <template v-else>
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-clock"></span> Pengaturan Jam Absensi</div></div>
                    <div class="form-group"><label class="form-label">Jam Masuk (Tepat Waktu)</label><input type="time" v-model="form.jam_masuk" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Jam Batas Terlambat</label><input type="time" v-model="form.jam_batas_terlambat" class="form-input"></div>
                    <div class="form-group"><label class="form-label">Jam Batas Pulang</label><input type="time" v-model="form.jam_batas_pulang" class="form-input"></div>
                    <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveJam"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-exit"></span> Pengaturan Jam Auto Checkout Per Hari</div><div class="card-subtitle">Absen masuk setelah jam ini akan dianggap sebagai checkout/pulang</div></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
                        <div v-for="(name, num) in daysList" :key="num" class="form-group" style="margin:0">
                            <label class="form-label" style="font-size:12px">{{ name }}</label>
                            <input type="time" v-model="form.jam_auto_checkout_harian[num]" class="form-input">
                        </div>
                    </div>
                    <button class="btn btn-primary" style="margin-top:16px" :class="{'btn-loading': saving}" :disabled="saving" @click="saveJamAutoCheckoutHarian"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-bubble"></span> Pengaturan WhatsApp API</div></div>
                    <div class="form-group"><label class="form-label">API URL</label><input type="url" v-model="form.wa_api_url" class="form-input"></div>
                    <div class="form-group"><label class="form-label">API Token</label><input type="text" v-model="form.wa_api_token" class="form-input"></div>
                    <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveWa"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-sync"></span> Pengaturan Sync Absensi API</div><div class="card-subtitle">Endpoint untuk sinkronisasi data absensi ke sistem eksternal</div></div>
                    <div class="form-group"><label class="form-label">Sync API URL</label><input type="url" v-model="form.sync_api_url" class="form-input" placeholder="http://127.0.0.1:8000/api/attendance/sync"><div class="form-hint">Endpoint URL untuk mengirim data absensi</div></div>
                    <div class="form-group"><label class="form-label">API Token (Opsional)</label><input type="text" v-model="form.sync_api_token" class="form-input" placeholder="Bearer token"><div class="form-hint">Token autentikasi jika diperlukan</div></div>
                    <div class="form-group"><label class="form-label">Interval Sinkronisasi (Menit)</label><input type="number" v-model="form.sync_interval" class="form-input" min="1" max="1440" placeholder="60"><div class="form-hint">Durasi interval sinkronisasi dalam menit (1-1440). Default: 60 menit</div></div>
                    <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveSyncApi"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                </div>
                <div class="card">
                    <div class="card-header"><div class="card-title"><span class="lnr lnr-book"></span> Pengaturan SIAKAD API (Izin/Sakit)</div><div class="card-subtitle">API untuk mengambil data siswa izin/sakit dari SIAKAD</div></div>
                    <div class="form-group"><label class="form-label">SIAKAD API URL</label><input type="url" v-model="form.siakad_api_url" class="form-input" placeholder="https://siakads.kurikulum-skansa.id/api/attendance/absences"><div class="form-hint">Endpoint URL untuk mengambil data izin/sakit</div></div>
                    <div class="form-group"><label class="form-label">API Token (Opsional)</label><input type="text" v-model="form.siakad_api_token" class="form-input" placeholder="Bearer token"><div class="form-hint">Token autentikasi jika diperlukan</div></div>
                    <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveSiakadApi"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
                </div>
            </div>
            <div class="card" style="margin-top:24px">
                <div class="card-header"><div class="card-title"><span class="lnr lnr-earth"></span> Pengaturan Zona Waktu</div></div>
                <div class="form-group"><label class="form-label">Zona Waktu</label>
                    <select v-model="form.timezone" class="form-input"><option v-for="(label, tz) in timezoneList" :value="tz">{{ label }}</option></select>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:16px">
                    <div class="time-display"><span class="lnr lnr-clock"></span><div><div class="time-label">Waktu Server</div><div class="time-value">{{ serverTime }}</div></div></div>
                    <div class="time-display"><span class="lnr lnr-calendar-full" style="color:#22c55e"></span><div><div class="time-label">Tanggal</div><div class="time-value">{{ serverDate }}</div></div></div>
                </div>
                <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveTimezone"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
            </div>
            <div class="card" style="margin-top:24px">
                <div class="card-header"><div class="card-title"><span class="lnr lnr-calendar-full"></span> Pengaturan Hari Libur</div></div>
                <div class="form-group"><label class="form-label">Hari Libur</label>
                    <div class="checkbox-group">
                        <label v-for="(name, num) in daysList" :key="num" class="checkbox-item" :class="{checked: form.hari_libur.includes(num)}">
                            <input type="checkbox" :value="num" v-model="form.hari_libur"> {{ name }}
                        </label>
                    </div>
                </div>
                <button class="btn btn-primary" :class="{'btn-loading': saving}" :disabled="saving" @click="saveHariLibur"><span class="lnr lnr-checkmark-circle"></span> Simpan</button>
            </div>
            </template>
        </div>`,
    setup() {
        const loading = ref(true); const saving = ref(false);
        const form = ref({ 
            jam_masuk: '07:00', jam_batas_terlambat: '08:00', jam_batas_pulang: '17:00', 
            jam_auto_checkout_harian: { '1': '14:00', '2': '14:00', '3': '14:00', '4': '14:00', '5': '14:00', '6': '12:00', '7': '12:00' },
            wa_api_url: '', wa_api_token: '', sync_api_url: '', sync_api_token: '', sync_interval: 60, 
            siakad_api_url: '', siakad_api_token: '',
            timezone: 'Asia/Jakarta', hari_libur: [] 
        });
        const timezoneList = ref({}); const daysList = ref({});
        const message = ref(''); const messageType = ref('success');
        const serverTime = ref('--:--:--'); const serverDate = ref('--');
        let timeInterval;
        const load = async () => {
            loading.value = true;
            const res = await fetch('api.php?action=settings'); const r = await res.json();
            form.value = { 
                ...r.data, 
                jam_masuk: r.data.jam_masuk?.slice(0,5), 
                jam_batas_terlambat: r.data.jam_batas_terlambat?.slice(0,5), 
                jam_batas_pulang: r.data.jam_batas_pulang?.slice(0,5),
                jam_auto_checkout_harian: r.data.jam_auto_checkout_harian || { '1': '14:00', '2': '14:00', '3': '14:00', '4': '14:00', '5': '14:00', '6': '12:00', '7': '12:00' }
            };
            timezoneList.value = r.timezone_list; daysList.value = r.days_list;
            loading.value = false;
        };
        const updateTime = async () => { try { const res = await fetch('api.php?action=time'); const r = await res.json(); serverTime.value = r.time; serverDate.value = r.date; } catch(e){} };
        const showMsg = (msg, type='success') => { message.value = msg; messageType.value = type; setTimeout(() => message.value = '', 3000); };
        const save = async (type, data) => {
            saving.value = true;
            await fetch('api.php?action=save_settings', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({type, ...data}) });
            showMsg('Pengaturan berhasil disimpan'); saving.value = false;
        };
        const saveJam = () => save('jam', { jam_masuk: form.value.jam_masuk, jam_batas_terlambat: form.value.jam_batas_terlambat, jam_batas_pulang: form.value.jam_batas_pulang });
        const saveJamAutoCheckoutHarian = () => save('jam_auto_checkout_harian', { jam_auto_checkout_harian: form.value.jam_auto_checkout_harian });
        const saveWa = () => save('wa', { wa_api_url: form.value.wa_api_url, wa_api_token: form.value.wa_api_token });
        const saveSyncApi = () => save('sync_api', { sync_api_url: form.value.sync_api_url, sync_api_token: form.value.sync_api_token, sync_interval: form.value.sync_interval });
        const saveSiakadApi = () => save('siakad_api', { siakad_api_url: form.value.siakad_api_url, siakad_api_token: form.value.siakad_api_token });
        const saveTimezone = () => save('timezone', { timezone: form.value.timezone });
        const saveHariLibur = () => save('hari_libur', { hari_libur: form.value.hari_libur });
        onMounted(() => { load(); updateTime(); timeInterval = setInterval(updateTime, 1000); });
        onUnmounted(() => clearInterval(timeInterval));
        return { loading, saving, form, timezoneList, daysList, message, messageType, serverTime, serverDate, saveJam, saveJamAutoCheckoutHarian, saveWa, saveSyncApi, saveSiakadApi, saveTimezone, saveHariLibur };
    }
};

// Log Notifikasi Component
const LogNotifikasi = {
    template: `
        <div>
            <div v-if="loading" class="stats-grid" style="grid-template-columns:repeat(3,1fr)"><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div><div class="skeleton skeleton-stat"></div></div>
            <div v-else class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
                <div class="stat-card green"><span class="lnr lnr-checkmark-circle"></span><div class="value">{{ stats.success }}</div><div class="label">Berhasil</div></div>
                <div class="stat-card red"><span class="lnr lnr-cross-circle"></span><div class="value">{{ stats.error }}</div><div class="label">Gagal</div></div>
                <div class="stat-card yellow"><span class="lnr lnr-warning"></span><div class="value">{{ stats.warning }}</div><div class="label">Peringatan</div></div>
            </div>
            <div class="filter-bar">
                <button :class="['filter-tab', {active: autoRefresh}]" @click="toggleAuto"><span class="lnr lnr-sync"></span> Auto Refresh {{ autoRefresh ? 'ON' : 'OFF' }}</button>
                <button class="filter-tab" :class="{'btn-loading': refreshing}" @click="load"><span class="lnr lnr-redo"></span> Refresh</button>
                <div style="margin-left:auto;font-size:12px;color:#64748b">Update: {{ lastUpdate }}</div>
            </div>
            <div class="filter-tabs">
                <button v-for="f in filters" :key="f.key" :class="['filter-tab', {active: filter===f.key}]" @click="filter=f.key"><span :class="f.icon"></span> {{ f.label }}</button>
            </div>
            <div class="terminal-container">
                <div class="terminal-header">
                    <div class="terminal-dot red"></div><div class="terminal-dot yellow"></div><div class="terminal-dot green"></div>
                    <span class="terminal-title">Log Notifikasi — {{ filteredLogs.length }} entries</span>
                    <div class="terminal-status"><div class="status-dot" :style="{background: autoRefresh ? '#27ca40' : '#ef4444', animation: autoRefresh ? 'pulse 2s infinite' : 'none'}"></div>{{ autoRefresh ? 'Live' : 'Paused' }}</div>
                </div>
                <div class="terminal-body">
                    <div v-if="loading" style="padding:20px;text-align:center;color:#6b7280"><div class="loading-spinner" style="border-color:#444;border-top-color:#3b82f6"></div><div style="margin-top:12px">Memuat log...</div></div>
                    <template v-else>
                        <div v-for="(log, i) in filteredLogs" :key="i" class="log-line">
                            <span :class="'log-icon ' + log.status"><span :class="getIcon(log.status)"></span></span>
                            <span class="log-badge" :style="{background: log.color+'20', color: log.color}">{{ log.type }}</span>
                            <span class="log-timestamp">{{ log.timestamp }}</span>
                            <span :class="'log-message ' + log.status">{{ log.message }}</span>
                        </div>
                        <div v-if="!filteredLogs.length" class="empty-state" style="color:#6b7280"><span class="lnr lnr-inbox"></span><p>Tidak ada log</p></div>
                    </template>
                </div>
            </div>
        </div>`,
    setup() {
        const loading = ref(true); const refreshing = ref(false);
        const logs = ref([]); const filter = ref('all'); const autoRefresh = ref(true); const lastUpdate = ref('-');
        let interval;
        const filters = [{key:'all', label:'Semua', icon:'lnr lnr-layers'},{key:'success', label:'Berhasil', icon:'lnr lnr-checkmark-circle'},{key:'error', label:'Gagal', icon:'lnr lnr-cross-circle'},{key:'warning', label:'Peringatan', icon:'lnr lnr-warning'}];
        const stats = computed(() => { const s = {success:0, error:0, warning:0}; logs.value.forEach(l => { if(s[l.status] !== undefined) s[l.status]++; }); return s; });
        const filteredLogs = computed(() => filter.value === 'all' ? logs.value : logs.value.filter(l => l.status === filter.value));
        const getIcon = (status) => ({success:'lnr lnr-checkmark-circle', error:'lnr lnr-cross-circle', warning:'lnr lnr-warning', skip:'lnr lnr-arrow-right-circle'}[status] || 'lnr lnr-bubble');
        const load = async () => { 
            if (!loading.value) refreshing.value = true;
            const res = await fetch('api.php?action=logs'); const r = await res.json(); 
            logs.value = r.logs; lastUpdate.value = new Date().toLocaleTimeString(); 
            loading.value = false; refreshing.value = false;
        };
        const toggleAuto = () => { autoRefresh.value = !autoRefresh.value; autoRefresh.value ? startInterval() : clearInterval(interval); };
        const startInterval = () => { interval = setInterval(load, 3000); };
        onMounted(() => { load(); startInterval(); });
        onUnmounted(() => clearInterval(interval));
        return { loading, refreshing, logs, filter, filters, stats, filteredLogs, autoRefresh, lastUpdate, load, toggleAuto, getIcon };
    }
};

// Router & App
const routes = [
    { path: '/', component: Dashboard, meta: { title: 'Dashboard' } },
    { path: '/absensi', component: Absensi, meta: { title: 'Data Absensi' } },
    { path: '/karyawan', component: Karyawan, meta: { title: 'Data Karyawan' } },
    { path: '/laporan/terlambat', component: LaporanTerlambat, meta: { title: 'Laporan Keterlambatan' } },
    { path: '/laporan/tidak-hadir', component: LaporanTidakHadir, meta: { title: 'Laporan Tidak Hadir' } },
    { path: '/laporan/bolos', component: LaporanBolos, meta: { title: 'Laporan Bolos' } },
    { path: '/pengaturan', component: Pengaturan, meta: { title: 'Pengaturan' } },
    { path: '/log', component: LogNotifikasi, meta: { title: 'Log Notifikasi' } }
];
const router = createRouter({ history: createWebHashHistory(), routes });
const app = createApp({
    setup() {
        const pageTitle = ref('Dashboard');
        const currentDate = ref(new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
        router.afterEach((to) => { pageTitle.value = to.meta.title || 'Dashboard'; });
        return { pageTitle, currentDate };
    }
});
app.use(router);
app.mount('#app');
</script>
</body>
</html>
