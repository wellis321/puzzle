<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Session.php';
require_once '../includes/Game.php';

$session = new Session();
$game = new Game($session->getSessionId());
$sessionId = $session->getSessionId();

$db = Database::getInstance()->getConnection();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Rank Update</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .debug-box {
            background: #f5f5f5;
            border: 2px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
        .success { background: #e8f5e9; border-color: #2e7d32; }
        .error { background: #ffebee; border-color: #c62828; }
        .info { background: #e3f2fd; border-color: #2196F3; }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Rank Update Test</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="test-rank-update.php" class="active">Rank Test</a>
            </nav>
        </header>

        <main class="admin-main">
            <h2>Debug Rank System</h2>
            
            <div class="debug-box info">
                <strong>Session ID:</strong> <?php echo htmlspecialchars($sessionId); ?>
            </div>

            <h3>1. Check User Sessions</h3>
            <?php
            $sessionCheck = $db->prepare("SELECT * FROM user_sessions WHERE session_id = ?");
            $sessionCheck->execute([$sessionId]);
            $sessionRecord = $sessionCheck->fetch();
            ?>
            <div class="debug-box <?php echo $sessionRecord ? 'success' : 'error'; ?>">
                <?php if ($sessionRecord): ?>
                    ✓ Session exists in user_sessions<br>
                    Created: <?php echo $sessionRecord['created_at'] ?? 'N/A'; ?>
                <?php else: ?>
                    ✗ Session NOT found in user_sessions<br>
                    <strong>This is the problem! The session must exist before rank can be created.</strong>
                <?php endif; ?>
            </div>

            <h3>2. Check Completions</h3>
            <?php
            $completions = $db->prepare("
                SELECT c.*, p.title, p.difficulty 
                FROM completions c 
                JOIN puzzles p ON c.puzzle_id = p.id 
                WHERE c.session_id = ? 
                ORDER BY c.id DESC
            ");
            $completions->execute([$sessionId]);
            $completionList = $completions->fetchAll();
            ?>
            <div class="debug-box <?php echo count($completionList) > 0 ? 'success' : 'info'; ?>">
                <strong>Total Completions:</strong> <?php echo count($completionList); ?><br>
                <?php if (count($completionList) > 0): ?>
                    <strong>Recent Completions:</strong><br>
                    <?php foreach (array_slice($completionList, 0, 5) as $comp): ?>
                        - <?php echo htmlspecialchars($comp['title']); ?> 
                        (<?php echo $comp['difficulty']; ?>, 
                        <?php echo $comp['solved'] ? 'SOLVED' : 'not solved'; ?>, 
                        <?php echo $comp['score']; ?>)<br>
                    <?php endforeach; ?>
                <?php else: ?>
                    No completions found for this session.
                <?php endif; ?>
            </div>

            <h3>3. Check User Rank Record</h3>
            <?php
            $rankCheck = $db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
            $rankCheck->execute([$sessionId]);
            $rankRecord = $rankCheck->fetch();
            ?>
            <div class="debug-box <?php echo $rankRecord ? 'success' : 'error'; ?>">
                <?php if ($rankRecord): ?>
                    ✓ Rank record exists<br>
                    <strong>Current Rank:</strong> <?php echo htmlspecialchars($rankRecord['rank_name']); ?> (Level <?php echo $rankRecord['rank_level']; ?>)<br>
                    <strong>Total Completions:</strong> <?php echo $rankRecord['total_completions']; ?><br>
                    <strong>Solved:</strong> <?php echo $rankRecord['solved_count']; ?><br>
                    <strong>Streak:</strong> <?php echo $rankRecord['current_streak']; ?> days<br>
                    <strong>Last Updated:</strong> <?php echo $rankRecord['updated_at']; ?>
                <?php else: ?>
                    ✗ No rank record found<br>
                    <strong>Rank will be created when you complete a puzzle or click "Update Rank" below.</strong>
                <?php endif; ?>
            </div>

            <h3>4. Test Rank Update</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_rank">
                <button type="submit" class="btn btn-primary">🔄 Force Update Rank</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_rank') {
                try {
                    // Ensure session exists first
                    if (!$sessionRecord) {
                        $createSession = $db->prepare("INSERT INTO user_sessions (session_id) VALUES (?)");
                        $createSession->execute([$sessionId]);
                        echo '<div class="debug-box success">✓ Created session record</div>';
                    }
                    
                    // Update rank
                    $game->updateUserRank();
                    echo '<div class="debug-box success">✓ Rank updated successfully! Refresh page to see changes.</div>';
                } catch (Exception $e) {
                    echo '<div class="debug-box error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>

            <h3>5. Get Rank Progress (Current Display)</h3>
            <?php
            $rankProgress = $game->getRankProgress();
            ?>
            <div class="debug-box info">
                <strong>Current Rank:</strong> <?php echo htmlspecialchars($rankProgress['current_rank']); ?><br>
                <strong>Level:</strong> <?php echo $rankProgress['current_level']; ?><br>
                <strong>Progress:</strong> <?php echo $rankProgress['progress']; ?> / <?php echo $rankProgress['needed']; ?><br>
                <strong>Percentage:</strong> <?php echo $rankProgress['percentage']; ?>%<br>
                <strong>Next Rank:</strong> <?php echo htmlspecialchars($rankProgress['next_rank'] ?? 'MAX'); ?><br>
                <strong>Stats:</strong><br>
                - Total Completions: <?php echo $rankProgress['stats']['total_completions']; ?><br>
                - Current Streak: <?php echo $rankProgress['stats']['current_streak']; ?><br>
            </div>

            <h3>6. Raw Data</h3>
            <div class="debug-box">
                <pre><?php print_r($rankProgress); ?></pre>
            </div>
        </main>
    </div>
</body>
</html>

