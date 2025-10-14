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

$userData = [];
$stmt = $conn->prepare("SELECT u.*, md.nama, md.no_ahli FROM users u LEFT JOIN maklumat_diri md ON md.user_id = u.id WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

// Get additional data based on role
$additionalData = [];
if ($role === 'penyelia') {
    // Get supervised users (after adding supervisor_id column)
    $stmt = $conn->prepare("SELECT u.id, u.no_kp, md.nama, md.no_ahli 
                           FROM users u 
                           LEFT JOIN maklumat_diri md ON md.user_id = u.id 
                           WHERE u.supervisor_id = ? AND u.role = 'user'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $additionalData['supervised_users'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($role === 'admin') {
    // Get system stats
    $stats = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'user') as regular_users,
            (SELECT COUNT(*) FROM users WHERE role = 'penyelia') as penyelia_count,
            (SELECT COUNT(*) FROM courses) as total_courses
    ")->fetch_assoc();
    $additionalData['stats'] = $stats;
}

// Role-based page titles and icons
$roleConfig = [
    'user' => [
        'title' => 'e-Kursus ALPHA',
        'icon' => 'fa-user'
    ],
    'penyelia' => [
        'title' => 'Penyelia',
        'icon' => 'fa-user-shield'
    ],
    'admin' => [
        'title' => 'Pentadbir Sistem',
        'icon' => 'fa-user-cog'
    ]
];
$currentRoleConfig = $roleConfig[$role] ?? $roleConfig['user'];
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
        :root { --primary-color: #0C3C60; --secondary-color: #E67E22; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
        .dashboard-header { background: var(--primary-color); color: white; padding: 20px 0; margin-bottom: 30px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: transform 0.3s; }
        .card:hover { transform: translateY(-5px); }
        .card-header { background: var(--primary-color); color: white; font-weight: 600; }
        .bg-secondary-light { background-color: #f8f9fa; }
        .quick-actions .btn { margin: 5px; }
        .profile-summary img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid var(--primary-color); }
        .badge-role { background-color: var(--secondary-color); }
        .nav-tabs .nav-link.active { background-color: var(--primary-color); color: white; }
        .progress { height: 10px; }
        .progress-bar { background-color: var(--secondary-color); }
        .table-responsive { overflow-x: auto; }
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr !important; }
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
                        <h3 class="mb-0"><?= date('d') ?></h3>
                        <p class="mb-2"><?= strftime('%B %Y') ?></p>
                        <a href="schedule.php" class="btn btn-sm btn-outline-primary">Lihat Jadual</a>
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
                            $completed = 3; // Replace with actual query
                            $total = 5; // Replace with actual query
                            $percentage = ($completed / $total) * 100;
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
                            <h5 class="card-title"><?= count($additionalData['supervised_users']) ?> Orang</h5>
                            <p class="card-text">2 perlu penilaian</p>
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
                            <h5 class="card-title">3 Penilaian Belum Selesai</h5>
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
                            <h5 class="card-title"><?= $additionalData['stats']['total_users'] ?> Pengguna</h5>
                            <p class="card-text">
                                <span class="badge bg-primary"><?= $additionalData['stats']['regular_users'] ?> Ahli</span>
                                <span class="badge bg-secondary"><?= $additionalData['stats']['penyelia_count'] ?> Penyelia</span>
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
                            <h5 class="card-title"><?= $additionalData['stats']['total_courses'] ?> Kursus</h5>
                            <p class="card-text">5 kursus aktif bulan ini</p>
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
                        <div class="card-header">
                            <i class="fas fa-book-open me-2"></i>Kursus Terkini
                        </div>
                        <div class="card-body">
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
                                        
                                        
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif($role === 'penyelia'): ?>
                    <!-- Penyelia Dashboard Content -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-check me-2"></i>Penilaian Terkini
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama Ahli</th>
                                            <th>Kursus</th>
                                            <th>Tarikh Hantar</th>
                                            <th>Status</th>
                                            <th>Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach(array_slice($additionalData['supervised_users'], 0, 3) as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['nama']) ?></td>
                                            <td>Kursus Asas</td>
                                            <td><?= date('d/m/Y', strtotime('-'.rand(1,5).' days')) ?></td>
                                            <td><span class="badge bg-warning">Perlu Penilaian</span></td>
                                            <td>
                                                <a href="evaluate.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">Nilai</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="evaluations.php" class="btn btn-outline-primary mt-3">Lihat Semua Penilaian</a>
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
                                    <div class="p-3 bg-secondary-light rounded">
                                        <h3>24</h3>
                                        <p class="mb-0">Kursus Aktif</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-secondary-light rounded">
                                        <h3>87</h3>
                                        <p class="mb-0">Permohonan</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-secondary-light rounded">
                                        <h3>92%</h3>
                                        <p class="mb-0">Penyiapan</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-secondary-light rounded">
                                        <h3>15</h3>
                                        <p class="mb-0">Penyelia</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-secondary-light p-3 rounded">
                                <p class="text-center text-muted">[Graf Statistik Akan Ditampilkan Di Sini]</p>
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
                                

                            <?php elseif($role === 'penyelia'): ?>
                                <a href="evaluations.php" class="btn btn-primary text-start">
                                    <i class="fas fa-clipboard-check me-2"></i>Penilaian Kursus
                                </a>
                                <a href="supervision.php" class="btn btn-success text-start">
                                    <i class="fas fa-users me-2"></i>Senarai Ahli
                                </a>
                                <a href="reports.php" class="btn btn-info text-start">
                                    <i class="fas fa-file-alt me-2"></i>Buat Laporan
                                </a>

                            <?php elseif($role === 'admin'): ?>
                                <a href="manage_users.php" class="btn btn-primary text-start">
                                    <i class="fas fa-user-plus me-2"></i>Tambah Pengguna
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
                        <img src="assets/img/default_profile.jpg" alt="Profile" class="mb-3">
                        <h5><?= htmlspecialchars($userData['nama'] ?? 'Nama Pengguna') ?></h5>
                        <p class="text-muted mb-1">No. KP: <?= htmlspecialchars($userData['no_kp'] ?? '') ?></p>
                        <span class="badge badge-role"><?= $currentRoleConfig['title'] ?></span>
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
            
            // You can add more role-specific JavaScript here
        });
    </script>
</body>
</html>