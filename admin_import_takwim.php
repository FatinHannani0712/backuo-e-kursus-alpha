<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['email'];
$userId = $_SESSION['user_id'];

// Get import history
$history_sql = "SELECT * FROM takwim_import_log ORDER BY imported_at DESC LIMIT 10";
$history_stmt = $pdo->query($history_sql);
$import_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current TAKWIM statistics
$stats_sql = "SELECT 
                tahun,
                semester,
                COUNT(*) as total_courses,
                SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif_courses
              FROM takwim_master 
              GROUP BY tahun, semester 
              ORDER BY tahun DESC, semester DESC";
$stats_stmt = $pdo->query($stats_sql);
$takwim_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import TAKWIM - Admin e-Kursus ALPHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --apm-blue: #0056b3;
            --apm-dark-blue: #003d82;
            --apm-gold: #ffc107;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .upload-area {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 3px dashed white;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            color: white;
            margin: 30px 0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            transform: scale(1.02);
            border-color: var(--apm-gold);
        }
        
        .upload-area i {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .history-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-upload {
            background: var(--apm-gold);
            color: var(--apm-dark-blue);
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 25px;
            border: none;
            font-size: 1.1rem;
        }
        
        .btn-upload:hover {
            background: #ffb300;
            transform: scale(1.05);
        }
        
        .file-input {
            display: none;
        }
    </style>
</head>
<body>

<div class="main-container">
    
    <!-- HEADER -->
    <div class="header-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-cloud-upload-alt text-primary"></i> Import TAKWIM Kursus</h2>
                <p class="text-muted mb-0">Upload jadual TAKWIM tahunan dari Excel ke sistem</p>
            </div>
            <div>
                <a href="admin_view_takwim.php" class="btn btn-outline-primary">
                    <i class="fas fa-list"></i> Lihat TAKWIM
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- UPLOAD AREA -->
    <div class="stats-card">
        <h5><i class="fas fa-file-upload"></i> Upload File TAKWIM</h5>
        <p class="text-muted">Format yang disokong: .xlsx, .xls (Excel)</p>
        
        <form action="process_import_takwim.php" method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <h4>Klik untuk pilih fail Excel</h4>
                <p class="mb-0">atau seret fail ke sini</p>
                <p class="mt-3" id="fileName" style="display: none;"></p>
            </div>
            
            <input type="file" 
                   id="fileInput" 
                   name="takwim_file" 
                   accept=".xlsx,.xls" 
                   class="file-input" 
                   required>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select" required>
                        <option value="">Pilih Tahun</option>
                        <?php for($y = 2025; $y <= 2030; $y++): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select" required>
                        <option value="">Pilih Semester</option>
                        <option value="JAN-JUN">Januari - Jun</option>
                        <option value="JULAI-DIS">Julai - Disember</option>
                    </select>
                </div>
            </div>
            
            <div class="text-center">
                <button type="submit" class="btn btn-upload">
                    <i class="fas fa-upload"></i> Mula Import TAKWIM
                </button>
            </div>
        </form>
    </div>

    <!-- CURRENT TAKWIM STATS -->
    <div class="stats-card">
        <h5><i class="fas fa-chart-bar"></i> Statistik TAKWIM Semasa</h5>
        
        <?php if (!empty($takwim_stats)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tahun</th>
                            <th>Semester</th>
                            <th>Jumlah Kursus</th>
                            <th>Aktif</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($takwim_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo $stat['tahun']; ?></strong></td>
                                <td><?php echo $stat['semester']; ?></td>
                                <td><span class="badge bg-primary"><?php echo $stat['total_courses']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $stat['aktif_courses']; ?></span></td>
                                <td>
                                    <a href="admin_view_takwim.php?tahun=<?php echo $stat['tahun']; ?>&semester=<?php echo urlencode($stat['semester']); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tiada TAKWIM dalam sistem. Sila import fail Excel.
            </div>
        <?php endif; ?>
    </div>

    <!-- IMPORT HISTORY -->
    <div class="history-table">
        <h5><i class="fas fa-history"></i> Sejarah Import</h5>
        
        <?php if (!empty($import_history)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Fail</th>
                            <th>Tahun</th>
                            <th>Semester</th>
                            <th>Berjaya</th>
                            <th>Gagal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_history as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['imported_at'])); ?></td>
                                <td><small><?php echo htmlspecialchars($log['filename']); ?></small></td>
                                <td><?php echo $log['tahun']; ?></td>
                                <td><?php echo $log['semester']; ?></td>
                                <td><span class="badge bg-success"><?php echo $log['success_count']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $log['error_count']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Tiada sejarah import lagi.</p>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Show selected filename
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        const fileNameDisplay = document.getElementById('fileName');
        if (fileName) {
            fileNameDisplay.textContent = '📄 ' + fileName;
            fileNameDisplay.style.display = 'block';
        }
    });

    // Drag and drop support
    const uploadArea = document.querySelector('.upload-area');
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--apm-gold)';
    });
    
    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'white';
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'white';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('fileInput').files = files;
            document.getElementById('fileName').textContent = '📄 ' + files[0].name;
            document.getElementById('fileName').style.display = 'block';
        }
    });
</script>

</body>
</html>