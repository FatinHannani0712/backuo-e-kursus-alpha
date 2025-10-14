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

if (!$data || !isset($data['import_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$importId = $data['import_id'];

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    // Get import log details
    $logSql = "SELECT * FROM takwim_import_log WHERE id = :import_id";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([':import_id' => $importId]);
    $importLog = $logStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$importLog) {
        throw new Exception('Import log not found');
    }
    
    // Delete imported courses (you might need to adjust this based on your actual data relationships)
    $deleteSql = "DELETE FROM takwim_master WHERE import_batch_id = :import_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute([':import_id' => $importId]);
    
    // Delete the import log
    $deleteLogSql = "DELETE FROM takwim_import_log WHERE id = :import_id";
    $deleteLogStmt = $pdo->prepare($deleteLogSql);
    $deleteLogStmt->execute([':import_id' => $importId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rollback completed successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>