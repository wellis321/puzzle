<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzle = new Puzzle();
$db = Database::getInstance()->getConnection();

// Get all solutions with images
$stmt = $db->query("
    SELECT s.id, s.puzzle_id, s.image_path, s.image_prompt, p.title, p.puzzle_date
    FROM solutions s
    JOIN puzzles p ON s.puzzle_id = p.id
    ORDER BY s.id DESC
    LIMIT 20
");
$solutions = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Diagnostic - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .image-test {
            margin: 20px 0;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .image-test img {
            max-width: 400px;
            border: 2px solid #8b4513;
            margin: 10px 0;
        }
        .path-info {
            background: #fff;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #2196F3;
        }
        .error {
            color: #c62828;
            font-weight: bold;
        }
        .success {
            color: #2e7d32;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin - Image Diagnostic</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="test-images.php" class="active">Image Test</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>Image Diagnostic Tool</h2>
                <a href="index.php" class="btn">← Back to Puzzles</a>
            </div>

            <div class="form-card">
                <h3>Images Directory Check</h3>
                <?php
                $imagesDir = __DIR__ . '/../images/solutions';
                $imagesDirExists = is_dir($imagesDir);
                $imagesDirWritable = $imagesDirExists && is_writable($imagesDir);
                
                echo "<div class='path-info'>";
                echo "<strong>Images Directory:</strong> " . htmlspecialchars($imagesDir) . "<br>";
                echo "<strong>Exists:</strong> " . ($imagesDirExists ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "<br>";
                echo "<strong>Writable:</strong> " . ($imagesDirWritable ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "<br>";
                
                if ($imagesDirExists) {
                    $files = scandir($imagesDir);
                    $imageFiles = array_filter($files, function($f) {
                        return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'gif']);
                    });
                    echo "<strong>Image Files Found:</strong> " . count($imageFiles) . "<br>";
                    if (count($imageFiles) > 0) {
                        echo "<strong>Files:</strong> " . implode(", ", array_slice($imageFiles, 0, 10)) . (count($imageFiles) > 10 ? "..." : "");
                    }
                }
                echo "</div>";
                ?>
            </div>

            <div class="form-card">
                <h3>Solutions with Image Paths (Last 20)</h3>
                
                <?php if (empty($solutions)): ?>
                    <p style="color: #999;">No solutions found in database.</p>
                <?php else: ?>
                    <?php foreach ($solutions as $sol): ?>
                        <div class="image-test">
                            <h4>Puzzle: <?php echo htmlspecialchars($sol['title']); ?> (<?php echo $sol['puzzle_date']; ?>)</h4>
                            <p><strong>Puzzle ID:</strong> <?php echo $sol['puzzle_id']; ?></p>
                            
                            <div class="path-info">
                                <strong>Image Path (DB):</strong> 
                                <?php if (empty($sol['image_path'])): ?>
                                    <span class="error">NOT SET</span>
                                <?php else: ?>
                                    <code><?php echo htmlspecialchars($sol['image_path']); ?></code>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($sol['image_path'])): ?>
                                <?php
                                $fullPath = __DIR__ . '/../' . $sol['image_path'];
                                $fileExists = file_exists($fullPath);
                                $relativePath = $sol['image_path'];
                                $webPath = '../' . $relativePath;
                                ?>
                                
                                <div class="path-info">
                                    <strong>Full File Path:</strong> <code><?php echo htmlspecialchars($fullPath); ?></code><br>
                                    <strong>File Exists:</strong> <?php echo $fileExists ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>"; ?><br>
                                    <strong>Web Path:</strong> <code><?php echo htmlspecialchars($webPath); ?></code><br>
                                    <?php if ($fileExists): ?>
                                        <strong>File Size:</strong> <?php echo number_format(filesize($fullPath) / 1024, 2); ?> KB<br>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($fileExists): ?>
                                    <div>
                                        <strong>Image Preview:</strong><br>
                                        <img src="<?php echo htmlspecialchars($webPath); ?>" alt="Solution image" 
                                             onerror="this.parentElement.innerHTML += '<br><span class=\'error\'>Error loading image!</span>'">
                                    </div>
                                <?php else: ?>
                                    <p class="error">⚠️ File does not exist at expected path!</p>
                                <?php endif; ?>
                                
                                <?php if (!empty($sol['image_prompt'])): ?>
                                    <div class="path-info" style="margin-top: 10px;">
                                        <strong>Image Prompt:</strong><br>
                                        <em><?php echo htmlspecialchars($sol['image_prompt']); ?></em>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div style="margin-top: 10px;">
                                <a href="puzzle-edit.php?id=<?php echo $sol['puzzle_id']; ?>" class="btn btn-small">Edit Puzzle</a>
                                <a href="puzzle-view.php?id=<?php echo $sol['puzzle_id']; ?>" class="btn btn-small">View Puzzle</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-card">
                <h3>Quick Test</h3>
                <p>Use this to test if images are accessible via web:</p>
                <ul>
                    <li><a href="../images/solutions/" target="_blank">Browse images directory</a></li>
                    <li>Check if images directory is web-accessible</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>

