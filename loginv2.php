<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start secure session
session_start([
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include database configuration
include 'config.php';

// Redirect if already logged in
if (isset($_SESSION['loggedin'])) {
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$no_kp = $password = '';
$error = '';
$account_exists = true; // To check if No KP is registered

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $no_kp = isset($_POST['no_kp']) ? trim($_POST['no_kp']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        // Validate No KP format (12 digits)
        if (!preg_match('/^[0-9]{12}$/', $no_kp)) {
            $error = 'No Kad Pengenalan mesti mengandungi 12 digit angka';
        } else {
            // Check if account exists first
            $stmt = $conn->prepare("SELECT id, password, email, role FROM users WHERE no_kp = ?");
            $stmt->bind_param("s", $no_kp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $account_exists = false;
                $error = 'No Kad Pengenalan belum didaftar. <a href="register.php">Daftar di sini</a>';
            } else {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Authentication successful
                    session_regenerate_id(true); // Prevent session fixation
                    
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['no_kp'] = $no_kp;
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role']; //  store role

                    
                    
                    // "Remember me" functionality
                    if (isset($_POST['remember'])) {
                        setcookie('remember_user', $no_kp, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    }
                    
                    // Redirect by role
                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } elseif ($user['role'] === 'penyelia') {
                        header('Location: penyelia_dashboard.php');
                    } else {
                        header('Location: dashboard.php'); // peserta
                    }
                    exit;
                } else {
                    $error = 'No Kad Pengenalan atau kata laluan tidak sah!';
                }
            }
            $stmt->close();
        }
    }
}

// Check for "remember me" cookie
if (isset($_COOKIE['remember_user'])) {
    $no_kp = $_COOKIE['remember_user'];
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - ALPHA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0C3C60;
            --secondary-color: #17a2b8;
            --accent-color: #ffc107;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('assets/img/slide2.jpeg') no-repeat center center;
            background-size: cover;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .login-header h3 {
            margin: 10px 0 0;
            font-weight: 600;
        }
        .login-logo {
            height: 80px;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 30px;
        }
        .btn-login {
            padding: 10px;
            font-weight: 600;
            background-color: var(--primary-color);
            border: none;
        }
        .btn-login:hover {
            background-color: #0a2e4a;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #6c757d;
            font-weight: 500;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider::before {
            margin-right: 10px;
        }
        .divider::after {
            margin-left: 10px;
        }
        .input-group-text {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        .password-toggle {
            cursor: pointer;
            background-color: #f8f9fa !important;
            border-left: 0 !important;
        }
        .password-toggle:hover {
            background-color: #e9ecef !important;
        }
        .alert-link {
            font-weight: 600;
            text-decoration: underline;
        }
        
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/img/logo_apm.jpeg" alt="ALPHA Logo" class="login-logo">
                <h3><i class="fas fa-sign-in-alt"></i> LOG MASUK</h3>
            </div>
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                        <?php if (!$account_exists): ?>
                            <br><small><a href="register.php" class="alert-link">Daftar Akaun Baru</a></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="no_kp" class="form-label">No Kad Pengenalan</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="no_kp" name="no_kp" 
                                   placeholder="cth: 901212145678" required
                                   value="<?php echo htmlspecialchars($no_kp); ?>"
                                   pattern="[0-9]{12}" title="Masukkan 12 digit No Kad Pengenalan">
                        </div>
                        <div class="form-text">12 digit tanpa tanda sempang (-)</div>
                    </div>
                    
                    <div class="mb-3">
                      <label for="password" class="form-label">Kata Laluan</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button type="button" class="input-group-text password-toggle" id="togglePassword">
                          <i class="fas fa-eye"></i> <!-- This will show the eye icon -->
                        </button>
                      </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                              <?php echo isset($_COOKIE['remember_user']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="remember">Ingat saya</label>
                        <a href="forgot-password.php" class="float-end">Lupa kata laluan?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i> Log Masuk
                    </button>
                    
                    <div class="divider">ATAU</div>
                    
                    <a href="register.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-plus me-2"></i> Daftar Akaun Baru
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Toggle Script -->
    <script>
      document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        // Toggle icon and input type
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          passwordInput.type = 'password';
          icon.classList.replace('fa-eye-slash', 'fa-eye');
        }

        // Auto-format No KP (remove non-numeric characters)
        document.getElementById('no_kp').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>