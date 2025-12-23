<?php
function renderHeader($title = 'Dashboard', $activePage = '') {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Sistem Absensi</title>
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            padding: 0; z-index: 100; transition: all 0.3s;
        }
        .sidebar-header {
            padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h1 {
            color: #fff; font-size: 20px; font-weight: 600;
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-header .lnr { font-size: 24px; }
        .sidebar-nav { padding: 16px 12px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; margin-bottom: 4px;
            color: #94a3b8; text-decoration: none;
            border-radius: 8px; transition: all 0.2s;
            font-size: 14px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: #3b82f6; color: #fff; }
        .nav-item .lnr { font-size: 18px; width: 24px; text-align: center; }
        .nav-section { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 16px 16px 8px; margin-top: 8px; }
        
        /* Main Content */
        .main-content { margin-left: 260px; min-height: 100vh; }
        .topbar {
            background: #fff; padding: 16px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .topbar h2 { font-size: 24px; color: #1e293b; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .topbar-time { color: #64748b; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        
        .content { padding: 32px; }
        
        /* Cards */
        .card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 24px; margin-bottom: 24px;
        }
        .card-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .card-subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }
        .card-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .stat-card .lnr { font-size: 32px; margin-bottom: 12px; display: block; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .stat-card .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stat-card.blue { border-left: 4px solid #3b82f6; }
        .stat-card.blue .lnr { color: #3b82f6; }
        .stat-card.green { border-left: 4px solid #22c55e; }
        .stat-card.green .lnr { color: #22c55e; }
        .stat-card.yellow { border-left: 4px solid #eab308; }
        .stat-card.yellow .lnr { color: #eab308; }
        .stat-card.red { border-left: 4px solid #ef4444; }
        .stat-card.red .lnr { color: #ef4444; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { color: #334155; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .form-hint { font-size: 12px; color: #6b7280; margin-top: 6px; }
        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; transition: all 0.2s;
        }
        .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        
        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 8px; font-size: 14px;
            font-weight: 500; cursor: pointer; transition: all 0.2s;
            border: none; text-decoration: none;
        }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { background: #16a34a; }
        
        /* Alert */
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        /* Filter */
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1><span class="lnr lnr-calendar-full"></span> Absensi</h1>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Menu Utama</div>
            <a href="index.php" class="nav-item <?= $activePage == 'dashboard' ? 'active' : '' ?>">
                <span class="lnr lnr-chart-bars"></span> Dashboard
            </a>
            <a href="data_absensi.php" class="nav-item <?= $activePage == 'absensi' ? 'active' : '' ?>">
                <span class="lnr lnr-list"></span> Data Absensi
            </a>
            <a href="data_karyawan.php" class="nav-item <?= $activePage == 'karyawan' ? 'active' : '' ?>">
                <span class="lnr lnr-users"></span> Data Karyawan
            </a>
            
            <div class="nav-section">Laporan</div>
            <a href="laporan_terlambat.php" class="nav-item <?= $activePage == 'terlambat' ? 'active' : '' ?>">
                <span class="lnr lnr-clock"></span> Keterlambatan
            </a>
            <a href="laporan_tidak_hadir.php" class="nav-item <?= $activePage == 'tidak_hadir' ? 'active' : '' ?>">
                <span class="lnr lnr-cross-circle"></span> Tidak Hadir
            </a>
            <a href="laporan_bolos.php" class="nav-item <?= $activePage == 'bolos' ? 'active' : '' ?>">
                <span class="lnr lnr-warning"></span> Bolos
            </a>
            
            <div class="nav-section">Pengaturan</div>
            <a href="pengaturan.php" class="nav-item <?= $activePage == 'pengaturan' ? 'active' : '' ?>">
                <span class="lnr lnr-cog"></span> Pengaturan Jam
            </a>
            <a href="log_notifikasi.php" class="nav-item <?= $activePage == 'log' ? 'active' : '' ?>">
                <span class="lnr lnr-envelope"></span> Log Notifikasi
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
        <header class="topbar">
            <h2><?= $title ?></h2>
            <div class="topbar-right">
                <span class="topbar-time"><span class="lnr lnr-calendar-full"></span> <?= date('l, d F Y') ?></span>
            </div>
        </header>
        <div class="content">
<?php
}

function renderFooter() {
?>
        </div>
    </main>
</body>
</html>
<?php
}
?>
