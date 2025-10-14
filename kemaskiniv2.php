<?php
// kemaskini.php
session_start();
require_once 'config.php'; // $conn (mysqli)

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';
$userData = [];

// List of Malaysian states
$negeri_options = [
    'johor' => 'Johor',
    'kedah' => 'Kedah',
    'kelantan' => 'Kelantan',
    'melaka' => 'Melaka',
    'negeri sembilan' => 'Negeri Sembilan',
    'pahang' => 'Pahang',
    'perak' => 'Perak',
    'perlis' => 'Perlis',
    'pulau pinang' => 'Pulau Pinang',
    'sabah' => 'Sabah',
    'sarawak' => 'Sarawak',
    'selangor' => 'Selangor',
    'terengganu' => 'Terengganu',
    'wilayah persekutuan kuala lumpur' => 'Wilayah Persekutuan Kuala Lumpur',
    'wilayah persekutuan labuan' => 'Wilayah Persekutuan Labuan',
    'wilayah persekutuan putrajaya' => 'Wilayah Persekutuan Putrajaya'
];

// --- Get user data + maklumat_diri (LEFT JOIN) ---
$sqlSelect = "
SELECT 
  u.id AS user_id,
  u.no_kp,
  md.id AS md_id,
  md.nama,
  md.no_ahli,
  md.status,
  md.jantina,
  md.umur,
  md.tarikh_keahlian,
  md.jenis_keahlian,
  md.pangkat,
  md.alamat,
  md.tel_rumah,
  md.tel_pejabat,
  md.jawatan,
  md.majikan,
  md.kelulusan,
  md.nama_kecemasan,
  md.alamat_kecemasan,
  md.telefon_kecemasan,
  md.tandatangan,
  md.tarikh_akuan
FROM users u
LEFT JOIN maklumat_diri md ON md.user_id = u.id
WHERE u.id = ?
LIMIT 1
";
$stmt = $conn->prepare($sqlSelect);
if (!$stmt) { die("Prepare failed: " . $conn->error); }
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res->fetch_assoc() ?: [];
$stmt->close();

// Parse existing combined address into components
$alamat_components = ['', '', '', '']; // alamat, poskod, bandar, negeri
if (!empty($userData['alamat'])) {
    $alamat_components = explode(', ', $userData['alamat']);
    // Ensure we have exactly 4 components
    $alamat_components = array_pad($alamat_components, 4, '');
}

