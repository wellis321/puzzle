<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';

$db = Database::getInstance()->getConnection();

// Get overall stats
$stmt = $db->query("
    SELECT
        COUNT(DISTINCT puzzle_id) as total_puzzles,
        COUNT(DISTINCT session_id) as total_players,
        SUM(solved) as total_solved,
        COUNT(*) as total_completions
    FROM completions
");
$overallStats = $stmt->fetch();

// Get puzzle stats
$stmt = $db->query("
    SELECT
        p.id,
        p.puzzle_date,
        p.title,
        p.difficulty,
        ps.total_completions,
        ps.total_solved,
        ps.avg_attempts,
        ROUND((ps.total_solved / NULLIF(ps.total_completions, 0)) * 100, 1) as solve_rate
    FROM puzzles p
    LEFT JOIN puzzle_stats ps ON p.id = ps.puzzle_id
    ORDER BY p.puzzle_date DESC
");
$puzzleStats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="stats.php" class="active">Statistics</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>Statistics</h2>
            </div>

            <!-- Overall Stats -->
            <div class="form-card">
                <h3>Overall Statistics</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: #667eea;"><?php echo $overallStats['total_puzzles'] ?? 0; ?></div>
                        <div style="color: #666; margin-top: 8px;">Total Puzzles</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: #667eea;"><?php echo $overallStats['total_players'] ?? 0; ?></div>
                        <div style="color: #666; margin-top: 8px;">Total Players</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: #667eea;"><?php echo $overallStats['total_completions'] ?? 0; ?></div>
                        <div style="color: #666; margin-top: 8px;">Total Plays</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 700; color: #28a745;">
                            <?php
                            $solveRate = $overallStats['total_completions'] > 0
                                ? round(($overallStats['total_solved'] / $overallStats['total_completions']) * 100, 1)
                                : 0;
                            echo $solveRate;
                            ?>%
                        </div>
                        <div style="color: #666; margin-top: 8px;">Overall Solve Rate</div>
                    </div>
                </div>
            </div>

            <!-- Puzzle Stats -->
            <div class="form-card">
                <h3>Puzzle Performance</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Difficulty</th>
                            <th>Players</th>
                            <th>Solved</th>
                            <th>Solve Rate</th>
                            <th>Avg Attempts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($puzzleStats as $stat): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($stat['puzzle_date'])); ?></td>
                                <td>
                                    <a href="puzzle-view.php?id=<?php echo $stat['id']; ?>" style="text-decoration: none; color: #667eea;">
                                        <?php echo htmlspecialchars($stat['title']); ?>
                                    </a>
                                </td>
                                <td><span class="badge badge-<?php echo $stat['difficulty']; ?>"><?php echo ucfirst($stat['difficulty']); ?></span></td>
                                <td><?php echo $stat['total_completions'] ?? 0; ?></td>
                                <td><?php echo $stat['total_solved'] ?? 0; ?></td>
                                <td>
                                    <?php if ($stat['solve_rate']): ?>
                                        <span style="color: <?php echo $stat['solve_rate'] >= 70 ? '#28a745' : ($stat['solve_rate'] >= 40 ? '#ffc107' : '#dc3545'); ?>; font-weight: 600;">
                                            <?php echo $stat['solve_rate']; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $stat['avg_attempts'] ? number_format($stat['avg_attempts'], 2) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
