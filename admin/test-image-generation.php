<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';
require_once '../includes/AIPuzzleGenerator.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Image Generation - Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border: 2px solid;
        }
        .success {
            background: #e8f5e9;
            border-color: #2e7d32;
            color: #1b5e20;
        }
        .error {
            background: #ffebee;
            border-color: #c62828;
            color: #c62828;
        }
        .info {
            background: #e3f2fd;
            border-color: #1565c0;
            color: #0d47a1;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin - Image Generation Test</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="test-image-generation.php" class="active">Image Test</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>Image Generation Diagnostic Test</h2>
                <a href="index.php" class="btn">← Back to Puzzles</a>
            </div>

            <div class="form-card">
                <h3>Environment Check</h3>
                <?php
                $openaiKey = EnvLoader::get('OPENAI_API_KEY');
                $hasOpenAI = !empty($openaiKey);
                
                echo "<div class='test-result " . ($hasOpenAI ? 'success' : 'error') . "'>";
                echo "<strong>OPENAI_API_KEY:</strong> " . ($hasOpenAI ? "✓ Found (" . substr($openaiKey, 0, 10) . "...)" : "✗ NOT FOUND") . "<br>";
                echo "</div>";
                
                echo "<div class='test-result info'>";
                echo "<strong>Images Directory:</strong> " . __DIR__ . "/../images/solutions/<br>";
                echo "<strong>Directory Exists:</strong> " . (is_dir(__DIR__ . '/../images/solutions') ? '✓ Yes' : '✗ No') . "<br>";
                echo "<strong>Directory Writable:</strong> " . (is_writable(__DIR__ . '/../images/solutions') ? '✓ Yes' : '✗ No') . "<br>";
                echo "</div>";
                ?>
            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_generate'])): ?>
                <div class="form-card">
                    <h3>Test Image Generation</h3>
                    
                    <?php
                    try {
                        $puzzle = new Puzzle();
                        
                        // Get a puzzle with a solution for testing
                        $stmt = Database::getInstance()->getConnection()->query("
                            SELECT p.id, p.title, p.theme, s.explanation 
                            FROM puzzles p
                            JOIN solutions s ON s.puzzle_id = p.id
                            LIMIT 1
                        ");
                        $testPuzzle = $stmt->fetch();
                        
                        if (!$testPuzzle) {
                            throw new Exception("No puzzles with solutions found. Create a puzzle first.");
                        }
                        
                        echo "<div class='test-result info'>";
                        echo "<strong>Test Puzzle:</strong> {$testPuzzle['title']} (ID: {$testPuzzle['id']})<br>";
                        echo "<strong>Theme:</strong> {$testPuzzle['theme']}<br>";
                        echo "</div>";
                        
                        // Test image generation
                        $generator = new AIPuzzleGenerator('groq'); // Use any provider for test
                        $puzzleArray = [
                            'theme' => $testPuzzle['theme'] ?? 'mystery',
                            'title' => $testPuzzle['title'] ?? 'test case',
                            'solution' => [
                                'explanation' => $testPuzzle['explanation'] ?? ''
                            ]
                        ];
                        
                        echo "<div class='test-result info'>";
                        echo "<strong>Attempting image generation...</strong><br>";
                        echo "</div>";
                        
                        $imageData = $generator->generateSolutionImage($puzzleArray);
                        
                        if ($imageData && isset($imageData['path'])) {
                            echo "<div class='test-result success'>";
                            echo "<strong>✓ SUCCESS!</strong><br>";
                            echo "<strong>Image Path:</strong> {$imageData['path']}<br>";
                            echo "<strong>Full Path:</strong> " . __DIR__ . "/../{$imageData['path']}<br>";
                            
                            $fullPath = __DIR__ . '/../' . $imageData['path'];
                            if (file_exists($fullPath)) {
                                $fileSize = filesize($fullPath);
                                echo "<strong>File Exists:</strong> ✓ Yes ({$fileSize} bytes)<br>";
                                echo "<strong>Image Preview:</strong><br>";
                                echo "<img src='../{$imageData['path']}' style='max-width: 400px; border: 2px solid #2e7d32; margin-top: 10px;'>";
                            } else {
                                echo "<strong>File Exists:</strong> ✗ No (but path was returned)<br>";
                            }
                            
                            if (isset($imageData['prompt'])) {
                                echo "<br><strong>Image Prompt:</strong><br>";
                                echo "<em style='font-size: 12px;'>" . htmlspecialchars($imageData['prompt']) . "</em>";
                            }
                            echo "</div>";
                        } else {
                            throw new Exception("Image generation returned no data");
                        }
                        
                    } catch (Exception $e) {
                        echo "<div class='test-result error'>";
                        echo "<strong>✗ ERROR:</strong><br>";
                        echo htmlspecialchars($e->getMessage());
                        echo "</div>";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <h3>Run Test</h3>
                <p>This will attempt to generate an image using your OpenAI API key to verify everything is working.</p>
                <form method="POST">
                    <input type="hidden" name="test_generate" value="1">
                    <button type="submit" class="btn btn-primary">Test Image Generation</button>
                </form>
            </div>

            <div class="form-card">
                <h3>Next Steps</h3>
                <ul>
                    <li>If the test succeeds, image generation should work when creating puzzles</li>
                    <li>Make sure to check the "Generate solution image" checkbox when generating puzzles</li>
                    <li>If test fails, check the error message above</li>
                    <li>Verify your OPENAI_API_KEY is correct in the .env file</li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>

