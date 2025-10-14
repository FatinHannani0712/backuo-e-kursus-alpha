<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // PhpSpreadsheet library

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['takwim_file']) || $_FILES['takwim_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = "Ralat: Fail tidak berjaya dimuat naik.";
    header("Location: admin_import_takwim.php");
    exit();
}

// Get form data
$tahun = $_POST['tahun'] ?? null;
$semester = $_POST['semester'] ?? null;

if (!$tahun || !$semester) {
    $_SESSION['error_message'] = "Ralat: Sila pilih tahun dan semester.";
    header("Location: admin_import_takwim.php");
    exit();
}

$uploadedFile = $_FILES['takwim_file'];
$fileName = $uploadedFile['name'];
$fileTmpPath = $uploadedFile['tmp_name'];

// Validate file extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($fileExtension, ['xlsx', 'xls'])) {
    $_SESSION['error_message'] = "Ralat: Format fail tidak sah. Sila upload fail .xlsx atau .xls";
    header("Location: admin_import_takwim.php");
    exit();
}

try {
    // Load the Excel file
    $spreadsheet = IOFactory::load($fileTmpPath);
    
    // Determine which sheet to use based on semester
    $sheetName = ($semester === 'JAN-JUN') ? 'JAN - JUN ' . $tahun : 'JULAI - DIS ' . $tahun;
    
    // Try to get the specified sheet, or use the first available sheet
    try {
        $worksheet = $spreadsheet->getSheetByName($sheetName);
    } catch (Exception $e) {
        // If sheet not found, use first sheet
        $worksheet = $spreadsheet->getActiveSheet();
    }
    
    // Convert to array
    $data = $worksheet->toArray();
    
    // Find the header row (contains "BIL", "MINGGU", "KURSUS", etc.)
    $headerRowIndex = -1;
    foreach ($data as $index => $row) { 
        if (isset($row[0]) && ($row[0] === 'BIL.' || $row[0] === 'BIL')) {
            $headerRowIndex = $index;
            break;
        }
    }
    
    if ($headerRowIndex === -1) {
        throw new Exception("Header row tidak dijumpai. Pastikan Excel mengandungi header BIL, MINGGU & TARIKH, KURSUS, dll.");
    }
    
    // Extract course data (rows after header)
    $courseRows = array_slice($data, $headerRowIndex + 1);
    
    // Filter valid course rows
    $validCourses = [];
    foreach ($courseRows as $row) {
        // Must have: number in BIL column and course name
        if (isset($row[0]) && is_numeric($row[0]) && isset($row[2]) && strlen(trim($row[2])) > 10) {
            $validCourses[] = $row;
        }
    }
    
    if (empty($validCourses)) {
        throw new Exception("Tiada kursus yang sah dijumpai dalam fail Excel.");
    }
    
    // Start database transaction
    $pdo->beginTransaction();
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($validCourses as $row) {
        try {
            $bil = is_numeric($row[0]) ? (int)$row[0] : null;  // Optional: keep for reference
            $mingguTarikh = trim($row[1] ?? '');
            $namaKursus = trim($row[2] ?? '');
            $platform = trim($row[3] ?? 'BERSEMUKA');
            $kapasiti = is_numeric($row[4]) ? (int)$row[4] : 30;
            $praSyarat = trim($row[5] ?? '');
            
            // Extract week number and dates from mingguTarikh
            // Example: "MINGGU 1\r\n5 - 11/7/25\r\n(SABTU-JUMAAT)"
            $mingguNo = null;
            $tarikhMula = null;
            $tarikhAkhir = null;
            
            if (preg_match('/MINGGU\s+(\d+)/i', $mingguTarikh, $matches)) {
                $mingguNo = 'MINGGU ' . $matches[1];
            }
            
            // Try to extract dates (format: DD - DD/MM/YY or DD/MM/YY - DD/MM/YY)
            if (preg_match('/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})/i', $mingguTarikh, $matches)) {
                $startDay = $matches[1];
                $endDay = $matches[2];
                $month = $matches[3];
                $year = strlen($matches[4]) == 2 ? '20' . $matches[4] : $matches[4];
                
                $tarikhMula = "$year-$month-$startDay";
                $tarikhAkhir = "$year-$month-$endDay";
            }
            
            // Extract siri from course name
            $siri = '';
            if (preg_match('/SIRI\s+(\d+\/\d+)/i', $namaKursus, $matches)) {
                $siri = 'SIRI ' . $matches[1];
            }
            
            // Auto-categorize course
            $kategori = getCourseCategory($namaKursus);
            
            // Insert into takwim_master
            $sql = "INSERT INTO takwim_master (
                        bil, minggu_no, minggu_tarikh, nama_kursus_takwim, siri,
                        platform, kapasiti_rancangan, pra_syarat,
                        tarikh_mula_rancangan, tarikh_akhir_rancangan, kategori,
                        tahun, semester, imported_by
                    ) VALUES (
                        :bil, :minggu_no, :minggu_tarikh, :nama_kursus, :siri,
                        :platform, :kapasiti, :pra_syarat,
                        :tarikh_mula, :tarikh_akhir, :kategori,
                        :tahun, :semester, :user_id
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':bil' => $bil,
                ':minggu_no' => $mingguNo,
                ':minggu_tarikh' => $mingguTarikh,
                ':nama_kursus' => $namaKursus,
                ':siri' => $siri,
                ':platform' => $platform,
                ':kapasiti' => $kapasiti,
                ':pra_syarat' => $praSyarat,
                ':tarikh_mula' => $tarikhMula,
                ':tarikh_akhir' => $tarikhAkhir,
                ':kategori' => $kategori,
                ':tahun' => $tahun,
                ':semester' => $semester,
                ':user_id' => $userId
            ]);
            
            $successCount++;
            
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Baris $bil: " . $e->getMessage();
        }
    }
    
    // Log the import
    $logSql = "INSERT INTO takwim_import_log (
                   filename, tahun, semester, total_courses, success_count, 
                   error_count, errors, imported_by
               ) VALUES (
                   :filename, :tahun, :semester, :total, :success, 
                   :error, :errors, :user_id
               )";
    
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        ':filename' => $fileName,
        ':tahun' => $tahun,
        ':semester' => $semester,
        ':total' => count($validCourses),
        ':success' => $successCount,
        ':error' => $errorCount,
        ':errors' => empty($errors) ? null : implode("\n", $errors),
        ':user_id' => $userId
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Success message
    $_SESSION['success_message'] = "Import berjaya! $successCount kursus telah diimport.";
    if ($errorCount > 0) {
        $_SESSION['success_message'] .= " ($errorCount kursus gagal diimport)";
    }
    
    header("Location: admin_import_takwim.php");
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = "Ralat import: " . $e->getMessage();
    header("Location: admin_import_takwim.php");
    exit();
}

