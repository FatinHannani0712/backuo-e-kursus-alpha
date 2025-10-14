<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get user info
$user_name = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Unknown';
$user_email = $_SESSION['email'] ?? '';

// Get filter parameters with sanitization
$filterTahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$filterSemester = $_GET['semester'] ?? '';

try {
    // Build query with proper table name and column names
    $sql = "SELECT * FROM takwim_kursus WHERE 1=1";
    $params = [];

    if ($filterTahun) {
        $sql .= " AND tahun = :tahun";
        $params[':tahun'] = $filterTahun;
    }

    if ($filterSemester) {
        $sql .= " AND semester = :semester";
        $params[':semester'] = $filterSemester;
    }

    $sql .= " ORDER BY bil ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available years
    $yearsSql = "SELECT DISTINCT tahun FROM takwim_kursus ORDER BY tahun DESC";
    $yearsStmt = $pdo->query($yearsSql);
    $availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Count by category
    $categoryCounts = [];
    $totalCapacity = 0;
    $activeCourses = 0;
    
    foreach ($courses as $course) {
        $cat = $course['kategori'] ?? 'Tiada Kategori';
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        
        // Calculate total capacity
        $totalCapacity += intval($course['kapasiti'] ?? 0);
        
        // Count active courses (assuming status field exists)
        if (($course['status'] ?? 'active') === 'active') {
            $activeCourses++;
        }
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $courses = [];
    $availableYears = [date('Y')];
    $categoryCounts = [];
    $totalCapacity = 0;
    $activeCourses = 0;
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat TAKWIM - Admin e-Kursus APM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --apm-blue: #0056b3;
            --apm-dark-blue: #003d82;
            --apm-gold: #ffc107;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: var(--gradient-primary);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
            border: none;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .course-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .course-card:hover {
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
            transform: translateY(-3px);
            border-color: #667eea;
        }
        
        .category-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .stats-box {
            background: var(--gradient-primary);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .platform-badge {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        .action-buttons .btn {
            margin-left: 5px;
            border-radius: 6px;
        }
        
        .course-header {
            border-bottom: 2px solid #f1f3f4;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
    </style>
</head>
<body>

<!-- Alert Container for Notifications -->
<div class="alert-container" id="alertContainer"></div>

<div class="container">
    
    <!-- HEADER -->
    <div class="main-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary mb-2"><i class="fas fa-calendar-alt me-2"></i>Lihat TAKWIM Kursus APM</h2>
                <p class="text-muted mb-0">Senarai kursus dari jadual TAKWIM tahunan - Sistem Pengurusan Kursus APM</p>
            </div>
            <div class="action-buttons">
                <a href="admin_import_takwim_f1.php" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i>Import TAKWIM
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
        
        <!-- FILTERS -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tahun</label>
                    <select name="tahun" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" 
                                <?php echo $filterTahun == $year ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Semester</label>
                    <select name="semester" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Semester</option>
                        <option value="JAN-JUN" <?php echo $filterSemester == 'JAN-JUN' ? 'selected' : ''; ?>>Januari - Jun</option>
                        <option value="JULAI-DIS" <?php echo $filterSemester == 'JULAI-DIS' ? 'selected' : ''; ?>>Julai - Disember</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Tapis
                    </button>
                </div>
            </form>
        </div>
        
        <!-- STATISTICS -->
        <div class="stats-box">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <h2 class="fw-bold"><?php echo count($courses); ?></h2>
                    <small>Jumlah Kursus</small>
                </div>
                <div class="col-md-3 mb-3">
                    <h2 class="fw-bold"><?php echo count($categoryCounts); ?></h2>
                    <small>Kategori Berbeza</small>
                </div>
                <div class="col-md-3 mb-3">
                    <h2 class="fw-bold"><?php echo number_format($totalCapacity); ?></h2>
                    <small>Jumlah Kapasiti</small>
                </div>
                <div class="col-md-3 mb-3">
                    <h2 class="fw-bold"><?php echo $activeCourses; ?></h2>
                    <small>Kursus Aktif</small>
                </div>
            </div>
        </div>
        
        <!-- CATEGORY SUMMARY -->
        <?php if (!empty($categoryCounts)): ?>
        <div class="mb-3">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-tags me-2"></i>Taburan Mengikut Kategori:</h6>
            <?php foreach ($categoryCounts as $cat => $count): ?>
                <span class="badge bg-light text-dark category-badge me-2 mb-2 border">
                    <?php echo htmlspecialchars($cat); ?> 
                    <span class="badge bg-primary ms-1"><?php echo $count; ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- COURSE LIST -->
    <div class="main-card">
        <div class="course-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Senarai Kursus 
                <?php if ($filterTahun): ?>
                    - Tahun <?php echo htmlspecialchars($filterTahun); ?>
                <?php endif; ?>
                <?php if ($filterSemester): ?>
                    (<?php echo htmlspecialchars($filterSemester); ?>)
                <?php endif; ?>
            </h5>
            <p class="text-muted mb-0"><?php echo count($courses); ?> kursus dijumpai</p>
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>Tiada kursus TAKWIM dijumpai</h5>
                <p class="mb-0">Sila import data TAKWIM terlebih dahulu</p>
                <a href="admin_import_takwim_f1.php" class="btn btn-primary mt-3">
                    <i class="fas fa-upload me-2"></i>Import TAKWIM
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
            <div class="course-card">
                <div class="row align-items-center">
                    <div class="col-md-1 text-center">
                        <div class="badge bg-primary rounded-circle" style="font-size: 1.1rem; padding: 12px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <?php echo htmlspecialchars($course['bil'] ?? '0'); ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h6 class="mb-2 fw-bold text-dark"><?php echo htmlspecialchars($course['nama_kursus'] ?? 'Nama Kursus Tidak Dinyatakan'); ?></h6>
                        <?php if (!empty($course['siri'])): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($course['siri']); ?>
                            </small>
                        <?php endif; ?>
                        <div class="mt-2">
                            <span class="badge bg-info category-badge"><?php echo htmlspecialchars($course['kategori'] ?? 'Tiada Kategori'); ?></span>
                            <span class="badge bg-warning text-dark platform-badge ms-1">
                                <i class="fas fa-desktop me-1"></i><?php echo htmlspecialchars($course['platform'] ?? 'BERSEMUKA'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php if (!empty($course['minggu_no'])): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-calendar-week me-1"></i>Minggu <?php echo htmlspecialchars($course['minggu_no']); ?>
                            </small>
                        <?php endif; ?>
                        <?php if (!empty($course['tarikh_mula'])): ?>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d/m/Y', strtotime($course['tarikh_mula'])); ?> 
                                <?php if (!empty($course['tarikh_akhir'])): ?>
                                    - <?php echo date('d/m/Y', strtotime($course['tarikh_akhir'])); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="badge bg-success rounded-pill" style="font-size: 0.9rem; padding: 8px 12px;">
                            <i class="fas fa-users me-1"></i> <?php echo htmlspecialchars($course['kapasiti'] ?? '0'); ?>
                        </div>
                        <?php if (!empty($course['pra_syarat'])): ?>
                            <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($course['pra_syarat']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-1 text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" 
                                       onclick="viewCourseDetails(<?php echo $course['id'] ?? $course['takwim_id'] ?? '0'; ?>)">
                                        <i class="fas fa-eye me-2"></i>Lihat Detail
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" 
                                       onclick="editCourse(<?php echo $course['id'] ?? $course['takwim_id'] ?? '0'; ?>)">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" 
                                       href="#" onclick="confirmDelete(<?php echo $course['id'] ?? $course['takwim_id'] ?? '0'; ?>)">
                                        <i class="fas fa-trash me-2"></i>Padam
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Notification system
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function viewCourseDetails(courseId) {
    // Show loading or fetch course details
    showAlert(`Memuatkan butiran kursus ID: ${courseId}...`, 'info');
    // You can implement AJAX call here to fetch and display course details
    setTimeout(() => {
        // Simulate API call
        showAlert(`Butiran kursus ID ${courseId} akan dipaparkan.`, 'success');
    }, 1000);
}

function editCourse(courseId) {
    showAlert(`Membuka editor untuk kursus ID: ${courseId}`, 'info');
    // Redirect to edit page or open modal
    window.location.href = `edit_takwim.php?id=${courseId}`;
}

function confirmDelete(courseId) {
    if (confirm('Adakah anda pasti ingin memadam kursus ini dari TAKWIM?\nTindakan ini tidak boleh dibatalkan.')) {
        showAlert('Memadam kursus...', 'warning');
        
        // Simulate delete action - replace with actual API call
        fetch(`api_delete_takwim.php?id=${courseId}`, {
            method: 'DELETE',
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Kursus berjaya dipadam!', 'success');
                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showAlert('Ralat: ' + (data.message || 'Gagal memadam kursus'), 'error');
            }
        })
        .catch(error => {
            showAlert('Ralat rangkaian: ' + error.message, 'error');
        });
    }
}

// URL parameter handling for success messages
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('import_success') === '1') {
    showAlert('✅ Data TAKWIM berjaya diimport!', 'success');
}
if (urlParams.get('delete_success') === '1') {
    showAlert('🗑️ Kursus berjaya dipadam!', 'success');
}
</script>

</body>
</html>