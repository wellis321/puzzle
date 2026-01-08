<?php
require_once 'config.php';
require_once 'includes/Auth.php';
require_once 'includes/Session.php';
require_once 'includes/Subscription.php';
require_once 'includes/Game.php';

$auth = new Auth();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $auth->getUserId();
$currentUser = $auth->getCurrentUser();

// Get subscription info
$subscription = new Subscription();
$subscriptionDetails = $subscription->getSubscriptionDetails($userId);
$isPremium = $subscription->isPremium($userId);

// Get rank progress
$session = new Session();
$game = new Game($session->getSessionId(), $userId);
$rankProgress = $game->getRankProgress();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .profile-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .profile-section h2 {
            color: #8b4513;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stat-grid {
                grid-template-columns: 1fr;
            }
        }
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #8b4513;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .premium-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #8b4513;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            margin-left: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #8b4513;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #6b3413;
        }
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-nav a {
            display: inline-block;
            padding: 10px 18px;
            background: #8b4513;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid #8b4513;
        }
        .header-nav a:hover {
            background: #6b3413;
            border-color: #6b3413;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(139, 69, 19, 0.3);
        }
        .header-nav a.btn-outline {
            background: transparent;
            color: #8b4513;
            border-color: #8b4513;
        }
        .header-nav a.btn-outline:hover {
            background: #8b4513;
            color: white;
        }
        @media (max-width: 768px) {
            .header-nav {
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }
            .header-nav a {
                width: 100%;
                text-align: center;
            }
        }
        .subscription-info {
            padding: 20px;
            background: #f0f8ff;
            border: 2px solid #2196F3;
            border-radius: 6px;
        }
        .subscription-info.premium {
            background: #fff9e6;
            border-color: #ffd700;
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
                    <div class="header-nav">
                        <a href="index.php" class="btn-outline">← Back to Game</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="profile-container">
            <div class="profile-header">
                <h1 style="color: #8b4513; font-size: 36px;">Your Profile</h1>
                <p style="color: #666; font-size: 18px;">
                    <?php echo htmlspecialchars($auth->getDisplayName()); ?>
                    <?php if ($isPremium): ?>
                        <span class="premium-badge">PREMIUM</span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="profile-section">
                <h2>Detective Rank</h2>
                <?php if (!isset($rankProgress['table_missing']) || !$rankProgress['table_missing']): ?>
                    <div style="text-align: center; padding: 30px;">
                        <div style="font-size: 48px; color: #8b4513; margin-bottom: 10px;">🔍</div>
                        <h3 style="color: #8b4513; font-size: 28px; margin-bottom: 20px;">
                            <?php echo htmlspecialchars($rankProgress['current_rank']); ?>
                        </h3>
                        <?php if (!isset($rankProgress['max_rank']) || !$rankProgress['max_rank']): ?>
                            <div style="margin: 20px 0;">
                                <div style="background: #f0f0f0; height: 30px; border-radius: 15px; overflow: hidden; margin-bottom: 10px;">
                                    <div style="background: #8b4513; height: 100%; width: <?php echo $rankProgress['percentage']; ?>%; transition: width 0.3s;"></div>
                                </div>
                                <p style="color: #666;">
                                    <?php echo $rankProgress['progress']; ?> wins / <?php echo $rankProgress['needed']; ?> to reach <?php echo htmlspecialchars($rankProgress['next_rank']); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <p style="color: #8b4513; font-weight: 700;">Maximum Rank Achieved!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Rank system is being set up...</p>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <h2>Statistics</h2>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $rankProgress['stats']['solved_count'] ?? 0; ?></div>
                        <div class="stat-label">Cases Solved</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $rankProgress['stats']['current_streak'] ?? 0; ?></div>
                        <div class="stat-label">Day Win Streak</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $rankProgress['stats']['perfect_scores'] ?? 0; ?></div>
                        <div class="stat-label">Perfect Scores</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $rankProgress['stats']['total_completions'] ?? 0; ?></div>
                        <div class="stat-label">Total Cases</div>
                    </div>
                </div>
            </div>

            <div class="profile-section">
                <h2>Subscription</h2>
                <div class="subscription-info <?php echo $isPremium ? 'premium' : ''; ?>">
                    <?php if ($isPremium && $subscriptionDetails): ?>
                        <h3 style="color: #8b4513; margin-bottom: 15px;">
                            Premium Active
                            <?php if ($subscriptionDetails['subscription_expires_at']): ?>
                                <span style="font-size: 14px; font-weight: normal; color: #666;">
                                    (expires <?php echo date('M j, Y', strtotime($subscriptionDetails['subscription_expires_at'])); ?>)
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p style="margin-bottom: 15px;">You have access to all premium features:</p>
                        <ul style="margin-left: 20px; margin-bottom: 20px;">
                            <li>Ad-free experience</li>
                            <li>Advanced statistics</li>
                            <li>Puzzle archive access</li>
                            <li>Leaderboards</li>
                            <li>Custom badge themes</li>
                        </ul>
                        <a href="subscribe.php?action=cancel" class="btn btn-secondary">Manage Subscription</a>
                    <?php else: ?>
                        <h3 style="color: #8b4513; margin-bottom: 15px;">Free Account</h3>
                        <p style="margin-bottom: 20px;">
                            Upgrade to Premium to unlock advanced features and remove ads!
                        </p>
                        <a href="subscribe.php" class="btn">Upgrade to Premium</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-section">
                <h2>Account Settings</h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($currentUser['email']); ?></p>
                <?php if ($currentUser['username']): ?>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($currentUser['username']); ?></p>
                <?php endif; ?>
                <p><strong>Member since:</strong> <?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></p>
            </div>
        </main>
    </div>
</body>
</html>

