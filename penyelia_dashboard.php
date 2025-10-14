<?php
session_start();
require_once 'config.php';

// Check if user is logged in as Penyelia
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penyelia') {
    header("Location: login.php");
    exit();
}

// Get penyelia info
$penyelia_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $penyelia_id);
$stmt->execute();
$result = $stmt->get_result();
$penyelia = $result->fetch_assoc();
$stmt->close();

// Get courses managed by this penyelia with corrected column names
$query = "
    SELECT 
        k.*,
        kk.nama_kategori,
        CASE 
            WHEN k.status_kursus = 'buka_pendaftaran' AND (k.registration_close_date IS NULL OR CURDATE() <= k.registration_close_date) THEN 'open'
            WHEN k.status_kursus = 'buka_pendaftaran' AND CURDATE() > k.registration_close_date THEN 'expired'
            WHEN k.status_kursus = 'penuh' THEN 'closed'
            ELSE 'upcoming'
        END as actual_status,
        DATEDIFF(COALESCE(k.registration_close_date, k.tarikh_tamat), CURDATE()) as days_remaining
    FROM kursus k
    LEFT JOIN kursus_kategori kk ON k.kategori = kk.nama_kategori
    WHERE k.user_id = ?
    ORDER BY k.kategori, k.nama_kursus
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $penyelia_id);
$stmt->execute();
$courses = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Penyelia - e-Kursus ALPHA APM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .main-container {
            padding: 2rem 0;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .course-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .table thead {
            background: var(--primary-color);
            color: white;
        }
        
        .badge-status-open {
            background-color: var(--success-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .badge-status-closed {
            background-color: #6b7280;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .badge-status-expired {
            background-color: var(--danger-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .badge-status-upcoming {
            background-color: var(--warning-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .btn-open-registration {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-open-registration:hover {
            background: #059669;
            transform: scale(1.05);
        }
        
        .btn-close-registration {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-close-registration:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        
        .countdown-badge {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin: 0 2px;
        }
        
        .course-type-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
        
        @media (max-width: 768px) {
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-group .btn {
                margin-bottom: 0.25rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap text-primary"></i>
                <strong>e-Kursus ALPHA APM</strong>
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3">
                    <i class="fas fa-user-tie text-primary"></i>
                    <strong>Penyelia:</strong> <?php echo htmlspecialchars($penyelia['email']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Log Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Action Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card text-center clickable" onclick="location.href='admin_import_takwim.php'">
                    <i class="fas fa-calendar-plus fa-3x text-primary mb-3"></i>
                    <h5>Buka Kursus TAKWIM</h5>
                    <p class="text-muted">Pilih dari jadual rasmi</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card text-center clickable" onclick="location.href='create_course.php'">
                    <i class="fas fa-plus-circle fa-3x text-success mb-3"></i>
                    <h5>Cipta Kursus Baru</h5>
                    <p class="text-muted">Kursus khas luar TAKWIM</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card text-center clickable" onclick="location.href='applications.php'">
                    <i class="fas fa-clipboard-check fa-3x text-warning mb-3"></i>
                    <h5>Semak Permohonan</h5>
                    <p class="text-muted">Kelulusan peserta</p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <?php
            // Calculate statistics
            $stats = [
                'total' => 0,
                'open' => 0,
                'closed' => 0,
                'expired' => 0,
                'upcoming' => 0,
                'takwim' => 0,
                'non_takwim' => 0
            ];
            
            $courses->data_seek(0); // Reset pointer
            while ($course = $courses->fetch_assoc()) {
                $stats['total']++;
                
                if ($course['is_takwim_course']) {
                    $stats['takwim']++;
                } else {
                    $stats['non_takwim']++;
                }
                
                switch ($course['actual_status']) {
                    case 'open': $stats['open']++; break;
                    case 'expired': $stats['expired']++; break;
                    case 'upcoming': $stats['upcoming']++; break;
                    default: $stats['closed']++; break;
                }
            }
            $courses->data_seek(0); // Reset pointer again
            ?>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-book fa-3x text-primary mb-3"></i>
                    <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0">Jumlah Kursus</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-check fa-3x text-info mb-3"></i>
                    <h3 class="mb-0"><?php echo $stats['takwim']; ?></h3>
                    <p class="text-muted mb-0">Kursus TAKWIM</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-plus-circle fa-3x text-success mb-3"></i>
                    <h3 class="mb-0"><?php echo $stats['non_takwim']; ?></h3>
                    <p class="text-muted mb-0">Kursus Khas</p>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card text-center">
                    <i class="fas fa-door-open fa-3x text-success mb-3"></i>
                    <h3 class="mb-0"><?php echo $stats['open']; ?></h3>
                    <p class="text-muted mb-0">Pendaftaran Dibuka</p>
                </div>
            </div>
        </div>

        <!-- Courses Table -->
        <div class="course-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 25%">Nama Kursus</th>
                            <th style="width: 10%">Jenis</th>
                            <th style="width: 15%">Kategori</th>
                            <th style="width: 10%" class="text-center">Kapasiti</th>
                            <th style="width: 15%" class="text-center">Status Pendaftaran</th>
                            <th style="width: 10%" class="text-center">Tarikh Tutup</th>
                            <th style="width: 10%" class="text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($course = $courses->fetch_assoc()): 
                            $actual_status = $course['actual_status'];
                            $days_remaining = $course['days_remaining'];
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($course['nama_kursus']); ?></strong>
                                <?php if ($course['takwim_series']): ?>
                                    <br><small class="text-muted"><?php echo $course['takwim_series']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($course['is_takwim_course']): ?>
                                    <span class="badge bg-primary course-type-badge">
                                        <i class="fas fa-calendar-check"></i> TAKWIM
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary course-type-badge">
                                        <i class="fas fa-plus-circle"></i> KHAS
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($course['nama_kategori'] ?? $course['kategori']); ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info">
                                    <?php echo $course['pendaftar']; ?> / <?php echo $course['kapasiti']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($actual_status == 'open'): ?>
                                    <span class="badge-status-open">
                                        <i class="fas fa-check-circle"></i> Dibuka
                                    </span>
                                    <?php if ($days_remaining <= 7 && $days_remaining >= 0): ?>
                                        <span class="countdown-badge">
                                            <i class="fas fa-clock"></i> <?php echo $days_remaining; ?> hari
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($actual_status == 'expired'): ?>
                                    <span class="badge-status-expired">
                                        <i class="fas fa-times-circle"></i> Tamat Tempoh
                                    </span>
                                <?php elseif ($actual_status == 'upcoming'): ?>
                                    <span class="badge-status-upcoming">
                                        <i class="fas fa-clock"></i> Akan Datang
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status-closed">
                                        <i class="fas fa-lock"></i> Ditutup
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($course['registration_close_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($course['registration_close_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($actual_status == 'open'): ?>
                                        <!-- Close Registration Button -->
                                        <form method="POST" action="penyelia_tutup_pendaftaran.php" class="d-inline">
                                            <input type="hidden" name="kursus_id" value="<?php echo $course['kursus_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Adakah anda pasti mahu menutup pendaftaran untuk kursus ini?')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Open Registration Button -->
                                        <form method="POST" action="penyelia_buka_pendaftaran.php" class="d-inline">
                                            <input type="hidden" name="kursus_id" value="<?php echo $course['kursus_id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm"
                                                    onclick="return confirm('Buka pendaftaran untuk kursus ini? Tempoh pendaftaran adalah 1 bulan.')">
                                                <i class="fas fa-door-open"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Edit course button -->
                                    <a href="edit_course.php?id=<?php echo $course['kursus_id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                       <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <!-- View applications button -->
                                    <a href="view_applications.php?course=<?php echo $course['kursus_id']; ?>" 
                                       class="btn btn-info btn-sm">
                                       <i class="fas fa-users"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($no === 1): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tiada Kursus Dikendalikan</h5>
                                <p class="text-muted">Anda belum mempunyai kursus yang dikendalikan.</p>
                                <div class="mt-3">
                                    <a href="admin_import_takwim.php" class="btn btn-primary me-2">
                                        <i class="fas fa-calendar-plus"></i> Pilih Kursus TAKWIM
                                    </a>
                                    <a href="create_course.php" class="btn btn-success">
                                        <i class="fas fa-plus-circle"></i> Cipta Kursus Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="mt-4">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Nota Penyelia:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Kursus TAKWIM:</strong> Kursus dari jadual rasmi ALPHA 2025</li>
                    <li><strong>Kursus Khas:</strong> Kursus di luar TAKWIM yang dicipta khas</li>
                    <li>Pendaftaran akan dibuka secara automatik untuk tempoh <strong>1 bulan</strong></li>
                    <li>Anda boleh menutup pendaftaran secara manual sebelum tarikh tamat tempoh</li>
                    <li>Sistem akan automatik menutup pendaftaran selepas tarikh tamat tempoh</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        submitBtn.disabled = true;
                    }
                });
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>

<?php 
$stmt->close();
$conn->close();
?>