<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $ic = htmlspecialchars($_POST['ic']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Kata laluan tidak sepadan!";
        header("Location: register.php");
        exit;
    }

    // Check password strength
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Kata laluan mesti mengandungi 8 aksara minimum";
        header("Location: register.php");
        exit;
    }

    // Check duplicate email or IC
    $check = $conn->prepare("SELECT id FROM users WHERE email=? OR no_kp=?");
    $check->bind_param("ss", $email, $ic);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Email atau No. KP sudah wujud!";
        header("Location: register.php");
        exit;
    }

    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    
    // Hash the password properly
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Changed to PASSWORD_DEFAULT

    // Insert user with verification status
    $stmt = $conn->prepare("INSERT INTO users (email, password, no_kp, verification_token, is_verified) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("ssss", $email, $hashed_password, $ic, $verification_token);
    
    if ($stmt->execute()) {
        // Send verification email
        $verification_link = "https://".$_SERVER['HTTP_HOST']."/verify.php?token=$verification_token";
        $subject = "Pengesahan Akaun e-Kursus ALPHA";
        $message = "Sila klik pautan berikut untuk mengesahkan akaun anda:\n\n$verification_link";
        $headers = "From: no-reply@".$_SERVER['HTTP_HOST'];
        
        mail($email, $subject, $message, $headers);
        
        $_SESSION['success'] = "Pendaftaran berjaya! Sila periksa email anda untuk pengesahan.";
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['error'] = "Ralat sistem. Sila cuba lagi.";
        header("Location: register.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>Daftar Akaun | e-Kursus ALPHA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
    .register-container {
      max-width: 500px;
      width: 100%;
      margin: 0 auto;
    }
    .register-card {
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      background-color: rgba(255,255,255,0.95);
    }
    .register-header {
      background-color: var(--primary-color);
      color: white;
      padding: 20px;
      text-align: center;
    }
    .register-body {
      padding: 30px;
    }
    .form-control {
      padding: 12px 15px;
      border-radius: 6px;
      border: 1px solid #ced4da;
    }
    .btn-register {
      background-color: var(--primary-color);
      border: none;
      padding: 12px;
      font-weight: 600;
      transition: all 0.3s;
    }
    .btn-register:hover {
      background-color: #0a2e4a;
      transform: translateY(-2px);
    }
    .register-logo {
      width: 80px;
      margin-bottom: 15px;
    }
    .input-group-text {
      background-color: var(--primary-color);
      color: white;
      border: none;
    }
    .password-strength {
      height: 5px;
      background: #e9ecef;
      margin-top: 5px;
      border-radius: 5px;
      overflow: hidden;
    }
    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: width 0.3s;
    }
  </style>
</head>
<body>
<div class="container register-container">
  <div class="register-card">
    <div class="register-header">
      <img src="assets/img/logo_apm.jpeg" alt="ALPHA Logo" class="register-logo">
      <h3><i class="fas fa-user-plus"></i> DAFTAR AKAUN BARU</h3>
    </div>
    <div class="register-body">
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="register.php">
        <!-- IC Number -->
        <div class="mb-3">
          <label for="ic" class="form-label">No. Kad Pengenalan</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
            <input type="text" class="form-control" id="ic" name="ic" 
                   placeholder="Contoh: 901231025678" pattern="[0-9]{12}" title="12 digit tanpa '-'" required>
          </div>
        </div>
        
        <!-- Email -->
        <div class="mb-3">
          <label for="email" class="form-label">Alamat Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" 
                   placeholder="cth: nama@example.com" required>
          </div>
        </div>
        
        <!-- Password -->
        <div class="mb-3">
          <label for="password" class="form-label">Reka Kata Laluan</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" 
                   placeholder="Minimum 8 aksara" required>
          </div>
          <div class="password-strength">
            <div class="password-strength-bar" id="password-strength-bar"></div>
          </div>
        </div>
        
        <!-- Confirm Password -->
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Sahkan Kata Laluan</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                   placeholder="Taip semula kata laluan" required>
          </div>
        </div>
        
        <!-- Terms Checkbox -->
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
          <label class="form-check-label" for="terms">Saya bersetuju dengan Terma dan Syarat</label>
        </div>
        
        <button type="submit" class="btn btn-register btn-primary w-100">
          <i class="fas fa-user-plus me-2"></i> Daftar Sekarang
        </button>
        
        <div class="mt-3 text-center">
          Sudah ada akaun? <a href="login.php">Log Masuk di sini</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Password strength indicator
  document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength-bar');
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    // Update strength bar
    const width = (strength / 5) * 100;
    strengthBar.style.width = width + '%';
    
    // Update color
    if (strength <= 2) {
      strengthBar.style.backgroundColor = '#dc3545';
    } else if (strength <= 4) {
      strengthBar.style.backgroundColor = '#ffc107';
    } else {
      strengthBar.style.backgroundColor = '#28a745';
    }
  });

  // Form validation
  document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
      e.preventDefault();
      alert('Kata laluan tidak sepadan!');
    }
    
    if (!document.getElementById('terms').checked) {
      e.preventDefault();
      alert('Sila setuju dengan Terma dan Syarat');
    }
  });
</script>
</body>
</html>