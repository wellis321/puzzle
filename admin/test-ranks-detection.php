<?php
/**
 * Test rank detection logic
 */

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Session.php';
require_once '../includes/Game.php';

$session = new Session();
$game = new Game($session->getSessionId());

echo "<h2>Testing Rank Detection</h2>";

// Test 1: Check if table exists
echo "<h3>Test 1: Table Exists Check</h3>";
$db = Database::getInstance()->getConnection();
try {
    $stmt = $db->query("SELECT 1 FROM user_ranks LIMIT 1");
    echo "<p style='color: green;'>✅ Table exists</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Table doesn't exist: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test 2: Test getUserRank()
echo "<h3>Test 2: getUserRank()</h3>";
try {
    $rank = $game->getUserRank();
    if ($rank === null) {
        echo "<p style='color: red;'>❌ getUserRank() returned NULL</p>";
        echo "<p>This means the code thinks the table doesn't exist.</p>";
    } else {
        echo "<p style='color: green;'>✅ getUserRank() returned data</p>";
        echo "<pre>" . print_r($rank, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Test getRankProgress()
echo "<h3>Test 3: getRankProgress()</h3>";
try {
    $progress = $game->getRankProgress();
    if (isset($progress['table_missing']) && $progress['table_missing']) {
        echo "<p style='color: red;'>❌ getRankProgress() says table is missing</p>";
        echo "<pre>" . print_r($progress, true) . "</pre>";
    } else {
        echo "<p style='color: green;'>✅ getRankProgress() works correctly</p>";
        echo "<pre>" . print_r($progress, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Try direct query
echo "<h3>Test 4: Direct Query Test</h3>";
try {
    $sessionId = $session->getSessionId();
    echo "<p>Session ID: " . htmlspecialchars($sessionId) . "</p>";
    
    $stmt = $db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Found rank record</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠️ No rank record found for this session (will be created on first puzzle completion)</p>";
    }
    
    // Try to insert
    echo "<h3>Test 5: Try INSERT</h3>";
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO user_ranks (session_id, rank_name, rank_level) VALUES (?, 'Novice Detective', 1)");
        $stmt->execute([$sessionId]);
        echo "<p style='color: green;'>✅ INSERT successful</p>";
        
        // Check again
        $stmt = $db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            echo "<p style='color: green;'>✅ Rank record now exists</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ INSERT failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>

