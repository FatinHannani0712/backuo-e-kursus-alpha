<?php
session_start();
require_once 'config.php';

echo "<h3>Database Structure Debug</h3>";

try {
    // Check all tables in database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Tables in database:</h4>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
    
    // Check structure of takwim-related tables
    foreach ($tables as $table) {
        if (strpos(strtolower($table), 'takwim') !== false || strpos(strtolower($table), 'kursus') !== false) {
            echo "<h4>Structure of table: $table</h4>";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Show sample data
            echo "<h5>Sample data from $table (first 5 rows):</h5>";
            $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
            $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($sampleData)) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr>";
                foreach (array_keys($sampleData[0]) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                foreach ($sampleData as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No data found in this table</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>