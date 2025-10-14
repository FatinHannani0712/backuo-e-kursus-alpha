<?php
session_start();

// Security check: Only admin can access
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - ALPHA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
    }
    .sidebar {
      height: 100vh;
      background: #0C3C60;
      color: white;
      padding-top: 20px;
    }
    .sidebar a {
      color: white;
      text-decoration: none;
      display: block;
      padding: 10px 20px;
      transition: background 0.3s;
    }
    .sidebar a:hover {
      background: #094064;
    }
    .content {
      padding: 20px;
    }
    .navbar {
      background: #0C3C60;
    }
    .navbar-brand {
      color: white !important;
      font-weight: bold;
    }
    .nav-link {
      color: white !important;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">

    <!-- Sidebar -->
    <nav class="col-md-2 d-none d-md-block sidebar">
      <h4 class="text-center">ADMIN</h4>
      <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
      <a href="users.php"><i class="fas fa-users me-2"></i>Pengguna</a>
      <a href="kursus.php"><i class="fas fa-book me-2"></i>Kursus</a>
      <a href="laporan.php"><i class="fas fa-file-alt me-2"></i>Laporan</a>
      <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Log Keluar</a>
    </nav>

    <!-- Main Content -->
    <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 content">
      <nav class="navbar navbar-expand-lg mb-4">
        <div class="container-fluid">
          <span class="navbar-brand">Admin Dashboard</span>
          <div class="d-flex">
            <span class="nav-link">👤 <?php echo $_SESSION['email']; ?></span>
          </div>
        </div>
      </nav>

      <h3>Selamat Datang, Admin!</h3>
      <p>Ini adalah papan pemuka admin. Anda boleh mengurus pengguna, kursus dan laporan di sini.</p>

      <!-- Future cards / widgets -->
      <div class="row">
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="fas fa-users me-2"></i>Pengguna</h5>
              <p class="card-text">Uruskan akaun pengguna dalam sistem.</p>
              <a href="users.php" class="btn btn-primary btn-sm">Lihat</a>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="fas fa-book me-2"></i>Kursus</h5>
              <p class="card-text">Tambah dan uruskan kursus (dibuat dalam kursus.php).</p>
              <a href="kursus.php" class="btn btn-primary btn-sm">Lihat</a>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="fas fa-file-alt me-2"></i>Laporan</h5>
              <p class="card-text">Jana laporan berkaitan kursus dan pengguna.</p>
              <a href="laporan.php" class="btn btn-primary btn-sm">Lihat</a>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

</body>
</html>
