<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start session
session_start();

// Check if user is logged in and is penyelia
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

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

$userEmail = $_SESSION['email'];
$userId = $_SESSION['user_id'];

// Get user info and check role
$user_sql = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([':user_id' => $userId]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is penyelia or admin
if (!$user_info || ($user_info['role'] !== 'penyelia' && $user_info['role'] !== 'admin')) {
    // Redirect to regular dashboard if not penyelia
    header("Location: dashboard.php");
    exit();
}

// Get courses managed by this penyelia (using user_id since penyelia_id seems unused)
$courses_sql = "SELECT k.*, 
                COUNT(d.daftar_id) as total_applications,
                COUNT(CASE WHEN d.status = 'pending' THEN 1 END) as pending_applications,
                COUNT(CASE WHEN d.status = 'diterima' THEN 1 END) as approved_applications,
                COUNT(CASE WHEN d.status = 'ditolak' THEN 1 END) as rejected_applications
                FROM kursus k
                LEFT JOIN daftar_kursus d ON k.kursus_id = d.kursus_id
                WHERE k.user_id = :user_id OR k.penyelia_id = :user_id
                GROUP BY k.kursus_id
                ORDER BY k.status_kursus DESC, k.nama_kursus";

$courses_stmt = $pdo->prepare($courses_sql);
$courses_stmt->execute([':user_id' => $userId]);
$managed_courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent applications for this penyelia's courses
$recent_apps_sql = "SELECT d.*, k.nama_kursus, u.email, m.nama as nama_pemohon
                    FROM daftar_kursus d
                    JOIN kursus k ON d.kursus_id = k.kursus_id
                    JOIN users u ON d.user_id = u.id
                    LEFT JOIN maklumat_diri m ON d.user_id = m.user_id
                    WHERE (k.user_id = :user_id OR k.penyelia_id = :user_id)
                    ORDER BY d.tarikh_daftar DESC
                    LIMIT 10";

$recent_stmt = $pdo->prepare($recent_apps_sql);
$recent_stmt->execute([':user_id' => $userId]);
$recent_applications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_courses = count($managed_courses);
$total_applications = array_sum(array_column($managed_courses, 'total_applications'));
$pending_applications = array_sum(array_column($managed_courses, 'pending_applications'));
$approved_applications = array_sum(array_column($managed_courses, 'approved_applications'));

// Get user profile info
$profile_sql = "SELECT * FROM maklumat_diri WHERE user_id = :user_id";
$profile_stmt = $pdo->prepare($profile_sql);
$profile_stmt->execute([':user_id' => $userId]);
$profile_info = $profile_stmt->fetch(PDO::FETCH_ASSOC);

// Function to get category icon
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
        case 'buka_pendaftaran': return 'success';
        case 'akan_datang': return 'warning';
        case 'penuh': return 'danger';
        case 'senarai_tunggu': return 'info';
        default: return 'secondary';
    }
}

