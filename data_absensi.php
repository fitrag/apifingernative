<?php
require_once 'config.php';
require_once 'settings.php';
require_once 'layout.php';

$conn = getConnection();
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$jamMasuk = getSetting('jam_masuk', '07:00:00');
$jamTerlambat = getSetting('jam_batas_terlambat', '08:00:00');

$sql = "SELECT c.*, u.name, u.badgenumber FROM checkinout c 
        LEFT JOIN userinfo u ON c.userid = u.userid 
        WHERE DATE(c.checktime) = ? 
        ORDER BY c.checktime DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tanggal);
$stmt->execute();
$result = $stmt->get_result();

renderHeader('Data Absensi', 'absensi');
?>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 12px; align-items: center;">
            <label class="form-label" style="margin: 0;">Tanggal:</label>
            <input type="date" name="tanggal" value="<?= $tanggal ?>" class="form-input" style="width: auto;">
            <button type="submit" class="btn btn-primary"><span class="lnr lnr-magnifier"></span> Tampilkan</button>
        </form>
    </div>
    
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-list"></span> Data Absensi - <?= date('d F Y', strtotime($tanggal)) ?></div>
        <div class="card-subtitle">Total: <?= $result->num_rows ?> data</div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Badge</th>
                    <th>Nama</th>
                    <th>Waktu</th>
                    <th>Tipe</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($row = $result->fetch_assoc()): 
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
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['badgenumber']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= date('H:i:s', strtotime($row['checktime'])) ?></td>
                    <td><?= $tipe ?></td>
                    <td><?= $status ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
renderFooter();
?>
