<?php
require_once 'config.php';
require_once 'settings.php';
require_once 'layout.php';

$conn = getConnection();
$tanggalMulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$tanggalSelesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

$jamMasuk = getSetting('jam_masuk', '07:00:00');
$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');

// Query laporan keterlambatan
$sql = "SELECT 
            c.userid,
            u.name,
            u.badgenumber,
            DATE(c.checktime) as tanggal,
            MIN(c.checktime) as jam_masuk,
            TIME(MIN(c.checktime)) as jam_masuk_only
        FROM checkinout c
        LEFT JOIN userinfo u ON c.userid = u.userid
        WHERE c.checktype = '0'
        AND DATE(c.checktime) BETWEEN ? AND ?
        AND TIME(c.checktime) > ? 
        AND TIME(c.checktime) <= ?
        GROUP BY c.userid, DATE(c.checktime)
        ORDER BY c.checktime DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $tanggalMulai, $tanggalSelesai, $jamMasuk, $jamTerlambat);
$stmt->execute();
$result = $stmt->get_result();

$dataList = [];
while ($row = $result->fetch_assoc()) {
    $dataList[] = $row;
}

$totalTerlambat = count($dataList);
$uniqueUsers = [];
foreach ($dataList as $row) {
    $uniqueUsers[$row['userid']] = $row['name'];
}
$totalUserTerlambat = count($uniqueUsers);

renderHeader('Laporan Keterlambatan', 'terlambat');
?>

<div class="stats-grid">
    <div class="stat-card yellow">
        <span class="lnr lnr-clock"></span>
        <div class="value"><?= $totalTerlambat ?></div>
        <div class="label">Total Keterlambatan</div>
    </div>
    <div class="stat-card red">
        <span class="lnr lnr-users"></span>
        <div class="value"><?= $totalUserTerlambat ?></div>
        <div class="label">Karyawan Terlambat</div>
    </div>
    <div class="stat-card blue">
        <span class="lnr lnr-calendar-full"></span>
        <div class="value"><?= (strtotime($tanggalSelesai) - strtotime($tanggalMulai)) / 86400 + 1 ?></div>
        <div class="label">Hari Periode</div>
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
        <div class="card-title"><span class="lnr lnr-clock"></span> Laporan Keterlambatan</div>
        <div class="card-subtitle">Periode: <?= date('d/m/Y', strtotime($tanggalMulai)) ?> - <?= date('d/m/Y', strtotime($tanggalSelesai)) ?></div>
    </div>
    
    <?php if ($totalTerlambat > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Badge</th>
                    <th>Nama</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Keterlambatan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($dataList as $row): 
                    $jamMasukTime = strtotime($jamMasuk);
                    $jamMasukUser = strtotime($row['jam_masuk_only']);
                    $selisihMenit = ($jamMasukUser - $jamMasukTime) / 60;
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['badgenumber']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td><?= date('H:i:s', strtotime($row['jam_masuk'])) ?></td>
                    <td><?= floor($selisihMenit) ?> menit</td>
                    <td><span class="badge badge-warning"><span class="lnr lnr-clock"></span> Terlambat</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <span class="lnr lnr-checkmark-circle" style="font-size: 48px; display: block; margin-bottom: 16px;"></span>
        <p>Tidak ada keterlambatan pada periode ini</p>
    </div>
    <?php endif; ?>
</div>

<?php
$stmt->close();
$conn->close();
renderFooter();
?>
