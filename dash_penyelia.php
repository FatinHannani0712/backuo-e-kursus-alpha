<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | e-Kursus ALPHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0C3C60;
            --secondary-color: #E67E22;
            --light-bg: #f5f5f5;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: #333;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a2e4a 100%);
            color: white;
            padding: 25px 0;
            margin-bottom: 30px;
            border-bottom: 5px solid var(--secondary-color);
        }
        
        .navbar {
            background: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a2e4a 100%);
            color: white;
            font-weight: 600;
            border-bottom: none;
            padding: 15px 20px;
        }
        
        .bg-secondary-light {
            background-color: #f8f9fa;
        }
        
        .quick-actions .btn {
            margin: 5px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .profile-summary img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .badge-role {
            background-color: var(--secondary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        
        .progress-bar {
            background-color: var(--secondary-color);
            border-radius: 5px;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .notification-alert {
            border-left: 5px solid var(--secondary-color);
            border-radius: 8px;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .stat-card h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        footer {
            background: var(--primary-color);
            color: white;
            padding: 20px 0;
            margin-top: 40px;
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr !important;
            }
            
            .dashboard-header h2 {
                font-size: 1.5rem;
            }
        }
        
        .role-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background: rgba(230, 126, 34, 0.15);
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
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
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
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
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fa fa-users me-1"></i>Urus Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fa fa-chart-bar me-1"></i>Laporan
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" id="userMenu" data-bs-toggle="dropdown">
                            <img src="https://ui-avatars.com/api/?name=FATIN+NASIR&background=random" class="user-avatar me-2">
                            FATIN NASIR
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
        <div class="dashboard-header text-center rounded">
            <div class="role-indicator">
                <i class="fas fa-user-shield me-1"></i>Penyelia
            </div>
            <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard Penyelia</h2>
            <p class="mb-0">Selamat datang kembali, FATIN NASIR</p>
        </div>

        <!-- Notification Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info alert-dismissible fade show notification-alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Sistem e-Kursus ALPHA versi 2.0. Lihat <a href="#" class="alert-link">panduan penggunaan</a> untuk maklumat lanjut.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
            <!-- Common Stats for All Roles -->
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <i class="fas fa-calendar-alt fa-2x mb-3" style="color: var(--primary-color);"></i>
                    <h3>25</h3>
                    <p class="mb-2">Ogos 2023</p>
                    <a href="schedule.php" class="btn btn-sm btn-outline-primary">Lihat Jadual</a>
                </div>
            </div>

            <!-- Penyelia Stats -->
            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <i class="fas fa-users fa-2x mb-3" style="color: var(--primary-color);"></i>
                    <h3>8</h3>
                    <p class="mb-2">Ahli Di Bawah Pengawasan</p>
                    <a href="supervision.php" class="btn btn-sm btn-outline-primary">Lihat Senarai</a>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="stat-card">
                    <i class="fas fa-tasks fa-2x mb-3" style="color: var(--primary-color);"></i>
                    <h3>3</h3>
                    <p class="mb-2">Penilaian Belum Selesai</p>
                    <a href="evaluations.php" class="btn btn-sm btn-outline-primary">Lihat Tugasan</a>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <!-- Penyelia Dashboard Content -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-check me-2"></i>Penilaian Terkini</span>
                        <a href="evaluations.php" class="btn btn-sm btn-light">Lihat Semua</a>
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
                                    <tr>
                                        <td>Ahmad bin Ali</td>
                                        <td>Kursus Asas Pertolongan Cemas</td>
                                        <td>22/08/2023</td>
                                        <td><span class="badge bg-warning">Perlu Penilaian</span></td>
                                        <td>
                                            <a href="evaluate.php?user_id=1" class="btn btn-sm btn-primary">Nilai</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Siti binti Abu</td>
                                        <td>Kursus Pengurusan Stress</td>
                                        <td>21/08/2023</td>
                                        <td><span class="badge bg-warning">Perlu Penilaian</span></td>
                                        <td>
                                            <a href="evaluate.php?user_id=2" class="btn btn-sm btn-primary">Nilai</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Mohd Faisal bin Ismail</td>
                                        <td>Kursus Kepimpinan</td>
                                        <td>20/08/2023</td>
                                        <td><span class="badge bg-success">Telah Dinilai</span></td>
                                        <td>
                                            <a href="evaluate.php?user_id=3" class="btn btn-sm btn-outline-secondary">Lihat</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Tindakan Pantas
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="evaluations.php" class="btn btn-primary text-start">
                                <i class="fas fa-clipboard-check me-2"></i>Penilaian Kursus
                            </a>
                            <a href="supervision.php" class="btn btn-success text-start">
                                <i class="fas fa-users me-2"></i>Senarai Ahli
                            </a>
                            <a href="reports.php" class="btn btn-info text-start">
                                <i class="fas fa-file-alt me-2"></i>Buat Laporan
                            </a>
                            <a href="courses.php" class="btn btn-warning text-start">
                                <i class="fas fa-book me-2"></i>Kursus Terkini
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-user me-2"></i>Ringkasan Profil
                    </div>
                    <div class="card-body text-center">
                        <img src="https://ui-avatars.com/api/?name=FATIN+NASIR&background=0C3C60&color=fff&size=100" alt="Profile" class="mb-3">
                        <h5>FATIN NASIR</h5>
                        <p class="text-muted mb-1">No. KP: 011207060910</p>
                        <p class="text-muted mb-2">No. Ahli: 123456ABC</p>
                        <span class="badge badge-role">Penyelia</span>
                        <hr>
                        <div class="d-grid gap-2">
                            <a href="profile.php" class="btn btn-outline-primary">Lihat Profil Penuh</a>
                            <a href="kemaskini.php" class="btn btn-outline-secondary">Kemaskini Profil</a>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-server me-2"></i>Status Sistem
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Pengguna Dalam Talian:</span>
                            <strong>24</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Kursus Aktif:</span>
                            <strong>12</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Status Sistem:</span>
                            <span class="badge bg-success">Stabil</span>
                        </div>
                        <hr>
                        <small class="text-muted">Kemaskini Terakhir: 25/08/2023 10:45</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5">
        <div class="container text-center py-3">
            <small>© 2023 e-Kursus ALPHA. Hak Cipta Terpelihara. | <a href="#" class="text-light">Dasar Privasi</a> | <a href="#" class="text-light">Terma Penggunaan</a></small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dashboard-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded for Penyelia role');
            
            // Enable tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Update current date
            const now = new Date();
            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            const currentDate = now.toLocaleDateString('ms-MY', options);
            document.getElementById('current-date').textContent = currentDate;
        });
    </script>
</body>
</html>