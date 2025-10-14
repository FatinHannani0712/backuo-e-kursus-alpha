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
$username = "root"; // Ganti dengan username database anda
$password = "";     // Ganti dengan password database anda
$dbname = "ecourses_apm"; // Ganti dengan nama database anda

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : 'semua';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tarikh_filter = isset($_GET['tarikh']) ? $_GET['tarikh'] : '';

// Build query based on filters
$sql = "SELECT k.*, 
               COALESCE(d.jumlah_daftar, 0) as jumlah_sudah_daftar,
               CASE 
                   WHEN k.pendaftar >= k.kapasiti THEN 'penuh'
                   WHEN k.pendaftar >= (k.kapasiti * 0.8) THEN 'senarai_tunggu'
                   ELSE k.status_kursus
               END as status_display,
               CASE 
                   WHEN k.status_kursus = 'buka_pendaftaran' THEN 1
                   WHEN k.status_kursus = 'akan_datang' THEN 2
                   ELSE 3
               END as status_order
        FROM kursus k
        LEFT JOIN (
            SELECT kursus_id, COUNT(*) as jumlah_daftar 
            FROM daftar_kursus 
            WHERE status = 'diterima' 
            GROUP BY kursus_id
        ) d ON k.kursus_id = d.kursus_id
        WHERE 1=1";

$params = array();

// Add category filter
if ($kategori_filter != 'semua') {
    $sql .= " AND k.kategori = :kategori";
    $params[':kategori'] = $kategori_filter;
}

