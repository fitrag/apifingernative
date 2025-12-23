<?php
/**
 * Export Data Karyawan ke Excel (CSV format)
 * Format: Title;name;Card;defaultdeptid;SN
 */

require_once 'config.php';

// Set headers untuk download Excel/CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data_karyawan_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM untuk UTF-8 Excel compatibility
echo "\xEF\xBB\xBF";

$conn = getConnection();

// Query data karyawan dengan join ke department dan device
$sql = "SELECT 
            u.title as Title,
            u.name,
            u.Card,
            COALESCE(u.defaultdeptid, 1) as defaultdeptid,
            COALESCE(u.SN, '') as SN
        FROM userinfo u
        ORDER BY u.name";

$result = $conn->query($sql);

// Output header CSV (sesuai format import)
$output = fopen('php://output', 'w');
fputcsv($output, ['Title', 'name', 'Card', 'defaultdeptid', 'SN'], ';');

// Output data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['Title'],
        $row['name'],
        $row['Card'] ?? '',
        $row['defaultdeptid'],
        $row['SN']
    ], ';');
}

fclose($output);
$conn->close();
?>
