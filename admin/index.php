<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzle = new Puzzle();
$puzzles = $puzzle->getAllPuzzles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <nav>
                <a href="index.php" class="active">Puzzles</a>
                <a href="puzzle-generate.php">AI Generator</a>
                <a href="stats.php">Statistics</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>Manage Puzzles</h2>
                <a href="puzzle-edit.php" class="btn btn-primary">+ Create New Puzzle</a>
            </div>

            <div class="puzzle-list">
                <?php if (empty($puzzles)): ?>
                    <div class="empty-state">
                        <p>No puzzles yet. Create your first puzzle to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Difficulty</th>
                                <th>Theme</th>
                                <th>Statements</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentDate = null;
                            foreach ($puzzles as $p): 
                                $puzzleDate = $p['puzzle_date'];
                                $showDateHeader = ($currentDate !== $puzzleDate);
                                $currentDate = $puzzleDate;
                            ?>
                                <?php if ($showDateHeader): ?>
                                    <tr style="background: #f5f5f5;">
                                        <td colspan="6" style="padding: 15px; font-weight: 700; font-size: 16px; color: #667eea;">
                                            📅 <?php echo date('l, F j, Y', strtotime($puzzleDate)); ?>
                                            <?php 
                                            // Count puzzles for this date
                                            $puzzlesForDate = array_filter($puzzles, function($puz) use ($puzzleDate) {
                                                return $puz['puzzle_date'] === $puzzleDate;
                                            });
                                            $difficultyCount = count($puzzlesForDate);
                                            if ($difficultyCount > 1) {
                                                echo '<span style="font-size: 14px; font-weight: 500; color: #666; margin-left: 10px;">(' . $difficultyCount . ' difficulties)</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="padding-left: 30px;"><?php echo date('M j, Y', strtotime($p['puzzle_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['title']); ?></td>
                                    <td><span class="badge badge-<?php echo $p['difficulty']; ?>"><?php echo ucfirst($p['difficulty']); ?></span></td>
                                    <td><?php echo htmlspecialchars($p['theme']); ?></td>
                                    <td><?php echo $p['statement_count']; ?></td>
                                    <td>
                                        <a href="puzzle-edit.php?id=<?php echo $p['id']; ?>" class="btn-small">Edit</a>
                                        <a href="puzzle-view.php?id=<?php echo $p['id']; ?>" class="btn-small">View</a>
                                        <a href="puzzle-delete.php?id=<?php echo $p['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this puzzle?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
