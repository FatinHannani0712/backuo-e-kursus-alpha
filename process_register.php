<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $icnumber = trim($_POST['icnumber']);
    $email = trim($_POST['email']);
    $confirm_email = trim($_POST['confirm_email']);
    $password = $_POST['password'];

    if ($email !== $confirm_email) {
        echo "<script>alert('Email tidak sepadan!'); window.history.back();</script>";
        exit();
    }

    $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        echo "<script>alert('Email telah didaftarkan.'); window.history.back();</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO users (email, password, ic_number, verification_token) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $hashed_password, $icnumber, $verification_token);

    if ($stmt->execute()) {
        $verifyLink = "http://localhost/e-kursus-alpha/verify.php?token=" . $verification_token;

        // Send verification email
        $mail = new PHPMailer(true);
        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // 
            $mail->SMTPAuth = true;
            $mail->Username = 'youremail@gmail.com'; // 🔁 ganti dengan email anda
            $mail->Password = 'yourpassword';         // 🔁 app password (not Gmail login password)
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Email content
            $mail->setFrom('youremail@gmail.com', 'e-Kursus ALPHA');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Pengesahan Akaun e-Kursus ALPHA';
            $mail->Body = "Hi, sila klik pautan di bawah untuk mengaktifkan akaun anda:<br><br>
                           <a href='$verifyLink'>$verifyLink</a>";

            $mail->send();
            echo "<script>alert('Daftar berjaya! Sila semak email anda untuk pengesahan.'); window.location='login.php';</script>";
        } catch (Exception $e) {
            echo "<script>alert('Ralat emel: {$mail->ErrorInfo}'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Gagal daftar.'); window.history.back();</script>";
    }
}
?>
