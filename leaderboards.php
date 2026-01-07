<?php
require_once 'config.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Database.php';

$auth = new Auth();
$subscription = new Subscription();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $auth->getUserId();
$isPremium = $subscription->isPremium($userId);

// Check premium access
if (!$isPremium) {
    header('Location: subscribe.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$leaderboardType = $_GET['type'] ?? 'rank'; // rank, streak, solved

// Get leaderboards based on type
$leaders = [];
$userRank = null;

switch ($leaderboardType) {
    case 'rank':
        // Top ranked detectives
        $stmt = $db->query("
            SELECT 
                ur.rank_level,
                ur.rank_name,
                ur.solved_count,
                u.username,
                u.email,
                CASE WHEN ur.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM user_ranks ur
            JOIN users u ON ur.user_id = u.id
            WHERE ur.user_id IS NOT NULL
            ORDER BY ur.rank_level DESC, ur.solved_count DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $leaders = $stmt->fetchAll();
        
        // Find user's rank
        foreach ($leaders as $index => $leader) {
            if ($leader['is_current_user']) {
                $userRank = $index + 1;
                break;
            }
        }
        break;
        
    case 'streak':
        // Highest streaks
        $stmt = $db->query("
            SELECT 
                ur.current_streak,
                ur.best_streak,
                ur.solved_count,
                u.username,
                u.email,
                CASE WHEN ur.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM user_ranks ur
            JOIN users u ON ur.user_id = u.id
            WHERE ur.user_id IS NOT NULL AND ur.current_streak > 0
            ORDER BY ur.current_streak DESC, ur.best_streak DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $leaders = $stmt->fetchAll();
        
        foreach ($leaders as $index => $leader) {
            if ($leader['is_current_user']) {
                $userRank = $index + 1;
                break;
            }
        }
        break;
        
    case 'solved':
        // Most puzzles solved
        $stmt = $db->query("
            SELECT 
                ur.solved_count,
                ur.rank_name,
                u.username,
                u.email,
                CASE WHEN ur.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM user_ranks ur
            JOIN users u ON ur.user_id = u.id
            WHERE ur.user_id IS NOT NULL
            ORDER BY ur.solved_count DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $leaders = $stmt->fetchAll();
        
        foreach ($leaders as $index => $leader) {
            if ($leader['is_current_user']) {
                $userRank = $index + 1;
                break;
            }
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .leaderboard-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
        }
        .leaderboard-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .leaderboard-tabs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .leaderboard-tab {
            padding: 12px 24px;
            background: #f0f0f0;
            border: 2px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: all 0.3s;
        }
        .leaderboard-tab:hover {
            background: #e0e0e0;
        }
        .leaderboard-tab.active {
            background: #8b4513;
            color: white;
            border-color: #8b4513;
        }
        .leaderboard-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .leaderboard-row {
            display: grid;
            grid-template-columns: 80px 1fr 200px;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        .leaderboard-row.header {
            background: #8b4513;
            color: white;
            font-weight: 700;
        }
        .leaderboard-row.current-user {
            background: #fff9e6;
            border-left: 4px solid #ffd700;
        }
        .rank-number {
            font-size: 24px;
            font-weight: 700;
            color: #8b4513;
        }
        .rank-number.current-user {
            color: #ffd700;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-name {
            font-weight: 600;
            color: #333;
        }
        .user-rank-badge {
            padding: 4px 10px;
            background: #f0f0f0;
            border-radius: 12px;
            font-size: 12px;
            color: #666;
        }
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #8b4513;
            text-align: right;
        }
        .medal {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-top">
                <div class="header-left">
                    <h1><a href="index.php" style="color: #8b4513; text-decoration: none;"><?php echo APP_NAME; ?></a></h1>
                </div>
                <div class="header-right">
                    <a href="profile.php" style="color: #8b4513; text-decoration: none; margin-right: 15px;">Profile</a>
                    <a href="index.php" style="color: #8b4513; text-decoration: none;">← Back to Game</a>
                </div>
            </div>
        </header>

        <main class="leaderboard-container">
            <div class="leaderboard-header">
                <h1 style="color: #8b4513; font-size: 36px;">Leaderboards</h1>
                <p style="color: #666;">Compete with other detectives!</p>
            </div>

            <div class="leaderboard-tabs">
                <a href="?type=rank" class="leaderboard-tab <?php echo $leaderboardType === 'rank' ? 'active' : ''; ?>">
                    Top Ranked
                </a>
                <a href="?type=streak" class="leaderboard-tab <?php echo $leaderboardType === 'streak' ? 'active' : ''; ?>">
                    Highest Streaks
                </a>
                <a href="?type=solved" class="leaderboard-tab <?php echo $leaderboardType === 'solved' ? 'active' : ''; ?>">
                    Most Solved
                </a>
            </div>

            <?php if ($userRank !== null): ?>
                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                    <strong>Your Rank:</strong> #<?php echo $userRank; ?> 
                    <?php if ($userRank <= 3): ?>
                        <span class="medal">🏆</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="leaderboard-table">
                <div class="leaderboard-row header">
                    <div>Rank</div>
                    <div>Detective</div>
                    <div style="text-align: right;">
                        <?php 
                        echo $leaderboardType === 'rank' ? 'Rank Level' : 
                            ($leaderboardType === 'streak' ? 'Current Streak' : 'Solved');
                        ?>
                    </div>
                </div>
                
                <?php foreach ($leaders as $index => $leader): ?>
                    <div class="leaderboard-row <?php echo $leader['is_current_user'] ? 'current-user' : ''; ?>">
                        <div class="rank-number <?php echo $leader['is_current_user'] ? 'current-user' : ''; ?>">
                            <?php 
                            $rank = $index + 1;
                            if ($rank === 1) echo '🥇';
                            elseif ($rank === 2) echo '🥈';
                            elseif ($rank === 3) echo '🥉';
                            else echo '#' . $rank;
                            ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($leader['username'] ?: explode('@', $leader['email'])[0]); ?></span>
                            <?php if (isset($leader['rank_name'])): ?>
                                <span class="user-rank-badge"><?php echo htmlspecialchars($leader['rank_name']); ?></span>
                            <?php endif; ?>
                            <?php if ($leader['is_current_user']): ?>
                                <span style="color: #ffd700; font-weight: 700;">(You)</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value">
                            <?php 
                            if ($leaderboardType === 'rank') {
                                echo 'Level ' . $leader['rank_level'];
                            } elseif ($leaderboardType === 'streak') {
                                echo $leader['current_streak'] . ' days';
                            } else {
                                echo $leader['solved_count'] . ' puzzles';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>

