<?php
require_once 'config.php';

function findProblematicRecord() {
    $pdo = connect_db();
    
    // Test each table for problematic data
    $tables = ['content', 'seasons', 'episodes', 'servers'];
    
    foreach ($tables as $table) {
        echo "Checking table: $table\n";
        $stmt = $pdo->query("SELECT * FROM $table");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $key => $value) {
                if (is_string($value)) {
                    // Test if this value causes JSON issues
                    $test = json_encode([$key => $value]);
                    if ($test === false) {
                        echo "PROBLEM FOUND in table $table, ID: {$row['id']}, field: $key\n";
                        echo "Problematic value: " . substr($value, 0, 100) . "\n";
                        echo "JSON error: " . json_last_error_msg() . "\n\n";
                    }
                }
            }
        }
    }
}

findProblematicRecord();
?>