<?php
// profile.php (improved)
// ---------------------------------------------------------
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
date_default_timezone_set('Asia/Kuala_Lumpur');

$userId = (int)$_SESSION['user_id'];
$userData = [];
$userActivities = [];

// ---------------------------------------------
// Helpers
// ---------------------------------------------
function humanUploadError($code) {
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'Fail melebihi had saiz pelayan.',
        UPLOAD_ERR_FORM_SIZE  => 'Fail melebihi had saiz borang.',
        UPLOAD_ERR_PARTIAL    => 'Fail dimuat naik secara separa.',
        UPLOAD_ERR_NO_FILE    => 'Tiada fail dimuat naik.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tiada.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis fail ke cakera.',
        UPLOAD_ERR_EXTENSION  => 'Muat naik dihentikan oleh sambungan PHP.',
    ];
    return $map[$code] ?? 'Ralat muat naik tidak diketahui.';
}

function isUnderUploadsProfiles($path) {
    $realBase = realpath(__DIR__ . '/uploads/profiles');
    $realTarget = realpath($path);
    return $realTarget && str_starts_with($realTarget, $realBase);
}

// ---------------------------------------------
// Fetch profile + settings (creates defaults if missing)
// ---------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT 
            u.*,
            md.*,
            us.email_notifications, 
            us.news_updates, 
            us.privacy_level
        FROM users u
        LEFT JOIN maklumat_diri md ON md.user_id = u.id
        LEFT JOIN user_settings us ON us.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $userData = $res->fetch_assoc() ?: [];
    $stmt->close();

    if (!array_key_exists('email_notifications', $userData)) {
        // create default settings row if not exists
        $stmt = $conn->prepare("
            INSERT INTO user_settings (user_id)
            VALUES (?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        // refetch with settings present
        $stmt = $conn->prepare("
            SELECT 
                u.*,
                md.*,
                us.email_notifications,
                us.news_updates,
                us.privacy_level
            FROM users u
            LEFT JOIN maklumat_diri md ON md.user_id = u.id
            LEFT JOIN user_settings us ON us.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $userData = $res->fetch_assoc() ?: [];
        $stmt->close();
    }

    // Fetch last 10 activities
    $stmt = $conn->prepare("
        SELECT activity_type, activity_details, created_at 
        FROM user_activities 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $userActivities = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Throwable $e) {
    error_log('Profile fetch error: ' . $e->getMessage());
}

// ---------------------------------------------
// Handle profile picture upload / remove
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove picture
    if (isset($_POST['remove_picture'])) {
        $old = $userData['gambar_profil'] ?? '';
        if ($old && file_exists($old) && isUnderUploadsProfiles($old)) {
            @unlink($old);
        }
        $stmt = $conn->prepare("UPDATE maklumat_diri SET gambar_profil = NULL WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Gambar profil telah dibuang.";
            $userData['gambar_profil'] = null;
        } else {
            $_SESSION['error_message'] = "Ralat ketika mengemas kini rekod gambar.";
        }
        $stmt->close();

        // Log activity
        $activityType = "profile_picture_update";
        $activityDetails = "User removed profile picture";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $conn->prepare("
            INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $userId, $activityType, $activityDetails, $ip, $ua);
        $stmt->execute();
        $stmt->close();

        header("Location: profile.php");
        exit();
    }

    // Upload picture
    if (isset($_FILES['profile_picture'])) {
        $dir = __DIR__ . '/uploads/profiles/';
        $relDir = 'uploads/profiles/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $f = $_FILES['profile_picture'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = humanUploadError($f['error']);
            header("Location: profile.php");
            exit();
        }

        // Basic size limit (5MB)
        if ($f['size'] > 5 * 1024 * 1024) {
            $_SESSION['error_message'] = "Fail terlalu besar. Maksimum 5MB.";
            header("Location: profile.php");
            exit();
        }

        // Validate as real image
        $imgInfo = @getimagesize($f['tmp_name']);
        if (!$imgInfo) {
            $_SESSION['error_message'] = "Fail bukan imej yang sah.";
            header("Location: profile.php");
            exit();
        }

        $mime = $imgInfo['mime'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            $_SESSION['error_message'] = "Hanya JPEG, PNG atau GIF dibenarkan.";
            header("Location: profile.php");
            exit();
        }

        // Generate unique filename
        $ext = $allowed[$mime];
        $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $destAbs = $dir . $filename;
        $destRel = $relDir . $filename;

        if (!move_uploaded_file($f['tmp_name'], $destAbs)) {
            $_SESSION['error_message'] = "Gagal memindahkan fail yang dimuat naik.";
            header("Location: profile.php");
            exit();
        }

        // Delete old pic (only inside uploads/profiles)
        $old = $userData['gambar_profil'] ?? '';
        if ($old && file_exists($old) && isUnderUploadsProfiles($old)) {
            @unlink($old);
        }

        // Save relative path
        $stmt = $conn->prepare("UPDATE maklumat_diri SET gambar_profil = ? WHERE user_id = ?");
        $stmt->bind_param("si", $destRel, $userId);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Gambar profil berjaya dikemaskini!";
            $userData['gambar_profil'] = $destRel;
        } else {
            $_SESSION['error_message'] = "Ralat ketika menyimpan maklumat gambar.";
        }
        $stmt->close();

        // Log activity
        $activityType = "profile_picture_update";
        $activityDetails = "User updated profile picture";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $conn->prepare("
            INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issss", $userId, $activityType, $activityDetails, $ip, $ua);
        $stmt->execute();
        $stmt->close();

        header("Location: profile.php");
        exit();
    }

    // Settings update
    if (isset($_POST['update_settings'])) {
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $newsUpdates       = isset($_POST['news_updates']) ? 1 : 0;
        $privacyLevel      = $_POST['privacy_level'] ?? 'connections';

        try {
            $stmt = $conn->prepare("
                UPDATE user_settings 
                SET email_notifications = ?, news_updates = ?, privacy_level = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("iisi", $emailNotifications, $newsUpdates, $privacyLevel, $userId);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Tetapan berjaya dikemaskini!";
                $userData['email_notifications'] = $emailNotifications;
                $userData['news_updates'] = $newsUpdates;
                $userData['privacy_level'] = $privacyLevel;

                $activityType = "settings_update";
                $activityDetails = "User updated account settings";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmt2 = $conn->prepare("
                    INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param("issss", $userId, $activityType, $activityDetails, $ip, $ua);
                $stmt2->execute();
                $stmt2->close();
            } else {
                $_SESSION['error_message'] = "Ralat ketika mengemaskini tetapan.";
            }
            $stmt->close();
        } catch (Throwable $e) {
            $_SESSION['error_message'] = "Ralat sistem: " . $e->getMessage();
        }

        header("Location: profile.php#settings");
        exit();
    }
}

// ---------------------------------------------
// Derived fields
// ---------------------------------------------
$age = null;
if (!empty($userData['tarikh_lahir'])) {
    try {
        $birthDate = new DateTime($userData['tarikh_lahir']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    } catch (Throwable $e) {
        $age = null;
    }
}

// Address parsing (alamat, poskod, bandar, negeri)
$alamat_components = ['', '', '', ''];
if (!empty($userData['alamat'])) {
    $alamat_components = preg_split('/,\s*/', $userData['alamat']);
    $alamat_components = array_pad($alamat_components, 4, '');
}

// Activity icon mapping
function activityIcon($type) {
    $map = [
        'login'                  => 'sign-in-alt',
        'profile_update'         => 'user-edit',
        'profile_picture_update' => 'camera',
        'settings_update'        => 'cog',
        'course_application'     => 'book',
        'password_change'        => 'lock',
    ];
    return $map[$type] ?? 'history';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya | e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --primary:#0C3C60;
            --secondary:#E67E22;
            --card-bg:#ffffff;
            --text:#1f2937;
            --muted:#6b7280;
            --border:#e5e7eb;
        }
        body.dark {
            --card-bg:#0f172a;
            --text:#e5e7eb;
            --muted:#94a3b8;
            --border:#1f2937;
            background:#0b1220;
            color:var(--text);
        }
        body{
            background:#f5f5f5;
            color:var(--text);
        }
        .navbar{ background:var(--primary); }
        .profile-header{
            background:linear-gradient(135deg, var(--primary), #1a4d7a);
            color:#fff; padding:32px 0; margin-bottom:28px;
        }
        .card.profile { border:none; border-radius:14px; background:var(--card-bg);
            box-shadow: 0 8px 30px rgba(0,0,0,.06); overflow:hidden; }
        .card.profile .card-header{
            background:var(--primary); color:#fff; font-weight:600;
        }
        .shadow-soft{box-shadow:0 8px 30px rgba(0,0,0,.06);}
        .profile-pic{
            width:160px; height:160px; border-radius:50%;
            overflow:hidden; margin:0 auto; position:relative;
            border:4px solid #fff; box-shadow:0 10px 30px rgba(0,0,0,.15);
        }
        .profile-pic img{ width:100%; height:100%; object-fit:cover; display:block; }
        .profile-pic .overlay{
            position:absolute; inset:0; display:flex; gap:10px;
            align-items:center; justify-content:center;
            background:rgba(0,0,0,.45); opacity:0; transition:.25s;
        }
        .profile-pic:hover .overlay{ opacity:1; }
        .overlay .btn-circle{
            width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center;
        }
        .badge-role{
            background:var(--secondary); border-radius:20px; padding:6px 12px; font-size:.85rem; color:#fff;
        }
        .nav-tabs .nav-link{ color:var(--primary); font-weight:600; }
        body.dark .nav-tabs .nav-link{ color:#cbd5e1; }
        .nav-tabs .nav-link.active{
            background:var(--primary); color:#fff; border-color:var(--primary);
        }
        .label{ font-weight:600; color:var(--primary); margin-bottom:4px; }
        .value{ border-bottom:1px dashed var(--border); padding-bottom:8px; margin-bottom:14px; color:var(--text); }
        .timeline .item{ display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
        .timeline .item:last-child{ border-bottom:none; }
        .timeline .icon{
            width:42px; height:42px; border-radius:50%; background:#eef2ff;
            display:flex; align-items:center; justify-content:center;
        }
        body.dark .timeline .icon{ background:#1e293b; }
        .toggle-dark{ cursor:pointer; }
        .btn-outline-light-alt{ border:1px solid rgba(255,255,255,.5); color:#fff; }
        .btn-outline-light-alt:hover{ background:rgba(255,255,255,.1); color:#fff; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-graduation-cap me-2"></i>e-Kursus ALPHA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="profile.php"><i class="fa fa-user me-1"></i>Profil</a></li>
                    <li class="nav-item"><a class="nav-link" href="kemaskini.php"><i class="fa fa-user-pen me-1"></i>Maklumat Diri</a></li>
                    <li class="nav-item"><a class="nav-link" href="courses.php"><i class="fa fa-list-ul me-1"></i>Senarai Kursus</a></li>
                    <li class="nav-item"><a class="nav-link" href="apply_status.php"><i class="fa fa-clipboard-check me-1"></i>Status Permohonan</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-light-alt toggle-dark" type="button" title="Togol Mod Gelap">
                        <i class="fa fa-moon"></i>
                    </button>
                    <a class="btn btn-sm btn-outline-light" href="logout.php"><i class="fa fa-right-from-bracket me-1"></i>Log Keluar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="profile-header">
        <div class="container text-center">
            <h1><i class="fas fa-user-circle me-2"></i>Profil Saya</h1>
            <p class="lead mb-0">Lihat dan urus maklumat profil anda</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Alerts -->
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-soft">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-soft">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Sidebar -->
            <div class="col-lg-4">
                <div class="card profile sticky-top" style="top:20px;">
                    <div class="card-body text-center">
                        <div class="profile-pic mb-3">
                            <?php if (!empty($userData['gambar_profil'])): ?>
                                <img src="<?= htmlspecialchars($userData['gambar_profil']) ?>" alt="Gambar Profil">
                            <?php else: ?>
                                <img src="assets/img/avatar_placeholder.png" alt="Placeholder" onerror="this.src='https://via.placeholder.com/160?text=Profil'">
                            <?php endif; ?>
                            <div class="overlay">
                                <!-- Upload -->
                                <label for="profile_picture" class="btn btn-light btn-circle" title="Muat naik">
                                    <i class="fa fa-camera"></i>
                                </label>
                                <!-- Remove -->
                                <?php if (!empty($userData['gambar_profil'])): ?>
                                    <button class="btn btn-danger btn-circle" title="Buang" form="removePicForm">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form id="uploadPicForm" action="profile.php" method="POST" enctype="multipart/form-data" style="display:none;">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                        </form>
                        <form id="removePicForm" action="profile.php" method="POST" style="display:none;">
                            <input type="hidden" name="remove_picture" value="1">
                        </form>

                        <h4 class="mb-1"><?= htmlspecialchars($userData['nama'] ?? 'Nama Pengguna') ?></h4>
                        <p class="text-muted mb-2">No. KP: <?= htmlspecialchars($userData['no_kp'] ?? '-') ?></p>
                        <p class="text-muted mb-2">No. Ahli: <?= htmlspecialchars($userData['no_ahli'] ?? 'Tiada') ?></p>
                        <span class="badge-role mb-3"><?= isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Ahli' ?></span>

                        <div class="d-grid gap-2">
                            <a href="kemaskini.php" class="btn btn-primary"><i class="fas fa-edit me-2"></i>Kemaskini Profil</a>
                            <a href="change_password.php" class="btn btn-outline-secondary"><i class="fas fa-lock me-2"></i>Tukar Kata Laluan</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main -->
            <div class="col-lg-8">
                <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                            <i class="fas fa-user me-2"></i>Maklumat Peribadi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>Aktiviti Terkini
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i>Tetapan
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="profileTabsContent">
                    <!-- Personal -->
                    <div class="tab-pane fade show active" id="personal" role="tabpanel">
                        <div class="card profile mb-3">
                            <div class="card-header"><i class="fas fa-id-card me-2"></i>Maklumat Peribadi</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="label">Nama Penuh</div>
                                        <div class="value"><?= htmlspecialchars($userData['nama'] ?? 'Tiada') ?></div>

                                        <div class="label">No. Kad Pengenalan</div>
                                        <div class="value"><?= htmlspecialchars($userData['no_kp'] ?? 'Tiada') ?></div>

                                        <div class="label">No. Ahli</div>
                                        <div class="value"><?= htmlspecialchars($userData['no_ahli'] ?? 'Tiada') ?></div>

                                        <div class="label">Jantina</div>
                                        <div class="value"><?= htmlspecialchars($userData['jantina'] ?? 'Tiada') ?></div>

                                        <div class="label">Status</div>
                                        <div class="value"><?= htmlspecialchars($userData['status'] ?? 'Tiada') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="label">Tarikh Lahir</div>
                                        <div class="value">
                                            <?= !empty($userData['tarikh_lahir']) ? date('d/m/Y', strtotime($userData['tarikh_lahir'])) . ($age !== null ? " ($age tahun)" : '') : 'Tiada' ?>
                                        </div>

                                        <div class="label">Tempat Lahir</div>
                                        <div class="value"><?= htmlspecialchars($userData['tempat_lahir'] ?? 'Tiada') ?></div>

                                        <div class="label">Umur (DB)</div>
                                        <div class="value"><?= htmlspecialchars($userData['umur'] ?? 'Tiada') ?> tahun</div>

                                        <div class="label">Bangsa</div>
                                        <div class="value"><?= htmlspecialchars($userData['bangsa'] ?? 'Tiada') ?></div>

                                        <div class="label">Agama</div>
                                        <div class="value"><?= htmlspecialchars($userData['agama'] ?? 'Tiada') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card profile mb-3">
                            <div class="card-header"><i class="fas fa-address-book me-2"></i>Maklumat Perhubungan</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="label">Alamat</div>
                                        <div class="value"><?= htmlspecialchars($alamat_components[0] ?: 'Tiada') ?></div>

                                        <div class="label">Poskod</div>
                                        <div class="value"><?= htmlspecialchars($alamat_components[1] ?: 'Tiada') ?></div>

                                        <div class="label">Bandar</div>
                                        <div class="value"><?= htmlspecialchars($alamat_components[2] ?: 'Tiada') ?></div>

                                        <div class="label">Negeri</div>
                                        <div class="value"><?= htmlspecialchars($alamat_components[3] ?: 'Tiada') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="label">Telefon (Rumah)</div>
                                        <div class="value"><?= htmlspecialchars($userData['tel_rumah'] ?? 'Tiada') ?></div>

                                        <div class="label">Telefon (Pejabat)</div>
                                        <div class="value"><?= htmlspecialchars($userData['tel_pejabat'] ?? 'Tiada') ?></div>

                                        <div class="label">Telefon Bimbit</div>
                                        <div class="value"><?= htmlspecialchars($userData['no_tel_bimbit'] ?? 'Tiada') ?></div>

                                        <div class="label">Emel</div>
                                        <div class="value"><?= htmlspecialchars($userData['emel'] ?? 'Tiada') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card profile mb-3">
                            <div class="card-header"><i class="fas fa-briefcase me-2"></i>Maklumat Pekerjaan</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="label">Jawatan</div>
                                        <div class="value"><?= htmlspecialchars($userData['jawatan'] ?? 'Tiada') ?></div>

                                        <div class="label">Majikan</div>
                                        <div class="value"><?= htmlspecialchars($userData['majikan'] ?? 'Tiada') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="label">Pangkat</div>
                                        <div class="value"><?= htmlspecialchars($userData['pangkat'] ?? 'Tiada') ?></div>

                                        <div class="label">Jenis Keahlian</div>
                                        <div class="value"><?= htmlspecialchars($userData['jenis_keahlian'] ?? 'Tiada') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card profile">
                            <div class="card-header"><i class="fas fa-users me-2"></i>Maklumat Waris (Kecemasan)</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="label">Nama Waris</div>
                                        <div class="value"><?= htmlspecialchars($userData['nama_kecemasan'] ?? 'Tiada') ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="label">Telefon Waris</div>
                                        <div class="value"><?= htmlspecialchars($userData['telefon_kecemasan'] ?? 'Tiada') ?></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="label">Alamat Waris</div>
                                        <div class="value"><?= htmlspecialchars($userData['alamat_kecemasan'] ?? 'Tiada') ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity -->
                    <div class="tab-pane fade" id="activity" role="tabpanel">
                        <div class="card profile">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-history me-2"></i>Aktiviti Terkini</span>
                                <span class="badge bg-primary"><?= count($userActivities) ?> aktiviti</span>
                            </div>
                            <div class="card-body">
                                <?php if ($userActivities): ?>
                                    <div class="timeline">
                                        <?php foreach ($userActivities as $a): ?>
                                            <div class="item">
                                                <div class="icon">
                                                    <i class="fas fa-<?= htmlspecialchars(activityIcon($a['activity_type'])) ?> text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">
                                                        <?php
                                                            $label = match ($a['activity_type']) {
                                                                'login' => 'Log Masuk',
                                                                'profile_update' => 'Kemaskini Profil',
                                                                'profile_picture_update' => 'Gambar Profil',
                                                                'settings_update' => 'Kemaskini Tetapan',
                                                                'course_application' => 'Permohonan Kursus',
                                                                'password_change' => 'Tukar Kata Laluan',
                                                                default => ucfirst(str_replace('_',' ', $a['activity_type']))
                                                            };
                                                            echo htmlspecialchars($label);
                                                        ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= !empty($a['activity_details']) ? htmlspecialchars($a['activity_details']) : 'Aktiviti direkodkan' ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">Tiada aktiviti direkodkan lagi.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <div class="card profile">
                            <div class="card-header"><i class="fas fa-cog me-2"></i>Tetapan Akaun</div>
                            <div class="card-body">
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="update_settings" value="1">

                                    <h5 class="mb-3"><i class="fas fa-bell me-2"></i>Pemberitahuan</h5>
                                    <div class="mb-4">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications"
                                                <?= (!empty($userData['email_notifications'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="email_notifications">Pemberitahuan melalui E-mel</label>
                                            <div class="form-text">Terima pemberitahuan penting melalui e-mel.</div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="news_updates" name="news_updates"
                                                <?= (!empty($userData['news_updates'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="news_updates">Kemas Kini & Berita</label>
                                            <div class="form-text">Terima berita dan kemas kini tentang kursus.</div>
                                        </div>
                                    </div>

                                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Privasi</h5>
                                    <div class="mb-4">
                                        <label for="privacy_level" class="form-label">Tahap Privasi Profil</label>
                                        <select class="form-select" id="privacy_level" name="privacy_level">
                                            <option value="public"      <?= (!empty($userData['privacy_level']) && $userData['privacy_level']==='public') ? 'selected' : '' ?>>Umum (Semua)</option>
                                            <option value="connections" <?= (empty($userData['privacy_level']) || $userData['privacy_level']==='connections') ? 'selected' : '' ?>>Sambungan Sahaja</option>
                                            <option value="private"     <?= (!empty($userData['privacy_level']) && $userData['privacy_level']==='private') ? 'selected' : '' ?>>Peribadi (Hanya saya)</option>
                                        </select>
                                    </div>

                                    <h5 class="mb-3"><i class="fas fa-moon me-2"></i>Penampilan</h5>
                                    <div class="mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                                            <label class="form-check-label" for="darkModeSwitch">Mod Gelap</label>
                                            <div class="form-text">Pilihan ini disimpan pada pelayar anda.</div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Tetapan</button>
                                </form>
                            </div>
                        </div>
                    </div> <!-- /settings -->
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <small class="text-muted">© <?= date('Y') ?> e-Kursus ALPHA. Hak Cipta Terpelihara.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode (client-side only)
        const DARK_KEY = 'alpha_dark_mode';
        function applyDarkMode(enabled){
            document.body.classList.toggle('dark', !!enabled);
            const sw = document.getElementById('darkModeSwitch');
            if (sw) sw.checked = !!enabled;
        }
        document.addEventListener('DOMContentLoaded', () => {
            // apply saved dark mode
            applyDarkMode(localStorage.getItem(DARK_KEY) === '1');

            // toggle button in navbar
            const navToggle = document.querySelector('.toggle-dark');
            if (navToggle) {
                navToggle.addEventListener('click', () => {
                    const enabled = !(document.body.classList.contains('dark'));
                    applyDarkMode(enabled);
                    localStorage.setItem(DARK_KEY, enabled ? '1' : '0');
                });
            }

            // settings switch binds to same storage
            const switchEl = document.getElementById('darkModeSwitch');
            if (switchEl) {
                switchEl.addEventListener('change', (e) => {
                    const enabled = e.target.checked;
                    applyDarkMode(enabled);
                    localStorage.setItem(DARK_KEY, enabled ? '1' : '0');
                });
            }

            // Show tab by hash
            if (window.location.hash) {
                const trigger = document.querySelector(`[data-bs-target="${window.location.hash}"]`);
                if (trigger) new bootstrap.Tab(trigger).show();
            }

            // Clicking camera opens file dialog
            const camLabel = document.querySelector('label[for="profile_picture"]');
            if (camLabel) {
                camLabel.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.getElementById('profile_picture').click();
                });
            }
        });
    </script>
</body>
</html>
