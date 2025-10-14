<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['email'];
$userId = $_SESSION['user_id'];

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ecourses_apm";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get course ID from URL
$kursus_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_waitlist = isset($_GET['waitlist']) && $_GET['waitlist'] == '1';

if ($kursus_id == 0) {
    header("Location: katalog_kursus.php");
    exit();
}

// Get course details
$course_sql = "SELECT k.*, 
               COALESCE(d.jumlah_daftar, 0) as jumlah_sudah_daftar,
               CASE 
                   WHEN k.pendaftar >= k.kapasiti THEN 'penuh'
                   WHEN k.pendaftar >= (k.kapasiti * 0.8) THEN 'senarai_tunggu'
                   ELSE k.status_kursus
               END as status_display
        FROM kursus k
        LEFT JOIN (
            SELECT kursus_id, COUNT(*) as jumlah_daftar 
            FROM daftar_kursus 
            WHERE status = 'diterima' 
            GROUP BY kursus_id
        ) d ON k.kursus_id = d.kursus_id
        WHERE k.kursus_id = :kursus_id";

$course_stmt = $pdo->prepare($course_sql);
$course_stmt->execute([':kursus_id' => $kursus_id]);
$course = $course_stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header("Location: katalog_kursus.php");
    exit();
}

// Check if user already registered for this course
$check_reg_sql = "SELECT * FROM daftar_kursus WHERE user_id = :user_id AND kursus_id = :kursus_id";
$check_reg_stmt = $pdo->prepare($check_reg_sql);
$check_reg_stmt->execute([':user_id' => $userId, ':kursus_id' => $kursus_id]);
$existing_registration = $check_reg_stmt->fetch(PDO::FETCH_ASSOC);

// Get user profile data to check completeness
$profile_sql = "SELECT u.*, p.*, h.* FROM users u 
                LEFT JOIN profile p ON u.user_id = p.user_id 
                LEFT JOIN borang_kesihatan h ON u.user_id = h.user_id 
                WHERE u.user_id = :user_id";
$profile_stmt = $pdo->prepare($profile_sql);
$profile_stmt->execute([':user_id' => $userId]);
$user_data = $profile_stmt->fetch(PDO::FETCH_ASSOC);

// Check profile completeness
$profile_complete = !empty($user_data['nama_penuh']) && !empty($user_data['no_ic']) && 
                   !empty($user_data['no_telefon']) && !empty($user_data['alamat']);
$health_complete = !empty($user_data['kesihatan_am']) && !empty($user_data['tarikh_kemaskini_kesihatan']);

// Handle form submission
$success_message = '';
$error_message = '';
$validation_errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['daftar_kursus'])) {
    
    // Validate required fields
    if (empty($_POST['alasan_penyertaan'])) {
        $validation_errors[] = "Alasan penyertaan diperlukan.";
    }
    
    if (empty($_POST['pengalaman_berkaitan'])) {
        $validation_errors[] = "Pengalaman berkaitan diperlukan.";
    }
    
    if (!isset($_POST['pengesahan_syarat'])) {
        $validation_errors[] = "Anda perlu mengesahkan pematuhan syarat kursus.";
    }
    
    // Check if user already registered
    if ($existing_registration) {
        $error_message = "Anda sudah mendaftar untuk kursus ini.";
    }
    
    // Check profile and health form completion
    if (!$profile_complete) {
        $error_message = "Sila lengkapkan profil anda terlebih dahulu sebelum mendaftar kursus.";
    }
    
    if (!$health_complete) {
        $error_message = "Sila lengkapkan borang kesihatan terlebih dahulu sebelum mendaftar kursus.";
    }
    
    // If no errors, process registration
    if (empty($validation_errors) && empty($error_message)) {
        try {
            // Determine registration status
            $reg_status = 'pending';
            $jenis_pendaftaran = 'biasa';
            
            if ($is_waitlist || $course['status_display'] == 'penuh') {
                $jenis_pendaftaran = 'senarai_tunggu';
            }
            
            // Insert registration
            $insert_sql = "INSERT INTO daftar_kursus (
                user_id, kursus_id, tarikh_daftar, status, jenis_pendaftaran,
                alasan_penyertaan, pengalaman_berkaitan, catatan_tambahan
            ) VALUES (
                :user_id, :kursus_id, NOW(), :status, :jenis_pendaftaran,
                :alasan_penyertaan, :pengalaman_berkaitan, :catatan_tambahan
            )";
            
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                ':user_id' => $userId,
                ':kursus_id' => $kursus_id,
                ':status' => $reg_status,
                ':jenis_pendaftaran' => $jenis_pendaftaran,
                ':alasan_penyertaan' => $_POST['alasan_penyertaan'],
                ':pengalaman_berkaitan' => $_POST['pengalaman_berkaitan'],
                ':catatan_tambahan' => $_POST['catatan_tambahan'] ?? ''
            ]);
            
            // Update course participant count if accepted directly (for future enhancement)
            // For now, all registrations are pending approval
            
            $success_message = $is_waitlist ? 
                "Pendaftaran anda telah berjaya dihantar dan dimasukkan ke dalam senarai tunggu. Anda akan dihubungi jika terdapat kekosongan." :
                "Pendaftaran anda telah berjaya dihantar dan sedang dalam proses semakan. Anda akan dihubungi dalam tempoh 3-5 hari bekerja.";
            
            // Refresh existing registration data
            $existing_registration = $check_reg_stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error_message = "Ralat dalam memproses pendaftaran. Sila cuba lagi.";
        }
    }
}

