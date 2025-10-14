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
    header("Location: dashboard.php");
    exit();
}

// Get TAKWIM courses assigned to this penyelia
$assigned_courses_sql = "SELECT tk.*, 
                        CASE WHEN k.kursus_id IS NOT NULL THEN 'activated' ELSE 'not_activated' END as status_kursus,
                        k.kursus_id,
                        k.tarikh_mula_sebenar,
                        k.tarikh_akhir_sebenar,
                        k.tempat_kursus,
                        k.kapasiti_sebenar,
                        k.status_pendaftaran,
                        COUNT(d.daftar_id) as total_applications,
                        COUNT(CASE WHEN d.status = 'pending' THEN 1 END) as pending_applications,
                        COUNT(CASE WHEN d.status = 'diterima' THEN 1 END) as approved_applications
                        FROM takwim_master tk
                        LEFT JOIN kursus k ON tk.takwim_id = k.takwim_id AND k.penyelia_id = :user_id
                        LEFT JOIN daftar_kursus d ON k.kursus_id = d.kursus_id
                        WHERE tk.penyelia_assigned = :user_id OR tk.penyelia_assigned IS NULL
                        GROUP BY tk.takwim_id
                        ORDER BY tk.minggu_no, tk.tarikh_mula_rancangan";

$courses_stmt = $pdo->prepare($assigned_courses_sql);
$courses_stmt->execute([':user_id' => $userId]);
$takwim_courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get activated courses summary
$activated_courses = array_filter($takwim_courses, function($course) {
    return $course['status_kursus'] === 'activated';
});

$pending_activation = array_filter($takwim_courses, function($course) {
    return $course['status_kursus'] === 'not_activated';
});

// Calculate statistics
$total_takwim_courses = count($takwim_courses);
$total_activated = count($activated_courses);
$total_pending_activation = count($pending_activation);
$total_applications = array_sum(array_column($activated_courses, 'total_applications'));
$pending_applications = array_sum(array_column($activated_courses, 'pending_applications'));

// Get user profile
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

function getStatusBadge($status) {
    switch($status) {
        case 'activated':
            return '<span class="badge bg-success">Diaktifkan</span>';
        case 'not_activated':
            return '<span class="badge bg-warning">Belum Diaktifkan</span>';
        default:
            return '<span class="badge bg-secondary">Tidak Diketahui</span>';
    }
}