function getStatusText($status) {
    switch($status) {
        case 'buka_pendaftaran': return 'Buka Pendaftaran';
        case 'akan_datang': return 'Akan Datang';
        case 'penuh': return 'Penuh';
        case 'senarai_tunggu': return 'Senarai Tunggu';
        default: return 'Tidak Diketahui';
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Penyelia - e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* APM OFFICIAL COLORS */
            --apm-orange: #FF6B00;
            --apm-dark-orange: #E55A00;
            --apm-red: #DC143C;
            --apm-dark-blue: #1B365D;
            --apm-navy: #0F2A44;
            --apm-white: #FFFFFF;
            --apm-light-gray: #F5F5F5;
            --apm-gray: #6C757D;
        }
        
        body { 
            background-color: var(--apm-light-gray); 
            font-family: 'Segoe UI', sans-serif; 
        }
        
        /* Header Styling - APM COLORS */
        .navbar-dark.bg-primary {
            background: linear-gradient(135deg, var(--apm-dark-blue) 0%, var(--apm-orange) 100%) !important;
        }
        
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
        .profile-btn { 
            background-color: var(--apm-red); 
            color: white; 
            border: 1px solid var(--apm-red); 
        }
        .profile-btn:hover { 
            background-color: #B91C3C; 
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
            background: linear-gradient(135deg, var(--apm-dark-blue) 0%, var(--apm-orange) 100%);
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

        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 5px solid var(--apm-orange);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 1rem auto;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--apm-dark-blue);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--apm-gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-left: 5px solid var(--apm-dark-blue);
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--apm-dark-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Course Table */
        .course-table {
            border-radius: 10px;
            overflow: hidden;
        }

        .course-table th {
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem 0.75rem;
        }

        .course-table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #f0f0f0;
        }

        .course-table tbody tr:hover {
            background-color: rgba(255, 107, 0, 0.05);
        }

        .course-icon-small {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        /* Buttons - APM Theme */
        .btn-custom {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            border: none;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            color: white;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
            color: white;
        }

        .btn-secondary-custom {
            background: white;
            color: var(--apm-dark-blue);
            border: 1px solid var(--apm-dark-blue);
        }

        .btn-secondary-custom:hover {
            background: var(--apm-dark-blue);
            color: white;
        }

        .btn-success-custom {
            background: #28a745;
            color: white;
        }

        .btn-warning-custom {
            background: var(--apm-orange);
            color: white;
        }

        .btn-danger-custom {
            background: var(--apm-red);
            color: white;
        }

        /* Applications List */
        .application-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.3s ease;
        }

        .application-item:hover {
            background-color: rgba(255, 107, 0, 0.05);
        }

        .application-item:last-child {
            border-bottom: none;
        }

        .applicant-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .applicant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .applicant-details h6 {
            margin: 0;
            color: var(--apm-dark-blue);
            font-weight: 600;
        }

        .applicant-details small {
            color: var(--apm-gray);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            border-top: 4px solid var(--apm-orange);
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            color: inherit;
            text-decoration: none;
        }

        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin: 0 auto 1rem auto;
        }

        .quick-action-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--apm-dark-blue);
            margin-bottom: 0.5rem;
        }

        .quick-action-desc {
            font-size: 0.85rem;
            color: var(--apm-gray);
        }

        /* Footer */
        footer { 
            background: var(--apm-dark-blue); 
            color: white; 
            text-align: center; 
            padding: 20px; 
            margin-top: 40px; 
        }

        /* Breadcrumb */
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
            color: var(--apm-dark-blue);
            text-decoration: none;
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--apm-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .course-table {
                font-size: 0.85rem;
            }

            .course-table th, .course-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--apm-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .empty-state h5 {
            color: var(--apm-dark-blue);
            margin-bottom: 0.5rem;
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

            <span class="user-email">
                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($userEmail); ?> (Penyelia)
            </span>
            <a href="katalog_kursus.php" class="nav-action-btn profile-btn">
                <i class="fas fa-book-open"></i> Katalog
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
                <li class="breadcrumb-item active" aria-current="page">Dashboard Penyelia</li>
            </ol>
        </nav>
    </div>
</div>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container">
        <div class="welcome-card">
            <div class="welcome-title">
                <i class="fas fa-user-tie me-3"></i>
                Dashboard Penyelia
            </div>
            <p class="welcome-subtitle">
                Selamat datang <?php echo $profile_info ? htmlspecialchars($profile_info['nama']) : htmlspecialchars($userEmail); ?>. 
                Urus dan pantau kursus-kursus yang anda kendalikan di ALPHA Bangi.
            </p>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">

    <!-- STATISTICS SECTION -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-number"><?php echo $total_courses; ?></div>
                <div class="stat-label">Kursus Dikendalikan</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_applications; ?></div>
                <div class="stat-label">Jumlah Permohonan</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending_applications; ?></div>
                <div class="stat-label">Menunggu Kelulusan</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $approved_applications; ?></div>
                <div class="stat-label">Permohonan Diluluskan</div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="create_course.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-plus"></i>
            </div>
            <div class="quick-action-title">Cipta Kursus Baharu</div>
            <div class="quick-action-desc">Tambah kursus baharu ke dalam sistem</div>
        </a>

        <a href="review_applications.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="quick-action-title">Semak Permohonan</div>
            <div class="quick-action-desc">Luluskan atau tolak permohonan peserta</div>
        </a>

        <a href="manage_courses.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div class="quick-action-title">Urus Kursus</div>
            <div class="quick-action-desc">Edit maklumat kursus yang dikendalikan</div>
        </a>

        <a href="course_reports.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="quick-action-title">Laporan Kursus</div>
            <div class="quick-action-desc">Lihat statistik dan laporan kursus</div>
        </a>
    </div>

    <!-- MY COURSES SECTION -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-chalkboard-teacher"></i>
            Kursus Yang Dikendalikan
        </div>
        
        <?php if (!empty($managed_courses)): ?>
        <div class="table-responsive">
            <table class="table course-table">
                <thead>
                    <tr>
                        <th>Kursus</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Kapasiti</th>
                        <th>Permohonan</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($managed_courses as $course): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="course-icon-small me-2">
                                    <i class="<?php echo getCategoryIcon($course['kategori']); ?>"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($course['nama_kursus']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($course['penerangan'], 0, 50)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo str_replace('Program ', '', $course['kategori']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo getStatusClass($course['status_kursus']); ?>">
                                <?php echo getStatusText($course['status_kursus']); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo $course['pendaftar']; ?></strong> / <?php echo $course['kapasiti']; ?>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar" style="width: <?php echo $course['kapasiti'] > 0 ? ($course['pendaftar']/$course['kapasiti'])*100 : 0; ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($course['pending_applications'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $course['pending_applications']; ?> Pending</span>
                                <?php endif; ?>
                                <?php if ($course['approved_applications'] > 0): ?>
                                    <span class="badge bg-success"><?php echo $course['approved_applications']; ?> Diluluskan</span>
                                <?php endif; ?>
                                <?php if ($course['total_applications'] == 0): ?>
                                    <span class="text-muted">Tiada permohonan</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="course_details.php?id=<?php echo $course['kursus_id']; ?>" class="btn-custom btn-primary-custom">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_course.php?id=<?php echo $course['kursus_id']; ?>" class="btn-custom btn-secondary-custom">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($course['pending_applications'] > 0): ?>
                                <a href="review_applications.php?course=<?php echo $course['kursus_id']; ?>" class="btn-custom btn-warning-custom">
                                    <i class="fas fa-clipboard-check"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <h5>Tiada Kursus Dikendalikan</h5>
            <p>Anda belum mempunyai kursus yang dikendalikan. Mulakan dengan mencipta kursus baharu.</p>
            <a href="create_course.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-plus"></i>
                Cipta Kursus Baharu
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- RECENT APPLICATIONS SECTION -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-bell"></i>
            Permohonan Terkini
            <?php if ($pending_applications > 0): ?>
                <span class="badge bg-warning ms-2"><?php echo $pending_applications; ?> pending</span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($recent_applications)): ?>
        <div class="applications-list">
            <?php foreach ($recent_applications as $app): ?>
            <div class="application-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="applicant-info">
                        <div class="applicant-avatar">
                            <?php echo strtoupper(substr($app['nama_pemohon'] ?: $app['email'], 0, 1)); ?>
                        </div>
                        <div class="applicant-details">
                            <h6><?php echo htmlspecialchars($app['nama_pemohon'] ?: $app['email']); ?></h6>
                            <small>
                                <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($app['nama_kursus']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($app['tarikh_daftar'])); ?>
                            </small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?php 
                            echo $app['status'] == 'pending' ? 'warning' : 
                                ($app['status'] == 'diterima' ? 'success' : 'danger'); 
                        ?>">
                            <?php 
                                switch($app['status']) {
                                    case 'pending': echo 'Menunggu'; break;
                                    case 'diterima': echo 'Diluluskan'; break;
                                    case 'ditolak': echo 'Ditolak'; break;
                                }
                            ?>
                        </span>
                        <?php if ($app['status'] == 'pending'): ?>
                        <div class="btn-group" role="group">
                            <a href="view_application.php?id=<?php echo $app['daftar_id']; ?>" class="btn-custom btn-primary-custom btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($recent_applications) >= 10): ?>
        <div class="text-center mt-3">
            <a href="review_applications.php" class="btn-custom btn-secondary-custom">
                <i class="fas fa-list"></i>
                Lihat Semua Permohonan
            </a>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h5>Tiada Permohonan Terkini</h5>
            <p>Belum ada permohonan untuk kursus-kursus yang anda kendalikan.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TIPS FOR PENYELIA -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-lightbulb"></i>
            Tips untuk Penyelia Kursus
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">Pengurusan Kursus:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Kemaskini maklumat kursus secara berkala
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Semak permohonan peserta dalam masa 3-5 hari
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Pastikan kapasiti kursus mencukupi
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Tetapkan tarikh kursus dengan jelas
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">Kriteria Kelulusan:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-user-check text-info me-2"></i>
                        Profil peserta lengkap dan tepat
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-heartbeat text-info me-2"></i>
                        Borang kesihatan telah diisi
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-graduation-cap text-info me-2"></i>
                        Memenuhi prasyarat kursus
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-building text-info me-2"></i>
                        Kakitangan kerajaan yang bertugas
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="mt-3 p-3 bg-light rounded">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-phone-alt text-primary" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                    <strong>Perlukan Bantuan?</strong><br>
                    <small class="text-muted">
                        Hubungi Admin ALPHA di <strong>03-8064 2222</strong> atau email <strong>admin@alpha.gov.my</strong>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- COURSE CATEGORIES OVERVIEW -->
    <div class="content-card">
        <div class="card-title">
            <i class="fas fa-chart-pie"></i>
            Ringkasan Kursus Mengikut Kategori
        </div>
        
        <div class="row">
            <?php
            // Group courses by category
            $categories = [];
            foreach ($managed_courses as $course) {
                $cat = $course['kategori'];
                if (!isset($categories[$cat])) {
                    $categories[$cat] = ['count' => 0, 'applications' => 0];
                }
                $categories[$cat]['count']++;
                $categories[$cat]['applications'] += $course['total_applications'];
            }
            ?>
            
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category => $data): ?>
                <div class="col-md-6 mb-3">
                    <div class="d-flex align-items-center p-3 border rounded">
                        <div class="me-3">
                            <div class="course-icon-small">
                                <i class="<?php echo getCategoryIcon($category); ?>"></i>
                            </div>
                        </div>
                        <div class="flex-fill">
                            <h6 class="mb-1"><?php echo str_replace('Program ', '', $category); ?></h6>
                            <small class="text-muted">
                                <?php echo $data['count']; ?> kursus • 
                                <?php echo $data['applications']; ?> permohonan
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-pie fa-2x mb-2"></i>
                        <p>Tiada data kategori untuk dipaparkan</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer>
    <p>&copy; <?php echo date("Y"); ?> e-Kursus ALPHA | Dashboard Penyelia | Hak Cipta Terpelihara</p>
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

    // Add hover effects to cards
    document.querySelectorAll('.stat-card, .quick-action-card, .content-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (this.classList.contains('stat-card') || this.classList.contains('quick-action-card')) {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            if (this.classList.contains('stat-card') || this.classList.contains('quick-action-card')) {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.05)';
            }
        });
    });

    // Add loading animation to action buttons
    document.querySelectorAll('.btn-custom').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.href && !this.href.includes('#')) {
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                setTimeout(() => {
                    this.innerHTML = originalContent;
                }, 2000);
            }
        });
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

    // Initialize tooltips if Bootstrap tooltips are available
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Auto-refresh pending applications count every 30 seconds
    setInterval(function() {
        // You can add AJAX call here to refresh pending count without page reload
        // For now, we'll just add a visual indicator that data might be outdated
        const badges = document.querySelectorAll('.badge.bg-warning');
        badges.forEach(badge => {
            badge.style.opacity = '0.7';
            setTimeout(() => {
                badge.style.opacity = '1';
            }, 500);
        });
    }, 30000);

    // Add confirmation for critical actions (if needed)
    document.querySelectorAll('a[href*="delete"], a[href*="remove"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Adakah anda pasti ingin melakukan tindakan ini?')) {
                e.preventDefault();
            }
        });
    });
</script>

</body>
</html>