// Function to get category icon (same as katalog_kursus.php)
function getCategoryIcon($kategori) {
    switch($kategori) {
        case 'Program Kemahiran Teknikal': return 'fas fa-cogs';
        case 'Program Pembangunan Personel': return 'fas fa-users';
        case 'Program Akuatik': return 'fas fa-swimmer';
        case 'Program Paramedik': return 'fas fa-medkit';
        default: return 'fas fa-graduation-cap';
    }
}

// Function to get status class and text
function getStatusClass($status) {
    switch($status) {
        case 'buka_pendaftaran': return 'status-registration';
        case 'akan_datang': return 'status-upcoming';
        case 'penuh': return 'status-full';
        case 'senarai_tunggu': return 'status-waitlist';
        default: return 'status-upcoming';
    }
}

function getStatusText($status) {
    switch($status) {
        case 'buka_pendaftaran': return 'Buka Pendaftaran';
        case 'akan_datang': return 'Akan Datang';
        case 'penuh': return 'Penuh';
        case 'senarai_tunggu': return 'Senarai Tunggu';
        default: return 'Akan Datang';
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Daftar Kursus - <?php echo htmlspecialchars($course['nama_kursus']); ?> | e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        
        /* Header Styling - Matching katalog_kursus.php */
        .nav-action-btn { 
            margin-left: 10px; 
            padding: 8px 15px; 
            border-radius: 6px; 
            font-weight: 500; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            text-decoration: none; 
        }
        .nav-action-btn i { margin-right: 8px; }
        .login-btn { 
            background-color: rgba(255, 255, 255, 0.2); 
            color: white; 
            border: 1px solid rgba(255, 255, 255, 0.3); 
        }
        .login-btn:hover { 
            background-color: rgba(255, 255, 255, 0.3); 
            transform: translateY(-2px); 
        }
        .register-btn { 
            background-color: #ffc107; 
            color: #212529; 
            border: 1px solid #ffc107; 
        }
        .register-btn:hover { 
            background-color: #e0a800; 
            transform: translateY(-2px); 
        }
        .profile-btn { 
            background-color: #1757b8ff; 
            color: white; 
            border: 1px solid #175db8ff; 
        }
        .profile-btn:hover { 
            background-color: #135096ff; 
            transform: translateY(-2px); 
        }
        .datetime-display { 
            background-color: rgba(0, 0, 0, 0.1); 
            padding: 8px 15px; 
            border-radius: 20px; 
            font-size: 0.9rem; 
            display: inline-flex; 
            align-items: center; 
            margin-left: 15px; 
        }
        .datetime-display i { margin-right: 8px; }
        .user-email { 
            color: white; 
            margin-right: 10px; 
            font-weight: 500; 
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #0C3C60 0%, #17a2b8 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .page-header .container { position: relative; z-index: 2; }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Custom Breadcrumb */
        .custom-breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem 0;
            margin-bottom: 1rem;
        }

        .custom-breadcrumb .breadcrumb {
            background: none;
            margin-bottom: 0;
        }

        .custom-breadcrumb .breadcrumb-item a {
            color: #0C3C60;
            text-decoration: none;
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: #6c757d;
        }

        /* Course Info Card */
        .course-info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #0C3C60;
            margin-bottom: 2rem;
        }

        .course-header-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .course-icon-large {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 6px 12px rgba(12, 60, 96, 0.3);
        }

        .course-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-upcoming {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-registration {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .status-full {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-waitlist {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .course-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0C3C60;
            margin-bottom: 0.5rem;
        }

        .course-category {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .course-description {
            color: #495057;
            line-height: 1.7;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .course-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #0C3C60;
        }

        .detail-item i {
            color: #0C3C60;
            width: 20px;
            text-align: center;
        }

        .detail-item strong {
            color: #495057;
        }

        /* Progress Bar */
        .capacity-progress {
            margin-top: 1rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #495057;
            font-weight: 600;
        }

        .progress-bar-custom {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        /* Alert Messages */
        .alert-custom {
            border: none;
            border-radius: 10px;
            border-left: 4px solid;
            margin-bottom: 2rem;
        }

        .alert-success-custom {
            background: #d1edff;
            border-left-color: #0c5460;
            color: #0c5460;
        }

        .alert-danger-custom {
            background: #f8d7da;
            border-left-color: #721c24;
            color: #721c24;
        }

        .alert-warning-custom {
            background: #fff3cd;
            border-left-color: #856404;
            color: #856404;
        }

        .alert-info-custom {
            background: #d1ecf1;
            border-left-color: #0c5460;
            color: #0c5460;
        }

        /* Registration Form */
        .registration-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #17a2b8;
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0C3C60;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label .required {
            color: #dc3545;
        }

        .form-control-custom {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: #0C3C60;
            box-shadow: 0 0 0 3px rgba(12, 60, 96, 0.1);
        }

        .form-control-custom.is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Checkbox and Radio */
        .form-check-custom {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .form-check-custom input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 0.1rem;
        }

        .form-check-custom label {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #495057;
        }

        /* Buttons */
        .btn-custom {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 1rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            color: white;
        }

        .btn-primary-custom:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 60, 96, 0.3);
            color: white;
        }

        .btn-secondary-custom {
            background: white;
            color: #6c757d;
            border: 2px solid #dee2e6;
        }

        .btn-secondary-custom:hover {
            background: #f8f9fa;
            border-color: #6c757d;
            color: #495057;
        }

        .btn-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-group-custom {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Requirements Checklist */
        .requirements-card {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .requirements-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0C3C60;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .requirement-item:last-child {
            border-bottom: none;
        }

        .requirement-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: white;
        }

        .requirement-icon.complete {
            background: #28a745;
        }

        .requirement-icon.incomplete {
            background: #dc3545;
        }

        .requirement-text {
            flex: 1;
            font-size: 0.95rem;
        }

        .requirement-text.complete {
            color: #28a745;
        }

        .requirement-text.incomplete {
            color: #dc3545;
        }

        /* Footer - Same as other pages */
        footer { 
            background: #0C3C60; 
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px; 
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .course-header-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .course-details-grid {
                grid-template-columns: 1fr;
            }

            .btn-group-custom {
                flex-direction: column;
            }

            .registration-form {
                padding: 1.5rem;
            }
        }

        /* Success/Already Registered State */
        .success-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 15px;
            border-left: 6px solid #28a745;
            margin-bottom: 2rem;
        }

        .success-state i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1.5rem;
        }

        .success-state h3 {
            color: #28a745;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .success-state p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>

<!-- NAVBAR - Same as katalog_kursus.php -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="assets/img/logo_apm.jpeg" width="50" height="50" class="d-inline-block align-text-top me-2" alt="Logo APM">
            <span>e-Kursus ALPHA</span>
        </a>
        <div class="d-flex align-items-center ms-auto">
            <div class="datetime-display text-white">
                <i class="fas fa-calendar-alt"></i>
                <span id="datetime"><?php echo date('l, j F Y, h:i:s A'); ?></span>
            </div>

            <span class="user-email">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($userEmail); ?>
            </span>
            <a href="dashboard.php" class="nav-action-btn profile-btn">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="logout.php" class="nav-action-btn login-btn">
                <i class="fas fa-sign-out-alt"></i> Log Keluar
            </a>
        </div>
    </div>
</nav>

<!-- BREADCRUMB -->
<div class="custom-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Laman Utama</a></li>
                <li class="breadcrumb-item"><a href="katalog_kursus.php"><i class="fas fa-book-open"></i> Katalog Kursus</a></li>
                <li class="breadcrumb-item active" aria-current="page">Daftar Kursus</li>
            </ol>
        </nav>
    </div>
</div>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="page-title">
                    <i class="fas fa-user-plus me-3"></i>
                    Daftar Kursus
                </h1>
                <p class="page-subtitle">
                    <?php echo $is_waitlist ? 'Sertai senarai tunggu untuk kursus ini' : 'Lengkapkan pendaftaran untuk menyertai kursus'; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">

    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
    <div class="success-state">
        <i class="fas fa-check-circle"></i>
        <h3>Pendaftaran Berjaya!</h3>
        <p><?php echo htmlspecialchars($success_message); ?></p>
        <div class="btn-group-custom justify-content-center">
            <a href="dashboard.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-tachometer-alt"></i>
                Lihat Dashboard
            </a>
            <a href="katalog_kursus.php" class="btn-custom btn-secondary-custom">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Katalog
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Already Registered State -->
    <?php if ($existing_registration && empty($success_message)): ?>
    <div class="success-state">
        <i class="fas fa-info-circle"></i>
        <h3>Anda Sudah Berdaftar</h3>
        <p>
            Anda telah mendaftar untuk kursus ini pada 
            <strong><?php echo date('d/m/Y', strtotime($existing_registration['tarikh_daftar'])); ?></strong>
            <br>
            Status pendaftaran: 
            <span class="badge bg-<?php 
                echo $existing_registration['status'] == 'diterima' ? 'success' : 
                    ($existing_registration['status'] == 'pending' ? 'warning' : 'danger'); 
            ?>">
                <?php 
                    switch($existing_registration['status']) {
                        case 'diterima': echo 'Diterima'; break;
                        case 'pending': echo 'Dalam Proses'; break;
                        case 'ditolak': echo 'Ditolak'; break;
                    }
                ?>
            </span>
        </p>
        <div class="btn-group-custom justify-content-center">
            <a href="dashboard.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-tachometer-alt"></i>
                Lihat Dashboard
            </a>
            <a href="katalog_kursus.php" class="btn-custom btn-secondary-custom">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Katalog
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Show registration form only if not already registered and no success message -->
    <?php if (!$existing_registration && empty($success_message)): ?>

    <!-- COURSE INFORMATION -->
    <div class="course-info-card">
        <div class="course-header-info">
            <div class="course-icon-large">
                <i class="<?php echo getCategoryIcon($course['kategori']); ?>"></i>
            </div>
            <div class="flex-fill">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="course-category">
                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($course['kategori']); ?>
                    </div>
                    <div class="course-status <?php echo getStatusClass($course['status_display']); ?>">
                        <?php echo getStatusText($course['status_display']); ?>
                    </div>
                </div>
                <h2 class="course-title"><?php echo htmlspecialchars($course['nama_kursus']); ?></h2>
            </div>
        </div>
        
        <div class="course-description">
            <?php echo nl2br(htmlspecialchars($course['penerangan'])); ?>
        </div>
        
        <div class="course-details-grid">
            <div class="detail-item">
                <i class="fas fa-calendar"></i>
                <div>
                    <strong>Tarikh Mula:</strong><br>
                    <?php echo $course['tarikh_mula'] ? date('d/m/Y', strtotime($course['tarikh_mula'])) : 'Akan Diumumkan'; ?>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-calendar-check"></i>
                <div>
                    <strong>Tarikh Tamat:</strong><br>
                    <?php echo $course['tarikh_tamat'] ? date('d/m/Y', strtotime($course['tarikh_tamat'])) : 'Akan Diumumkan'; ?>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Tempoh:</strong><br>
                    <?php echo htmlspecialchars($course['tempoh'] ?: 'Akan Diumumkan'); ?>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <strong>Lokasi:</strong><br>
                    <?php echo htmlspecialchars($course['lokasi']); ?>
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-users"></i>
                <div>
                    <strong>Kapasiti:</strong><br>
                    <?php echo $course['pendaftar']; ?>/<?php echo $course['kapasiti']; ?> peserta
                </div>
            </div>
            <div class="detail-item">
                <i class="fas fa-money-bill-wave"></i>
                <div>
                    <strong>Yuran:</strong><br>
                    <?php echo $course['yuran'] ? 'RM ' . number_format($course['yuran'], 2) : 'Percuma'; ?>
                </div>
            </div>
        </div>

        <!-- Capacity Progress -->
        <div class="capacity-progress">
            <?php 
                $progress_percentage = $course['kapasiti'] > 0 ? ($course['pendaftar'] / $course['kapasiti']) * 100 : 0;
            ?>
            <div class="progress-label">
                <span>Kapasiti Pendaftaran</span>
                <span><?php echo round($progress_percentage); ?>%</span>
            </div>
            <div class="progress-bar-custom">
                <div class="progress-fill" style="width: <?php echo min($progress_percentage, 100); ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- REQUIREMENTS CHECKLIST -->
    <div class="requirements-card">
        <div class="requirements-title">
            <i class="fas fa-clipboard-check"></i>
            Semakan Kelayakan Pendaftaran
        </div>
        
        <div class="requirement-item">
            <div class="requirement-icon <?php echo $profile_complete ? 'complete' : 'incomplete'; ?>">
                <i class="fas <?php echo $profile_complete ? 'fa-check' : 'fa-times'; ?>"></i>
            </div>
            <div class="requirement-text <?php echo $profile_complete ? 'complete' : 'incomplete'; ?>">
                <strong>Profil Lengkap:</strong> Maklumat peribadi, No. IC, telefon dan alamat
                <?php if (!$profile_complete): ?>
                    <br><small><a href="kemaskini.php" class="text-primary">Klik di sini untuk melengkapkan profil</a></small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="requirement-item">
            <div class="requirement-icon <?php echo $health_complete ? 'complete' : 'incomplete'; ?>">
                <i class="fas <?php echo $health_complete ? 'fa-check' : 'fa-times'; ?>"></i>
            </div>
            <div class="requirement-text <?php echo $health_complete ? 'complete' : 'incomplete'; ?>">
                <strong>Borang Kesihatan:</strong> Borang kesihatan dan pemeriksaan perubatan
                <?php if (!$health_complete): ?>
                    <br><small><a href="borang_kesihatan.php" class="text-primary">Klik di sini untuk melengkapkan borang kesihatan</a></small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="requirement-item">
            <div class="requirement-icon complete">
                <i class="fas fa-check"></i>
            </div>
            <div class="requirement-text complete">
                <strong>Akaun Aktif:</strong> Akaun pengguna e-Kursus ALPHA yang sah
            </div>
        </div>

        <?php if ($is_waitlist): ?>
        <div class="alert alert-info-custom mt-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Maklumat Senarai Tunggu:</strong> Kursus ini telah penuh. Pendaftaran anda akan dimasukkan ke dalam senarai tunggu dan anda akan dihubungi jika terdapat kekosongan.
        </div>
        <?php endif; ?>
    </div>

    <!-- ERROR MESSAGES -->
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger-custom">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($validation_errors)): ?>
    <div class="alert alert-danger-custom">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Sila betulkan ralat berikut:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($validation_errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- REGISTRATION FORM -->
    <?php if (($profile_complete && $health_complete) || 
              (!empty($error_message) && strpos($error_message, 'sudah mendaftar') === false)): ?>
    
    <div class="registration-form">
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-edit"></i>
                Borang Pendaftaran Kursus
            </div>
            
            <form method="POST" action="" id="registrationForm">
                
                <!-- Personal Information Display -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user"></i>
                        Maklumat Peribadi
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nama Penuh</label>
                                <input type="text" class="form-control-custom" 
                                       value="<?php echo htmlspecialchars($user_data['nama_penuh']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">No. Kad Pengenalan</label>
                                <input type="text" class="form-control-custom" 
                                       value="<?php echo htmlspecialchars($user_data['no_ic']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control-custom" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">No. Telefon</label>
                                <input type="text" class="form-control-custom" 
                                       value="<?php echo htmlspecialchars($user_data['no_telefon']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application Details -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-clipboard-list"></i>
                        Maklumat Permohonan
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-question-circle"></i>
                            Alasan Penyertaan <span class="required">*</span>
                        </label>
                        <textarea name="alasan_penyertaan" class="form-control-custom <?php echo in_array('Alasan penyertaan diperlukan.', $validation_errors) ? 'is-invalid' : ''; ?>" 
                                  rows="4" placeholder="Nyatakan alasan mengapa anda ingin menyertai kursus ini..."></textarea>
                        <?php if (in_array('Alasan penyertaan diperlukan.', $validation_errors)): ?>
                            <div class="invalid-feedback">Alasan penyertaan diperlukan.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-history"></i>
                            Pengalaman Berkaitan <span class="required">*</span>
                        </label>
                        <textarea name="pengalaman_berkaitan" class="form-control-custom <?php echo in_array('Pengalaman berkaitan diperlukan.', $validation_errors) ? 'is-invalid' : ''; ?>" 
                                  rows="4" placeholder="Nyatakan pengalaman berkaitan dengan kursus ini (jika ada) atau tulis 'Tiada' jika tidak ada pengalaman..."></textarea>
                        <?php if (in_array('Pengalaman berkaitan diperlukan.', $validation_errors)): ?>
                            <div class="invalid-feedback">Pengalaman berkaitan diperlukan.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-comment"></i>
                            Catatan Tambahan
                        </label>
                        <textarea name="catatan_tambahan" class="form-control-custom" 
                                  rows="3" placeholder="Sebarang catatan tambahan (pilihan)..."></textarea>
                        <small class="text-muted">Catatan ini adalah pilihan. Anda boleh menyatakan sebarang keperluan khas atau soalan.</small>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-gavel"></i>
                        Terma dan Syarat
                    </div>
                    
                    <div class="form-check-custom">
                        <input type="checkbox" name="pengesahan_syarat" id="pengesahan_syarat" required
                               class="<?php echo in_array('Anda perlu mengesahkan pematuhan syarat kursus.', $validation_errors) ? 'is-invalid' : ''; ?>">
                        <label for="pengesahan_syarat">
                            Saya mengesahkan bahawa:
                            <ul class="mt-2 mb-0">
                                <li>Semua maklumat yang diberikan adalah benar dan tepat</li>
                                <li>Saya memenuhi syarat kelayakan untuk kursus ini</li>
                                <li>Saya bersetuju dengan terma dan syarat yang ditetapkan</li>
                                <li>Saya akan hadir tepat pada masa dan mengikuti segala peraturan kursus</li>
                                <li>Saya memahami bahawa pendaftaran ini tertakluk kepada kelulusan pihak pengurusan</li>
                            </ul>
                        </label>
                        <?php if (in_array('Anda perlu mengesahkan pematuhan syarat kursus.', $validation_errors)): ?>
                            <div class="invalid-feedback">Anda perlu mengesahkan pematuhan syarat kursus.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="btn-group-custom">
                    <button type="submit" name="daftar_kursus" class="btn-custom btn-primary-custom" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        <?php echo $is_waitlist ? 'Sertai Senarai Tunggu' : 'Hantar Permohonan'; ?>
                    </button>
                    <a href="katalog_kursus.php" class="btn-custom btn-secondary-custom">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Katalog
                    </a>
                </div>
                
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Requirements Not Met -->
    <div class="alert alert-warning-custom">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Kelayakan Tidak Dipenuhi:</strong> 
        Sila lengkapkan profil dan borang kesihatan anda terlebih dahulu sebelum mendaftar kursus.
        <div class="mt-2">
            <?php if (!$profile_complete): ?>
                <a href="kemaskini.php" class="btn btn-sm btn-primary me-2">
                    <i class="fas fa-user-edit"></i> Lengkapkan Profil
                </a>
            <?php endif; ?>
            <?php if (!$health_complete): ?>
                <a href="borang_kesihatan.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-medkit"></i> Lengkapkan Borang Kesihatan
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // End of not already registered check ?>

    <!-- ADDITIONAL COURSE INFORMATION -->
    <div class="row mt-4">
        <div class="col-md-6 mb-4">
            <div class="course-info-card">
                <div class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    Maklumat Penting
                </div>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Semua kursus adalah percuma untuk kakitangan kerajaan</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Sijil akan diberikan setelah tamat kursus</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Kemudahan penginapan disediakan (jika diperlukan)</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Makanan dan minuman disediakan</li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="course-info-card">
                <div class="form-section-title">
                    <i class="fas fa-phone"></i>
                    Hubungi Kami
                </div>
                <p>Jika anda mempunyai sebarang pertanyaan mengenai kursus ini, sila hubungi:</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-phone text-primary me-2"></i>Tel: 03-8064 2222</li>
                    <li class="mb-2"><i class="fas fa-fax text-primary me-2"></i>Faks: 03-8064 2223</li>
                    <li class="mb-2"><i class="fas fa-envelope text-primary me-2"></i>Email: info@alpha.gov.my</li>
                    <li class="mb-2"><i class="fas fa-globe text-primary me-2"></i>Laman Web: www.alpha.gov.my</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer>
    <p>&copy; <?php echo date("Y"); ?> e-Kursus ALPHA | Hak Cipta Terpelihara</p>
</footer>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update datetime display every second
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        };
        document.getElementById('datetime').textContent =
            now.toLocaleString('ms-MY', options);
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Form validation and submission
    document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const originalContent = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        submitBtn.disabled = true;
        
        // Basic client-side validation
        const alasanField = document.querySelector('textarea[name="alasan_penyertaan"]');
        const pengalamanField = document.querySelector('textarea[name="pengalaman_berkaitan"]');
        const pengesahanField = document.querySelector('input[name="pengesahan_syarat"]');
        
        let hasError = false;
        
        // Remove previous error states
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        if (!alasanField.value.trim()) {
            alasanField.classList.add('is-invalid');
            hasError = true;
        }
        
        if (!pengalamanField.value.trim()) {
            pengalamanField.classList.add('is-invalid');
            hasError = true;
        }
        
        if (!pengesahanField.checked) {
            pengesahanField.classList.add('is-invalid');
            hasError = true;
        }
        
        if (hasError) {
            e.preventDefault();
            submitBtn.innerHTML = originalContent;
            submitBtn.disabled = false;
            
            // Scroll to first error
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
    });

    // Auto-resize textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });

    // Character counter for textareas
    document.querySelectorAll('textarea[name="alasan_penyertaan"], textarea[name="pengalaman_berkaitan"]').forEach(textarea => {
        const maxLength = 500;
        const counter = document.createElement('small');
        counter.className = 'text-muted float-end';
        textarea.parentElement.appendChild(counter);
        
        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length}/${maxLength} aksara`;
            counter.className = remaining < 50 ? 'text-danger float-end' : 'text-muted float-end';
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Progress bar animation on page load
    window.addEventListener('load', function() {
        const progressFill = document.querySelector('.progress-fill');
        if (progressFill) {
            const width = progressFill.style.width;
            progressFill.style.width = '0%';
            setTimeout(() => {
                progressFill.style.width = width;
            }, 500);
        }
    });
</script>

</body>
</html>