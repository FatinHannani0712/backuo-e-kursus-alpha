<?php
session_start();
require_once 'config.php';

// Admin access only
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Handle role changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $newRole, $userId);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success'] = "Peranan pengguna berjaya dikemaskini";
    header("Location: manage_users.php");
    exit();
}

// Get all users
$users = [];
$result = $conn->query("SELECT id, no_kp, role FROM users ORDER BY role, no_kp");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= htmlspecialchars($pageTitle) ?> | e-Kursus ALPHA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color:#0C3C60; }
        body { font-family:'Segoe UI',sans-serif; background:#f5f5f5; }
        .dashboard-header { background:var(--primary-color); color:white; padding:20px 0; margin-bottom:30px; }
        .card { border:none; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-bottom:20px; }
        .card-header { background:var(--primary-color); color:white; font-weight:600; }
        .quick-actions .btn { margin:5px; }
        .profile-summary img { width:100px; height:100px; object-fit:cover; border-radius:50%; border:3px solid var(--primary-color); }
    </style>
</head>
<body>
    <!-- Same navbar as dashboard -->

    <div class="container mt-4">
        <h2><i class="fas fa-users-cog me-2"></i>Pengurusan Pengguna</h2>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>Senarai Pengguna
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No. KP</th>
                            <th>Nama</th>
                            <th>Peranan</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['no_kp']) ?></td>
                            <td><?= htmlspecialchars($user['nama']) ?></td>
                            <td>
                                <?php 
                                $roleNames = [
                                    'user' => 'Ahli Biasa',
                                    'penyelia' => 'Penyelia',
                                    'admin' => 'Admin'
                                ];
                                echo $roleNames[$user['role']] ?? $user['role'];
                                ?>
                            </td>
                            <td>
                                <form method="post" class="form-inline">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="new_role" class="form-select form-select-sm">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Ahli Biasa</option>
                                        <option value="penyelia" <?= $user['role'] === 'penyelia' ? 'selected' : '' ?>>Penyelia</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="change_role" class="btn btn-sm btn-primary ms-2">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>