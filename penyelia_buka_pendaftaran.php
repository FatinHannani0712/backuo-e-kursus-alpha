<?php
session_start();
require_once 'config.php';

// Check if user is logged in as Penyelia
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penyelia') {
    header("Location: login.php");
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: penyelia_dashboard.php");
    exit();
}

// Get kursus_id from POST
$kursus_id = isset($_POST['kursus_id']) ? intval($_POST['kursus_id']) : 0;

if ($kursus_id <= 0) {
    $_SESSION['error'] = "Kursus tidak sah";
    header("Location: penyelia_dashboard.php");
    exit();
}

// Calculate dates
$registration_open_date = date('Y-m-d'); // Today
$registration_close_date = date('Y-m-d', strtotime('+1 month')); // Today + 1 month

// Update the course
$stmt = $conn->prepare("
    UPDATE kursus 
    SET 
        registration_status = 'open',
        registration_open_date = ?,
        registration_close_date = ?,
        status_kursus = 'buka_pendaftaran',
        opened_by_penyelia = ?,
        opened_at = NOW()
    WHERE kursus_id = ?
");

$penyelia_id = $_SESSION['user_id'];
$stmt->bind_param("ssii", $registration_open_date, $registration_close_date, $penyelia_id, $kursus_id);

if ($stmt->execute()) {
    // Get course name for success message
    $stmt_name = $conn->prepare("SELECT nama_kursus FROM kursus WHERE kursus_id = ?");
    $stmt_name->bind_param("i", $kursus_id);
    $stmt_name->execute();
    $result = $stmt_name->get_result();
    $course = $result->fetch_assoc();
    
    $_SESSION['success'] = "Pendaftaran untuk kursus '{$course['nama_kursus']}' telah dibuka sehingga " . date('d/m/Y', strtotime($registration_close_date));
    $stmt_name->close();
} else {
    $_SESSION['error'] = "Gagal membuka pendaftaran: " . $conn->error;
}

$stmt->close();
$conn->close();

header("Location: penyelia_dashboard.php");
exit();
?>