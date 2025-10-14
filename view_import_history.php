<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ecourses_apm";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT * FROM takwim_import_log ORDER BY imported_at DESC LIMIT 50";
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import History - Takwim Kursus</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 28px; }
        .back-btn {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); }
        .content { padding: 40px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 { font-size: 32px; margin-bottom: 10px; }
        .stat-card p { opacity: 0.9; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover { background: #f7fafc; }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
        
        .error-details {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #666;
            font-size: 13px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-data svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Import History</h1>
            <a href="admin_import_takwim_v3.php" class="back-btn">← Back to Import</a>
        </div>
        
        <div class="content">
            <?php if (isset($error)): ?>
                <div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 10px; margin-bottom: 20px;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($logs)): ?>
                <?php
                $totalImports = count($logs);
                $totalCourses = array_sum(array_column($logs, 'success_count'));
                $totalErrors = array_sum(array_column($logs, 'error_count'));
                $successRate = $totalCourses > 0 ? round(($totalCourses / ($totalCourses + $totalErrors)) * 100, 1) : 0;
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $totalImports; ?></h3>
                        <p>Total Imports</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalCourses; ?></h3>
                        <p>Courses Imported</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $totalErrors; ?></h3>
                        <p>Failed</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $successRate; ?>%</h3>
                        <p>Success Rate</p>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Filename</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Total</th>
                            <th>Success</th>
                            <th>Failed</th>
                            <th>Status</th>
                            <th>Imported By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['imported_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['filename']); ?></strong></td>
                                <td><?php echo $log['tahun']; ?></td>
                                <td><?php echo htmlspecialchars($log['semester']); ?></td>
                                <td><?php echo $log['total_courses']; ?></td>
                                <td><span class="badge badge-success"><?php echo $log['success_count']; ?> ✓</span></td>
                                <td>
                                    <?php if ($log['error_count'] > 0): ?>
                                        <span class="badge badge-error"><?php echo $log['error_count']; ?> ✗</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['error_count'] == 0): ?>
                                        <span class="badge badge-success">✓ Success</span>
                                    <?php elseif ($log['success_count'] > 0): ?>
                                        <span class="badge badge-warning">⚠ Partial</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">✗ Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $importedBy = $log['imported_by'] ?? 'Unknown';
                                    // Try to get user info
                                    if (is_numeric($importedBy)) {
                                        try {
                                            $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                                            $userStmt->execute([$importedBy]);
                                            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                                            if ($userInfo) {
                                                $importedBy = $userInfo['email'];
                                            }
                                        } catch (Exception $e) {
                                            // Keep original value
                                        }
                                    }
                                    echo htmlspecialchars($importedBy);
                                    ?>
                                </td>
                            </tr>
                            <?php if ($log['errors'] && $log['error_count'] > 0): ?>
                                <tr>
                                    <td colspan="9" style="background: #fff3cd; padding: 10px;">
                                        <strong>Errors:</strong>
                                        <div class="error-details" title="<?php echo htmlspecialchars($log['errors']); ?>">
                                            <?php echo htmlspecialchars(substr($log['errors'], 0, 200)); ?>
                                            <?php if (strlen($log['errors']) > 200): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h2>No Import History</h2>
                    <p>Start importing courses to see history here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>