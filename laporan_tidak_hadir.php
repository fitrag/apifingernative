<?php
require_once 'config.php';
require_once 'settings.php';
require_once 'layout.php';

$conn = getConnection();
$tanggalMulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$tanggalSelesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');

// Optimized: Single query untuk mendapatkan semua data tidak hadir
// Menggunakan LEFT JOIN dan subquery untuk menghindari N+1 problem
$sql = "SELECT 
            u.userid,
            u.name,
            u.badgenumber,
            d.tanggal,
            c.jam_masuk,
            CASE 
                WHEN c.jam_masuk IS NULL THEN 'Tidak Check In'
                WHEN c.jam_masuk > ? THEN 'Check In Terlalu Lambat'
                ELSE NULL
            END as status
        FROM userinfo u
        CROSS JOIN (
            SELECT DISTINCT DATE(checktime) as tanggal 
            FROM checkinout 
            WHERE DATE(checktime) BETWEEN ? AND ?
        ) d
        LEFT JOIN (
            SELECT userid, DATE(checktime) as tgl, MIN(TIME(checktime)) as jam_masuk
            FROM checkinout 
            WHERE checktype = '0' AND DATE(checktime) BETWEEN ? AND ?
            GROUP BY userid, DATE(checktime)
        ) c ON u.userid = c.userid AND d.tanggal = c.tgl
        WHERE u.Card IS NOT NULL AND u.Card != ''
        AND (c.jam_masuk IS NULL OR c.jam_masuk > ?)
        ORDER BY d.tanggal DESC, u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $jamTerlambat, $tanggalMulai, $tanggalSelesai, $tanggalMulai, $tanggalSelesai, $jamTerlambat);
$stmt->execute();
$result = $stmt->get_result();

$tidakHadirData = [];
$uniqueUsers = [];
$tanggalSet = [];

while ($row = $result->fetch_assoc()) {
    $tidakHadirData[] = [
        'userid' => $row['userid'],
        'name' => $row['name'],
        'badgenumber' => $row['badgenumber'],
        'tanggal' => $row['tanggal'],
        'jam_masuk' => $row['jam_masuk'],
        'status' => $row['status']
    ];
    $uniqueUsers[$row['userid']] = $row['name'];
    $tanggalSet[$row['tanggal']] = true;
}

$totalTidakHadir = count($tidakHadirData);
$totalUserTidakHadir = count($uniqueUsers);
$totalHariKerja = count($tanggalSet);

$stmt->close();

renderHeader('Laporan Tidak Hadir', 'tidak_hadir');
?>

<div class="stats-grid">
    <div class="stat-card red">
        <span class="lnr lnr-cross-circle"></span>
        <div class="value"><?= $totalTidakHadir ?></div>
        <div class="label">Total Tidak Hadir</div>
    </div>
    <div class="stat-card yellow">
        <span class="lnr lnr-users"></span>
        <div class="value"><?= $totalUserTidakHadir ?></div>
        <div class="label">Karyawan Tidak Hadir</div>
    </div>
    <div class="stat-card blue">
        <span class="lnr lnr-calendar-full"></span>
        <div class="value"><?= $totalHariKerja ?></div>
        <div class="label">Hari Kerja</div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <label class="form-label" style="margin: 0;">Periode:</label>
            <input type="date" name="tanggal_mulai" value="<?= $tanggalMulai ?>" class="form-input" style="width: auto;">
            <span style="color: #64748b;">s/d</span>
            <input type="date" name="tanggal_selesai" value="<?= $tanggalSelesai ?>" class="form-input" style="width: auto;">
            <button type="submit" class="btn btn-primary"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
        </form>
    </div>
    
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-cross-circle"></span> Laporan Tidak Hadir</div>
        <div class="card-subtitle">Periode: <?= date('d/m/Y', strtotime($tanggalMulai)) ?> - <?= date('d/m/Y', strtotime($tanggalSelesai)) ?></div>
    </div>
    
    <?php if ($totalTidakHadir > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Badge</th>
                    <th>Nama</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($tidakHadirData as $data): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($data['badgenumber']) ?></td>
                    <td><?= htmlspecialchars($data['name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($data['tanggal'])) ?></td>
                    <td><?= $data['jam_masuk'] ?? '-' ?></td>
                    <td><?= $data['status'] ?></td>
                    <td><span class="badge badge-danger"><span class="lnr lnr-cross-circle"></span> Tidak Hadir</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <span class="lnr lnr-checkmark-circle" style="font-size: 48px; display: block; margin-bottom: 16px;"></span>
        <p>Tidak ada ketidakhadiran pada periode ini</p>
    </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
renderFooter();
?>
