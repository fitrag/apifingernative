<?php
require_once 'config.php';
require_once 'layout.php';

$conn = getConnection();
$result = $conn->query("SELECT * FROM userinfo ORDER BY name");

renderHeader('Data Karyawan', 'karyawan');
?>

<div class="card">
    <div class="card-header">
        <div class="card-title"><span class="lnr lnr-users"></span> Daftar Karyawan</div>
        <div class="card-subtitle">Total: <?= $result->num_rows ?> karyawan</div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Badge</th>
                    <th>Nama</th>
                    <th>No. WhatsApp</th>
                    <th>Status WA</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['badgenumber']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= !empty($row['Card']) ? htmlspecialchars($row['Card']) : '-' ?></td>
                    <td>
                        <?php if (!empty($row['Card'])): ?>
                            <span class="badge badge-success"><span class="lnr lnr-checkmark-circle"></span> Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><span class="lnr lnr-cross-circle"></span> Tidak Ada</span>
                        <?php endif; ?>
                    </td>
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