// Add search filter
if (!empty($search)) {
    $sql .= " AND (k.nama_kursus LIKE :search OR k.penerangan LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add date filter
if (!empty($tarikh_filter)) {
    if ($tarikh_filter == 'minggu_ini') {
        $sql .= " AND k.tarikh_mula BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($tarikh_filter == 'bulan_ini') {
        $sql .= " AND MONTH(k.tarikh_mula) = MONTH(CURDATE()) AND YEAR(k.tarikh_mula) = YEAR(CURDATE())";
    } elseif ($tarikh_filter == 'akan_datang') {
        $sql .= " AND k.tarikh_mula > CURDATE()";
    } elseif ($tarikh_filter == 'sedang_berlangsung') {
        $sql .= " AND k.tarikh_mula <= CURDATE() AND k.tarikh_tamat >= CURDATE()";
    }
}

// Order by: Open courses first, then by category
$sql .= " ORDER BY status_order ASC, k.kategori, k.nama_kursus";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kursus_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get course statistics
$stats_sql = "SELECT 
    COUNT(*) as total_kursus,
    SUM(k.kapasiti) as total_kapasiti,
    SUM(k.pendaftar) as total_pendaftar,
    COUNT(CASE WHEN k.status_kursus = 'buka_pendaftaran' THEN 1 END) as kursus_aktif
    FROM kursus k";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for filter
$kategori_sql = "SELECT DISTINCT kategori FROM kursus ORDER BY kategori";
$kategori_stmt = $pdo->prepare($kategori_sql);
$kategori_stmt->execute();
$kategori_list = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user already registered for courses
$user_registrations = array();
if ($isLoggedIn) {
    $reg_sql = "SELECT kursus_id, status FROM daftar_kursus WHERE user_id = :user_id";
    $reg_stmt = $pdo->prepare($reg_sql);
    $reg_stmt->execute([':user_id' => $userId]);
    $user_registrations = $reg_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Function to get status badge class
function getStatusClass($status) {
    switch($status) {
        case 'buka_pendaftaran': return 'status-registration';
        case 'akan_datang': return 'status-upcoming';
        case 'penuh': return 'status-full';
        case 'senarai_tunggu': return 'status-waitlist';
        default: return 'status-upcoming';
    }
}

// Function to get status text
function getStatusText($status) {
    switch($status) {
        case 'buka_pendaftaran': return 'Buka Pendaftaran';
        case 'akan_datang': return 'Akan Datang';
        case 'penuh': return 'Penuh';
        case 'senarai_tunggu': return 'Senarai Tunggu Tersedia';
        default: return 'Akan Datang';
    }
}

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

// APM Color Theme
$apm_colors = [
    'primary' => '#0C3C60',    // Dark Blue
    'secondary' => '#FF6B00',  // Orange
    'accent' => '#D4AF37',     // Gold
    'success' => '#28a745',    // Green
    'warning' => '#ffc107',    // Yellow
    'danger' => '#dc3545'      // Red
];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>Katalog Kursus - e-Kursus ALPHA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --apm-primary: <?php echo $apm_colors['primary']; ?>;
      --apm-secondary: <?php echo $apm_colors['secondary']; ?>;
      --apm-accent: <?php echo $apm_colors['accent']; ?>;
      --apm-success: <?php echo $apm_colors['success']; ?>;
      --apm-warning: <?php echo $apm_colors['warning']; ?>;
      --apm-danger: <?php echo $apm_colors['danger']; ?>;
    }

    body { 
      background-color: #f8f9fa; 
      font-family: 'Segoe UI', sans-serif; 
    }
    
    /* Header Styling - Updated with APM Colors */
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
      background-color: var(--apm-secondary); 
      color: white; 
      border: 1px solid var(--apm-secondary); 
    }
    .register-btn:hover { 
      background-color: #e05a00; 
      transform: translateY(-2px); 
    }
    .profile-btn { 
      background-color: var(--apm-primary); 
      color: white; 
      border: 1px solid var(--apm-primary); 
    }
    .profile-btn:hover { 
      background-color: #062152; 
      transform: translateY(-2px); 
    }

    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--apm-primary) 0%, #1757b8 100%);
      color: white;
      padding: 4rem 0;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    /* Statistics Cards */
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 4px solid var(--apm-primary);
      transition: transform 0.3s ease;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--apm-primary);
      margin-bottom: 0.5rem;
    }

    .stat-icon {
      font-size: 2.5rem;
      color: var(--apm-primary);
      margin-bottom: 1rem;
    }

    /* Search & Filter Section */
    .search-filter-section {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 6px solid var(--apm-primary);
    }

    .search-bar input:focus {
      outline: none;
      border-color: var(--apm-primary);
      box-shadow: 0 0 0 3px rgba(12, 60, 96, 0.1);
    }

    .filter-tab:hover {
      border-color: var(--apm-primary);
      color: var(--apm-primary);
      transform: translateY(-2px);
      text-decoration: none;
    }

    .filter-tab.active {
      background: var(--apm-primary);
      border-color: var(--apm-primary);
      color: white;
    }

    /* Course Cards */
    .course-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 6px solid var(--apm-primary);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .course-icon {
      background: linear-gradient(135deg, var(--apm-primary), var(--apm-secondary));
    }

    .course-title {
      color: var(--apm-primary);
    }

    .detail-item i {
      color: var(--apm-primary);
    }

    .progress-fill {
      background: linear-gradient(135deg, var(--apm-primary), var(--apm-secondary));
    }

    .btn-primary-custom {
      background: linear-gradient(135deg, var(--apm-primary), var(--apm-secondary));
      color: white;
    }

    .btn-secondary-custom {
      background: white;
      color: var(--apm-primary);
      border: 2px solid var(--apm-primary);
    }

    .btn-secondary-custom:hover {
      background: var(--apm-primary);
      color: white;
    }

    .btn-success-custom {
      background: var(--apm-success);
      color: white;
    }

    .btn-warning-custom {
      background: var(--apm-warning);
      color: #212529;
    }

    /* Date Filter Styles */
    .date-filter-tabs {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin-top: 1rem;
    }

    .date-filter-tab {
      padding: 0.6rem 1.2rem;
      border: 2px solid #dee2e6;
      border-radius: 20px;
      background: white;
      color: #6c757d;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 500;
      font-size: 0.85rem;
      text-decoration: none;
    }

    .date-filter-tab:hover {
      border-color: var(--apm-secondary);
      color: var(--apm-secondary);
      transform: translateY(-2px);
      text-decoration: none;
    }

    .date-filter-tab.active {
      background: var(--apm-secondary);
      border-color: var(--apm-secondary);
      color: white;
    }

    /* Health Form Notice */
    .health-notice {
      background: linear-gradient(135deg, #fff3cd, #ffeaa7);
      border-left: 4px solid var(--apm-warning);
      padding: 1rem;
      border-radius: 8px;
      margin: 1rem 0;
      font-size: 0.9rem;
    }

    .health-notice strong {
      color: var(--apm-primary);
    }

    /* Registration Info Section */
    .info-section {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      margin: 2rem 0;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 6px solid var(--apm-secondary);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .date-filter-tabs {
        gap: 0.5rem;
      }
      .date-filter-tab {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
      }
    }

    /* Existing styles remain the same, just updating colors */
    .navbar-dark.bg-primary {
      background-color: var(--apm-primary) !important;
    }

    footer { 
      background: var(--apm-primary); 
      color: white; 
    }

    /* Status colors updated */
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

    /* Clear search button styles */
    .btn-clear-search {
        position: absolute;
        right: 40px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.3s ease;
        z-index: 5;
    }

    .btn-clear-search:hover {
        color: var(--apm-danger);
        background: rgba(220, 53, 69, 0.1);
    }

    .btn-clear-all {
        background: #6c757d;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .btn-clear-all:hover {
        background: #5a6268;
        color: white;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .filter-active-badge {
        background: var(--apm-primary);
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 15px;
        font-size: 0.75rem;
        margin-left: 0.5rem;
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
        <li class="breadcrumb-item active" aria-current="page">Katalog Kursus</li>
      </ol>
    </nav>
  </div>
</div>

<!-- PAGE HEADER -->
<div class="page-header">
  <div class="container text-center">
    <h1 class="page-title">
      <i class="fas fa-graduation-cap me-3"></i>
      Katalog Kursus ALPHA
    </h1>
    <p class="page-subtitle">
      Jelajahi <?php echo count($kursus_list); ?> kursus latihan profesional yang tersedia untuk meningkatkan kemahiran dan pengetahuan anda dalam bidang pertahanan awam di Akademi Latihan Pertahanan Awam (ALPHA).
    </p>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">
  
  <!-- STATISTICS SECTION -->
  <div class="stats-section">
    <div class="row">
      <div class="col-md-3 mb-3">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-book-open"></i></div>
          <div class="stat-number"><?php echo $stats['total_kursus']; ?></div>
          <div class="stat-label">Jumlah Kursus</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
          <div class="stat-number"><?php echo $stats['total_pendaftar']; ?></div>
          <div class="stat-label">Peserta Berdaftar</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
          <div class="stat-number"><?php echo $stats['kursus_aktif']; ?></div>
          <div class="stat-label">Kursus Aktif</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-percentage"></i></div>
          <div class="stat-number"><?php echo $stats['total_kapasiti'] > 0 ? round(($stats['total_pendaftar']/$stats['total_kapasiti'])*100) : 0; ?>%</div>
          <div class="stat-label">Kadar Penggunaan</div>
        </div>
      </div>
    </div>
  </div>

  <!-- SEARCH AND FILTER SECTION -->
  <div class="search-filter-section">
    <div class="row">
      <div class="col-12">
        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Cari & Tapis Kursus</h5>
        
        <form method="GET" action="" id="searchForm">
          <div class="search-bar position-relative">
            <input type="text" name="search" class="form-control" 
                  placeholder="Cari kursus mengikut nama atau penerangan..." 
                  value="<?php echo htmlspecialchars($search); ?>"
                  id="searchInput">
            <i class="fas fa-search search-icon"></i>
            
            <?php if (!empty($search)): ?>
            <button type="button" class="btn-clear-search" onclick="clearSearch()" 
                    title="Kosongkan carian">
                <i class="fas fa-times"></i>
            </button>
            <?php endif; ?>
          </div>
          
          <!-- Date Filter Tabs -->
          <div class="date-filter-tabs">
            <span class="me-2" style="color: #6c757d; font-weight: 500;"><i class="fas fa-calendar me-1"></i>Pilih Tarikh:</span>
            <a href="?kategori=<?php echo urlencode($kategori_filter); ?>&search=<?php echo urlencode($search); ?>&tarikh=" 
              class="date-filter-tab <?php echo empty($tarikh_filter) ? 'active' : ''; ?>">
              Semua Tarikh
            </a>
            <a href="?kategori=<?php echo urlencode($kategori_filter); ?>&search=<?php echo urlencode($search); ?>&tarikh=minggu_ini" 
              class="date-filter-tab <?php echo $tarikh_filter == 'minggu_ini' ? 'active' : ''; ?>">
              Minggu Ini
            </a>
            <a href="?kategori=<?php echo urlencode($kategori_filter); ?>&search=<?php echo urlencode($search); ?>&tarikh=bulan_ini" 
              class="date-filter-tab <?php echo $tarikh_filter == 'bulan_ini' ? 'active' : ''; ?>">
              Bulan Ini
            </a>
            <a href="?kategori=<?php echo urlencode($kategori_filter); ?>&search=<?php echo urlencode($search); ?>&tarikh=akan_datang" 
              class="date-filter-tab <?php echo $tarikh_filter == 'akan_datang' ? 'active' : ''; ?>">
              Akan Datang
            </a>
            <a href="?kategori=<?php echo urlencode($kategori_filter); ?>&search=<?php echo urlencode($search); ?>&tarikh=sedang_berlangsung" 
              class="date-filter-tab <?php echo $tarikh_filter == 'sedang_berlangsung' ? 'active' : ''; ?>">
              Sedang Berlangsung
            </a>
          </div>
          
          <!-- Category Filter Tabs -->
          <div class="filter-tabs mt-3">
            <a href="?tarikh=<?php echo urlencode($tarikh_filter); ?>&search=<?php echo urlencode($search); ?>&kategori=semua" 
              class="filter-tab <?php echo $kategori_filter == 'semua' ? 'active' : ''; ?>">
                <i class="fas fa-th-large me-1"></i> Semua Kursus
            </a>
            
            <?php foreach ($kategori_list as $kat): ?>
            <a href="?tarikh=<?php echo urlencode($tarikh_filter); ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kat['kategori']); ?>" 
              class="filter-tab <?php echo $kategori_filter == $kat['kategori'] ? 'active' : ''; ?>">
                <i class="<?php echo getCategoryIcon($kat['kategori']); ?> me-1"></i> 
                <?php echo str_replace('Program ', '', $kat['kategori']); ?>
            </a>
            <?php endforeach; ?>
          </div>
          
          <?php if (!empty($search) || $kategori_filter != 'semua' || !empty($tarikh_filter)): ?>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="text-muted">
              <?php 
              $filters = [];
              if (!empty($search)) $filters[] = 'Carian: "' . htmlspecialchars($search) . '"';
              if ($kategori_filter != 'semua') $filters[] = 'Kategori: ' . htmlspecialchars($kategori_filter);
              if (!empty($tarikh_filter)) {
                $tarikh_labels = [
                  'minggu_ini' => 'Minggu Ini',
                  'bulan_ini' => 'Bulan Ini', 
                  'akan_datang' => 'Akan Datang',
                  'sedang_berlangsung' => 'Sedang Berlangsung'
                ];
                $filters[] = 'Tarikh: ' . $tarikh_labels[$tarikh_filter];
              }
              echo implode(' | ', $filters);
              ?>
            </small>
            <a href="katalog_kursus.php" class="btn-clear-all">
              <i class="fas fa-times-circle"></i> Padam Semua Penapis
            </a>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- Health Form Notice -->
  <?php if ($isLoggedIn): ?>
  <div class="health-notice">
    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
    <strong>Penting:</strong> Sebelum mendaftar untuk mana-mana kursus, pastikan anda telah mengisi 
    <a href="borang_kesihatan.php" class="text-primary fw-bold">Borang Pengakuan Kesihatan</a>. 
    Permohonan tanpa borang kesihatan yang lengkap tidak akan diproses.
  </div>
  <?php else: ?>
  <div class="health-notice">
    <i class="fas fa-info-circle text-primary me-2"></i>
    <strong>Makluman:</strong> Untuk mendaftar kursus, anda perlu 
    <a href="login.php" class="text-primary fw-bold">log masuk</a> terlebih dahulu dan mengisi 
    Borang Pengakuan Kesihatan sebelum membuat permohonan.
  </div>
  <?php endif; ?>

  <!-- COURSE GRID -->
  <?php if (count($kursus_list) > 0): ?>
  <div class="course-grid" id="courseGrid">
    
    <?php foreach ($kursus_list as $kursus): 
      $progress_percentage = $kursus['kapasiti'] > 0 ? ($kursus['pendaftar'] / $kursus['kapasiti']) * 100 : 0;
      $user_registered = isset($user_registrations[$kursus['kursus_id']]);
      $registration_status = $user_registered ? $user_registrations[$kursus['kursus_id']] : '';
    ?>
    
    <div class="course-card">
      <!-- Registration Status Badge -->
      <?php if ($user_registered): ?>
        <div class="registration-badge <?php echo $registration_status; ?>">
          <?php 
            switch($registration_status) {
              case 'diterima': echo 'Berdaftar'; break;
              case 'pending': echo 'Dalam Proses'; break;
              case 'ditolak': echo 'Ditolak'; break;
            }
          ?>
        </div>
      <?php endif; ?>

      <div class="course-header">
        <div class="course-icon">
          <i class="<?php echo getCategoryIcon($kursus['kategori']); ?>"></i>
        </div>
        <div class="course-status <?php echo getStatusClass($kursus['status_display']); ?>">
          <?php echo getStatusText($kursus['status_display']); ?>
        </div>
      </div>
      
      <h3 class="course-title"><?php echo htmlspecialchars($kursus['nama_kursus']); ?></h3>
      <p class="course-description"><?php echo htmlspecialchars($kursus['penerangan']); ?></p>
      
      <div class="course-details">
        <div class="detail-item">
          <i class="fas fa-calendar"></i>
          <span><?php echo $kursus['tarikh_mula'] ? date('d/m/Y', strtotime($kursus['tarikh_mula'])) : 'Akan Diumumkan'; ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-clock"></i>
          <span><?php echo $kursus['tempoh'] ?: 'Akan Diumumkan'; ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-map-marker-alt"></i>
          <span><?php echo htmlspecialchars($kursus['lokasi']); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-users"></i>
          <span><?php echo $kursus['pendaftar']; ?>/<?php echo $kursus['kapasiti']; ?> peserta</span>
        </div>
      </div>

      <div class="course-progress">
        <div class="progress-label">
          <span>Kapasiti Pendaftaran</span>
          <span><?php echo round($progress_percentage); ?>%</span>
        </div>
        <div class="progress-bar-custom">
          <div class="progress-fill" style="width: <?php echo min($progress_percentage, 100); ?>%;"></div>
        </div>
      </div>

      <div class="course-footer">
        <?php if ($user_registered): ?>
          <?php if ($registration_status == 'diterima'): ?>
            <a href="dashboard.php" class="btn-custom btn-success-custom w-100">
              <i class="fas fa-check-circle"></i>
              Anda Sudah Berdaftar
            </a>
          <?php elseif ($registration_status == 'pending'): ?>
            <a href="dashboard.php" class="btn-custom btn-warning-custom w-100">
              <i class="fas fa-hourglass-half"></i>
              Menunggu Keputusan
            </a>
          <?php else: ?>
            <a href="daftar_kursus.php?id=<?php echo $kursus['kursus_id']; ?>" class="btn-custom btn-primary-custom">
              <i class="fas fa-redo"></i>
              Daftar Semula
            </a>
            <a href="maklumat_kursus.php?id=<?php echo $kursus['kursus_id']; ?>" class="btn-custom btn-secondary-custom">
              <i class="fas fa-info-circle"></i>
              Maklumat
            </a>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($kursus['status_display'] == 'buka_pendaftaran'): ?>
            <?php if ($isLoggedIn): ?>
              <a href="daftar_kursus.php?id=<?php echo $kursus['kursus_id']; ?>" class="btn-custom btn-primary-custom">
                <i class="fas fa-user-plus"></i>
                Daftar Sekarang
              </a>
            <?php else: ?>
              <a href="login.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-sign-in-alt"></i>
                Login untuk Daftar
              </a>
            <?php endif; ?>
          <?php elseif ($kursus['status_display'] == 'penuh'): ?>
            <?php if ($isLoggedIn): ?>
              <a href="daftar_kursus.php?id=<?php echo $kursus['kursus_id']; ?>&waitlist=1" class="btn-custom btn-primary-custom">
                <i class="fas fa-list"></i>
                Senarai Tunggu
              </a>
            <?php else: ?>
              <a href="login.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-sign-in-alt"></i>
                Login untuk Senarai Tunggu
              </a>
            <?php endif; ?>
          <?php elseif ($kursus['status_display'] == 'senarai_tunggu'): ?>
            <?php if ($isLoggedIn): ?>
              <a href="daftar_kursus.php?id=<?php echo $kursus['kursus_id']; ?>&waitlist=1" class="btn-custom btn-primary-custom">
                <i class="fas fa-user-clock"></i>
                Sertai Senarai Tunggu
              </a>
            <?php else: ?>
              <a href="login.php" class="btn-custom btn-primary-custom">
                <i class="fas fa-sign-in-alt"></i>
                Login untuk Senarai Tunggu
              </a>
            <?php endif; ?>
          <?php else: ?>
            <button class="btn-custom btn-primary-custom" disabled>
              <i class="fas fa-clock"></i>
              <?php echo getStatusText($kursus['status_display']); ?>
            </button>
          <?php endif; ?>
          
          <a href="maklumat_kursus.php?id=<?php echo $kursus['kursus_id']; ?>" class="btn-custom btn-secondary-custom">
            <i class="fas fa-info-circle"></i>
            Maklumat
          </a>
        <?php endif; ?>
      </div>
    </div>
    
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <!-- Empty State -->
  <div class="empty-state" id="emptyState">
    <i class="fas fa-search"></i>
    <h3>Tiada Kursus Dijumpai</h3>
    <p>Maaf, tiada kursus yang sepadan dengan carian atau penapis yang dipilih.</p>
    <a href="katalog_kursus.php" class="btn-custom btn-primary-custom mt-3">
      <i class="fas fa-refresh"></i>
      Papar Semua Kursus
    </a>
  </div>
  <?php endif; ?>

  <!-- INFORMATION SECTION -->
  <div class="info-section">
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="d-flex align-items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-info-circle fa-2x text-primary me-3"></i>
          </div>
          <div>
            <h5 class="text-primary">Maklumat Kursus & Bantuan</h5>
            <p class="mb-2">Semua kursus dijalankan di Akademi Latihan Pertahanan Awam (ALPHA) dengan kemudahan terkini dan tenaga pengajar yang berpengalaman.</p>
            <a href="index.php#hubungi" class="btn-custom btn-primary-custom btn-sm">
              <i class="fas fa-phone me-1"></i>Hubungi Kami
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="d-flex align-items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-clipboard-list fa-2x text-secondary me-3"></i>
          </div>
          <div>
            <h5 class="text-secondary">Lihat Senarai Permohonan</h5>
            <p class="mb-2">Semak status permohonan kursus anda dan uruskan pendaftaran yang telah dibuat.</p>
            <?php if ($isLoggedIn): ?>
              <a href="dashboard.php" class="btn-custom btn-secondary-custom btn-sm">
                <i class="fas fa-tachometer-alt me-1"></i>Ke Dashboard
              </a>
            <?php else: ?>
              <a href="login.php" class="btn-custom btn-secondary-custom btn-sm">
                <i class="fas fa-sign-in-alt me-1"></i>Log Masuk
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row mt-4">
      <div class="col-12">
        <div class="d-flex align-items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-file-medical fa-2x text-warning me-3"></i>
          </div>
          <div>
            <h5 class="text-warning">Borang Pengakuan Kesihatan</h5>
            <p class="mb-2">Pastikan borang kesihatan telah diisi sebelum mendaftar kursus. Borang ini diperlukan untuk keselamatan peserta semasa latihan.</p>
            <?php if ($isLoggedIn): ?>
              <a href="borang_kesihatan.php" class="btn-custom btn-warning-custom btn-sm">
                <i class="fas fa-edit me-1"></i>Isi Borang Kesihatan
              </a>
            <?php else: ?>
              <a href="login.php" class="btn-custom btn-warning-custom btn-sm">
                <i class="fas fa-sign-in-alt me-1"></i>Log Masuk untuk Isi Borang
              </a>
            <?php endif; ?>
          </div>
        </div>
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
  // Update datetime display every second (Malay locale)
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

  // Clear search input
  function clearSearch() {
      document.getElementById('searchInput').value = '';
      document.getElementById('searchForm').submit();
  }

  // Auto-submit search form on enter
  document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
          this.closest('form').submit();
      }
  });

  // Enhanced search form submission
  document.getElementById('searchForm')?.addEventListener('submit', function(e) {
      const searchInput = document.getElementById('searchInput');
      if (searchInput && searchInput.value.trim() === '') {
          const url = new URL(window.location.href);
          url.searchParams.delete('search');
          window.location.href = url.toString();
          e.preventDefault();
      }
  });

  // Add loading animation to registration buttons
  document.querySelectorAll('.btn-primary-custom').forEach(btn => {
    if (!btn.hasAttribute('disabled') && !btn.href.includes('dashboard.php')) {
      btn.addEventListener('click', function(e) {
        const originalContent = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        
        setTimeout(() => {
          this.innerHTML = originalContent;
        }, 3000);
      });
    }
  });

  // Course card hover effects
  document.querySelectorAll('.course-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.borderLeftColor = 'var(--apm-secondary)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.borderLeftColor = 'var(--apm-primary)';
    });
  });

  // Initialize tooltips if Bootstrap tooltips are available
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  });
</script>

</body>
</html>