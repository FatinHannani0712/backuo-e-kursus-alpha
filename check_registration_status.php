<?php
/**
 * Check if registration is open for a course
 * Returns array with status and days remaining
 */
function checkRegistrationStatus($conn, $kursus_id) {
    $stmt = $conn->prepare("
        SELECT 
            registration_status,
            registration_open_date,
            registration_close_date,
            nama_kursus
        FROM kursus 
        WHERE kursus_id = ?
    ");
    
    $stmt->bind_param("i", $kursus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();
    
    if (!$course) {
        return [
            'is_open' => false,
            'message' => 'Kursus tidak dijumpai',
            'days_remaining' => 0
        ];
    }
    
    // Check if registration status is open
    if ($course['registration_status'] !== 'open') {
        return [
            'is_open' => false,
            'message' => 'Pendaftaran belum dibuka',
            'days_remaining' => 0
        ];
    }
    
    // Check if current date is within registration period
    $today = date('Y-m-d');
    $close_date = $course['registration_close_date'];
    
    if ($today > $close_date) {
        // Registration period has expired - auto close it
        $update = $conn->prepare("UPDATE kursus SET registration_status = 'closed' WHERE kursus_id = ?");
        $update->bind_param("i", $kursus_id);
        $update->execute();
        $update->close();
        
        return [
            'is_open' => false,
            'message' => 'Tempoh pendaftaran telah tamat',
            'days_remaining' => 0
        ];
    }
    
    // Calculate days remaining
    $today_time = strtotime($today);
    $close_time = strtotime($close_date);
    $days_remaining = ceil(($close_time - $today_time) / 86400); // 86400 seconds in a day
    
    return [
        'is_open' => true,
        'message' => 'Pendaftaran dibuka',
        'days_remaining' => $days_remaining,
        'close_date' => $close_date,
        'close_date_formatted' => date('d/m/Y', strtotime($close_date))
    ];
}
?>