function getRegistrationStatus($status) {
    switch($status) {
        case 'buka': return '<span class="badge bg-success">Buka Pendaftaran</span>';
        case 'tutup': return '<span class="badge bg-danger">Tutup Pendaftaran</span>';
        case 'akan_datang': return '<span class="badge bg-info">Akan Datang</span>';
        case 'selesai': return '<span class="badge bg-secondary">Selesai</span>';
        default: return '<span class="badge bg-warning">Belum Ditetapkan</span>';
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
        .profile-btn { 
            background-color: var(--apm-red); 
            color: white; 
        }
        .datetime-display { 
            background-color: rgba(0, 0, 0, 0.1); 
            padding: 8px 15px; 
            border-radius: 20px; 
            font-size: 0.9rem; 
            margin-left: 15px; 
        }
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
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
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

        /* TAKWIM Table */
        .takwim-table {
            border-radius: 10px;
            overflow: hidden;
        }

        .takwim-table th {
            background: linear-gradient(135deg, var(--apm-dark-blue), var(--apm-orange));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
        }

        .takwim-table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-color: #f0f0f0;
        }

        .takwim-table tbody tr:hover {
            background-color: rgba(255, 107, 0, 0.05);
        }

        .takwim-table tbody tr.not-activated {
            background-color: rgba(255, 193, 7, 0.1);
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

        /* Action Buttons */
        .btn-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
        }

        .btn-activate {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-activate:hover {
            transform: translateY(-2px);
            color: white;
        }

        .btn-edit {
            background: var(--apm-orange);
            color: white;
        }

        .btn-edit:hover {
            background: var(--apm-dark-orange);
            color: white;
        }

        .btn-manage {
            background: var(--apm-dark-blue);
            color: white;
        }

        .btn-manage:hover {
            background: var(--apm-navy);
            color: white;
        }

        /* Quick Actions */
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
            height: 100%;
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

        /* Helper Text */
        .helper-text {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 4px solid var(--apm-dark-blue);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .helper-text h6 {
            color: var(--apm-dark-blue);
            margin-bottom: 0.5rem;
        }

        .helper-text p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: var(--apm-gray);
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

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container">
        <div class="welcome-card">
            <div style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                <i class="fas fa-user-tie me-3"></i>
                Dashboard Penyelia TAKWIM
            </div>
            <p style="font-size: 1.1rem; opacity: 0.9;">
                Selamat datang <?php echo $profile_info ? htmlspecialchars($profile_info['nama']) : htmlspecialchars($userEmail); ?>. 
                Pilih kursus dari TAKWIM dan aktifkan untuk pendaftaran peserta.
            </p>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">

    <!-- HELPER TEXT -->
    <div class="helper-text">
        <h6><i class="fas fa-lightbulb me-2"></i>Cara Kerja Sistem TAKWIM</h6>
        <p><strong>1.</strong> Pilih kursus dari jadual TAKWIM yang ingin anda kendalikan 
        <strong>2.</strong> Aktifkan kursus dan laraskan maklumat jika perlu 
        <strong>3.</strong> Buka pendaftaran untuk peserta 
        <strong>4.</strong> Semak dan luluskan permohonan peserta</p>
    </div>

    <!-- STATISTICS SECTION -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $total_takwim_courses; ?></div>
                <div class="stat-label">Kursus dalam TAKWIM</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $total_activated; ?></div>
                <div class="stat-label">Kursus Diaktifkan</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $total_pending_activation; ?></div>
                <div class="stat-label">Belum Diaktifkan</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $pending_applications; ?></div>
                <div class="stat-label">Permohonan Pending</div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <a href="#activate-section" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="fw-bold mb-2">Aktifkan Kursus</div>
                <small class="text-muted">Pilih kursus TAKWIM untuk diaktifkan</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#manage-section" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="fw-bold mb-2">Urus Kursus</div>
                <small class="text-muted">Edit kursus yang telah diaktifkan</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="review_applications.php" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="fw-bold mb-2">Semak Permohonan</div>
                <small class="text-muted">Luluskan permohonan peserta</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="course_reports.php" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="fw-bold mb-2">Laporan</div>
                <small class="text-muted">Lihat statistik kursus</small>
            </a>
        </div>
    </div>

    <!-- TAKWIM COURSES SECTION -->
    <div class="content-card" id="activate-section">
        <div class="card-title">
            <i class="fas fa-list"></i>
            Senarai Kursus TAKWIM 2025
        </div>
        
        <?php if (!empty($takwim_courses)): ?>
        <div class="table-responsive">
            <table class="table takwim-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Nama Kursus</th>
                        <th>Kategori</th>
                        <th>Jadual TAKWIM</th>
                        <th>Kapasiti</th>
                        <th>Status</th>
                        <th>Permohonan</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($takwim_courses as $course): ?>
                    <tr class="<?php echo $course['status_kursus'] === 'not_activated' ? 'not-activated' : ''; ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="course-icon-small me-2">
                                    <i class="<?php echo getCategoryIcon($course['kategori']); ?>"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($course['nama_kursus_takwim']); ?></strong>
                                    <br><small class="text-muted">Siri: <?php echo htmlspecialchars($course['siri']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo str_replace('Program ', '', $course['kategori']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($course['minggu_tarikh']); ?></strong>
                            <br><small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($course['tarikh_mula_rancangan'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($course['tarikh_akhir_rancangan'])); ?>
                            </small>
                        </td>
                        <td>
                            <span class="fw-bold"><?php echo $course['kapasiti_rancangan']; ?></span> peserta
                            <?php if ($course['kapasiti_sebenar'] && $course['kapasiti_sebenar'] != $course['kapasiti_rancangan']): ?>
                                <br><small class="text-primary">Sebenar: <?php echo $course['kapasiti_sebenar']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo getStatusBadge($course['status_kursus']); ?>
                            <?php if ($course['status_kursus'] === 'activated'): ?>
                                <br><?php echo getRegistrationStatus($course['status_pendaftaran']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($course['status_kursus'] === 'activated'): ?>
                                <div class="d-flex gap-1 flex-wrap">
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
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($course['status_kursus'] === 'not_activated'): ?>
                                    <a href="activate_course.php?takwim_id=<?php echo $course['takwim_id']; ?>" 
                                       class="btn-custom btn-activate" title="Aktifkan kursus ini">
                                        <i class="fas fa-play"></i> Aktif
                                    </a>
                                <?php else: ?>
                                    <a href="edit_course.php?kursus_id=<?php echo $course['kursus_id']; ?>" 
                                       class="btn-custom btn-edit" title="Edit maklumat kursus">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="manage_course.php?kursus_id=<?php echo $course['kursus_id']; ?>" 
                                       class="btn-custom btn-manage" title="Urus kursus dan peserta">
                                        <i class="fas fa-cogs"></i> Urus
                                    </a>
                                    <?php if ($course['pending_applications'] > 0): ?>
                                    <a href="review_applications.php?kursus_id=<?php echo $course['kursus_id']; ?>" 
                                       class="btn-custom" style="background: #17a2b8; color: white;" title="Semak permohonan">
                                        <i class="fas fa-clipboard-check"></i> Semak
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3 p-3 bg-light rounded">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Nota:</strong> Kursus dengan latar kuning adalah kursus TAKWIM yang belum diaktifkan. 
                        Klik "Aktif" untuk mengendalikan kursus tersebut.
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        Jadual TAKWIM: Julai - Disember 2025
                    </small>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h5>Tiada Kursus TAKWIM</h5>
            <p>Belum ada kursus TAKWIM yang ditetapkan untuk anda. Sila hubungi admin untuk penugasan kursus.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- FOOTER -->
<footer style="background: var(--apm-dark-blue); color: white; text-align: center; padding: 20px; margin-top: 40px;">
    <p>&copy; <?php echo date("Y"); ?> e-Kursus ALPHA | Dashboard Penyelia TAKWIM | Hak Cipta Terpelihara</p>
</footer>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update datetime display
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

    // Confirm activation action
    document.querySelectorAll('a[href*="activate_course"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Adakah anda pasti ingin mengaktifkan kursus ini? Setelah diaktifkan, kursus akan tersedia untuk pendaftaran peserta.')) {
                e.preventDefault();
            }
        });
    });

    // Add loading state for action buttons
    document.querySelectorAll('.btn-custom').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.href && !this.href.includes('#') && !this.href.includes('javascript:')) {
                const originalContent = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.style.pointerEvents = 'none';
                
                // Reset after 3 seconds if page hasn't changed
                setTimeout(() => {
                    if (this.innerHTML.includes('spinner')) {
                        this.innerHTML = originalContent;
                        this.style.pointerEvents = 'auto';
                    }
                }, 3000);
            }
        });
    });

    // Highlight pending courses
    document.addEventListener('DOMContentLoaded', function() {
        const pendingRows = document.querySelectorAll('tr.not-activated');
        if (pendingRows.length > 0) {
            console.log(`Found ${pendingRows.length} courses ready for activation`);
        }
    });
</script>

</body>
</html>