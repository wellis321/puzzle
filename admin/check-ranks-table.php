<?php
/**
 * Check if user_ranks table exists
 */

require_once '../config.php';
require_once '../includes/Database.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Checking user_ranks Table</h2>";

// Check if table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'user_ranks'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: green; font-weight: bold;'>✅ Table 'user_ranks' EXISTS</p>";
        
        // Show table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $db->query("DESCRIBE user_ranks");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Try a simple query
        echo "<h3>Testing Query:</h3>";
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM user_ranks");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p style='color: green;'>✅ Query successful! Found " . $result['count'] . " rank records.</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Table 'user_ranks' DOES NOT EXIST</p>";
        echo "<p>Please run: <code>database/add-ranks-table.sql</code> or <code>database/add-ranks-table-simple.sql</code></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error checking table: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// List all tables
echo "<hr><h3>All Tables in Database:</h3>";
try {
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        $highlight = ($table === 'user_ranks') ? " style='color: green; font-weight: bold;'" : "";
        echo "<li{$highlight}>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error listing tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>

