<?php
require_once 'config.php';
require_once 'settings.php';
require_once 'layout.php';

$conn = getConnection();
$tanggalHariIni = date('Y-m-d');
$jamMasuk = getSetting('jam_masuk', '07:00:00');
$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');

// Single optimized query untuk semua statistik
$sql = "SELECT 
    (SELECT COUNT(*) FROM userinfo) as total_karyawan,
    (SELECT COUNT(DISTINCT userid) FROM checkinout WHERE DATE(checktime) = ? AND checktype = '0') as hadir,
    (SELECT COUNT(DISTINCT userid) FROM checkinout WHERE DATE(checktime) = ? AND checktype = '0' AND TIME(checktime) > ? AND TIME(checktime) <= ?) as terlambat";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $tanggalHariIni, $tanggalHariIni, $jamMasuk, $jamTerlambat);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalKaryawan = $stats['total_karyawan'];
$hadirHariIni = $stats['hadir'];
$terlambatHariIni = $stats['terlambat'];
$tidakHadirHariIni = $totalKaryawan - $hadirHariIni;

// Absensi terbaru dengan index hint
$sql2 = "SELECT c.id, c.userid, c.checktime, c.checktype, u.name, u.badgenumber 
         FROM checkinout c 
         LEFT JOIN userinfo u ON c.userid = u.userid 
         ORDER BY c.id DESC LIMIT 10";
$absensiTerbaru = $conn->query($sql2);

renderHeader('Dashboard', 'dashboard');
?>

<div class="stats-grid">
    <div class="stat-card blue">
        <span class="lnr lnr-users"></span>
        <div class="value"><?= $totalKaryawan ?></div>
        <div class="label">Total Karyawan</div>
    </div>
    <div class="stat-card green">
        <span class="lnr lnr-checkmark-circle"></span>
        <div class="value"><?= $hadirHariIni ?></div>
        <div class="label">Hadir Hari Ini</div>
    </div>
    <div class="stat-card yellow">
        <span class="lnr lnr-clock"></span>
        <div class="value"><?= $terlambatHariIni ?></div>
        <div class="label">Terlambat Hari Ini</div>
    </div>
    <div class="stat-card red">
        <span class="lnr lnr-cross-circle"></span>
        <div class="value"><?= $tidakHadirHariIni ?></div>
        <div class="label">Tidak Hadir</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-list"></span> Absensi Terbaru</div>
        <div class="card-subtitle">10 data absensi terakhir</div>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Badge</th>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $absensiTerbaru->fetch_assoc()): 
                    $jamAbsen = date('H:i:s', strtotime($row['checktime']));
                    $tipe = $row['checktype'] == '0' ? 'Check In' : 'Check Out';
                    
                    if ($row['checktype'] == '0') {
                        if ($jamAbsen <= $jamMasuk) {
                            $status = '<span class="badge badge-success"><span class="lnr lnr-checkmark-circle"></span> Tepat Waktu</span>';
                        } elseif ($jamAbsen <= $jamTerlambat) {
                            $status = '<span class="badge badge-warning"><span class="lnr lnr-clock"></span> Terlambat</span>';
                        } else {
                            $status = '<span class="badge badge-danger"><span class="lnr lnr-cross-circle"></span> Tidak Hadir</span>';
                        }
                    } else {
                        $status = '<span class="badge badge-info"><span class="lnr lnr-exit"></span> Pulang</span>';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['badgenumber']) ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($row['checktime'])) ?></td>
                    <td><?= $tipe ?></td>
                    <td><?= $status ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conn->close();
renderFooter();
?>
