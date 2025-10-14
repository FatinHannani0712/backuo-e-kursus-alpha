<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Fetch user data with better error handling
$userData = [];
try {
    $stmt = $conn->prepare("SELECT u.*, md.nama, md.no_ahli, md.jawatan, md.pangkat 
                           FROM users u 
                           LEFT JOIN maklumat_diri md ON md.user_id = u.id 
                           WHERE u.id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc() ?? [];
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

// Get additional data based on role
$additionalData = [];
try {
    if ($role === 'penyelia') {
        // Get supervised users count
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM users 
                               WHERE supervisor_id = ? AND role = 'user' AND status = 'active'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $additionalData['supervised_count'] = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        
        // Get pending evaluations count
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                               FROM course_applications ca
                               JOIN users u ON ca.user_id = u.id
                               WHERE u.supervisor_id = ? AND ca.status = 'pending_review'");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $additionalData['pending_evaluations'] = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        
    } elseif ($role === 'admin') {
        // Get system stats
        $stats = $conn->query("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
                (SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active') as regular_users,
                (SELECT COUNT(*) FROM users WHERE role = 'penyelia' AND status = 'active') as penyelia_count,
                (SELECT COUNT(*) FROM courses WHERE status = 'active') as total_courses,
                (SELECT COUNT(*) FROM course_applications WHERE status = 'approved') as completed_courses
        ")->fetch_assoc();
        $additionalData['stats'] = $stats;
    } elseif ($role === 'user') {
        // Get user course stats
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_courses
            FROM course_applications 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $additionalData['course_stats'] = $result->fetch_assoc() ?? ['total_applications' => 0, 'completed_courses' => 0, 'approved_courses' => 0];
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching additional data: " . $e->getMessage());
}

// Role-based page titles and icons
$roleConfig = [
    'user' => [
        'title' => 'e-Kursus ALPHA',
        'icon' => 'fa-user',
        'badge_class' => 'bg-primary'
    ],
    'penyelia' => [
        'title' => 'Penyelia',
        'icon' => 'fa-user-shield',
        'badge_class' => 'bg-warning'
    ],
    'admin' => [
        'title' => 'Pentadbir Sistem',
        'icon' => 'fa-user-cog',
        'badge_class' => 'bg-danger'
    ]
];
$currentRoleConfig = $roleConfig[$role] ?? $roleConfig['user'];

// Get recent courses for user
$recentCourses = [];
if ($role === 'user') {
    try {
        $stmt = $conn->prepare("
            SELECT c.course_name, ca.application_date, ca.status 
            FROM course_applications ca
            JOIN courses c ON ca.course_id = c.id
            WHERE ca.user_id = ?
            ORDER BY ca.application_date DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $recentCourses = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching recent courses: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>SELAMAT DATANG ke <?= $currentRoleConfig['title'] ?> | e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #0C3C60; 
            --secondary-color: #E67E22; 
            --light-color: #f8f9fa;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .dashboard-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a4d7a 100%); 
            color: white; 
            padding: 25px 0; 
            margin-bottom: 30px; 
            border-bottom: 4px solid var(--secondary-color);
        }
        .card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            overflow: hidden;
        }
        .card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-header { 
            background: var(--primary-color); 
            color: white; 
            font-weight: 600; 
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .card-body { padding: 20px; }
        .bg-secondary-light { background-color: var(--light-color); }
        .quick-actions .btn { 
            margin: 5px; 
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .quick-actions .btn:hover {
            transform: translateY(-2px);
        }
        .profile-img-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-img-container i {
            font-size: 40px;
            color: white;
        }
        .badge-role { 
            background-color: var(--secondary-color);
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .nav-tabs .nav-link.active { 
            background-color: var(--primary-color); 
            color: white; 
            border-color: var(--primary-color);
            font-weight: 600;
        }
        .progress { height: 12px; border-radius: 10px; }
        .progress-bar { 
            background: linear-gradient(90deg, var(--secondary-color) 0%, #f39c12 100%);
            border-radius: 10px;
        }
        .table-responsive { overflow-x: auto; }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr !important; }
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fa-solid fa-graduation-cap me-2"></i>e-Kursus ALPHA
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas <?= $currentRoleConfig['icon'] ?> me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kemaskini.php">
                            <i class="fa fa-user-pen me-1"></i>Maklumat Diri
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">
                            <i class="fa fa-list-ul me-1"></i>Senarai Kursus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="apply_status.php">
                            <i class="fa fa-clipboard-check me-1"></i>Status Permohonan
                        </a>
                    </li>
                    <?php if($role === 'admin' || $role === 'penyelia'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fa fa-users me-1"></i>Urus Pengguna
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if($role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fa fa-chart-bar me-1"></i>Laporan
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown">
                            <i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($userData['nama'] ?? 'Pengguna') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-1"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out me-1"></i>Log Keluar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container">
        <div class="dashboard-header text-center">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard <?= $currentRoleConfig['title'] ?></h2>
            <p class="mb-0">Selamat datang kembali, <?= htmlspecialchars($userData['nama'] ?? 'Pengguna') ?></p>
        </div>

        <!-- Notification Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i>
                    Sistem e-Kursus ALPHA versi 2.0. Lihat <a href="#" class="alert-link">panduan penggunaan</a> untuk maklumat lanjut.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <!-- Common Stats for All Roles -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-2"></i>Kalendar
                    </div>
                    <div class="card-body text-center">
                        <h3 class="mb-0 text-primary"><?= date('d') ?></h3>
                        <p class="mb-2"><?= date('F Y') ?></p>
                        <p class="mb-0 small"><?= date('l') ?></p>
                        <a href="schedule.php" class="btn btn-sm btn-outline-primary mt-2">Lihat Jadual</a>
                    </div>
                </div>
            </div>

            <!-- Role-Specific Stats -->
            <?php if($role === 'user'): ?>
                <!-- User Stats -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-clipboard-check me-2"></i>Status Kursus
                        </div>
                        <div class="card-body">
                            <?php
                            $completed = $additionalData['course_stats']['completed_courses'] ?? 0;
                            $total = $additionalData['course_stats']['total_applications'] ?? 1;
                            $percentage = $total > 0 ? ($completed / $total) * 100 : 0;
                            ?>
                            <h5 class="card-title"><?= $completed ?>/<?= $total ?> Kursus Selesai</h5>
                            <div class="progress mt-3">
                                <div class="progress-bar" style="width: <?= $percentage ?>%"><?= round($percentage) ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-certificate me-2"></i>Keahlian
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($userData['no_ahli'] ?? 'Tiada') ?></h5>
                            <p class="card-text">Status: <span class="badge bg-success">Aktif</span></p>
                            <a href="membership.php" class="btn btn-sm btn-outline-primary">Butiran Keahlian</a>
                        </div>
                    </div>
                </div>

            <?php elseif($role === 'penyelia'): ?>
                <!-- Penyelia Stats -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users me-2"></i>Ahli Di Bawah Pengawasan
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= $additionalData['supervised_count'] ?? 0 ?> Orang</h5>
                            <p class="card-text"><?= $additionalData['pending_evaluations'] ?? 0 ?> perlu penilaian</p>
                            <a href="supervision.php" class="btn btn-sm btn-outline-primary">Lihat Senarai</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-tasks me-2"></i>Tugasan
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= $additionalData['pending_evaluations'] ?? 0 ?> Penilaian Belum Selesai</h5>
                            <a href="evaluations.php" class="btn btn-sm btn-outline-primary">Lihat Tugasan</a>
                        </div>
                    </div>
                </div>

            <?php elseif($role === 'admin'): ?>
                <!-- Admin Stats -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users me-2"></i>Pengguna
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= $additionalData['stats']['total_users'] ?? 0 ?> Pengguna</h5>
                            <p class="card-text">
                                <span class="badge bg-primary"><?= $additionalData['stats']['regular_users'] ?? 0 ?> Ahli</span>
                                <span class="badge bg-secondary"><?= $additionalData['stats']['penyelia_count'] ?? 0 ?> Penyelia</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-book me-2"></i>Kursus
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?= $additionalData['stats']['total_courses'] ?? 0 ?> Kursus</h5>
                            <p class="card-text"><?= $additionalData['stats']['completed_courses'] ?? 0 ?> kursus selesai</p>
                            <a href="manage_courses.php" class="btn btn-sm btn-outline-primary">Urus Kursus</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Area -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <!-- Role-Specific Main Content -->
                <?php if($role === 'user'): ?>
                    <!-- User Dashboard Content -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-book-open me-2"></i>Kursus Terkini</span>
                            <a href="courses.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentCourses)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kursus</th>
                                                <th>Tarikh</th>
                                                <th>Status</th>
                                                <th>Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recentCourses as $course): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($course['course_name']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($course['application_date'])) ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = 'bg-secondary';
                                                    if ($course['status'] === 'approved') $statusClass = 'bg-success';
                                                    if ($course['status'] === 'pending') $statusClass = 'bg-warning';
                                                    if ($course['status'] === 'rejected') $statusClass = 'bg-danger';
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($course['status']) ?></span>
                                                </td>
                                                <td>
                                                    <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">Lihat</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Anda belum memohon sebarang kursus.</p>
                                    <a href="courses.php" class="btn btn-primary">Cari Kursus</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif($role === 'penyelia'): ?>
                    <!-- Penyelia Dashboard Content -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-check me-2"></i>Penilaian Terkini</span>
                            <a href="evaluations.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Anda mempunyai <?= $additionalData['pending_evaluations'] ?? 0 ?> penilaian yang perlu diselesaikan.
                            </div>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Fungsi penilaian akan datang tidak lama lagi.</p>
                                <a href="evaluations.php" class="btn btn-primary">Lihat Penilaian</a>
                            </div>
                        </div>
                    </div>

                <?php elseif($role === 'admin'): ?>
                    <!-- Admin Dashboard Content -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-chart-line me-2"></i>Statistik Sistem</span>
                            <select class="form-select form-select-sm w-auto">
                                <option>30 Hari Terakhir</option>
                                <option>Bulan Ini</option>
                                <option>Tahun Ini</option>
                            </select>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded">
                                        <div class="stat-number"><?= $additionalData['stats']['total_courses'] ?? 0 ?></div>
                                        <div class="stat-label">Kursus Aktif</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded">
                                        <div class="stat-number"><?= rand(50, 100) ?></div>
                                        <div class="stat-label">Permohonan</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded">
                                        <div class="stat-number"><?= rand(80, 95) ?>%</div>
                                        <div class="stat-label">Penyiapan</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded">
                                        <div class="stat-number"><?= $additionalData['stats']['penyelia_count'] ?? 0 ?></div>
                                        <div class="stat-label">Penyelia</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-light p-4 rounded text-center">
                                <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Graf statistik akan ditampilkan di sini</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Tindakan Pantas
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if($role === 'user'): ?>
                                <a href="courses.php" class="btn btn-primary text-start">
                                    <i class="fas fa-search me-2"></i>Cari Kursus Baru
                                </a>
                                <a href="apply_status.php" class="btn btn-success text-start">
                                    <i class="fas fa-clipboard-list me-2"></i>Lihat Status Permohonan
                                </a>
                                <a href="borang_kesihatan.php" class="btn btn-info text-start">
                                    <i class="fas fa-file-medical me-2"></i>Borang Kesihatan
                                </a>

                            <?php elseif($role === 'penyelia'): ?>
                                <a href="evaluations.php" class="btn btn-primary text-start position-relative">
                                    <i class="fas fa-clipboard-check me-2"></i>Penilaian Kursus
                                    <?php if(($additionalData['pending_evaluations'] ?? 0) > 0): ?>
                                        <span class="notification-badge"><?= $additionalData['pending_evaluations'] ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="supervision.php" class="btn btn-success text-start">
                                    <i class="fas fa-users me-2"></i>Senarai Ahli
                                </a>
                                <a href="reports.php" class="btn btn-info text-start">
                                    <i class="fas fa-file-alt me-2"></i>Buat Laporan
                                </a>

                            <?php elseif($role === 'admin'): ?>
                                <a href="borang_kesihatan.php" class="btn btn-primary text-start">
                                    <i class="fas fa-user-plus me-2"></i> Pengakuan Kesihatan
                                </a>
                                <a href="manage_courses.php" class="btn btn-success text-start">
                                    <i class="fas fa-book-medical me-2"></i>Tambah Kursus
                                </a>
                                <a href="system_settings.php" class="btn btn-info text-start">
                                    <i class="fas fa-cog me-2"></i>Pengaturan Sistem
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i>Ringkasan Profil
                    </div>
                    <div class="card-body text-center">
                        <!-- General user icon instead of passport photo -->
                        <div class="profile-img-container">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5><?= htmlspecialchars($userData['nama'] ?? 'Nama Pengguna') ?></h5>
                        <p class="text-muted mb-1">No. KP: <?= htmlspecialchars($userData['no_kp'] ?? '') ?></p>
                        <?php if(!empty($userData['jawatan'])): ?>
                            <p class="text-muted mb-1"><?= htmlspecialchars($userData['jawatan']) ?></p>
                        <?php endif; ?>
                        <?php if(!empty($userData['pangkat'])): ?>
                            <p class="text-muted mb-2"><?= htmlspecialchars($userData['pangkat']) ?></p>
                        <?php endif; ?>
                        <span class="badge <?= $currentRoleConfig['badge_class'] ?>"><?= $currentRoleConfig['title'] ?></span>
                        <hr>
                        <div class="d-grid gap-2">
                            <a href="profile2.php" class="btn btn-outline-primary">Lihat Profil Penuh</a>
                            <a href="kemaskini.php" class="btn btn-outline-secondary">Kemaskini Profil</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <small class="text-muted">© <?= date('Y') ?> e-Kursus ALPHA. Hak Cipta Terpelihara.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dashboard-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded for <?= $role ?> role');
            
            // Enable tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Simple greeting based on time of day
            const hour = new Date().getHours();
            let greeting = '';
            if (hour < 12) greeting = 'Selamat Pagi';
            else if (hour < 18) greeting = 'Selamat Petang';
            else greeting = 'Selamat Malam';
            
            // Update greeting if needed
            const greetingEl = document.querySelector('.dashboard-header p');
            if (greetingEl) {
                greetingEl.textContent = `${greeting}, <?= htmlspecialchars($userData['nama'] ?? 'Pengguna') ?>`;
            }
        });
    </script>
</body>
</html>