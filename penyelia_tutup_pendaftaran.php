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

// Update the course to close registration
$stmt = $conn->prepare("
    UPDATE kursus 
    SET 
        registration_status = 'closed',
        status_kursus = 'akan_datang'
    WHERE kursus_id = ?
");

$stmt->bind_param("i", $kursus_id);

if ($stmt->execute()) {
    // Get course name for success message
    $stmt_name = $conn->prepare("SELECT nama_kursus FROM kursus WHERE kursus_id = ?");
    $stmt_name->bind_param("i", $kursus_id);
    $stmt_name->execute();
    $result = $stmt_name->get_result();
    $course = $result->fetch_assoc();
    
    $_SESSION['success'] = "Pendaftaran untuk kursus '{$course['nama_kursus']}' telah ditutup";
    $stmt_name->close();
} else {
    $_SESSION['error'] = "Gagal menutup pendaftaran: " . $conn->error;
}

$stmt->close();
$conn->close();

header("Location: penyelia_dashboard.php");
exit();
?>