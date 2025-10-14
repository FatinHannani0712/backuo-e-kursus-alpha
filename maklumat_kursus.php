<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start session (to check login status)
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$userEmail  = $isLoggedIn ? $_SESSION['email'] : '';
$userId = $isLoggedIn ? $_SESSION['user_id'] : 0;

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
$user_registration = null;
if ($isLoggedIn) {
    $reg_sql = "SELECT * FROM daftar_kursus WHERE user_id = :user_id AND kursus_id = :kursus_id";
    $reg_stmt = $pdo->prepare($reg_sql);
    $reg_stmt->execute([':user_id' => $userId, ':kursus_id' => $kursus_id]);
    $user_registration = $reg_stmt->fetch(PDO::FETCH_ASSOC);
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
    <title><?php echo htmlspecialchars($course['nama_kursus']); ?> - e-Kursus ALPHA</title>
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
        
        /* Header Styling - Same as katalog_kursus.php */
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
            padding: 4rem 0;
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

        /* Course Header */
        .course-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #0C3C60;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: linear-gradient(45deg, rgba(12, 60, 96, 0.05), transparent);
            border-radius: 0 0 0 150px;
        }

        .course-main-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .course-icon-large {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 8px 16px rgba(12, 60, 96, 0.3);
        }

        .course-title-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0C3C60;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .course-category {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .course-status {
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-upcoming {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        .status-registration {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }

        .status-full {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .status-waitlist {
            background: #e2e3e5;
            color: #383d41;
            border: 2px solid #d6d8db;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #17a2b8;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0C3C60;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .course-description {
            color: #495057;
            line-height: 1.8;
            font-size: 1rem;
            text-align: justify;
        }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 4px solid #0C3C60;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .detail-text strong {
            color: #0C3C60;
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .detail-text span {
            color: #495057;
            font-size: 1rem;
        }

        /* Progress Bar */
        .capacity-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #28a745;
            margin-bottom: 2rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .progress-label h5 {
            color: #0C3C60;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-stats {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
        }

        .progress-bar-custom {
            width: 100%;
            height: 15px;
            background: #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 8px;
            transition: width 0.8s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Action Buttons */
        .action-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 6px solid #ffc107;
            margin-bottom: 2rem;
            text-align: center;
        }

        .btn-custom {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 1rem;
            margin: 0.5rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            color: white;
        }

        .btn-primary-custom:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(12, 60, 96, 0.3);
            color: white;
        }

        .btn-secondary-custom {
            background: white;
            color: #0C3C60;
            border: 2px solid #0C3C60;
        }

        .btn-secondary-custom:hover {
            background: #0C3C60;
            color: white;
        }

        .btn-success-custom {
            background: #28a745;
            color: white;
        }

        .btn-warning-custom {
            background: #ffc107;
            color: #212529;
        }

        .btn-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Registration Badge */
        .registration-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            z-index: 3;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .registration-badge.diterima {
            background: #28a745;
            color: white;
        }

        .registration-badge.pending {
            background: #ffc107;
            color: #212529;
        }

        .registration-badge.ditolak {
            background: #dc3545;
            color: white;
        }

        /* Footer */
        footer { 
            background: #0C3C60; 
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px; 
        }

        /* Responsive */
        @media (max-width: 768px) {
            .course-main-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .course-title-section h1 {
                font-size: 2rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .content-card, .detail-card {
                padding: 1.5rem;
            }

            .course-icon-large {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }

        /* Additional Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-top: 4px solid #17a2b8;
            text-align: center;
        }

        .info-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0C3C60, #17a2b8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 1rem auto;
        }

        .info-card h5 {
            color: #0C3C60;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .info-card p {
            color: #6c757d;
            margin: 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
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

            <?php if ($isLoggedIn): ?>
                <span class="user-email">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($userEmail); ?>
                </span>
                <a href="dashboard.php" class="nav-action-btn profile-btn">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="logout.php" class="nav-action-btn login-btn">
                    <i class="fas fa-sign-out-alt"></i> Log Keluar
                </a>
            <?php else: ?>
                <a href="login.php" class="nav-action-btn login-btn">
                    <i class="fas fa-sign-in-alt"></i> Log Masuk
                </a>
                <a href="register.php" class="nav-action-btn register-btn">
                    <i class="fas fa-user-plus"></i> Daftar Akaun
                </a>
                <a href="kemaskini.php" class="nav-action-btn profile-btn">
                    <i class="fas fa-user-edit"></i> Kemaskini Profil
                </a>
            <?php endif; ?>
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
                <li class="breadcrumb-item active" aria-current="page">Maklumat Kursus</li>
            </ol>
        </nav>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">

    <!-- COURSE HEADER -->
    <div class="course-header">
        <!-- Registration Status Badge -->
        <?php if ($user_registration): ?>
            <div class="registration-badge <?php echo $user_registration['status']; ?>">
                <i class="fas fa-<?php echo $user_registration['status'] == 'diterima' ? 'check-circle' : ($user_registration['status'] == 'pending' ? 'hourglass-half' : 'times-circle'); ?>"></i>
                <?php 
                    switch($user_registration['status']) {
                        case 'diterima': echo 'Berdaftar'; break;
                        case 'pending': echo 'Dalam Proses'; break;
                        case 'ditolak': echo 'Ditolak'; break;
                    }
                ?>
            </div>
        <?php endif; ?>

        <div class="course-main-info">
            <div class="course-icon-large">
                <i class="<?php echo getCategoryIcon($course['kategori']); ?>"></i>
            </div>
            <div class="course-title-section">
                <div class="course-category">
                    <i class="fas fa-tag"></i>
                    <?php echo htmlspecialchars($course['kategori']); ?>
                </div>
                <h1><?php echo htmlspecialchars($course['nama_kursus']); ?></h1>
                <div class="course-status <?php echo getStatusClass($course['status_display']); ?>">
                    <i class="fas fa-info-circle"></i>
                    <?php echo getStatusText($course['status_display']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- COURSE DESCRIPTION -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-align-left"></i>
            Penerangan Kursus
        </div>
        <div class="course-description">
            <?php echo nl2br(htmlspecialchars($course['penerangan'])); ?>
        </div>
    </div>

    <!-- COURSE DETAILS -->
    <div class="details-grid">
        <div class="detail-card">
            <h5 class="card-title">
                <i class="fas fa-calendar-alt"></i>
                Maklumat Tarikh & Masa
            </h5>
            
            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="detail-text">
                    <strong>Tarikh Mula</strong>
                    <span><?php echo $course['tarikh_mula'] ? date('l, d F Y', strtotime($course['tarikh_mula'])) : 'Akan Diumumkan'; ?></span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="detail-text">
                    <strong>Tarikh Tamat</strong>
                    <span><?php echo $course['tarikh_tamat'] ? date('l, d F Y', strtotime($course['tarikh_tamat'])) : 'Akan Diumumkan'; ?></span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="detail-text">
                    <strong>Tempoh Kursus</strong>
                    <span><?php echo htmlspecialchars($course['tempoh'] ?: 'Akan Diumumkan'); ?></span>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <h5 class="card-title">
                <i class="fas fa-map-marker-alt"></i>
                Maklumat Lokasi & Yuran
            </h5>
            
            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="detail-text">
                    <strong>Lokasi</strong>
                    <span><?php echo htmlspecialchars($course['lokasi']); ?></span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="detail-text">
                    <strong>Yuran Kursus</strong>
                    <span><?php echo $course['yuran'] ? 'RM ' . number_format($course['yuran'], 2) : 'Percuma'; ?></span>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="detail-text">
                    <strong>Kapasiti</strong>
                    <span><?php echo $course['kapasiti']; ?> peserta</span>
                </div>
            </div>
        </div>
    </div>

    <!-- CAPACITY PROGRESS -->
    <div class="capacity-section">
        <div class="progress-label">
            <h5>
                <i class="fas fa-chart-bar"></i>
                Kapasiti Pendaftaran
            </h5>
            <div class="progress-stats">
                <?php echo $course['pendaftar']; ?> / <?php echo $course['kapasiti']; ?> peserta
            </div>
        </div>
        
        <?php $progress_percentage = $course['kapasiti'] > 0 ? ($course['pendaftar'] / $course['kapasiti']) * 100 : 0; ?>
        <div class="progress-bar-custom">
            <div class="progress-fill" style="width: <?php echo min($progress_percentage, 100); ?>%;"></div>
        </div>
        
        <div class="mt-2 text-center">
            <small class="text-muted">
                <?php echo round($progress_percentage); ?>% daripada kapasiti penuh
            </small>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-section">
        <h5 class="card-title mb-4">
            <i class="fas fa-hand-point-right"></i>
            Tindakan
        </h5>

        <?php if ($user_registration): ?>
            <!-- Already Registered -->
            <div class="mb-3">
                <div class="alert alert-info border-0 rounded-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Status Pendaftaran:</strong> 
                    <?php 
                        switch($user_registration['status']) {
                            case 'diterima': 
                                echo '<span class="text-success">Diterima - Anda sudah berdaftar untuk kursus ini</span>'; 
                                break;
                            case 'pending': 
                                echo '<span class="text-warning">Dalam Proses - Permohonan anda sedang disemak</span>'; 
                                break;
                            case 'ditolak': 
                                echo '<span class="text-danger">Ditolak - Permohonan anda telah ditolak</span>'; 
                                break;
                        }
                    ?>
                </div>
            </div>

            <a href="dashboard.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-tachometer-alt"></i>
                Lihat Dashboard
            </a>

        <?php else: ?>
            <!-- Not Registered Yet -->
            <?php if ($course['status_display'] == 'buka_pendaftaran'): ?>
                <?php if ($isLoggedIn): ?>
                    <a href="daftar_kursus.php?id=<?php echo $course['kursus_id']; ?>" class="btn-custom btn-primary-custom">
                        <i class="fas fa-user-plus"></i>
                        Daftar Sekarang
                    </a>
                <?php else: ?>
                    <div class="mb-3">
                        <div class="alert alert-warning border-0 rounded-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Anda perlu log masuk terlebih dahulu untuk mendaftar kursus ini.
                        </div>
                    </div>
                    <a href="login.php" class="btn-custom btn-primary-custom">
                        <i class="fas fa-sign-in-alt"></i>
                        Log Masuk untuk Daftar
                    </a>
                <?php endif; ?>

            <?php elseif ($course['status_display'] == 'penuh'): ?>
                <div class="mb-3">
                    <div class="alert alert-danger border-0 rounded-3">
                        <i class="fas fa-users me-2"></i>
                        Kursus ini telah penuh. Anda boleh menyertai senarai tunggu.
                    </div>
                </div>
                
                <?php if ($isLoggedIn): ?>
                    <a href="daftar_kursus.php?id=<?php echo $course['kursus_id']; ?>&waitlist=1" class="btn-custom btn-warning-custom">
                        <i class="fas fa-list"></i>
                        Sertai Senarai Tunggu
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-custom btn-warning-custom">
                        <i class="fas fa-sign-in-alt"></i>
                        Log Masuk untuk Senarai Tunggu
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <div class="mb-3">
                    <div class="alert alert-secondary border-0 rounded-3">
                        <i class="fas fa-clock me-2"></i>
                        Kursus ini belum dibuka untuk pendaftaran. Sila semak kembali kemudian.
                    </div>
                </div>
                
                <button class="btn-custom btn-secondary-custom" disabled>
                    <i class="fas fa-clock"></i>
                    <?php echo getStatusText($course['status_display']); ?>
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <a href="katalog_kursus.php" class="btn-custom btn-secondary-custom">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Katalog
        </a>
    </div>

    <!-- ADDITIONAL INFORMATION -->
    <div class="info-grid">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-certificate"></i>
            </div>
            <h5>Sijil Kursus</h5>
            <p>Peserta yang berjaya menamatkan kursus akan menerima sijil yang diiktiraf oleh Jabatan Pertahanan Awam Malaysia.</p>
        </div>

        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <h5>Kemudahan</h5>
            <p>Makanan, minuman, dan bahan kursus akan disediakan. Penginapan turut disediakan untuk kursus yang memerlukan.</p>
        </div>

        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h5>Tenaga Pengajar</h5>
            <p>Kursus akan dikendalikan oleh jurulatih bertauliah dan berpengalaman dalam bidang pertahanan awam.</p>
        </div>
    </div>

    <!-- COURSE REQUIREMENTS -->
    <div class="content-card mt-4">
        <div class="card-title">
            <i class="fas fa-clipboard-check"></i>
            Syarat-syarat Kursus
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">Kelayakan Am:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Kakitangan kerajaan yang masih berkhidmat</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Berumur 18 tahun ke atas</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Sihat tubuh badan dan mental</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Lulus pemeriksaan perubatan</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">Dokumen Diperlukan:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-file text-info me-2"></i>Salinan kad pengenalan</li>
                    <li class="mb-2"><i class="fas fa-file text-info me-2"></i>Surat pengesahan jawatan</li>
                    <li class="mb-2"><i class="fas fa-file text-info me-2"></i>Borang kesihatan lengkap</li>
                    <li class="mb-2"><i class="fas fa-file text-info me-2"></i>Gambar passport terkini</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- CONTACT INFORMATION -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-phone"></i>
            Maklumat Hubungan
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="detail-text">
                        <strong>Akademi Latihan Pertahanan Awam (ALPHA)</strong>
                        <span>Batu 10, Jalan Cheras, 43200 Cheras, Selangor</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="detail-text">
                        <strong>Telefon</strong>
                        <span>03-8064 2222 / 03-8064 2223</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="detail-text">
                        <strong>Email</strong>
                        <span>info@alpha.gov.my</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="detail-text">
                        <strong>Laman Web</strong>
                        <span>www.alpha.gov.my</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RELATED COURSES -->
    <?php
    // Get related courses from the same category
    $related_sql = "SELECT kursus_id, nama_kursus, penerangan, status_kursus, pendaftar, kapasiti 
                    FROM kursus 
                    WHERE kategori = :kategori AND kursus_id != :current_id 
                    ORDER BY RAND() 
                    LIMIT 3";
    $related_stmt = $pdo->prepare($related_sql);
    $related_stmt->execute([
        ':kategori' => $course['kategori'], 
        ':current_id' => $kursus_id
    ]);
    $related_courses = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (!empty($related_courses)): ?>
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-graduation-cap"></i>
            Kursus Berkaitan
        </div>
        <div class="row">
            <?php foreach ($related_courses as $related): ?>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="course-icon me-3">
                                <i class="<?php echo getCategoryIcon($course['kategori']); ?>"></i>
                            </div>
                            <h6 class="card-title mb-0 text-primary"><?php echo htmlspecialchars($related['nama_kursus']); ?></h6>
                        </div>
                        <p class="card-text small text-muted">
                            <?php echo htmlspecialchars(substr($related['penerangan'], 0, 100)) . '...'; ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <?php echo $related['pendaftar']; ?>/<?php echo $related['kapasiti']; ?> peserta
                            </small>
                            <a href="maklumat_kursus.php?id=<?php echo $related['kursus_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-info-circle"></i> Lihat
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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

    // Progress bar animation on page load
    window.addEventListener('load', function() {
        const progressFill = document.querySelector('.progress-fill');
        if (progressFill) {
            const targetWidth = progressFill.style.width;
            progressFill.style.width = '0%';
            setTimeout(() => {
                progressFill.style.width = targetWidth;
            }, 500);
        }
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

    // Add hover effects to cards
    document.querySelectorAll('.info-card, .detail-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.05)';
        });
    });

    // Add loading animation to registration buttons
    document.querySelectorAll('.btn-primary-custom').forEach(btn => {
        if (btn.href && btn.href.includes('daftar_kursus.php')) {
            btn.addEventListener('click', function(e) {
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                
                // Reset after 3 seconds in case user goes back
                setTimeout(() => {
                    this.innerHTML = originalContent;
                }, 3000);
            });
        }
    });

    // Initialize tooltips if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Add tooltips to status badges
        const statusElements = document.querySelectorAll('.course-status');
        statusElements.forEach(element => {
            element.setAttribute('data-bs-toggle', 'tooltip');
            element.setAttribute('title', 'Status pendaftaran kursus');
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Add click tracking for analytics (optional)
    document.querySelectorAll('.btn-custom').forEach(btn => {
        btn.addEventListener('click', function() {
            // You can add analytics tracking here if needed
            console.log('Button clicked:', this.textContent.trim());
        });
    });
</script>

</body>
</html>