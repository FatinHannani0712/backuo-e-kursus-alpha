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

// Get filter parameter
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : 'semua';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$sql = "SELECT k.*, 
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

$sql .= " ORDER BY k.kategori, k.nama_kursus";

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
        case 'senarai_tunggu': return 'Senarai Tunggu';
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
    body { 
      background-color: #f8f9fa; 
      font-family: 'Segoe UI', sans-serif; 
    }
    
    /* Header Styling - Matching your index.php */
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
      margin-bottom: 3rem;
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
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.15"/><circle cx="20" cy="80" r="0.5" fill="white" opacity="0.15"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      pointer-events: none;
    }

    .page-header .container {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .page-subtitle {
      font-size: 1.2rem;
      opacity: 0.9;
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* Statistics Cards */
    .stats-section {
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 4px solid #062152ff;
      transition: transform 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-3px);
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: #0C3C60;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: #6c757d;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .stat-icon {
      font-size: 2.5rem;
      color: #1770b8ff;
      margin-bottom: 1rem;
    }

    /* Search & Filter Section */
    .search-filter-section {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 6px solid #0C3C60;
    }

    .search-bar {
      position: relative;
      margin-bottom: 1.5rem;
    }

    .search-bar input {
      width: 100%;
      padding: 1rem 3rem 1rem 1rem;
      border: 2px solid #dee2e6;
      border-radius: 10px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .search-bar input:focus {
      outline: none;
      border-color: #0C3C60;
      box-shadow: 0 0 0 3px rgba(12, 60, 96, 0.1);
    }

    .search-bar .search-icon {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
    }

    .filter-tabs {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .filter-tab {
      padding: 0.75rem 1.5rem;
      border: 2px solid #dee2e6;
      border-radius: 25px;
      background: white;
      color: #6c757d;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 500;
      font-size: 0.9rem;
      text-decoration: none;
    }

    .filter-tab:hover {
      border-color: #0C3C60;
      color: #0C3C60;
      transform: translateY(-2px);
      text-decoration: none;
    }

    .filter-tab.active {
      background: #0C3C60;
      border-color: #0C3C60;
      color: white;
    }

    /* Course Cards */
    .course-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 2rem;
    }

    .course-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      border-left: 6px solid #0C3C60;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .course-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .course-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 100px;
      height: 100px;
      background: linear-gradient(45deg, rgba(12, 60, 96, 0.1), transparent);
      border-radius: 0 0 0 100px;
    }

    .course-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
      position: relative;
      z-index: 2;
    }

    .course-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0C3C60, #17a2b8);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      box-shadow: 0 4px 8px rgba(12, 60, 96, 0.3);
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
      font-size: 1.4rem;
      font-weight: 700;
      color: #0C3C60;
      margin-bottom: 0.75rem;
      line-height: 1.3;
    }

    .course-description {
      color: #6c757d;
      margin-bottom: 1.5rem;
      line-height: 1.6;
      font-size: 0.95rem;
    }

    .course-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
      color: #495057;
    }

    .detail-item i {
      color: #0C3C60;
      width: 16px;
      text-align: center;
    }

    .course-progress {
      margin-bottom: 1.5rem;
    }

    .progress-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      color: #495057;
      font-weight: 500;
    }

    .progress-bar-custom {
      width: 100%;
      height: 8px;
      background: #e9ecef;
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(135deg, #0C3C60, #17a2b8);
      border-radius: 4px;
      transition: width 0.3s ease;
    }

    .course-footer {
      display: flex;
      gap: 1rem;
    }

    .btn-custom {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      font-size: 0.9rem;
    }

    .btn-primary-custom {
      background: linear-gradient(135deg, #0C3C60, #17a2b8);
      color: white;
      flex: 1;
    }

    .btn-primary-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(12, 60, 96, 0.3);
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

    .btn-custom:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
    }

    .btn-success-custom {
      background: #28a745;
      color: white;
    }

    .btn-warning-custom {
      background: #ffc107;
      color: #212529;
    }

    /* Footer - Matching your style */
    footer { 
      background: #0C3C60; 
      color: white; 
      text-align: center; 
      padding: 20px; 
      margin-top: 40px; 
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      color: #dee2e6;
    }

    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      color: #495057;
    }

    /* Breadcrumb */
    .custom-breadcrumb {
      background: rgba(255, 255, 255, 0.9);
      padding: 1rem 0;
      margin-bottom: 2rem;
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

    /* Responsive */
    @media (max-width: 768px) {
      .page-title {
        font-size: 2rem;
      }

      .course-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .filter-tabs {
        gap: 0.5rem;
      }

      .filter-tab {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
      }

      .course-details {
        grid-template-columns: 1fr;
      }

      .course-footer {
        flex-direction: column;
      }

      .search-filter-section {
        padding: 1.5rem;
      }
    }

    /* Registration Status Badge */
    .registration-badge {
      position: absolute;
      top: 10px;
      left: 10px;
      background: #28a745;
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 15px;
      font-size: 0.7rem;
      font-weight: bold;
      z-index: 3;
    }

    .registration-badge.pending {
      background: #ffc107;
      color: #212529;
    }

    .registration-badge.ditolak {
      background: #dc3545;
      color: white;
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
        color: #dc3545;
        background: rgba(220, 53, 69, 0.1);
    }

    /* Clear all filters button */
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

    /* Active filter indicator */
    .filter-active-badge {
        background: #0C3C60;
        color: white;
        padding: 0.2rem 0.6rem;
        border-radius: 15px;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }

    /* Responsive adjustments for clear button */
    @media (max-width: 768px) {
        .btn-clear-search {
            right: 35px;
        }
        
        .btn-clear-all {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
    }


  </style>
</head>
<body>

<!-- NAVBAR - Exact same as your index.php -->
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
                        
                        <!-- Clear search button - only show when there's search text -->
                        <?php if (!empty($search)): ?>
                        <button type="button" class="btn-clear-search" onclick="clearSearch()" 
                                title="Kosongkan carian">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add clear all filters button -->
                    <?php if (!empty($search) || $kategori_filter != 'semua'): ?>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">
                            <?php if (!empty($search)): ?>
                                Carian: "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                            <?php if ($kategori_filter != 'semua'): ?>
                                <?php echo !empty($search) ? ' | ' : ''; ?>
                                Kategori: <?php echo htmlspecialchars($kategori_filter); ?>
                            <?php endif; ?>
                        </small>
                        <a href="katalog_kursus.php" class="btn-clear-all">
                            <i class="fas fa-times-circle"></i> Padam Semua Penapis
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-tabs mt-3">
                        <!-- Existing filter tabs code remains the same -->
                        <a href="?kategori=semua<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                          class="filter-tab <?php echo $kategori_filter == 'semua' ? 'active' : ''; ?>">
                            <i class="fas fa-th-large me-1"></i> Semua Kursus
                        </a>
                        
                        <?php foreach ($kategori_list as $kat): ?>
                        <a href="?kategori=<?php echo urlencode($kat['kategori']); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                          class="filter-tab <?php echo $kategori_filter == $kat['kategori'] ? 'active' : ''; ?>">
                            <i class="<?php echo getCategoryIcon($kat['kategori']); ?> me-1"></i> 
                            <?php echo str_replace('Program ', '', $kat['kategori']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

  <!-- Course Information Section -->
  <div class="row mt-5">
    <div class="col-md-4 mb-4">
      <div class="course-card">
        <div class="course-header">
          <div class="course-icon">
            <i class="fas fa-info-circle"></i>
          </div>
        </div>
        <h4 class="course-title">Maklumat Kursus</h4>
        <p class="course-description">
          Semua kursus dijalankan di Akademi Latihan Pertahanan Awam (ALPHA) dengan kemudahan terkini dan tenaga pengajar yang berpengalaman dalam bidang pertahanan awam.
        </p>
        <div class="course-footer">
          <a href="index.php#hubungi" class="btn-custom btn-primary-custom w-100">
            <i class="fas fa-phone"></i>
            Hubungi Kami
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-4">
      <div class="course-card">
        <div class="course-header">
          <div class="course-icon">
            <i class="fas fa-file-alt"></i>
          </div>
        </div>
        <h4 class="course-title">Syarat Permohonan</h4>
        <p class="course-description">
          Pastikan anda telah melengkapkan profil pengguna dan borang kesihatan sebelum memohon kursus. Syarat kelayakan berbeza mengikut jenis dan tahap kursus.
        </p>
        <div class="course-footer">
          <a href="kemaskini.php" class="btn-custom btn-primary-custom w-100">
            <i class="fas fa-user-edit"></i>
            Kemaskini Profil
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-4">
      <div class="course-card">
        <div class="course-header">
          <div class="course-icon">
            <i class="fas fa-question-circle"></i>
          </div>
        </div>
        <h4 class="course-title">Bantuan & Sokongan</h4>
        <p class="course-description">
          Perlukan bantuan dengan permohonan kursus? Hubungi pasukan sokongan kami atau lawati pusat bantuan untuk panduan lengkap mengenai sistem e-Kursus ALPHA.
        </p>
        <div class="course-footer">
          <a href="index.php#hubungi" class="btn-custom btn-primary-custom w-100">
            <i class="fas fa-life-ring"></i>
            Pusat Bantuan
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Program Categories Overview -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="course-card">
        <h4 class="course-title text-center mb-4">
          <i class="fas fa-list-alt me-2"></i>
          Program Kursus Tahunan APM
        </h4>
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="p-3 border rounded">
              <h6 class="text-primary"><i class="fas fa-cogs me-2"></i>Program Kemahiran Teknikal</h6>
              <ul class="list-unstyled small mb-0">
                <li>• Ascending & Descending</li>
                <li>• Navigasi Darat</li>
                <li>• Asas Radio Komunikasi</li>
                <li>• Pengurusan Bencana Runtuhan</li>
                <li>• Rope Rescue Operator System</li>
                <li>• Pengendalian & Penyelenggaraan Peralatan Operasi</li>
                <li>• Kejurulatihan A&D</li>
                <li>• Kejurulatihan PPPO</li>
              </ul>
            </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <div class="p-3 border rounded">
              <h6 class="text-primary"><i class="fas fa-users me-2"></i>Program Pembangunan Personel</h6>
              <ul class="list-unstyled small mb-0">
                <li>• Metodologi Latihan</li>
                <li>• Bakal Pegawai</li>
                <li>• Pengurusan Pusat Pemindahan</li>
                <li>• Asas Kecergasan</li>
                <li>• Kejurulatihan Kecergasan</li>
                <li>• Kecergasan Lanjutan</li>
                <li>• Asas Kawad Kaki</li>
                <li>• Kejurulatihan Metodologi</li>
                <li>• PED MERS 999</li>
              </ul>
            </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <div class="p-3 border rounded">
              <h6 class="text-primary"><i class="fas fa-swimmer me-2"></i>Program Akuatik</h6>
              <ul class="list-unstyled small mb-0">
                <li>• Asas Renang</li>
                <li>• Kejurulatihan Asas Renang</li>
                <li>• Menyelamat Kelemasan di Air</li>
                <li>• Penyelamat Pantai</li>
                <li>• Kejurulatihan Penyelamat Pantai</li>
                <li>• Scuba Rescue Civil Defence</li>
                <li>• Menyelamat Air Deras</li>
              </ul>
            </div>
          </div>
          
          <div class="col-md-6 mb-3">
            <div class="p-3 border rounded">
              <h6 class="text-primary"><i class="fas fa-medkit me-2"></i>Program Paramedik</h6>
              <ul class="list-unstyled small mb-0">
                <li>• First Responder Level 1</li>
                <li>• First Responder Level 2</li>
                <li>• Kejurulatihan FRLS Level 1</li>
                <li>• Disaster Victim Identification (DVI)</li>
                <li>• Kejurulatihan Disaster Victim Identification (DVI)</li>
              </ul>
            </div>
          </div>
        </div>
        
        <div class="text-center mt-3">
          <p class="text-muted small">
            <strong>Jumlah:</strong> 4 Program Utama | 29 Sub-Kursus | Penawaran Standard Tahunan
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- FOOTER - Exact same as your index.php -->
<footer>
  <p>&copy; <?php echo date("Y"); ?> e-Kursus ALPHA | Hak Cipta Terpelihara</p>
</footer>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Update datetime display every second (Malay locale) - Same as your index.php
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

  // Auto-submit search form on enter
  document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      this.closest('form').submit();
    }
  });

    // Clear search input
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchForm').submit();
}

  // Clear specific filter
  function clearFilter(filterType) {
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);
      
      if (filterType === 'search') {
          params.delete('search');
      } else if (filterType === 'kategori') {
          params.delete('kategori');
      }
      
      // Update URL without page reload for better UX
      window.location.href = 'katalog_kursus.php?' + params.toString();
  }

  // Enhanced filter display with individual clear buttons
  function updateFilterDisplay() {
      const urlParams = new URLSearchParams(window.location.search);
      const searchValue = urlParams.get('search');
      const kategoriValue = urlParams.get('kategori');
      
      // You can add dynamic filter display here if needed
  }

  // Auto-clear functionality
  document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      
      // Add clear button dynamically based on input
      if (searchInput) {
          searchInput.addEventListener('input', function() {
              const clearBtn = document.querySelector('.btn-clear-search');
              if (this.value.trim() !== '' && !clearBtn) {
                  // Create clear button if it doesn't exist
                  const clearButton = document.createElement('button');
                  clearButton.type = 'button';
                  clearButton.className = 'btn-clear-search';
                  clearButton.innerHTML = '<i class="fas fa-times"></i>';
                  clearButton.title = 'Kosongkan carian';
                  clearButton.onclick = clearSearch;
                  
                  const searchIcon = document.querySelector('.search-icon');
                  searchIcon.parentNode.insertBefore(clearButton, searchIcon);
              } else if (this.value.trim() === '' && clearBtn) {
                  clearBtn.remove();
              }
          });
      }
      
      // Initialize filter display
      updateFilterDisplay();
  });

  // Enhanced search form submission
  document.getElementById('searchForm')?.addEventListener('submit', function(e) {
      const searchInput = document.getElementById('searchInput');
      if (searchInput && searchInput.value.trim() === '') {
          // If search is empty, remove search parameter from URL
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
      this.style.borderLeftColor = '#17a2b8';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.borderLeftColor = '#0C3C60';
    });
  });

  // Smooth scrolling for anchor links
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