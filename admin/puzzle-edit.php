<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzle = new Puzzle();
$editMode = isset($_GET['id']);
$puzzleData = null;
$statements = [];
$hints = [];
$solution = null;

if ($editMode) {
    $puzzleId = (int)$_GET['id'];
    $puzzleData = $puzzle->getPuzzleById($puzzleId);
    if (!$puzzleData) {
        die('Puzzle not found');
    }
    $statements = $puzzle->getStatements($puzzleId);
    $hints = $puzzle->getHints($puzzleId);
    $solution = $puzzle->getSolution($puzzleId);
    
    // Debug: Check if solution has image_prompt (remove after testing)
    // Uncomment to debug:
    // if ($solution) {
    //     error_log("Solution data: " . print_r($solution, true));
    // }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_puzzle') {
    $data = [
        'puzzle_date' => $_POST['puzzle_date'],
        'title' => $_POST['title'],
        'difficulty' => $_POST['difficulty'],
        'theme' => $_POST['theme'],
        'case_summary' => $_POST['case_summary'],
        'report_text' => $_POST['report_text']
    ];

    try {
        if ($editMode) {
            $puzzle->updatePuzzle($puzzleId, $data);
            $success = 'Puzzle updated successfully!';
        } else {
            $puzzleId = $puzzle->createPuzzle($data);
            $success = 'Puzzle created successfully!';
            header('Location: puzzle-edit.php?id=' . $puzzleId);
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error saving puzzle: ' . $e->getMessage();
    }
}

// Handle statement operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = Database::getInstance()->getConnection();

    if ($_POST['action'] === 'add_statement') {
        $stmt = $db->prepare("
            INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $puzzleId,
            $_POST['statement_order'],
            $_POST['statement_text'],
            isset($_POST['is_correct']) ? 1 : 0,
            $_POST['category']
        ]);
        $success = 'Statement added!';
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }

    if ($_POST['action'] === 'delete_statement') {
        $stmt = $db->prepare("DELETE FROM statements WHERE id = ?");
        $stmt->execute([$_POST['statement_id']]);
        $success = 'Statement deleted!';
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }

    if ($_POST['action'] === 'add_hint') {
        $stmt = $db->prepare("
            INSERT INTO hints (puzzle_id, hint_order, hint_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $puzzleId,
            $_POST['hint_order'],
            $_POST['hint_text']
        ]);
        $success = 'Hint added!';
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }

    if ($_POST['action'] === 'save_solution') {
        $imagePath = !empty($_POST['image_path']) ? $_POST['image_path'] : null;
        $imagePrompt = !empty($_POST['image_prompt']) ? $_POST['image_prompt'] : null;
        
        $stmt = $db->prepare("
            INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning, image_path, image_prompt)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                explanation = VALUES(explanation),
                detailed_reasoning = VALUES(detailed_reasoning),
                image_path = VALUES(image_path),
                image_prompt = VALUES(image_prompt)
        ");
        $stmt->execute([
            $puzzleId,
            $_POST['explanation'],
            $_POST['detailed_reasoning'],
            $imagePath,
            $imagePrompt
        ]);
        $success = 'Solution saved!';
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }

    if ($_POST['action'] === 'generate_solution_image') {
        require_once '../includes/AIPuzzleGenerator.php';
        
        // Get puzzle data for image generation
        $puzzleData = $puzzle->getPuzzleById($puzzleId);
        $solution = $puzzle->getSolution($puzzleId);
        
        if (!$puzzleData || !$solution) {
            $error = 'Puzzle or solution not found';
        } else {
            try {
                $generator = new AIPuzzleGenerator('openai'); // Use OpenAI for DALL-E
                $puzzleArray = [
                    'theme' => $puzzleData['theme'] ?? 'mystery',
                    'title' => $puzzleData['title'] ?? 'case',
                    'solution' => [
                        'explanation' => $solution['explanation'] ?? ''
                    ]
                ];
                
                $imageData = $generator->generateSolutionImage($puzzleArray);
                
                if ($imageData) {
                    // Update solution with image
                    $stmt = $db->prepare("
                        UPDATE solutions 
                        SET image_path = ?, image_prompt = ?
                        WHERE puzzle_id = ?
                    ");
                    $stmt->execute([$imageData['path'], $imageData['prompt'], $puzzleId]);
                    $success = 'Solution image generated successfully!';
                } else {
                    $error = 'Failed to generate image. Make sure OPENAI_API_KEY is set in .env';
                }
            } catch (Exception $e) {
                $error = 'Error generating image: ' . $e->getMessage();
            }
        }
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }

    if ($_POST['action'] === 'upload_solution_image') {
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed. Please select a valid image file.';
        } else {
            $file = $_FILES['image_file'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $error = 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.';
            } else {
                // Create images directory if it doesn't exist
                $imagesDir = __DIR__ . '/../images/solutions';
                if (!is_dir($imagesDir)) {
                    mkdir($imagesDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'solution_' . $puzzleId . '_' . uniqid() . '_' . time() . '.' . $extension;
                $filepath = $imagesDir . '/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Save path to database
                    $relativePath = 'images/solutions/' . $filename;
                    $imagePrompt = !empty($_POST['upload_image_prompt']) ? $_POST['upload_image_prompt'] : null;
                    
                    $stmt = $db->prepare("
                        UPDATE solutions 
                        SET image_path = ?, image_prompt = ?
                        WHERE puzzle_id = ?
                    ");
                    $stmt->execute([$relativePath, $imagePrompt, $puzzleId]);
                    $success = 'Image uploaded successfully!';
                } else {
                    $error = 'Failed to save uploaded file. Please check directory permissions.';
                }
            }
        }
        header('Location: puzzle-edit.php?id=' . $puzzleId);
        exit;
    }
}

if ($editMode) {
    $statements = $puzzle->getStatements($puzzleId);
    $hints = $puzzle->getHints($puzzleId);
    $solution = $puzzle->getSolution($puzzleId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit' : 'Create'; ?> Puzzle - Admin</title>
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
                <h2><?php echo $editMode ? 'Edit Puzzle' : 'Create New Puzzle'; ?></h2>
                <a href="index.php" class="btn">← Back to List</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Basic Puzzle Info -->
            <div class="form-card">
                <h3>Basic Information</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_puzzle">

                    <div class="form-group">
                        <label for="puzzle_date">Puzzle Date</label>
                        <input type="date" id="puzzle_date" name="puzzle_date"
                               value="<?php echo $puzzleData ? $puzzleData['puzzle_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title"
                               value="<?php echo $puzzleData ? htmlspecialchars($puzzleData['title']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="difficulty">Difficulty</label>
                        <select id="difficulty" name="difficulty">
                            <option value="easy" <?php echo ($puzzleData && $puzzleData['difficulty'] === 'easy') ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo ($puzzleData && $puzzleData['difficulty'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo ($puzzleData && $puzzleData['difficulty'] === 'hard') ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="theme">Theme</label>
                        <input type="text" id="theme" name="theme"
                               value="<?php echo $puzzleData ? htmlspecialchars($puzzleData['theme']) : ''; ?>"
                               placeholder="e.g., Office Theft, Home Invasion, Missing Person">
                    </div>

                    <div class="form-group">
                        <label for="case_summary">Case Summary (3-4 sentences)</label>
                        <textarea id="case_summary" name="case_summary" required><?php echo $puzzleData ? htmlspecialchars($puzzleData['case_summary']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="report_text">Full Report (use markdown formatting)</label>
                        <textarea id="report_text" name="report_text" style="min-height: 300px;" required><?php echo $puzzleData ? htmlspecialchars($puzzleData['report_text']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Puzzle Info</button>
                    </div>
                </form>
            </div>

            <?php if ($editMode): ?>
                <!-- Statements -->
                <div class="form-card">
                    <h3>Statements (Clickable Options)</h3>

                    <?php if (!empty($statements)): ?>
                        <?php foreach ($statements as $stmt): ?>
                            <div class="statement-item <?php echo $stmt['is_correct_answer'] ? 'correct' : ''; ?>">
                                <div>
                                    <strong>#<?php echo $stmt['statement_order']; ?></strong>
                                    <?php echo htmlspecialchars($stmt['statement_text']); ?>
                                    <?php if ($stmt['is_correct_answer']): ?>
                                        <span style="color: green; font-weight: bold;">✓ CORRECT ANSWER</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_statement">
                                    <input type="hidden" name="statement_id" value="<?php echo $stmt['id']; ?>">
                                    <button type="submit" class="btn-small btn-danger"
                                            onclick="return confirm('Delete this statement?')">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #999;">No statements yet. Add the clickable options below.</p>
                    <?php endif; ?>

                    <h4 style="margin-top: 30px;">Add New Statement</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_statement">

                        <div class="form-group">
                            <label for="statement_order">Order</label>
                            <input type="number" id="statement_order" name="statement_order"
                                   value="<?php echo count($statements) + 1; ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="statement_text">Statement Text</label>
                            <textarea id="statement_text" name="statement_text" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category"
                                   placeholder="e.g., timeline, witness, physical_evidence">
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_correct" value="1">
                                This is the CORRECT answer (inconsistency)
                            </label>
                        </div>

                        <button type="submit" class="btn">Add Statement</button>
                    </form>
                </div>

                <!-- Hints -->
                <div class="form-card">
                    <h3>Hints</h3>

                    <?php if (!empty($hints)): ?>
                        <?php foreach ($hints as $hint): ?>
                            <div class="statement-item">
                                <strong>Hint <?php echo $hint['hint_order']; ?>:</strong>
                                <?php echo htmlspecialchars($hint['hint_text']); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <h4 style="margin-top: 20px;">Add Hint</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_hint">

                        <div class="form-group">
                            <label for="hint_order">Hint Number (1 = first hint shown)</label>
                            <input type="number" id="hint_order" name="hint_order"
                                   value="<?php echo count($hints) + 1; ?>" min="1" max="2" required>
                        </div>

                        <div class="form-group">
                            <label for="hint_text">Hint Text</label>
                            <textarea id="hint_text" name="hint_text" required></textarea>
                        </div>

                        <button type="submit" class="btn">Add Hint</button>
                    </form>
                </div>

                <!-- Solution -->
                <div class="form-card">
                    <h3>Solution Explanation</h3>

                    <?php if ($solution && !empty($solution['image_path'])): ?>
                        <?php
                        // Check if image file exists
                        $imageFullPath = __DIR__ . '/../' . $solution['image_path'];
                        $imageExists = file_exists($imageFullPath);
                        ?>
                        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 2px solid #ddd; border-radius: 4px;">
                            <h4 style="margin-bottom: 10px;">Current Solution Image</h4>
                            <div style="text-align: center;">
                                <?php if ($imageExists): ?>
                                    <img src="../<?php echo htmlspecialchars($solution['image_path']); ?>" 
                                         alt="Solution illustration" 
                                         style="max-width: 100%; max-height: 400px; border: 2px solid #8b4513; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div style="display:none; padding:15px; background:#ffebee; color:#c62828; border:2px solid #c62828; border-radius:4px;">
                                        ⚠️ Image could not be loaded
                                    </div>
                                <?php else: ?>
                                    <div style="padding:15px; background:#fff3cd; color:#856404; border:2px solid #ffc107; border-radius:4px;">
                                        ⚠️ Image file not found at: <code><?php echo htmlspecialchars($solution['image_path']); ?></code><br>
                                        <small>Path in database: <?php echo htmlspecialchars($solution['image_path']); ?></small><br>
                                        <small>Expected full path: <?php echo htmlspecialchars($imageFullPath); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($solution['image_prompt'])): ?>
                                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                    <strong>Image Prompt:</strong><br>
                                    <em><?php echo htmlspecialchars($solution['image_prompt']); ?></em>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 20px; padding: 15px; background: #f0f7ff; border: 2px solid #2196F3; border-radius: 4px;">
                        <h4 style="margin-bottom: 10px;">Upload Locally Generated Image</h4>
                        <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            Generate images locally using Stable Diffusion or any other tool, then upload them here.
                        </p>
                        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 0;">
                            <input type="hidden" name="action" value="upload_solution_image">
                            
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="image_file">Select Image File</label>
                                <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required>
                                <small style="display: block; margin-top: 5px; color: #666;">
                                    Accepted formats: JPEG, PNG, GIF, WebP (Max recommended: 1024x1024px)
                                </small>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="upload_image_prompt">Image Prompt (optional)</label>
                                <textarea id="upload_image_prompt" name="upload_image_prompt" rows="2" placeholder="Describe the image prompt used (for reference)"><?php echo $solution && !empty($solution['image_prompt']) ? htmlspecialchars($solution['image_prompt']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn" style="background: #4CAF50; color: white;">
                                📤 Upload Image
                            </button>
                        </form>
                    </div>

                    <form method="POST" style="margin-bottom: 15px;">
                        <input type="hidden" name="action" value="generate_solution_image">
                        <button type="submit" class="btn" style="background: #2196F3; color: white;">
                            🎨 Generate AI Image (DALL-E)
                        </button>
                        <small style="display: block; margin-top: 5px; color: #666;">
                            Requires OPENAI_API_KEY in .env file. Generates a new image based on the puzzle theme and solution.
                        </small>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_solution">

                        <div class="form-group">
                            <label for="explanation">Brief Explanation</label>
                            <textarea id="explanation" name="explanation" required><?php echo $solution ? htmlspecialchars($solution['explanation']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="detailed_reasoning">Detailed Reasoning</label>
                            <textarea id="detailed_reasoning" name="detailed_reasoning" style="min-height: 200px;" required><?php echo $solution ? htmlspecialchars($solution['detailed_reasoning']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image_path">Image Path (Manual Entry)</label>
                            <input type="text" id="image_path" name="image_path" 
                                   value="<?php echo $solution && !empty($solution['image_path']) ? htmlspecialchars($solution['image_path']) : ''; ?>"
                                   placeholder="e.g., images/solutions/solution_123.png">
                            <small style="display: block; margin-top: 5px; color: #666;">
                                Leave empty to keep current image, or enter a new path. Use the "Generate New AI Image" button above for automatic generation.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="image_prompt">Image Prompt (for reference)</label>
                            <textarea id="image_prompt" name="image_prompt" rows="3"><?php 
                                if ($solution && isset($solution['image_prompt']) && $solution['image_prompt'] !== null) {
                                    echo htmlspecialchars($solution['image_prompt']);
                                }
                            ?></textarea>
                            <small style="display: block; margin-top: 5px; color: #666;">
                                The prompt used to generate the image (auto-filled when generating via AI).
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Solution</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <p>Save the basic puzzle information first, then you can add statements, hints, and the solution.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
