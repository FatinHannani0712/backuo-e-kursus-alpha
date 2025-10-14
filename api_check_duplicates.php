<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['courses']) || !isset($data['tahun']) || !isset($data['semester'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$tahun = $data['tahun'];
$semester = $data['semester'];
$courses = $data['courses'];

try {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "ecourses_apm";
    
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $duplicates = [];
    
    foreach ($courses as $index => $course) {
        // Extract siri from course name
        $siri = '';
        if (preg_match('/SIRI\s+(\d+\/\d+)/i', $course['nama_kursus'], $matches)) {
            $siri = 'SIRI ' . $matches[1];
        }
        
        // Check for duplicates
        $sql = "SELECT * FROM takwim_master 
                WHERE nama_kursus_takwim = :nama_kursus 
                AND siri = :siri 
                AND tahun = :tahun 
                AND semester = :semester";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama_kursus' => $course['nama_kursus'],
            ':siri' => $siri,
            ':tahun' => $tahun,
            ':semester' => $semester
        ]);
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $duplicates[] = [
                'index' => $index,
                'existing' => $existing,
                'new' => $course
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'duplicates' => $duplicates,
        'count' => count($duplicates)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>