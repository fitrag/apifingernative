<?php
require_once 'config.php';
require_once 'settings.php';
require_once 'layout.php';

$conn = getConnection();
$tanggalMulai = isset($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-01');
$tanggalSelesai = isset($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');

$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');
$jamPulang = getSetting('jam_batas_pulang', '17:00:00');

// Optimized: Single query untuk mendapatkan semua data bolos
// User yang check in tepat waktu tapi tidak check out
$sql = "SELECT 
            u.userid,
            u.name,
            u.badgenumber,
            ci.tanggal,
            ci.jam_masuk
        FROM userinfo u
        INNER JOIN (
            SELECT userid, DATE(checktime) as tanggal, MIN(TIME(checktime)) as jam_masuk
            FROM checkinout 
            WHERE checktype = '0' 
            AND DATE(checktime) BETWEEN ? AND ?
            GROUP BY userid, DATE(checktime)
            HAVING MIN(TIME(checktime)) <= ?
        ) ci ON u.userid = ci.userid
        LEFT JOIN (
            SELECT DISTINCT userid, DATE(checktime) as tanggal
            FROM checkinout 
            WHERE checktype = '1' 
            AND DATE(checktime) BETWEEN ? AND ?
        ) co ON u.userid = co.userid AND ci.tanggal = co.tanggal
        WHERE u.Card IS NOT NULL AND u.Card != ''
        AND co.userid IS NULL
        ORDER BY ci.tanggal DESC, u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $tanggalMulai, $tanggalSelesai, $jamTerlambat, $tanggalMulai, $tanggalSelesai);
$stmt->execute();
$result = $stmt->get_result();

$bolosData = [];
$uniqueUsers = [];
$tanggalSet = [];

while ($row = $result->fetch_assoc()) {
    $bolosData[] = [
        'userid' => $row['userid'],
        'name' => $row['name'],
        'badgenumber' => $row['badgenumber'],
        'tanggal' => $row['tanggal'],
        'jam_masuk' => $row['jam_masuk'],
        'status' => 'Tidak Check Out'
    ];
    $uniqueUsers[$row['userid']] = $row['name'];
    $tanggalSet[$row['tanggal']] = true;
}

$totalBolos = count($bolosData);
$totalUserBolos = count($uniqueUsers);
$totalHariKerja = count($tanggalSet);

$stmt->close();

renderHeader('Laporan Bolos', 'bolos');
?>

<div class="stats-grid">
    <div class="stat-card red">
        <span class="lnr lnr-warning"></span>
        <div class="value"><?= $totalBolos ?></div>
        <div class="label">Total Bolos</div>
    </div>
    <div class="stat-card yellow">
        <span class="lnr lnr-users"></span>
        <div class="value"><?= $totalUserBolos ?></div>
        <div class="label">Karyawan Bolos</div>
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
        <div class="card-title"><span class="lnr lnr-warning"></span> Laporan Bolos</div>
        <div class="card-subtitle">Periode: <?= date('d/m/Y', strtotime($tanggalMulai)) ?> - <?= date('d/m/Y', strtotime($tanggalSelesai)) ?></div>
    </div>
    
    <?php if ($totalBolos > 0): ?>
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
                <?php $no = 1; foreach ($bolosData as $data): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($data['badgenumber']) ?></td>
                    <td><?= htmlspecialchars($data['name']) ?></td>
                    <td><?= date('d/m/Y', strtotime($data['tanggal'])) ?></td>
                    <td><?= $data['jam_masuk'] ?></td>
                    <td>Hadir tapi tidak check out setelah <?= substr($jamPulang, 0, 5) ?></td>
                    <td><span class="badge badge-warning"><span class="lnr lnr-warning"></span> Bolos</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #64748b;">
        <span class="lnr lnr-checkmark-circle" style="font-size: 48px; display: block; margin-bottom: 16px;"></span>
        <p>Tidak ada kasus bolos pada periode ini</p>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-question-circle"></span> Keterangan</div>
    </div>
    <div style="padding: 16px; background: #f8fafc; border-radius: 8px; color: #475569; line-height: 1.6;">
        <p><strong>Kriteria Bolos:</strong></p>
        <ul style="margin: 8px 0 0 20px;">
            <li>Karyawan check in tepat waktu (≤ <?= substr($jamTerlambat, 0, 5) ?>)</li>
            <li>Tidak melakukan check out sampai batas waktu (<?= substr($jamPulang, 0, 5) ?>)</li>
            <li>Dianggap meninggalkan tempat kerja tanpa izin</li>
        </ul>
    </div>
</div>

<?php
$conn->close();
renderFooter();
?>
