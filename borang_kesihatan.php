
<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php'; // Database connection

// Add this after your database connection
$tableCheck = $conn->query("SELECT 1 FROM borang_kesihatan LIMIT 1");
if ($tableCheck === FALSE) {
    die("Error: borang_kesihatan table doesn't exist. Please run the SQL script to create it.");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
date_default_timezone_set('Asia/Kuala_Lumpur');

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

// Get user data
$userData = [
    'nama' => '',
    'no_ahli' => '',
    'jantina' => '',
    'umur' => '',
    'no_kp' => ''
];

$sql = "SELECT m.nama, m.no_ahli, m.jantina, m.umur, u.no_kp
        FROM maklumat_diri m
        INNER JOIN users u ON m.user_id = u.id
        WHERE m.user_id = ? LIMIT 1";
// THIS IS FOR GET THE USER INFO MAKLUMAT DIRI 
    if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result(
        $userData['nama'],
        $userData['no_ahli'],
        $userData['jantina'],
        $userData['umur'],
        $userData['no_kp']
    );
    $stmt->fetch();
    $stmt->close();
} else {
    die("Database error: " . $conn->error);
}



// Check if health form already exists
$healthData = [];
$sql = "SELECT * FROM borang_kesihatan WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $healthData = $result->fetch_assoc();
}
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $merokok = trim($_POST['merokok'] ?? '');
    $alkohol = trim($_POST['alkohol'] ?? '');
    $dadah = trim($_POST['dadah'] ?? '');
    
    // Health conditions
    $asma = trim($_POST['asma'] ?? '');
    $masalah_jantung = trim($_POST['masalah_jantung'] ?? '');
    $diabetis = trim($_POST['diabetis'] ?? '');
    $psychiatric = trim($_POST['psychiatric'] ?? '');
    $pembedahan = trim($_POST['pembedahan'] ?? '');
    $epilepsy = trim($_POST['epilepsy'] ?? '');
    $tb = trim($_POST['tb'] ?? '');
    $gangguan_pendarahan = trim($_POST['gangguan_pendarahan'] ?? '');
    $kusta = trim($_POST['kusta'] ?? '');
    $rawatan_lama = trim($_POST['rawatan_lama'] ?? '');
    $penyakit_telinga = trim($_POST['penyakit_telinga'] ?? '');
    $darah_tinggi = trim($_POST['darah_tinggi'] ?? '');
    $polio = trim($_POST['polio'] ?? '');
    $penyakit_kelamin = trim($_POST['penyakit_kelamin'] ?? '');
    $lain_lain = trim($_POST['lain_lain'] ?? '');
    
    // Additional questions
    $hamil = trim($_POST['hamil'] ?? '');
    $sakit_dada_aktiviti = trim($_POST['sakit_dada_aktiviti'] ?? '');
    $kemalangan_menyelam = trim($_POST['kemalangan_menyelam'] ?? '');
    $hilang_kesedaran = trim($_POST['hilang_kesedaran'] ?? '');
    $masalah_tulang_sendi = trim($_POST['masalah_tulang_sendi'] ?? '');
    $ubat_jantung = trim($_POST['ubat_jantung'] ?? '');
    
    // Health measurements
    $tinggi_cm = filter_input(INPUT_POST, 'tinggi_cm', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $berat_kg = filter_input(INPUT_POST, 'berat_kg', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $tekanan_darah = trim($_POST['tekanan_darah'] ?? '');
    $gula_darah = trim($_POST['gula_darah'] ?? '');
    
    // Declaration
    $pengakuan = isset($_POST['pengakuan']) ? 1 : 0;
    $tarikh_pengisian = date('Y-m-d');
    
    // Calculate BMI
    $bmi = null;
    if ($tinggi_cm && $berat_kg) {
        $height_m = $tinggi_cm / 100;
        $bmi = $berat_kg / ($height_m * $height_m);
    }
    
    // Validate required fields
    if (empty($merokok)) $errors['merokok'] = "Sila pilih sama ada anda merokok atau tidak";
    if (empty($alkohol)) $errors['alkohol'] = "Sila pilih sama ada anda mengambil alkohol atau tidak";
    if (empty($dadah)) $errors['dadah'] = "Sila pilih sama ada anda mengambil dadah atau tidak";
    if (empty($hamil)) $errors['hamil'] = "Sila jawab soalan kehamilan";
    if (empty($sakit_dada_aktiviti)) $errors['sakit_dada_aktiviti'] = "Sila jawab soalan sakit dada";
    if (empty($kemalangan_menyelam)) $errors['kemalangan_menyelam'] = "Sila jawab soalan kemalangan menyelam";
    if (empty($hilang_kesedaran)) $errors['hilang_kesedaran'] = "Sila jawab soalan hilang kesedaran";
    if (empty($masalah_tulang_sendi)) $errors['masalah_tulang_sendi'] = "Sila jawab soalan masalah tulang/sendi";
    if (empty($ubat_jantung)) $errors['ubat_jantung'] = "Sila jawab soalan ubat jantung";
    if (empty($tinggi_cm)) $errors['tinggi_cm'] = "Sila masukkan ketinggian anda";
    if (empty($berat_kg)) $errors['berat_kg'] = "Sila masukkan berat anda";
    if (!$pengakuan) $errors['pengakuan'] = "Sila tandakan pengakuan";
    
    // If no errors, save to database
    if (empty($errors)) {
        if (empty($healthData)) {
            // Insert new record
            $sql = "INSERT INTO borang_kesihatan (
                user_id, merokok, alkohol, dadah, asma, masalah_jantung, diabetis, psychiatric,
                pembedahan, epilepsy, tb, gangguan_pendarahan, kusta, rawatan_lama, penyakit_telinga,
                darah_tinggi, polio, penyakit_kelamin, lain_lain, hamil, sakit_dada_aktiviti,
                kemalangan_menyelam, hilang_kesedaran, masalah_tulang_sendi, ubat_jantung,
                tinggi_cm, berat_kg, bmi, tekanan_darah, gula_darah, pengakuan, tarikh_pengisian
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isssssssssssssssssssssssssddsssss", 
                $userId, $merokok, $alkohol, $dadah, $asma, $masalah_jantung, $diabetis, $psychiatric,
                $pembedahan, $epilepsy, $tb, $gangguan_pendarahan, $kusta, $rawatan_lama, $penyakit_telinga,
                $darah_tinggi, $polio, $penyakit_kelamin, $lain_lain, $hamil, $sakit_dada_aktiviti,
                $kemalangan_menyelam, $hilang_kesedaran, $masalah_tulang_sendi, $ubat_jantung,
                $tinggi_cm, $berat_kg, $bmi, $tekanan_darah, $gula_darah, $pengakuan, $tarikh_pengisian
            );
        } else {
            // Update existing record
            $sql = "UPDATE borang_kesihatan SET 
                merokok = ?, alkohol = ?, dadah = ?, asma = ?, masalah_jantung = ?, diabetis = ?, psychiatric = ?,
                pembedahan = ?, epilepsy = ?, tb = ?, gangguan_pendarahan = ?, kusta = ?, rawatan_lama = ?, penyakit_telinga = ?,
                darah_tinggi = ?, polio = ?, penyakit_kelamin = ?, lain_lain = ?, hamil = ?, sakit_dada_aktiviti = ?,
                kemalangan_menyelam = ?, hilang_kesedaran = ?, masalah_tulang_sendi = ?, ubat_jantung = ?,
                tinggi_cm = ?, berat_kg = ?, bmi = ?, tekanan_darah = ?, gula_darah = ?, pengakuan = ?, tarikh_pengisian = ?
                WHERE user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssssssssssssssssddsssssi", 
                $merokok, $alkohol, $dadah, $asma, $masalah_jantung, $diabetis, $psychiatric,
                $pembedahan, $epilepsy, $tb, $gangguan_pendarahan, $kusta, $rawatan_lama, $penyakit_telinga,
                $darah_tinggi, $polio, $penyakit_kelamin, $lain_lain, $hamil, $sakit_dada_aktiviti,
                $kemalangan_menyelam, $hilang_kesedaran, $masalah_tulang_sendi, $ubat_jantung,
                $tinggi_cm, $berat_kg, $bmi, $tekanan_darah, $gula_darah, $pengakuan, $tarikh_pengisian, $userId
            );
        }
        
        if ($stmt->execute()) {
            $success = "Borang kesihatan berjaya disimpan!";
            // Refresh health data
            $sql = "SELECT * FROM borang_kesihatan WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $healthData = $result->fetch_assoc();
            }
        } else {
            $errors['database'] = "Ralat sistem: " . $stmt->error;
            // Add detailed error info for debugging
            error_log("SQL Error: " . $stmt->error);
            error_log("SQL Query: " . $sql);
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Kesihatan - eCourses APM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #1f4988ff 0%, #071933ff 100%);
            color: white;
            padding: 25px 0;
            border-bottom: 5px solid #ffc107;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .logo i {
            font-size: 40px;
            color: #0d6efd;
        }
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #0d6efd;
        }
        .form-header {
            color: #0d6efd;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .form-header i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .btn-submit {
            background: linear-gradient(135deg, #234b86ff 0%, #0a58ca 100%);
            color: white;
            padding: 12px 30px;
            font-weight: bold;
            border: none;
            border-radius: 50px;
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(13, 110, 253, 0.4);
        }
        .health-table {
            width: 100%;
            border-collapse: collapse;
        }
        .health-table th, .health-table td {
            border: 1px solid #2367aaff;
            padding: 12px;
            text-align: left;
        }
        .health-table th {
            background-color: #93c0f3ff;
            color: #11202eff,center;
            font-weight: 700;
        }
        .health-table tr:nth-child(even) {
            background-color: #e6e5e5ff;
        }
        .health-table tr:hover {
            background-color: #9fb8daff;
        }
        .progress-container {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .progress {
            height: 20px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .footer {
            background-color: #212529;
            color: white;
            padding: 20px 0;
            margin-top: 40px;
            text-align: center;
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        }
        .form-navigation {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        @media (max-width: 768px) {
            .form-navigation {
                position: static;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header text-center">
        <div class="logo-container">
            <div class="logo">
            <i class="fas fa-heartbeat"></i>
            </div>
            <h1>JABATAN PERTAHANAN AWAM MALAYSIA</h1>
        </div>
        <img src="assets/img/logo_apm.jpeg" alt="ALPHA Logo" class="profile-logo" style="width:80px;margin-bottom:15px;">
        <h2>BORANG PERAKUAN KESIHATAN</h2>
       
        <p>Sila isi maklumat kesihatan dengan lengkap dan tepat</p>
    </div>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0C3C60;">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-graduation-cap me-2"></i>e-Kursus ALPHA</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fa fa-house me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="kemaskini.php"><i class="fa fa-user-pen me-1"></i>Maklumat Diri</a></li>
        <li class="nav-item"><a class="nav-link" href="courses.php"><i class="fa fa-list-ul me-1"></i>Senarai Kursus</a></li>
        <li class="nav-item"><a class="nav-link" href="apply_status.php"><i class="fa fa-clipboard-check me-1"></i>Status Permohonan</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-sm btn-outline-light" href="logout.php"><i class="fa fa-right-from-bracket me-1"></i>Log Keluar</a>
      </div>
    </div>
  </div>
</nav>

    <div class="container py-5">
        <!-- Progress Section -->
        <div class="progress-container">
            <h4 class="mb-3">Kemajuan Pengisian Borang</h4>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <p class="text-center">0% selesai</p>
        </div>

        <div class="row">
            <!-- Navigation -->
             
            <div class="col-lg-3">
                <div class="form-navigation">
                    <div class="list-group">
                        <a href="#personal-info" class="list-group-item list-group-item-action">Maklumat Peribadi</a>
                        <a href="#personal-history" class="list-group-item list-group-item-action">Sejarah Peribadi</a>
                        <a href="#health-history" class="list-group-item list-group-item-action">Sejarah Kesihatan</a>
                        <a href="#additional-questions" class="list-group-item list-group-item-action">Soalan Tambahan</a>
                        <a href="#health-measurements" class="list-group-item list-group-item-action">Pengukuran Kesihatan</a>
                        <a href="#declaration" class="list-group-item list-group-item-action">Pengakuan</a>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="col-lg-9">
            <div class="form-container p-4">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['database']; ?></div>
                <?php endif; ?>
        
                <form id="borangKesihatanForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- Maklumat Peribadi -->
                        <!-- Maklumat Peribadi -->
                        <div class="form-section" id="personal-info">
                            <h4 class="form-header"><i class="fas fa-user"></i> MAKLUMAT PERIBADI</h4>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Nama Penuh</label>
                                    <input type="text" class="form-control" id="nama" name="nama"
                                    value="<?= htmlspecialchars($userData['nama'] ?? ''); ?>" readonly aria-readonly="true"
                                    title="Nama dikunci dan tidak boleh diubah">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">No. Ahli</label>
                                    <input type="text" class="form-control" id="no_ahli" name="no_ahli"
                                    value="<?= htmlspecialchars($userData['no_ahli'] ?? ''); ?>" readonly aria-readonly="true"
                                    title="No Ahli dikunci dan tidak boleh diubah">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Umur
                                    
                                    </label>
                                    <input type="text" class="form-control" id="umur" name="umur"
                                    value="<?= htmlspecialchars($userData['umur'] ?? ''); ?>" readonly aria-readonly="true"
                                    title="Umur dikunci dan tidak boleh diubah">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jantina</label>
                                    <input type="text" class="form-control" id="jantina" name="jantina"
                                    value="<?= htmlspecialchars($userData['jantina'] ?? ''); ?>" readonly aria-readonly="true"
                                    title="Jantina dikunci dan tidak boleh diubah">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. Kad Pengenalan</label>
                                    <input type="text" class="form-control" id="no_kp" name="no_kp"
                                    value="<?= htmlspecialchars($userData['no_kp'] ?? ''); ?>" readonly aria-readonly="true"
                                    title="No kad pengenalan dikunci dan tidak boleh diubah">
                                </div>
                            </div>
                        </div>

                        <!-- Sejarah Peribadi -->
                        <div class="form-section" id="personal-history">
                            <h4 class="form-header"><i class="fas fa-history"></i> SEJARAH PERIBADI</h4>
                            <!-- Fix the radio button structure -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label required-field d-block">Merokok</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="merokok" id="merokok_ya" value="YA" <?php echo (isset($healthData['merokok']) && $healthData['merokok'] == 'YA') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="merokok_ya">YA</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="merokok" id="merokok_tidak" value="TIDAK" <?php echo (isset($healthData['merokok']) && $healthData['merokok'] == 'TIDAK') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="merokok_tidak">TIDAK</label>
                                </div>
                                <?php if (isset($errors['merokok'])): ?>
                                    <div class="text-danger"><?php echo $errors['merokok']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label required-field d-block">Alkohol</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="alkohol" id="alkohol_ya" value="YA" <?php echo (isset($healthData['alkohol']) && $healthData['alkohol'] == 'YA') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="alkohol_ya">YA</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="alkohol" id="alkohol_tidak" value="TIDAK" <?php echo (isset($healthData['alkohol']) && $healthData['alkohol'] == 'TIDAK') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="alkohol_tidak">TIDAK</label>
                                </div>
                                <?php if (isset($errors['alkohol'])): ?>
                                    <div class="text-danger"><?php echo $errors['alkohol']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label required-field d-block">Dadah</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="dadah" id="dadah_ya" value="YA" <?php echo (isset($healthData['dadah']) && $healthData['dadah'] == 'YA') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="dadah_ya">YA</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="dadah" id="dadah_tidak" value="TIDAK" <?php echo (isset($healthData['dadah']) && $healthData['dadah'] == 'TIDAK') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="dadah_tidak">TIDAK</label>
                                </div>
                                <?php if (isset($errors['dadah'])): ?>
                                    <div class="text-danger"><?php echo $errors['dadah']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        </div>

                        <!-- Sejarah Kesihatan -->
                        <div class="form-section" id="health-history">
                            <h4 class="form-header"><i class="fas fa-stethoscope"></i> SEJARAH KESIHATAN</h4>
                            <p class="fw-bold">Sila tandakan YA atau TIDAK untuk setiap keadaan berikut:</p>
                            
                            <table class="health-table mb-4">
                                <tr>
                                    <th>Keadaan Kesihatan</th>
                                    <th width="100">YA</th>
                                    <th width="100">TIDAK</th>
                                </tr>
                                <tr>
                                    <td>Asma</td>
                                    <td><input type="radio" name="asma" value="YA" class="form-check-input" required></td>
                                    <td><input type="radio" name="asma" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Masalah Jantung</td>
                                    <td><input type="radio" name="masalah_jantung" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="masalah_jantung" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Diabetis</td>
                                    <td><input type="radio" name="diabetis" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="diabetis" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Masalah Psychiatric</td>
                                    <td><input type="radio" name="psychiatric" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="psychiatric" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Menjalani Pembedahan</td>
                                    <td><input type="radio" name="pembedahan" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="pembedahan" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Epilepsy (Sawan)</td>
                                    <td><input type="radio" name="epilepsy" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="epilepsy" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Tibi (TB)</td>
                                    <td><input type="radio" name="tb" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="tb" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Gangguan Pendarahan</td>
                                    <td><input type="radio" name="gangguan_pendarahan" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="gangguan_pendarahan" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Kusta</td>
                                    <td><input type="radio" name="kusta" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="kusta" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Sedang Dalam Rawatan</td>
                                    <td><input type="radio" name="rawatan_lama" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="rawatan_lama" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Penyakit Telinga</td>
                                    <td><input type="radio" name="penyakit_telinga" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="penyakit_telinga" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Darah Tinggi</td>
                                    <td><input type="radio" name="darah_tinggi" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="darah_tinggi" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Polio</td>
                                    <td><input type="radio" name="polio" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="polio" value="TIDAK" class="form-check-input"></td>
                                </tr>
                                <tr>
                                    <td>Penyakit Kelamin</td>
                                    <td><input type="radio" name="penyakit_kelamin" value="YA" class="form-check-input"></td>
                                    <td><input type="radio" name="penyakit_kelamin" value="TIDAK" class="form-check-input"></td>
                                </tr>
                            </table>
                            
                            <div class="mb-3">
                                <label class="form-label">Lain-lain Penyakit (Jika ada)</label>
                                <textarea class="form-control" name="lain_lain" rows="3" placeholder="Sila nyatakan jika ada penyakit lain..."></textarea>
                            </div>
                        </div>

                        <!-- Soalan Kesihatan Tambahan -->
                        <div class="form-section" id="additional-questions">
                            <h4 class="form-header"><i class="fas fa-question-circle"></i> SOALAN KESIHATAN TAMBAHAN</h4>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah anda hamil, atau dalam proses kehamilan?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hamil" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hamil" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah anda merasa sakit di dada apabila melakukan aktiviti fizikal?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sakit_dada_aktiviti" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="sakit_dada_aktiviti" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah anda pernah terlibat dengan kemalangan menyelam atau penyakit penyahmampatan?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="kemalangan_menyelam" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="kemalangan_menyelam" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah anda pernah hilang pengawalan diri disebabkan kepeningan atau hilang kesedaran?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hilang_kesedaran" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hilang_kesedaran" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah anda mempunyai masalah tulang atau sendi (contohnya tulang belakang, lutut atau pinggul) yang akan bertambah teruk dengan melakukan aktiviti berat?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="masalah_tulang_sendi" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="masalah_tulang_sendi" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required-field">Adakah doktor anda mempreskripsikan ubat dadah (contohnya 'waterpills') untuk rawatan darah dan jantung anda sekarang?</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ubat_jantung" value="YA" required>
                                        <label class="form-check-label">YA</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ubat_jantung" value="TIDAK">
                                        <label class="form-check-label">TIDAK</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pengukuran Kesihatan -->
                        <div class="form-section" id="health-measurements">
                            <h4 class="form-header"><i class="fas fa-weight"></i> PENGUKURAN INDEKS JISIM BADAN (BMI)</h4>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required-field">Tinggi (cm)</label>
                                    <input type="number" class="form-control" name="tinggi_cm" required min="100" max="250">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required-field">Berat (kg)</label>
                                    <input type="number" step="0.1" class="form-control" name="berat_kg" required min="30" max="200">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">BMI</label>
                                    <input type="text" class="form-control" name="bmi" readonly>
                                    <small class="form-text text-muted">Dikira secara automatik</small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tekanan Darah</label>
                                    <input type="text" class="form-control" name="tekanan_darah" placeholder="Contoh: 120/80">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kandungan Gula dalam Darah</label>
                                    <input type="text" class="form-control" name="gula_darah" placeholder="Contoh: 5.5 mmol/L">
                                </div>
                            </div>
                        </div>

                        <!-- Pengakuan -->
                        <div class="form-section" id="declaration">
                            <h4 class="form-header"><i class="fas fa-file-signature"></i> PENGAKUAN</h4>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pengakuan" required>
                                    <label class="form-check-label" for="pengakuan">
                                        Dengan ini, saya mengaku bahawa semua maklumat yang diberikan adalah benar. 
                                        Saya tidak akan membuat sebarang tuntutan kepada Jabatan Pertahanan Awam Malaysia 
                                        atau Pusat Latihan Pertahanan Awam atau jurulatih atau Fasilitator jika berlaku 
                                        sebarang kemalangan disebabkan faktor kesihatan saya sepanjang kursus ini dan 
                                        saya bersetuju untuk bertanggungjawab mengenai kegagalan saya untuk mendedahkan 
                                        apa-apa keadaan kesihatan yang sedia ada atau yang lalu.
                                    </label>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Tarikh</label>
                                    <input type="date" class="form-control" name="tarikh_pengisian" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="button" class="btn btn-secondary me-md-2"><i class="fas fa-times"></i> Batal</button>
                            <button type="submit" class="btn btn-primary btn-submit"><i class="fas fa-paper-plane"></i> Hantar Borang</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© 2025 Jabatan Pertahanan Awam Malaysia. Semua hak cipta terpelihara.</p>
        <p>Line Bantuan: 03-8888 9999 | Emel: bantuan@pertahananawam.gov.my</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto calculate BMI
        document.querySelector('input[name="tinggi_cm"]').addEventListener('input', calculateBMI);
        document.querySelector('input[name="berat_kg"]').addEventListener('input', calculateBMI);
        
        function calculateBMI() {
            const height = parseFloat(document.querySelector('input[name="tinggi_cm"]').value) / 100; // convert to meters
            const weight = parseFloat(document.querySelector('input[name="berat_kg"]').value);
            
            if (height && weight) {
                const bmi = weight / (height * height);
                document.querySelector('input[name="bmi"]').value = bmi.toFixed(2);
                
                // Update progress
                updateProgress();
            }
        }
        
        // Form submission
            document.getElementById('borangKesihatanForm').addEventListener('submit', function(e) {
                // Only validate the form, don't prevent default submission
                if (!this.checkValidity()) {
                    e.preventDefault();
                    alert('Sila isi semua maklumat yang diperlukan.');
                }
                // Let the form submit normally if validation passes
            });
        
        // Set today's date as default
        document.querySelector('input[name="tarikh_pengisian"]').valueAsDate = new Date();
        
        // Update progress bar as user fills the form
        function updateProgress() {
            const form = document.getElementById('borangKesihatanForm');
            const totalFields = form.querySelectorAll('input[required], select[required], textarea[required]').length;
            const filledFields = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]')).filter(field => {
                if (field.type === 'radio') {
                    const name = field.getAttribute('name');
                    return form.querySelector(`input[name="${name}"]:checked`);
                }
                return field.value;
            }).length;
            
            const progress = Math.min(100, Math.round((filledFields / totalFields) * 100));
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.width = `${progress}%`;
            progressBar.setAttribute('aria-valuenow', progress);
            progressBar.textContent = `${progress}%`;
            
            document.querySelector('.progress-container p').textContent = `${progress}% selesai`;
        }
        
        // Add event listeners to update progress
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', updateProgress);
            field.addEventListener('change', updateProgress);
        });
        
        // Initialize progress
        updateProgress();
    </script>
</body>
</html>