// --- Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lock no_kp
    $no_kp = $userData['no_kp'] ?? '';

    // Sanitize inputs
    $nama            = trim(filter_input(INPUT_POST, 'nama', FILTER_SANITIZE_SPECIAL_CHARS));
    $no_ahli         = trim(filter_input(INPUT_POST, 'no_ahli', FILTER_SANITIZE_SPECIAL_CHARS));
    $status          = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS));
    $jantina         = trim(filter_input(INPUT_POST, 'jantina', FILTER_SANITIZE_SPECIAL_CHARS));
    $umur_raw        = filter_input(INPUT_POST, 'umur', FILTER_SANITIZE_NUMBER_INT);
    $tarikh_keahlian = trim(filter_input(INPUT_POST, 'tarikh_keahlian', FILTER_SANITIZE_SPECIAL_CHARS));
    $jenis_keahlian  = trim(filter_input(INPUT_POST, 'pnpa_pdpa', FILTER_SANITIZE_SPECIAL_CHARS));
    $pangkat         = trim(filter_input(INPUT_POST, 'pangkat', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Address components
    $alamat          = trim(filter_input(INPUT_POST, 'alamat', FILTER_SANITIZE_SPECIAL_CHARS));
    $poskod          = trim(filter_input(INPUT_POST, 'poskod', FILTER_SANITIZE_SPECIAL_CHARS));
    $bandar          = trim(filter_input(INPUT_POST, 'bandar', FILTER_SANITIZE_SPECIAL_CHARS));
    $negeri          = trim(filter_input(INPUT_POST, 'negeri', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Combine address components
    $full_alamat = implode(', ', array_filter([$alamat, $poskod, $bandar, $negeri]));
    
    $tel_rumah       = trim(filter_input(INPUT_POST, 'no_tel_rumah', FILTER_SANITIZE_SPECIAL_CHARS));
    $tel_pejabat     = trim(filter_input(INPUT_POST, 'no_tel_pejabat', FILTER_SANITIZE_SPECIAL_CHARS));
    $jawatan         = trim(filter_input(INPUT_POST, 'jawatan', FILTER_SANITIZE_SPECIAL_CHARS));
    $majikan         = trim(filter_input(INPUT_POST, 'majikan', FILTER_SANITIZE_SPECIAL_CHARS));
    $kelulusan       = trim(filter_input(INPUT_POST, 'kelulusan', FILTER_SANITIZE_SPECIAL_CHARS));
    $nama_kecemasan  = trim(filter_input(INPUT_POST, 'nama_waris', FILTER_SANITIZE_SPECIAL_CHARS));
    $alamat_kecemasan= trim(filter_input(INPUT_POST, 'alamat_waris', FILTER_SANITIZE_SPECIAL_CHARS));
    $telefon_kecemasan = trim(filter_input(INPUT_POST, 'no_tel_waris', FILTER_SANITIZE_SPECIAL_CHARS));

    // Checkbox pengakuan (WAJIB)
    $pengakuan = isset($_POST['pengakuan']) ? 'YA' : '';
    $tarikh_akuan = trim(filter_input(INPUT_POST, 'tarikh_akuan', FILTER_SANITIZE_SPECIAL_CHARS));
    
    // Auto-fill date if pengakuan is checked
    if ($pengakuan === 'YA' && empty($tarikh_akuan)) {
        $tarikh_akuan = date('Y-m-d');
    }

    $umur = ($umur_raw !== null && $umur_raw !== '') ? (int)$umur_raw : null;

    // Enhanced Validation
    if (empty($nama)) {
        $errors['nama'] = "Nama penuh diperlukan";
    } elseif (strlen($nama) > 100) {
        $errors['nama'] = "Nama terlalu panjang (maksimum 100 aksara)";
    }

    if (!empty($tel_rumah) && !preg_match('/^[0-9+\(\)\s-]{6,20}$/', $tel_rumah)) {
        $errors['no_tel_rumah'] = "Format telefon rumah tidak sah";
    }

    if (!empty($tel_pejabat) && !preg_match('/^[0-9+\(\)\s-]{6,20}$/', $tel_pejabat)) {
        $errors['no_tel_pejabat'] = "Format telefon pejabat tidak sah";
    }

    if (!empty($telefon_kecemasan) && !preg_match('/^[0-9+\(\)\s-]{6,20}$/', $telefon_kecemasan)) {
        $errors['no_tel_waris'] = "Format telefon waris tidak sah";
    }

    // Address validation
    if (empty($negeri)) {
        $errors['negeri'] = "Sila pilih negeri";
    }
    
    if (!empty($poskod) && !preg_match('/^\d{5}$/', $poskod)) {
        $errors['poskod'] = "Poskod mesti 5 digit";
    }

    if ($pengakuan !== 'YA') {
        $errors['pengakuan'] = "Sila tanda pengesahan maklumat.";
    }

    if (empty($errors)) {
        // Check if maklumat_diri exists
        $exists = 0;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM maklumat_diri WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($exists);
        $stmt->fetch();
        $stmt->close();

        if ($exists) {
            // UPDATE
            $sqlUpdate = "
                UPDATE maklumat_diri SET
                    nama = ?,
                    no_ahli = ?,
                    status = ?,
                    jantina = ?,
                    umur = ?,
                    tarikh_keahlian = ?,
                    jenis_keahlian = ?,
                    pangkat = ?,
                    alamat = ?,
                    tel_rumah = ?,
                    tel_pejabat = ?,
                    jawatan = ?,
                    majikan = ?,
                    kelulusan = ?,
                    nama_kecemasan = ?,
                    alamat_kecemasan = ?,
                    telefon_kecemasan = ?,
                    tandatangan = ?,
                    tarikh_akuan = ?
                WHERE user_id = ?
            ";
            $stmt = $conn->prepare($sqlUpdate);
            if (!$stmt) { 
                $errors['database'] = "Ralat sediakan query: " . $conn->error; 
            } else {
                $stmt->bind_param(
                    "ssssisssssssssssssi",
                    $nama,
                    $no_ahli,
                    $status,
                    $jantina,
                    $umur,
                    $tarikh_keahlian,
                    $jenis_keahlian,
                    $pangkat,
                    $full_alamat,
                    $tel_rumah,
                    $tel_pejabat,
                    $jawatan,
                    $majikan,
                    $kelulusan,
                    $nama_kecemasan,
                    $alamat_kecemasan,
                    $telefon_kecemasan,
                    $pengakuan,
                    $tarikh_akuan,
                    $userId
                );
                if ($stmt->execute()) {
                    $success = "Maklumat berjaya dikemaskini!";
                    // Store success in session to persist through redirect
                    $_SESSION['success_message'] = $success;
                    header("Location: kemaskini.php");
                    exit();
                } else {
                    $errors['database'] = "Gagal mengemaskini: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // INSERT
            $sqlInsert = "
                INSERT INTO maklumat_diri
                    (user_id, nama, no_ahli, status, jantina, umur, tarikh_keahlian, jenis_keahlian, pangkat,
                     alamat, tel_rumah, tel_pejabat, jawatan, majikan, kelulusan,
                     nama_kecemasan, alamat_kecemasan, telefon_kecemasan, tandatangan, tarikh_akuan)
                VALUES
                    (?,?,?,?,?,?,?,?,?,
                     ?,?,?,?,?,?,
                     ?,?,?,?,?)
            ";
            $stmt = $conn->prepare($sqlInsert);
            if (!$stmt) { 
                $errors['database'] = "Ralat sediakan query: " . $conn->error; 
            } else {
                $stmt->bind_param(
                    "issssissssssssssssss",
                    $userId,
                    $nama,
                    $no_ahli,
                    $status,
                    $jantina,
                    $umur,
                    $tarikh_keahlian,
                    $jenis_keahlian,
                    $pangkat,
                    $full_alamat,
                    $tel_rumah,
                    $tel_pejabat,
                    $jawatan,
                    $majikan,
                    $kelulusan,
                    $nama_kecemasan,
                    $alamat_kecemasan,
                    $telefon_kecemasan,
                    $pengakuan,
                    $tarikh_akuan
                );
                if ($stmt->execute()) {
                    $success = "Maklumat berjaya disimpan!";
                    $_SESSION['success_message'] = $success;
                    header("Location: kemaskini.php");
                    exit();
                } else {
                    $errors['database'] = "Gagal menyimpan: " . $stmt->error;
                }
                $stmt->close();
            }
        }

        // Refresh data if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare($sqlSelect);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $userData = $res->fetch_assoc() ?: $userData;
            $stmt->close();
        }
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}


?>

<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>Kemaskini Maklumat Diri | e-Kursus ALPHA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root { --primary-color:#0C3C60; }
    body { font-family:'Segoe UI',sans-serif; background:#f5f5f5; }
    .navbar-brand { font-weight:600; letter-spacing:.3px; }
    .profile-container { max-width:1000px; width:100%; margin:30px auto; }
    .profile-card { border-radius:10px; box-shadow:0 5px 20px rgba(0,0,0,.1); overflow:hidden; background:#fff; }
    .profile-header { background:var(--primary-color); color:#fff; padding:20px; text-align:center; }
    .profile-body { padding:30px; }
    .form-control { padding:12px 15px; border-radius:6px; }
    .btn-save { background:var(--primary-color); border:none; padding:12px; font-weight:600; }
    .btn-save:hover { background:#0a2e4a; }
    .input-group-text { background:var(--primary-color); color:#fff; border:none; }
    .section-title { color:var(--primary-color); border-bottom:2px solid var(--primary-color); padding-bottom:8px; margin:25px 0 15px; font-weight:600; }
    .form-section { background:#f9f9f9; padding:20px; border-radius:8px; margin-bottom:20px; border-left:4px solid var(--primary-color); }
    .nav-tabs .nav-link.active { background-color:var(--primary-color); color:#fff; border-color:var(--primary-color); }
    .nav-tabs .nav-link { color:var(--primary-color); }
    .two-columns { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .one-columns { display:grid; grid-template-columns:1fr; gap:20px; }
    input[readonly] { background:#e9ecef; cursor:not-allowed; }
    .is-invalid { border-color:#dc3545 !important; }
    .invalid-feedback { display:none; color:#dc3545; font-size:.875em; }
    .is-invalid ~ .invalid-feedback { display:block; }
    .tab-error { position:relative; }
    .tab-error::after { content:''; position:absolute; top:5px; right:5px; width:8px; height:8px; background:#dc3545; border-radius:50%; }
    @media (max-width:768px){ .two-columns{grid-template-columns:1fr;} }
  </style>
</head>
<body>

<!-- NAVBAR -->
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

<div class="container profile-container">
  <div class="profile-card">
    <div class="profile-header">
      <img src="assets/img/logo_apm.jpeg" alt="ALPHA Logo" class="profile-logo" style="width:80px;margin-bottom:15px;">
      <h3><i class="fas fa-user-circle"></i> MAKLUMAT DIRI</h3>
    </div>

    <div class="profile-body">
      <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
          <?= htmlspecialchars($success); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
          <?= htmlspecialchars($errors['database']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($errors['nama']) ? 'tab-error' : '' ?> active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
            <i class="fas fa-user me-2"></i>Peribadi
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($errors['no_tel_rumah']) || isset($errors['no_tel_pejabat']) || isset($errors['negeri']) || isset($errors['poskod']) ? 'tab-error' : '' ?>" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
            <i class="fas fa-address-book me-2"></i>Perhubungan
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="employment-tab" data-bs-toggle="tab" data-bs-target="#employment" type="button" role="tab">
            <i class="fas fa-briefcase me-2"></i>Pekerjaan
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">
            <i class="fas fa-graduation-cap me-2"></i>Akademik
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= isset($errors['pengakuan']) || isset($errors['no_tel_waris']) ? 'tab-error' : '' ?>" id="kin-tab" data-bs-toggle="tab" data-bs-target="#kin" type="button" role="tab">
            <i class="fas fa-users me-2"></i>Waris
          </button>
        </li>
      </ul>

      <form method="post" action="" id="profileForm">
        <div class="tab-content" id="profileTabsContent">
          <!-- Personal -->
          <div class="tab-pane fade show active" id="personal" role="tabpanel">
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-id-card me-2"></i>Maklumat Peribadi</h5>

              <div class="one-columns">
                <div class="mb-3">
                  <label for="no_kp" class="form-label">No. Kad Pengenalan</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                    <input type="text" class="form-control" id="no_kp" name="no_kp"
                           value="<?= htmlspecialchars($userData['no_kp'] ?? ''); ?>" readonly aria-readonly="true"
                           title="No. Kad Pengenalan dikunci dan tidak boleh diubah">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="nama" class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>" id="nama" name="nama"
                           value="<?= htmlspecialchars($userData['nama'] ?? ''); ?>" required maxlength="100">
                  </div>
                  <?php if (isset($errors['nama'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['nama']); ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="no_ahli" class="form-label">No. Ahli</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                    <input type="text" class="form-control" id="no_ahli" name="no_ahli"
                           value="<?= htmlspecialchars($userData['no_ahli'] ?? ''); ?>" maxlength="20">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="status" class="form-label">Status</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-heart"></i></span>
                    <select class="form-control" id="status" name="status">
                      <option value="Bujang"    <?= (($userData['status'] ?? '')==='Bujang')?'selected':''; ?>>Bujang</option>
                      <option value="Berkahwin" <?= (($userData['status'] ?? '')==='Berkahwin')?'selected':''; ?>>Berkahwin</option>
                      <option value="Duda"      <?= (($userData['status'] ?? '')==='Duda')?'selected':''; ?>>Duda</option>
                      <option value="Janda"     <?= (($userData['status'] ?? '')==='Janda')?'selected':''; ?>>Janda</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="jantina" class="form-label">Jantina</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                    <select class="form-control" id="jantina" name="jantina">
                      <option value="Lelaki"    <?= (($userData['jantina'] ?? '')==='Lelaki')?'selected':''; ?>>Lelaki</option>
                      <option value="Perempuan" <?= (($userData['jantina'] ?? '')==='Perempuan')?'selected':''; ?>>Perempuan</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="umur" class="form-label">Umur</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-birthday-cake"></i></span>
                    <input type="number" class="form-control" id="umur" name="umur" min="18" max="120"
                           value="<?= htmlspecialchars($userData['umur'] ?? ''); ?>">
                  </div>
                </div>
              </div>
            </div>

            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-users me-2"></i>Maklumat Keahlian</h5>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="tarikh_keahlian" class="form-label">Tarikh Keahlian</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    <input type="date" class="form-control" id="tarikh_keahlian" name="tarikh_keahlian"
                           value="<?= htmlspecialchars($userData['tarikh_keahlian'] ?? ''); ?>">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="pnpa_pdpa" class="form-label">Jenis Keahlian (PNPA/PDPA)</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-certificate"></i></span>
                    <select class="form-control" id="pnpa_pdpa" name="pnpa_pdpa">
                      <option value="PNPA" <?= (($userData['jenis_keahlian'] ?? '')==='PNPA')?'selected':''; ?>>PNPA</option>
                      <option value="PDPA" <?= (($userData['jenis_keahlian'] ?? '')==='PDPA')?'selected':''; ?>>PDPA</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label for="pangkat" class="form-label">Pangkat</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-star"></i></span>
                  <input type="text" class="form-control" id="pangkat" name="pangkat" maxlength="50"
                         value="<?= htmlspecialchars($userData['pangkat'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Contact -->
          <div class="tab-pane fade" id="contact" role="tabpanel">
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-map-marker-alt me-2"></i>Alamat</h5>

              <div class="mb-3">
                <label for="alamat" class="form-label">Alamat Penuh</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-home"></i></span>
                  <textarea class="form-control" id="alamat" name="alamat" rows="3" maxlength="255"><?= htmlspecialchars($alamat_components[0] ?? ''); ?></textarea>
                </div>
              </div>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="poskod" class="form-label">Poskod</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-mail-bulk"></i></span>
                    <input type="text" class="form-control <?= isset($errors['poskod']) ? 'is-invalid' : '' ?>" 
                           id="poskod" name="poskod" placeholder="Contoh: 12345"
                           value="<?= htmlspecialchars($alamat_components[1] ?? ''); ?>" maxlength="5">
                    <?php if (isset($errors['poskod'])): ?>
                      <div class="invalid-feedback"><?= htmlspecialchars($errors['poskod']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="bandar" class="form-label">Bandar</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-city"></i></span>
                    <input type="text" class="form-control" id="bandar" name="bandar"
                           value="<?= htmlspecialchars($alamat_components[2] ?? ''); ?>" maxlength="50">
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label for="negeri" class="form-label">Negeri</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-map"></i></span>
                  <select class="form-control <?= isset($errors['negeri']) ? 'is-invalid' : '' ?>" id="negeri" name="negeri">
                    <option value="">-- Sila Pilih --</option>
                    <?php foreach ($negeri_options as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value) ?>" 
                        <?= strtolower($alamat_components[3] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['negeri'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['negeri']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-phone-alt me-2"></i>Maklumat Perhubungan</h5>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="no_tel_rumah" class="form-label">No. Telefon (Rumah)</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="text" class="form-control <?= isset($errors['no_tel_rumah']) ? 'is-invalid' : '' ?>" 
                           id="no_tel_rumah" name="no_tel_rumah" placeholder="Contoh: 03-1234567"
                           value="<?= htmlspecialchars($userData['tel_rumah'] ?? ''); ?>" maxlength="20">
                  </div>
                  <?php if (isset($errors['no_tel_rumah'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['no_tel_rumah']); ?></div>
                  <?php endif; ?>
                </div>

                <div class="mb-3">
                  <label for="no_tel_pejabat" class="form-label">No. Telefon (Pejabat)</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                    <input type="text" class="form-control <?= isset($errors['no_tel_pejabat']) ? 'is-invalid' : '' ?>" 
                           id="no_tel_pejabat" name="no_tel_pejabat" placeholder="Contoh: 03-7654321"
                           value="<?= htmlspecialchars($userData['tel_pejabat'] ?? ''); ?>" maxlength="20">
                  </div>
                  <?php if (isset($errors['no_tel_pejabat'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['no_tel_pejabat']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Employment -->
          <div class="tab-pane fade" id="employment" role="tabpanel">
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-briefcase me-2"></i>Maklumat Pekerjaan</h5>

              <div class="mb-3">
                <label for="jawatan" class="form-label">Jawatan Sekarang</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                  <input type="text" class="form-control" id="jawatan" name="jawatan" maxlength="100"
                         value="<?= htmlspecialchars($userData['jawatan'] ?? ''); ?>">
                </div>
              </div>

              <div class="mb-3">
                <label for="majikan" class="form-label">Nama & Alamat Majikan</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-building"></i></span>
                  <textarea class="form-control" id="majikan" name="majikan" rows="3" maxlength="255"><?= htmlspecialchars($userData['majikan'] ?? ''); ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Academic -->
          <div class="tab-pane fade" id="academic" role="tabpanel">
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-graduation-cap me-2"></i>Maklumat Akademik</h5>

              <div class="mb-3">
                <label for="kelulusan" class="form-label">Kelulusan Tertinggi</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                  <input type="text" class="form-control" id="kelulusan" name="kelulusan" maxlength="100"
                         value="<?= htmlspecialchars($userData['kelulusan'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </div>

          <!-- Next of Kin & Pengesahan -->
          <div class="tab-pane fade" id="kin" role="tabpanel">
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-users me-2"></i>Maklumat Waris (Kecemasan)</h5>

              <div class="one-columns">
                <div class="mb-3">
                  <label for="nama_waris" class="form-label">Nama Waris</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="nama_waris" name="nama_waris" maxlength="100"
                           value="<?= htmlspecialchars($userData['nama_kecemasan'] ?? ''); ?>">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="no_tel_waris" class="form-label">No. Telefon Waris</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="text" class="form-control <?= isset($errors['no_tel_waris']) ? 'is-invalid' : '' ?>" 
                           id="no_tel_waris" name="no_tel_waris" placeholder="Contoh: 012-3456789"
                           value="<?= htmlspecialchars($userData['telefon_kecemasan'] ?? ''); ?>" maxlength="20">
                  </div>
                  <?php if (isset($errors['no_tel_waris'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['no_tel_waris']); ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="mb-3">
                <label for="alamat_waris" class="form-label">Alamat Waris</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                  <textarea class="form-control" id="alamat_waris" name="alamat_waris" rows="3" maxlength="255"><?= htmlspecialchars($userData['alamat_kecemasan'] ?? ''); ?></textarea>
                </div>
              </div>
            </div>

            <!-- PENGESAHAN -->
            <div class="form-section">
              <h5 class="section-title"><i class="fas fa-file-signature me-2"></i>Pengesahan Maklumat</h5>

              <div class="mb-3 form-check">
                <input class="form-check-input <?= isset($errors['pengakuan']) ? 'is-invalid' : '' ?>" 
                       type="checkbox" value="1" id="pengakuan" name="pengakuan" required
                       <?= (($userData['tandatangan'] ?? '') === 'YA') ? 'checked' : ''; ?>>
                <label class="form-check-label" for="pengakuan">
                  Saya mengesahkan bahawa semua maklumat yang diberikan adalah benar dan tepat.
                </label>
                <?php if (isset($errors['pengakuan'])): ?>
                  <div class="invalid-feedback"><?= htmlspecialchars($errors['pengakuan']); ?></div>
                <?php endif; ?>
              </div>

              <div class="two-columns">
                <div class="mb-3">
                  <label for="tarikh_akuan" class="form-label">Tarikh Akuan</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                    <input type="date" class="form-control" id="tarikh_akuan" name="tarikh_akuan"
                           value="<?= htmlspecialchars($userData['tarikh_akuan'] ?? ''); ?>">
                  </div>
                  <div class="form-text">Tarikh akan diisi secara automatik apabila pengesahan ditandakan.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-grid gap-2 mt-4">
          <button type="submit" class="btn btn-save btn-primary">
            <i class="fas fa-save me-2"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Auto-fill date when pengakuan is checked
  document.getElementById('pengakuan').addEventListener('change', function() {
    if (this.checked) {
      const today = new Date().toISOString().split('T')[0];
      const dateField = document.getElementById('tarikh_akuan');
      // Only auto-fill if empty or matches today's date (don't override manual changes)
      if (!dateField.value || dateField.value === today) {
        dateField.value = today;
      }
    }
  });

  // Enhanced client-side validation
  document.getElementById('profileForm').addEventListener('submit', function(e) {
    let isValid = true;
    const errorTabs = new Set();

    // Validate required fields
    const nama = document.getElementById('nama');
    if (!nama.value.trim()) {
      isValid = false;
      nama.classList.add('is-invalid');
      errorTabs.add('personal-tab');
    } else {
      nama.classList.remove('is-invalid');
    }

    // Validate phone formats
    const validatePhone = (fieldId, errorKey) => {
      const field = document.getElementById(fieldId);
      const value = field.value.trim();
      if (value && !/^[0-9+\(\)\s-]{6,20}$/.test(value)) {
        isValid = false;
        field.classList.add('is-invalid');
        errorTabs.add(fieldId.includes('waris') ? 'kin-tab' : 'contact-tab');
      } else {
        field.classList.remove('is-invalid');
      }
    };

    validatePhone('no_tel_rumah', 'no_tel_rumah');
    validatePhone('no_tel_pejabat', 'no_tel_pejabat');
    validatePhone('no_tel_waris', 'no_tel_waris');

    // Validate poskod
    const poskod = document.getElementById('poskod');
    if (poskod.value && !/^\d{5}$/.test(poskod.value)) {
      isValid = false;
      poskod.classList.add('is-invalid');
      errorTabs.add('contact-tab');
    } else {
      poskod.classList.remove('is-invalid');
    }

    // Validate negeri
    const negeri = document.getElementById('negeri');
    if (!negeri.value) {
      isValid = false;
      negeri.classList.add('is-invalid');
      errorTabs.add('contact-tab');
    } else {
      negeri.classList.remove('is-invalid');
    }

    // Validate pengakuan checkbox
    const chk = document.getElementById('pengakuan');
    if (!chk.checked) {
      isValid = false;
      chk.classList.add('is-invalid');
      errorTabs.add('kin-tab');
    } else {
      chk.classList.remove('is-invalid');
    }

    if (!isValid) {
      e.preventDefault();
      const firstErrorTab = document.getElementById(Array.from(errorTabs)[0]);
      if (firstErrorTab) {
        new bootstrap.Tab(firstErrorTab).show();
      }
      
      // Scroll to first error
      const firstError = document.querySelector('.is-invalid');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
      }
    }
  });

  // Show error tabs on page load if errors exist
  document.addEventListener('DOMContentLoaded', function() {
    const errorFields = document.querySelectorAll('.is-invalid');
    if (errorFields.length > 0) {
      const firstError = errorFields[0];
      let tabToShow = null;
      
      if (firstError.id === 'nama' || firstError.closest('#personal')) {
        tabToShow = 'personal-tab';
      } else if (firstError.id.includes('no_tel_rumah') || firstError.id.includes('no_tel_pejabat') || 
                 firstError.id.includes('poskod') || firstError.id.includes('negeri') || 
                 firstError.closest('#contact')) {
        tabToShow = 'contact-tab';
      } else if (firstError.id === 'pengakuan' || firstError.id.includes('no_tel_waris') || firstError.closest('#kin')) {
        tabToShow = 'kin-tab';
      }
      
      if (tabToShow) {
        new bootstrap.Tab(document.getElementById(tabToShow)).show();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
      }
    }
  });
</script>
</body>
</html>