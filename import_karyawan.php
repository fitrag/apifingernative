<?php
/**
 * Import Karyawan dari Excel/CSV - Optimized Version
 */

require_once 'config.php';
require_once 'settings.php';

header('Content-Type: application/json');

// Download SimpleXLSX jika belum ada
if (!file_exists('SimpleXLSX.php')) {
    $xlsxLib = @file_get_contents('https://raw.githubusercontent.com/shuchkin/simplexlsx/master/src/SimpleXLSX.php');
    if ($xlsxLib) file_put_contents('SimpleXLSX.php', $xlsxLib);
}
if (file_exists('SimpleXLSX.php')) require_once 'SimpleXLSX.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'template':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="template_karyawan.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Title', 'name', 'Card', 'FPHONE', 'defaultdeptid', 'SN'], ';');
        fputcsv($output, ['001', 'John Doe', '628123456789', '021123456', '1', 'DEVICE001'], ';');
        fputcsv($output, ['002', 'Jane Smith', '628987654321', '021654321', '1', 'DEVICE001'], ';');
        fclose($output);
        exit;
        
    case 'import':
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File tidak valid']);
            exit;
        }
        
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $data = [];
        $errors = [];
        
        // Parse file
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) rewind($handle);
            
            $header = null;
            $lineNum = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $lineNum++;
                if ($header === null) {
                    $header = array_map('trim', array_map('strtolower', $row));
                    continue;
                }
                if (count($row) >= 2) {
                    $rowData = array_combine($header, array_pad($row, count($header), ''));
                    $data[] = [
                        'line' => $lineNum,
                        'title' => trim($rowData['title'] ?? ''),
                        'name' => trim($rowData['name'] ?? ''),
                        'card' => trim($rowData['card'] ?? ''),
                        'fphone' => trim($rowData['fphone'] ?? ''),
                        'defaultdeptid' => trim($rowData['defaultdeptid'] ?? '1'),
                        'sn' => trim($rowData['sn'] ?? '')
                    ];
                }
            }
            fclose($handle);
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            if (!class_exists('Shuchkin\SimpleXLSX')) {
                echo json_encode(['success' => false, 'message' => 'Library SimpleXLSX tidak tersedia']);
                exit;
            }
            $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);
            if ($xlsx) {
                $rows = $xlsx->rows();
                $header = null;
                $lineNum = 0;
                foreach ($rows as $row) {
                    $lineNum++;
                    if ($header === null) {
                        $header = array_map('trim', array_map('strtolower', $row));
                        continue;
                    }
                    if (count($row) >= 2) {
                        $rowData = array_combine($header, array_pad($row, count($header), ''));
                        $data[] = [
                            'line' => $lineNum,
                            'title' => trim($rowData['title'] ?? ''),
                            'name' => trim($rowData['name'] ?? ''),
                            'card' => trim($rowData['card'] ?? ''),
                            'fphone' => trim($rowData['fphone'] ?? ''),
                            'defaultdeptid' => trim($rowData['defaultdeptid'] ?? '1'),
                            'sn' => trim($rowData['sn'] ?? '')
                        ];
                    }
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membaca file Excel']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Format tidak didukung']);
            exit;
        }
        
        if (empty($data)) {
            echo json_encode(['success' => false, 'message' => 'File kosong']);
            exit;
        }
        
        $conn = getConnection();
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $mode = $_POST['mode'] ?? 'skip';
        
        // Ambil max badge number sekali saja
        $maxBadgeResult = $conn->query("SELECT MAX(CAST(badgenumber AS UNSIGNED)) as max_badge FROM userinfo WHERE badgenumber REGEXP '^[0-9]+$'");
        $nextBadgeNumber = (($maxBadgeResult->fetch_assoc()['max_badge'] ?? 0) + 1);
        
        // Ambil semua title yang sudah ada untuk lookup cepat
        $existingTitles = [];
        $result = $conn->query("SELECT userid, title FROM userinfo WHERE title IS NOT NULL AND title != ''");
        while ($row = $result->fetch_assoc()) {
            $existingTitles[$row['title']] = $row['userid'];
        }
        $result->free();
        
        // Prepare statements sekali saja
        $updateStmt = $conn->prepare("UPDATE userinfo SET name=?, Card=?, FPHONE=?, defaultdeptid=? WHERE title=?");
        $insertStmt = $conn->prepare("INSERT INTO userinfo (badgenumber, name, title, Card, FPHONE, defaultdeptid, SN) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Mulai transaction untuk batch processing
        $conn->begin_transaction();
        
        try {
            foreach ($data as $row) {
                if (empty($row['name'])) {
                    $errors[] = "Baris {$row['line']}: Nama wajib diisi";
                    $skipped++;
                    continue;
                }
                if (empty($row['title'])) {
                    $errors[] = "Baris {$row['line']}: Title (NIS) wajib diisi";
                    $skipped++;
                    continue;
                }
                
                $deptId = !empty($row['defaultdeptid']) ? (int)$row['defaultdeptid'] : 1;
                
                if (isset($existingTitles[$row['title']])) {
                    if ($mode === 'skip') {
                        $skipped++;
                        continue;
                    }
                    // Update
                    $updateStmt->bind_param("sssis", $row['name'], $row['card'], $row['fphone'], $deptId, $row['title']);
                    $updateStmt->execute();
                    $updated++;
                } else {
                    // Insert
                    $newBadge = str_pad($nextBadgeNumber, 9, '0', STR_PAD_LEFT);
                    $insertStmt->bind_param("sssssis", $newBadge, $row['name'], $row['title'], $row['card'], $row['fphone'], $deptId, $row['sn']);
                    if ($insertStmt->execute()) {
                        $existingTitles[$row['title']] = $conn->insert_id;
                        $imported++;
                        $nextBadgeNumber++;
                    } else {
                        $errors[] = "Baris {$row['line']}: " . $insertStmt->error;
                        $skipped++;
                    }
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
        
        $updateStmt->close();
        $insertStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Import selesai: $imported baru, $updated diupdate, $skipped dilewati",
            'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10)
        ]);
        exit;
        
    case 'columns':
        $conn = getConnection();
        $result = $conn->query("DESCRIBE userinfo");
        $columns = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'columns' => $columns]);
        exit;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
