<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$filterTahun = $_GET['tahun'] ?? date('Y');
$filterSemester = $_GET['semester'] ?? '';

// Initialize variables
$courses = [];
$availableYears = [date('Y')];
$categoryCounts = [];
$totalCapacity = 0;
$activeCourses = 0;
$debug_info = '';

try {
    // Debug: Check connection and tables
    $debug_info .= "PDO connection: " . (isset($pdo) ? "OK" : "FAILED") . "<br>";
    
    // Try different possible table names
    $possible_tables = ['takwim_kursus', 'takwim_master', 'kursus', 'takwim', 'courses', 'takwim_courses'];
    $actual_table = null;
    
    foreach ($possible_tables as $table) {
        try {
            $test_stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $actual_table = $table;
            $debug_info .= "Found table: $table<br>";
            break;
        } catch (Exception $e) {
            $debug_info .= "Table $table not found<br>";
        }
    }
    
    if (!$actual_table) {
        throw new Exception("No takwim table found in database");
    }
    
    $debug_info .= "Using table: $actual_table<br>";
    
    // Build query
    $sql = "SELECT * FROM $actual_table WHERE 1=1";
    $params = [];
    
    if ($filterTahun && $filterTahun != '') {
        $sql .= " AND tahun = :tahun";
        $params[':tahun'] = $filterTahun;
    }
    
    if ($filterSemester && $filterSemester != '') {
        $sql .= " AND semester = :semester";
        $params[':semester'] = $filterSemester;
    }
    
    $sql .= " ORDER BY bil ASC";
    
    $debug_info .= "SQL: $sql<br>";
    $debug_info .= "Params: " . print_r($params, true) . "<br>";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug_info .= "Courses found: " . count($courses) . "<br>";
    
    // Get available years
    $yearsSql = "SELECT DISTINCT tahun FROM $actual_table ORDER BY tahun DESC";
    $yearsStmt = $pdo->query($yearsSql);
    $availableYears = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $debug_info .= "Available years: " . implode(', ', $availableYears) . "<br>";
    
    // Process courses data
    foreach ($courses as $course) {
        // Determine category column name
        $category = $course['kategori'] ?? $course['category'] ?? $course['KATEGORI'] ?? 'Tiada Kategori';
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        
        // Determine capacity column name
        $capacity = $course['kapasiti'] ?? $course['kapasiti_rancangan'] ?? $course['KAPASITI'] ?? $course['capacity'] ?? 0;
        $totalCapacity += intval($capacity);
        
        // Count active courses
        $status = $course['status'] ?? $course['STATUS'] ?? 'active';
        if ($status === 'active' || $status === 'ACTIVE') {
            $activeCourses++;
        }
    }

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("admin_view_takwim error: " . $e->getMessage());
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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 12px;
            display: none; /* Hide by default, can be shown when needed */
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- DEBUG INFO (can be enabled by removing display:none) -->
    <div class="debug-info">
        <strong>Debug Information:</strong><br>
        <?php echo $debug_info; ?>
    </div>
    
    <!-- HEADER -->
    <div class="main-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-primary mb-2"><i class="fas fa-calendar-alt me-2"></i>Lihat TAKWIM Kursus APM</h2>
                <p class="text-muted mb-0">Senarai kursus dari jadual TAKWIM tahunan</p>
            </div>
            <div>
                <a href="admin_import_takwim.php" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i>Import TAKWIM
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
        
        <!-- Display Error Message if any -->
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Ralat Sistem</h5>
            <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
            <small class="text-muted">Sila semak konfigurasi database.</small>
        </div>
        <?php endif; ?>
        
        <!-- FILTERS -->
        <div class="filter-section" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
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
        <div class="stats-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
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
    </div>

    <!-- COURSE LIST -->
    <div class="main-card">
        <h5 class="mb-4">
            <i class="fas fa-list me-2"></i>Senarai Kursus 
            <?php if ($filterTahun): ?>
                - Tahun <?php echo htmlspecialchars($filterTahun); ?>
            <?php endif; ?>
            <?php if ($filterSemester): ?>
                (<?php echo htmlspecialchars($filterSemester); ?>)
            <?php endif; ?>
        </h5>
        
        <?php if (empty($courses)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>Tiada kursus TAKWIM dijumpai</h5>
                <p class="mb-2">Ini mungkin disebabkan oleh:</p>
                <ul class="text-start">
                    <li>Table database tidak sama dengan yang dicari</li>
                    <li>Tiada data untuk tahun/semester yang dipilih</li>
                    <li>Masalah connection database</li>
                </ul>
                <a href="admin_import_takwim.php" class="btn btn-primary mt-3">
                    <i class="fas fa-upload me-2"></i>Import TAKWIM
                </a>
                <button onclick="document.querySelector('.debug-info').style.display='block'" class="btn btn-warning mt-3">
                    <i class="fas fa-bug me-2"></i>Show Debug Info
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
            <div class="course-card" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                <div class="row align-items-center">
                    <div class="col-md-1 text-center">
                        <div class="badge bg-primary rounded-circle" style="padding: 12px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <?php echo htmlspecialchars($course['bil'] ?? $course['BIL'] ?? '0'); ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h6 class="mb-2 fw-bold text-dark">
                            <?php echo htmlspecialchars($course['nama_kursus'] ?? $course['nama_kursus_takwim'] ?? $course['NAMA_KURSUS'] ?? 'Nama Kursus'); ?>
                        </h6>
                        <?php if (!empty($course['siri'])): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($course['siri']); ?>
                            </small>
                        <?php endif; ?>
                        <div class="mt-2">
                            <span class="badge bg-info">
                                <?php echo htmlspecialchars($course['kategori'] ?? $course['KATEGORI'] ?? 'Tiada Kategori'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <?php if (!empty($course['minggu_no'])): ?>
                            <small class="text-muted d-block">
                                <i class="fas fa-calendar-week me-1"></i>Minggu <?php echo htmlspecialchars($course['minggu_no']); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="badge bg-success rounded-pill" style="padding: 8px 12px;">
                            <i class="fas fa-users me-1"></i> 
                            <?php echo htmlspecialchars($course['kapasiti'] ?? $course['kapasiti_rancangan'] ?? $course['KAPASITI'] ?? '0'); ?>
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-eye me-2"></i>Lihat</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash me-2"></i>Padam</a></li>
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
</body>
</html>