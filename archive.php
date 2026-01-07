<?php
require_once 'config.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Puzzle.php';

$auth = new Auth();
$subscription = new Subscription();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $auth->getUserId();
$isPremium = $subscription->isPremium($userId);

// Check if user has premium access
if (!$isPremium) {
    // Free users see last 7 days only
    $daysToShow = 7;
} else {
    // Premium users see all puzzles
    $daysToShow = null;
}

// Get filter parameters
$filterDifficulty = $_GET['difficulty'] ?? 'all';
$filterDate = $_GET['date'] ?? null;

$puzzle = new Puzzle();

// Get puzzles
if ($filterDate) {
    $puzzles = $puzzle->getPuzzlesByDate($filterDate);
} elseif ($daysToShow) {
    // Get last N days
    $puzzles = [];
    for ($i = 0; $i < $daysToShow; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $datePuzzles = $puzzle->getPuzzlesByDate($date);
        foreach ($datePuzzles as $p) {
            $puzzles[] = $p;
        }
    }
} else {
    // Premium: Get all puzzles
    $puzzles = $puzzle->getAllPuzzles();
}

// Filter by difficulty if specified
if ($filterDifficulty !== 'all') {
    $puzzles = array_filter($puzzles, function($p) use ($filterDifficulty) {
        return $p['difficulty'] === $filterDifficulty;
    });
}

// Group by date
$puzzlesByDate = [];
foreach ($puzzles as $p) {
    $date = $p['puzzle_date'];
    if (!isset($puzzlesByDate[$date])) {
        $puzzlesByDate[$date] = [];
    }
    $puzzlesByDate[$date][] = $p;
}

// Sort dates descending
krsort($puzzlesByDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puzzle Archive - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .archive-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
        }
        .archive-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .archive-filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .archive-filters form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .archive-filters select,
        .archive-filters input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .archive-filters button {
            padding: 10px 20px;
            background: #8b4513;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .puzzle-date-group {
            margin-bottom: 40px;
        }
        .puzzle-date-header {
            font-size: 20px;
            font-weight: 700;
            color: #8b4513;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }
        .puzzle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .puzzle-card {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .puzzle-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #8b4513;
        }
        .puzzle-card h3 {
            color: #8b4513;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .puzzle-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        .puzzle-difficulty {
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        .puzzle-difficulty.easy {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .puzzle-difficulty.medium {
            background: #fff3e0;
            color: #e65100;
        }
        .puzzle-difficulty.hard {
            background: #ffebee;
            color: #c62828;
        }
        .puzzle-card .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #8b4513;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
        }
        .premium-notice {
            background: #fff9e6;
            border: 2px solid #ffd700;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
        }
        .premium-notice h3 {
            color: #8b4513;
            margin-bottom: 15px;
        }
        .premium-notice .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #8b4513;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
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

        <main class="archive-container">
            <div class="archive-header">
                <h1 style="color: #8b4513; font-size: 36px;">Puzzle Archive</h1>
                <?php if (!$isPremium): ?>
                    <p style="color: #666;">Showing last 7 days (Free)</p>
                <?php else: ?>
                    <p style="color: #666;">Full archive access (Premium)</p>
                <?php endif; ?>
            </div>

            <?php if (!$isPremium): ?>
                <div class="premium-notice">
                    <h3>Unlock Full Archive with Premium</h3>
                    <p>Free users can view the last 7 days. Upgrade to Premium to access the complete puzzle archive!</p>
                    <a href="subscribe.php" class="btn">Upgrade to Premium</a>
                </div>
            <?php endif; ?>

            <div class="archive-filters">
                <form method="GET">
                    <label>
                        Difficulty:
                        <select name="difficulty">
                            <option value="all" <?php echo $filterDifficulty === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="easy" <?php echo $filterDifficulty === 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $filterDifficulty === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $filterDifficulty === 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </label>
                    <label>
                        Date:
                        <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate ?? ''); ?>">
                    </label>
                    <button type="submit">Filter</button>
                    <a href="archive.php" style="padding: 10px 20px; background: #ddd; color: #333; text-decoration: none; border-radius: 4px; font-weight: 600;">Clear</a>
                </form>
            </div>

            <?php if (empty($puzzlesByDate)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <p style="font-size: 20px; color: #666;">No puzzles found matching your filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($puzzlesByDate as $date => $datePuzzles): ?>
                    <div class="puzzle-date-group">
                        <div class="puzzle-date-header">
                            <?php echo date('l, F j, Y', strtotime($date)); ?>
                        </div>
                        <div class="puzzle-grid">
                            <?php foreach ($datePuzzles as $p): ?>
                                <div class="puzzle-card">
                                    <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                                    <div class="puzzle-meta">
                                        <span class="puzzle-difficulty <?php echo $p['difficulty']; ?>">
                                            <?php echo ucfirst($p['difficulty']); ?>
                                        </span>
                                        <?php if ($p['theme']): ?>
                                            <span><?php echo htmlspecialchars($p['theme']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                        <?php echo htmlspecialchars(substr($p['case_summary'], 0, 100)); ?>...
                                    </p>
                                    <a href="index.php?puzzle_id=<?php echo $p['id']; ?>" class="btn">Play Puzzle</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

