<?php
// register.php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $name = $_POST['name'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $ic = $_POST['ic'];
    $agency = $_POST['agency'];

    // Check duplicate email
    $check = $conn->prepare("SELECT * FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>alert('Email sudah wujud!');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();

        $user_id = $stmt->insert_id;
        $stmt2 = $conn->prepare("INSERT INTO profiles (user_id, name, phone, ic_number, agency) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("issss", $user_id, $name, $phone, $ic, $agency);
        $stmt2->execute();

        echo "<script>alert('Pendaftaran berjaya!'); window.location='login.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>Daftar Akaun</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <h2 class="mb-4">Daftar Akaun Baru</h2>
    <form method="POST" action="">
      <div class="mb-3"><label>Nama Penuh</label><input type="text" name="name" class="form-control" required></div>
      <div class="mb-3"><label>No. Telefon</label><input type="text" name="phone" class="form-control"></div>
      <div class="mb-3"><label>No. KP/IC</label><input type="text" name="ic" class="form-control"></div>
      <div class="mb-3"><label>Agensi</label><input type="text" name="agency" class="form-control"></div>
      <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="mb-3"><label>Katalaluan</label><input type="password" name="password" class="form-control" required></div>
      <button type="submit" class="btn btn-primary">Daftar</button>
    </form>
  </div>
</body>
</html>
