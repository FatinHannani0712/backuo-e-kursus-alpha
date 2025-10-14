<?php
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
$userSettings = [];

try {
    // Get user profile data
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
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc() ?? [];
    $stmt->close();

    // Get user activities (last 10)
    $stmt = $conn->prepare("
        SELECT activity_type, activity_details, created_at 
        FROM user_activities 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userActivities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If no settings exist, create default settings
    if (!isset($userData['email_notifications'])) {
        $stmt = $conn->prepare("
            INSERT INTO user_settings (user_id) 
            VALUES (?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        
        // Refetch user data to include default settings
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
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc() ?? [];
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    // Continue execution even if there are errors
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $uploadDir = 'uploads/profiles/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $file = $_FILES['profile_picture'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileType = mime_content_type($file['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Delete old profile picture if exists
                if (!empty($userData['gambar_profil']) && file_exists($userData['gambar_profil'])) {
                    unlink($userData['gambar_profil']);
                }
                
                // Update database
                $stmt = $conn->prepare("UPDATE maklumat_diri SET gambar_profil = ? WHERE user_id = ?");
                $stmt->bind_param("si", $destination, $userId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Gambar profil berjaya dikemaskini!";
                    $userData['gambar_profil'] = $destination;
                } else {
                    $_SESSION['error_message'] = "Ralat ketika menyimpan maklumat gambar.";
                }
                $stmt->close();
                
                // Log activity
                $activityType = "profile_picture_update";
                $activityDetails = "User updated profile picture";
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                $stmt = $conn->prepare("
                    INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issss", $userId, $activityType, $activityDetails, $ipAddress, $userAgent);
                $stmt->execute();
                $stmt->close();
                
            } else {
                $_SESSION['error_message'] = "Ralat ketika memuat naik fail.";
            }
        } else {
            $_SESSION['error_message'] = "Hanya fail JPEG, PNG dan GIF dibenarkan.";
        }
    } else {
        $_SESSION['error_message'] = "Ralat ketika memuat naik fail: " . $file['error'];
    }
    
    header("Location: profile.php");
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $newsUpdates = isset($_POST['news_updates']) ? 1 : 0;
    $privacyLevel = $_POST['privacy_level'] ?? 'connections';
    
    try {
        $stmt = $conn->prepare("
            UPDATE user_settings 
            SET email_notifications = ?, news_updates = ?, privacy_level = ? 
            WHERE user_id = ?
        ");
        $stmt->bind_param("iisi", $emailNotifications, $newsUpdates, $privacyLevel, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Tetapan berjaya dikemaskini!";
            
            // Update local user data
            $userData['email_notifications'] = $emailNotifications;
            $userData['news_updates'] = $newsUpdates;
            $userData['privacy_level'] = $privacyLevel;
            
            // Log activity
            $activityType = "settings_update";
            $activityDetails = "User updated account settings";
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            $stmt2 = $conn->prepare("
                INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param("issss", $userId, $activityType, $activityDetails, $ipAddress, $userAgent);
            $stmt2->execute();
            $stmt2->close();
        } else {
            $_SESSION['error_message'] = "Ralat ketika mengemaskini tetapan.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Ralat sistem: " . $e->getMessage();
    }
    
    header("Location: profile.php");
    exit();
}

// Calculate age from tarikh_lahir if available
$age = null;
if (!empty($userData['tarikh_lahir'])) {
    $birthDate = new DateTime($userData['tarikh_lahir']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}

// Parse address into components
$alamat_components = ['', '', '', '']; // alamat, poskod, bandar, negeri
if (!empty($userData['alamat'])) {
    $alamat_components = explode(', ', $userData['alamat']);
    $alamat_components = array_pad($alamat_components, 4, '');
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya | e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #0C3C60; 
            --secondary-color: #E67E22; 
            --light-color: #f8f9fa;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .profile-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a4d7a 100%); 
            color: white; 
            padding: 30px 0; 
            margin-bottom: 30px; 
        }
        .profile-card { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            overflow: hidden;
        }
        .profile-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-header { 
            background: var(--primary-color); 
            color: white; 
            font-weight: 600; 
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .card-body { padding: 20px; }
        .profile-img-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-img-container i {
            font-size: 60px;
            color: white;
        }
        .profile-img-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--secondary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .profile-img-upload:hover {
            background: #d35400;
            transform: scale(1.1);
        }
        .profile-info-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        .profile-info-value {
            margin-bottom: 15px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .badge-role { 
            background-color: var(--secondary-color);
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .nav-tabs .nav-link.active { 
            background-color: var(--primary-color); 
            color: white; 
            border-color: var(--primary-color);
            font-weight: 600;
        }
        .nav-tabs .nav-link {
            color: var(--primary-color);
            font-weight: 500;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        @media (max-width: 768px) {
            .profile-img-container {
                width: 120px;
                height: 120px;
            }
            .profile-img-container i {
                font-size: 50px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--primary-color);">
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="fa fa-user me-1"></i>Profil
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
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown">
                            <i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($userData['nama'] ?? 'Pengguna') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fa fa-user me-1"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="kemaskini.php"><i class="fa fa-user-edit me-1"></i>Kemaskini Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out me-1"></i>Log Keluar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container text-center">
            <h1><i class="fas fa-user-circle me-2"></i>Profil Saya</h1>
            <p class="lead">Lihat dan urus maklumat profil anda</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Notification Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Left Sidebar - Profile Summary -->
            <div class="col-lg-4">
                <div class="profile-card sticky-top" style="top: 20px;">
                    <div class="card-body text-center">
                        <div class="profile-img-container">
                            <?php if (!empty($userData['gambar_profil'])): ?>
                                <img src="<?= htmlspecialchars($userData['gambar_profil']) ?>" alt="Profile Picture">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                            <label for="profile_picture" class="profile-img-upload" title="Tukar gambar profil">
                                <i class="fas fa-camera"></i>
                            </label>
                            <form id="profilePictureForm" action="profile.php" method="POST" enctype="multipart/form-data" style="display: none;">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                            </form>
                        </div>
                        
                        <h4><?= htmlspecialchars($userData['nama'] ?? 'Nama Pengguna') ?></h4>
                        <p class="text-muted mb-1">No. KP: <?= htmlspecialchars($userData['no_kp'] ?? '') ?></p>
                        <p class="text-muted mb-2">No. Ahli: <?= htmlspecialchars($userData['no_ahli'] ) ?></p>
                        
                        <span class="badge badge-role mb-3">
                            <?= isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Ahli' ?>
                        </span>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="kemaskini.php" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Kemaskini Profil
                            </a>
                            <a href="change_password.php" class="btn btn-outline-secondary">
                                <i class="fas fa-lock me-2"></i>Tukar Kata Laluan
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
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
                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade show active" id="personal" role="tabpanel">
                        <div class="profile-card">
                            <div class="card-header">
                                <i class="fas fa-id-card me-2"></i>Maklumat Peribadi
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Nama Penuh</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['nama'] ) ?></div>
                                        
                                        <div class="profile-info-label">No. Kad Pengenalan</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['no_kp'] ) ?></div>
                                        
                                        <div class="profile-info-label">No. Ahli</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['no_ahli']) ?></div>
                                        
                                        <div class="profile-info-label">Jantina</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['jantina']) ?></div>
                                        
                                        <div class="profile-info-label">Status</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['status'] ) ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Tarikh Lahir</div>
                                        <div class="profile-info-value">
                                            <?= !empty($userData['tarikh_lahir']) ? date('d/m/Y', strtotime($userData['tarikh_lahir'])) . " ($age tahun)" : '' ?>
                                        </div>
                                        
                                        <div class="profile-info-label">Tempat Lahir</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['tempat_lahir']) ?></div>
                                        
                                        <div class="profile-info-label">Umur</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['umur'] ) ?> tahun</div>
                                        
                                        <div class="profile-info-label">Bangsa</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['bangsa'] ) ?></div>
                                        
                                        <div class="profile-info-label">Agama</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['agama'] ) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-card">
                            <div class="card-header">
                                <i class="fas fa-address-book me-2"></i>Maklumat Perhubungan
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Alamat</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($alamat_components[0]) ?></div>
                                        
                                        <div class="profile-info-label">Poskod</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($alamat_components[1] ) ?></div>
                                        
                                        <div class="profile-info-label">Bandar</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($alamat_components[2]  ) ?></div>
                                        
                                        <div class="profile-info-label">Negeri</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($alamat_components[3] ) ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Telefon (Rumah)</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['tel_rumah'] ) ?></div>
                                        
                                        <div class="profile-info-label">Telefon (Pejabat)</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['tel_pejabat']) ?></div>
                                        
                                        <div class="profile-info-label">Telefon Bimbit</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['no_tel_bimbit']) ?></div>
                                        
                                        <div class="profile-info-label">Emel</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['emel'] ) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-card">
                            <div class="card-header">
                                <i class="fas fa-briefcase me-2"></i>Maklumat Pekerjaan
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Jawatan</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['jawatan'] ) ?></div>
                                        
                                        <div class="profile-info-label">Majikan</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['majikan']) ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Pangkat</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['pangkat'] ) ?></div>
                                        
                                        <div class="profile-info-label">Jenis Keahlian</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['jenis_keahlian'] ) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-card">
                            <div class="card-header">
                                <i class="fas fa-users me-2"></i>Maklumat Waris (Kecemasan)
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Nama Waris</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['nama_kecemasan'] ) ?></div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="profile-info-label">Telefon Waris</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['telefon_kecemasan'] ) ?></div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="profile-info-label">Alamat Waris</div>
                                        <div class="profile-info-value"><?= htmlspecialchars($userData['alamat_kecemasan'] ) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Tab -->
                    <div class="tab-pane fade" id="activity" role="tabpanel">
                        <div class="profile-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-history me-2"></i>Aktiviti Terkini</span>
                                <span class="badge bg-primary"><?= count($userActivities) ?> aktiviti</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($userActivities)): ?>
                                    <div class="activity-list">
                                        <?php foreach ($userActivities as $activity): ?>
                                            <div class="activity-item d-flex align-items-center">
                                                <div class="activity-icon">
                                                    <i class="fas fa-<?= 
                                                        $activity['activity_type'] === 'login' ? 'sign-in-alt' : 
                                                        ($activity['activity_type'] === 'profile_update' ? 'user-edit' : 
                                                        ($activity['activity_type'] === 'course_application' ? 'book' : 'history')) 
                                                    ?> text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-medium">
                                                        <?= 
                                                            $activity['activity_type'] === 'login' ? 'Log Masuk' : 
                                                            ($activity['activity_type'] === 'profile_update' ? 'Kemaskini Profil' : 
                                                            ($activity['activity_type'] === 'course_application' ? 'Permohonan Kursus' : 
                                                            ucfirst(str_replace('_', ' ', $activity['activity_type'])))) 
                                                        ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?= !empty($activity['activity_details']) ? htmlspecialchars($activity['activity_details']) : 'Aktiviti dilakukan' ?>
                                                    </div>
                                                    <div class="text-muted smaller">
                                                        <?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Aktiviti papar sini .</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <div class="profile-card">
                            <div class="card-header">
                                <i class="fas fa-cog me-2"></i>Tetapan Akaun
                            </div>
                            <div class="card-body">
                                <form method="POST" action="profile.php">
                                    <input type="hidden" name="update_settings" value="1">
                                    
                                    <h5 class="mb-3"><i class="fas fa-bell me-2"></i>Pemberitahuan</h5>
                                    <div class="mb-4">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                <?= (!empty($userData['email_notifications']) && $userData['email_notifications']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                Pemberitahuan melalui E-mel
                                            </label>
                                            <div class="form-text">Terima pemberitahuan penting melalui e-mel</div>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="news_updates" name="news_updates" 
                                                <?= (!empty($userData['news_updates']) && $userData['news_updates']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="news_updates">
                                                Kemas Kini & Berita
                                            </label>
                                            <div class="form-text">Terima berita dan kemas kini tentang kursus terkini</div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Privasi</h5>
                                    <div class="mb-4">
                                        <label for="privacy_level" class="form-label">Tahap Privasi Profil</label>
                                        <select class="form-select" id="privacy_level" name="privacy_level">
                                            <option value="public" <?= (!empty($userData['privacy_level']) && $userData['privacy_level'] === 'public') ? 'selected' : '' ?>>Umum (Boleh dilihat oleh semua)</option>
                                            <option value="connections" <?= (empty($userData['privacy_level']) || $userData['privacy_level'] === 'connections') ? 'selected' : '' ?>>Sambungan Sahaja (Penyelia & Rakan)</option>
                                            <option value="private" <?= (!empty($userData['privacy_level']) && $userData['privacy_level'] === 'private') ? 'selected' : '' ?>>Peribadi (Hanya saya)</option>
                                        </select>
                                        <div class="form-text">Kawal siapa yang boleh melihat maklumat profil anda</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Simpan Tetapan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <small class="text-muted">© <?= date('Y') ?> e-Kursus ALPHA. Hak Cipta Terpelihara.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Handle profile picture upload click
            document.querySelector('.profile-img-upload').addEventListener('click', function() {
                document.getElementById('profile_picture').click();
            });
            
            // Show active tab based on URL hash
            if (window.location.hash) {
                const hash = window.location.hash;
                const tabTrigger = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
        });
    </script>
</body>
</html>