// Helper function to categorize courses
function getCourseCategory($courseName) {
    $courseName = strtoupper($courseName);
    
    if (strpos($courseName, 'RENANG') !== false || 
        strpos($courseName, 'AKUATIK') !== false || 
        strpos($courseName, 'PANTAI') !== false || 
        strpos($courseName, 'KOLAM') !== false) {
        return 'Program Akuatik';
    }
    
    if (strpos($courseName, 'ASCENDING') !== false || 
        strpos($courseName, 'DESCENDING') !== false || 
        strpos($courseName, 'A&D') !== false || 
        strpos($courseName, 'TALI') !== false) {
        return 'Program Teknik Tali';
    }
    
    if (strpos($courseName, 'FRLS') !== false || 
        strpos($courseName, 'FIRST RESPONDER') !== false || 
        strpos($courseName, 'LIFE SUPPORT') !== false) {
        return 'Program Kesihatan';
    }
    
    if (strpos($courseName, 'BAKAL PEGAWAI') !== false || 
        strpos($courseName, 'KEPIMPINAN') !== false) {
        return 'Program Kepimpinan';
    }
    
    if (strpos($courseName, 'KECERGASAN') !== false || 
        strpos($courseName, 'FITNESS') !== false) {
        return 'Program Kecergasan';
    }
    
    if (strpos($courseName, 'BENCANA') !== false || 
        strpos($courseName, 'RUNTUHAN') !== false || 
        strpos($courseName, 'KESELAMATAN') !== false || 
        strpos($courseName, 'SAR') !== false) {
        return 'Program Keselamatan';
    }
    
    if (strpos($courseName, 'ASAS PERTAHANAN AWAM') !== false || 
        strpos($courseName, 'ASPA') !== false) {
        return 'Program Asas';
    }
    
    return 'Program Lain-lain';
}
?>