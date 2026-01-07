<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzle = new Puzzle();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$puzzleId = (int)$_GET['id'];
$puzzleData = $puzzle->getPuzzleById($puzzleId);

if (!$puzzleData) {
    die('Puzzle not found');
}

$statements = $puzzle->getStatements($puzzleId);
$hints = $puzzle->getHints($puzzleId);
$solution = $puzzle->getSolution($puzzleId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Puzzle - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="stats.php">Statistics</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>View Puzzle: <?php echo htmlspecialchars($puzzleData['title']); ?></h2>
                <div style="display: flex; gap: 10px;">
                    <a href="puzzle-edit.php?id=<?php echo $puzzleId; ?>" class="btn btn-primary">Edit Puzzle</a>
                    <a href="../?puzzle_id=<?php echo $puzzleId; ?>" class="btn" target="_blank">Play This Puzzle</a>
                    <a href="index.php" class="btn">← Back to List</a>
                </div>
            </div>

            <!-- Puzzle Info -->
            <div class="form-card">
                <h3>Puzzle Information</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600; width: 150px;">Date:</td>
                        <td style="padding: 12px;"><?php echo date('l, F j, Y', strtotime($puzzleData['puzzle_date'])); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600;">Title:</td>
                        <td style="padding: 12px;"><?php echo htmlspecialchars($puzzleData['title']); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600;">Difficulty:</td>
                        <td style="padding: 12px;">
                            <span class="badge badge-<?php echo $puzzleData['difficulty']; ?>">
                                <?php echo ucfirst($puzzleData['difficulty']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600;">Theme:</td>
                        <td style="padding: 12px;"><?php echo htmlspecialchars($puzzleData['theme']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: 600;">Created:</td>
                        <td style="padding: 12px;"><?php echo date('M j, Y g:i A', strtotime($puzzleData['created_at'])); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Case Summary -->
            <div class="form-card">
                <h3>Case Summary</h3>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($puzzleData['case_summary'])); ?>
                </div>
            </div>

            <!-- Report -->
            <div class="form-card">
                <h3>Full Report</h3>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; line-height: 1.6; white-space: pre-wrap;">
                    <?php echo nl2br(htmlspecialchars($puzzleData['report_text'])); ?>
                </div>
            </div>

            <!-- Statements -->
            <div class="form-card">
                <h3>Statements (<?php echo count($statements); ?>)</h3>

                <?php if (empty($statements)): ?>
                    <p style="color: #999;">No statements added yet.</p>
                <?php else: ?>
                    <?php foreach ($statements as $stmt): ?>
                        <div class="statement-item <?php echo $stmt['is_correct_answer'] ? 'correct' : ''; ?>">
                            <div>
                                <strong>#<?php echo $stmt['statement_order']; ?></strong>
                                <?php echo htmlspecialchars($stmt['statement_text']); ?>
                                <?php if ($stmt['is_correct_answer']): ?>
                                    <span style="color: green; font-weight: bold; margin-left: 10px;">✓ CORRECT ANSWER</span>
                                <?php endif; ?>
                                <?php if ($stmt['category']): ?>
                                    <span style="color: #666; font-size: 12px; margin-left: 10px;">[<?php echo htmlspecialchars($stmt['category']); ?>]</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Hints -->
            <div class="form-card">
                <h3>Hints (<?php echo count($hints); ?>)</h3>

                <?php if (empty($hints)): ?>
                    <p style="color: #999;">No hints added yet.</p>
                <?php else: ?>
                    <?php foreach ($hints as $hint): ?>
                        <div class="statement-item" style="background: #fff3cd;">
                            <strong>Hint <?php echo $hint['hint_order']; ?>:</strong>
                            <?php echo htmlspecialchars($hint['hint_text']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Solution -->
            <div class="form-card">
                <h3>Solution</h3>

                <?php if (!$solution): ?>
                    <p style="color: #999;">No solution added yet.</p>
                <?php else: ?>
                    <?php if (!empty($solution['image_path'])): ?>
                        <?php
                        // Check if image file exists
                        $imageFullPath = __DIR__ . '/../' . $solution['image_path'];
                        $imageExists = file_exists($imageFullPath);
                        ?>
                        <div style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border: 2px solid #ddd; border-radius: 8px; text-align: center;">
                            <h4 style="margin-bottom: 15px; color: #333;">Solution Image</h4>
                            <?php if ($imageExists): ?>
                                <img src="../<?php echo htmlspecialchars($solution['image_path']); ?>" 
                                     alt="Solution illustration" 
                                     style="max-width: 100%; max-height: 500px; border: 2px solid #8b4513; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div style="display:none; padding:15px; background:#ffebee; color:#c62828; border:2px solid #c62828; border-radius:4px;">
                                    ⚠️ Image could not be loaded
                                </div>
                            <?php else: ?>
                                <div style="padding:15px; background:#fff3cd; color:#856404; border:2px solid #ffc107; border-radius:4px;">
                                    ⚠️ Image file not found at: <code><?php echo htmlspecialchars($solution['image_path']); ?></code><br>
                                    <small>Generate a new image using the Edit page.</small>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($solution['image_prompt'])): ?>
                                <p style="margin-top: 15px; font-size: 13px; color: #666; font-style: italic; text-align: left; background: #fff; padding: 10px; border-radius: 4px;">
                                    <strong>Image Prompt:</strong><br>
                                    <?php echo htmlspecialchars($solution['image_prompt']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #333;">Brief Explanation:</h4>
                        <div style="background: #d4edda; padding: 16px; border-radius: 8px; border-left: 4px solid #28a745; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($solution['explanation'])); ?>
                        </div>
                    </div>

                    <div>
                        <h4 style="margin-bottom: 10px; color: #333;">Detailed Reasoning:</h4>
                        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($solution['detailed_reasoning'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Stats -->
            <?php
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM puzzle_stats WHERE puzzle_id = ?");
            $stmt->execute([$puzzleId]);
            $stats = $stmt->fetch();
            ?>

            <?php if ($stats && $stats['total_completions'] > 0): ?>
            <div class="form-card">
                <h3>Statistics</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600;">Total Players:</td>
                        <td style="padding: 12px;"><?php echo $stats['total_completions']; ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 12px; font-weight: 600;">Solved:</td>
                        <td style="padding: 12px;"><?php echo $stats['total_solved']; ?> (<?php echo $stats['total_completions'] > 0 ? round(($stats['total_solved'] / $stats['total_completions']) * 100) : 0; ?>%)</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: 600;">Avg Attempts:</td>
                        <td style="padding: 12px;"><?php echo number_format($stats['avg_attempts'], 2); ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="form-card">
                <h3>Actions</h3>
                <div style="display: flex; gap: 10px;">
                    <a href="puzzle-edit.php?id=<?php echo $puzzleId; ?>" class="btn btn-primary">Edit This Puzzle</a>
                    <a href="../?puzzle_id=<?php echo $puzzleId; ?>" class="btn" target="_blank">Play This Puzzle</a>
                    <a href="puzzle-delete.php?id=<?php echo $puzzleId; ?>" class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to delete this puzzle? This cannot be undone.')">
                        Delete Puzzle
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
