<?php
include 'config.php';
session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token=? AND is_verified=0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conn->query("UPDATE users SET is_verified=1, verification_token=NULL WHERE verification_token='$token'");
        $_SESSION['success'] = "Akaun anda telah disahkan! Sila log masuk.";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "Token tidak sah atau akaun sudah disahkan.";
        header("Location: register.php");
    }
} else {
    header("Location: register.php");
}
exit;
?>