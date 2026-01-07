<?php
/**
 * AI Puzzle Generator
 * Generates puzzles using AI API (Gemini, Groq, or OpenAI)
 */

require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzle = new Puzzle();
$success = '';
$error = '';

// Handle puzzle generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $targetDate = $_POST['puzzle_date'] ?? date('Y-m-d');
    $difficulty = $_POST['difficulty'] ?? 'medium';
    $aiProvider = $_POST['ai_provider'] ?? 'gemini';
    $generateImage = isset($_POST['generate_image']) && $_POST['generate_image'] === '1';
    
    // Load AI generator
    require_once '../includes/AIPuzzleGenerator.php';
    $generator = new AIPuzzleGenerator($aiProvider);
    
    try {
        $generatedPuzzle = $generator->generatePuzzle($targetDate, $difficulty, $generateImage);
        
        if ($generatedPuzzle) {
            // Save to database
            $puzzleId = $puzzle->createPuzzle([
                'puzzle_date' => $targetDate,
                'title' => $generatedPuzzle['title'],
                'difficulty' => $difficulty,
                'theme' => $generatedPuzzle['theme'],
                'case_summary' => $generatedPuzzle['case_summary'],
                'report_text' => $generatedPuzzle['report_text']
            ]);
            
            // Save statements
            foreach ($generatedPuzzle['statements'] as $order => $stmt) {
                $puzzle->createStatement($puzzleId, $order + 1, $stmt['text'], $stmt['is_correct'], $stmt['category'] ?? 'general');
            }
            
            // Save hints
            foreach ($generatedPuzzle['hints'] as $order => $hint) {
                $puzzle->createHint($puzzleId, $order + 1, $hint);
            }
            
            // Save solution with optional image
            $imagePath = null;
            $imagePrompt = null;
            if (isset($generatedPuzzle['solution_image'])) {
                $imagePath = $generatedPuzzle['solution_image']['path'];
                $imagePrompt = $generatedPuzzle['solution_image']['prompt'];
            }
            
            $puzzle->createSolution(
                $puzzleId, 
                $generatedPuzzle['solution']['explanation'], 
                $generatedPuzzle['solution']['detailed_reasoning'],
                $imagePath,
                $imagePrompt
            );
            
            // Build success/error messages
            if ($generateImage) {
                if ($imagePath) {
                    $success = "Puzzle generated and saved successfully! 🎨 Image generated! <a href='puzzle-edit.php?id={$puzzleId}'>Edit puzzle</a>";
                } else {
                    $imageError = isset($generatedPuzzle['image_generation_error']) ? $generatedPuzzle['image_generation_error'] : 'Unknown error';
                    $success = "Puzzle generated and saved successfully! ⚠️ Image generation failed: " . htmlspecialchars($imageError) . " <a href='puzzle-edit.php?id={$puzzleId}'>Edit puzzle</a>";
                }
            } else {
                $success = "Puzzle generated and saved successfully! <a href='puzzle-edit.php?id={$puzzleId}'>Edit puzzle</a>";
            }
        } else {
            $error = "Failed to generate puzzle. Please check your API key and try again.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle batch generation (all 3 difficulties for a date)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_all'])) {
    $targetDate = $_POST['puzzle_date'] ?? date('Y-m-d');
    $aiProvider = $_POST['ai_provider'] ?? 'gemini';
    $generateImage = isset($_POST['generate_image']) && $_POST['generate_image'] === '1';
    
    require_once '../includes/AIPuzzleGenerator.php';
    $generator = new AIPuzzleGenerator($aiProvider);
    
    $generated = [];
    $errors = [];
    $imagesGenerated = 0;
    
    foreach (['easy', 'medium', 'hard'] as $difficulty) {
        try {
            $generatedPuzzle = $generator->generatePuzzle($targetDate, $difficulty, $generateImage);
            
            if ($generatedPuzzle) {
                $puzzleId = $puzzle->createPuzzle([
                    'puzzle_date' => $targetDate,
                    'title' => $generatedPuzzle['title'],
                    'difficulty' => $difficulty,
                    'theme' => $generatedPuzzle['theme'],
                    'case_summary' => $generatedPuzzle['case_summary'],
                    'report_text' => $generatedPuzzle['report_text']
                ]);
                
                foreach ($generatedPuzzle['statements'] as $order => $stmt) {
                    $puzzle->createStatement($puzzleId, $order + 1, $stmt['text'], $stmt['is_correct'], $stmt['category'] ?? 'general');
                }
                
                foreach ($generatedPuzzle['hints'] as $order => $hint) {
                    $puzzle->createHint($puzzleId, $order + 1, $hint);
                }
                
                // Save solution with optional image
                $imagePath = null;
                $imagePrompt = null;
                if (isset($generatedPuzzle['solution_image'])) {
                    $imagePath = $generatedPuzzle['solution_image']['path'];
                    $imagePrompt = $generatedPuzzle['solution_image']['prompt'];
                    $imagesGenerated++;
                }
                
                $puzzle->createSolution(
                    $puzzleId, 
                    $generatedPuzzle['solution']['explanation'], 
                    $generatedPuzzle['solution']['detailed_reasoning'],
                    $imagePath,
                    $imagePrompt
                );
                
                $difficultyLabel = ucfirst($difficulty);
                if ($generateImage && !$imagePath && isset($generatedPuzzle['image_generation_error'])) {
                    $difficultyLabel .= " (image failed)";
                    $errors[] = ucfirst($difficulty) . " image: " . $generatedPuzzle['image_generation_error'];
                }
                $generated[] = $difficultyLabel;
            }
        } catch (Exception $e) {
            $errors[] = ucfirst($difficulty) . ": " . $e->getMessage();
        }
    }
    
    if (!empty($generated)) {
        $imageNote = $imagesGenerated > 0 ? " ({$imagesGenerated} images generated)" : "";
        $success = "Generated puzzles: " . implode(", ", $generated) . $imageNote;
        if ($generateImage && $imagesGenerated === 0) {
            $success .= " ⚠️ No images were generated. Check that OPENAI_API_KEY is set in .env";
        }
    }
    if (!empty($errors)) {
        $error = "Errors: " . implode("; ", $errors);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Puzzle Generator - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="puzzle-generate.php" class="active">AI Generator</a>
                <a href="stats.php">Statistics</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>AI Puzzle Generator</h2>
                <a href="index.php" class="btn">← Back to Puzzles</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <h3>Generate Puzzle with AI</h3>
                <p style="margin-bottom: 20px; color: #666;">
                    Automatically generate puzzles using AI. Choose your preferred AI provider and configure API keys in your <code>.env</code> file.
                </p>

                <form method="POST">
                    <div class="form-group">
                        <label for="puzzle_date">Puzzle Date</label>
                        <input type="date" id="puzzle_date" name="puzzle_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="ai_provider">AI Provider</label>
                        <select id="ai_provider" name="ai_provider">
                            <option value="gemini" selected>Google Gemini (Free: 15 req/min)</option>
                            <option value="groq">Groq (Free, Very Fast)</option>
                            <option value="openai">OpenAI GPT-3.5 (Free tier available)</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Add API key to .env: GEMINI_API_KEY, GROQ_API_KEY, or OPENAI_API_KEY
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="difficulty">Difficulty (Single)</label>
                        <select id="difficulty" name="difficulty">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="generate_image" value="1" id="generate_image">
                            <span>Generate solution image (requires OpenAI API key for DALL-E)</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Creates an AI-generated illustration of the mystery scene for the solution section.<br>
                            <strong>Note:</strong> Images always use OpenAI DALL-E, even if you select Groq or Gemini for puzzle text generation.
                        </small>
                        <?php if (empty(EnvLoader::get('OPENAI_API_KEY'))): ?>
                            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; color: #856404;">
                                ⚠️ <strong>Warning:</strong> OPENAI_API_KEY not found in .env file. Image generation will fail.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="generate" class="btn btn-primary">
                            Generate Single Puzzle
                        </button>
                    </div>
                </form>

                <hr style="margin: 30px 0; border: none; border-top: 2px solid #e0e0e0;">

                <h3>Generate All Difficulties (Recommended)</h3>
                <p style="margin-bottom: 20px; color: #666;">
                    Generate all three difficulty levels (Easy, Medium, Hard) for the selected date in one go.
                </p>

                <form method="POST">
                    <div class="form-group">
                        <label for="batch_date">Puzzle Date</label>
                        <input type="date" id="batch_date" name="puzzle_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="batch_ai_provider">AI Provider</label>
                        <select id="batch_ai_provider" name="ai_provider">
                            <option value="gemini" selected>Google Gemini (Free: 15 req/min)</option>
                            <option value="groq">Groq (Free, Very Fast)</option>
                            <option value="openai">OpenAI GPT-3.5 (Free tier available)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="generate_image" value="1" id="batch_generate_image">
                            <span>Generate solution images (requires OpenAI API key for DALL-E)</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Creates AI-generated illustrations for all three puzzles (may take longer).<br>
                            <strong>Note:</strong> Images always use OpenAI DALL-E, even if you select Groq or Gemini for puzzle text generation.
                        </small>
                        <?php if (empty(EnvLoader::get('OPENAI_API_KEY'))): ?>
                            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; color: #856404;">
                                ⚠️ <strong>Warning:</strong> OPENAI_API_KEY not found in .env file. Image generation will fail.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="generate_all" class="btn btn-primary">
                            🚀 Generate All 3 Difficulties
                        </button>
                    </div>
                </form>
            </div>

            <div class="form-card" style="margin-top: 30px; background: #f9f9f9;">
                <h3>📚 Setup Instructions</h3>
                <ol style="line-height: 2;">
                    <li><strong>Get a free API key:</strong>
                        <ul>
                            <li><strong>Gemini</strong>: <a href="https://makersuite.google.com/app/apikey" target="_blank">Get free API key</a> (15 requests/minute)</li>
                            <li><strong>Groq</strong>: <a href="https://console.groq.com/keys" target="_blank">Get free API key</a> (Fast & free)</li>
                            <li><strong>OpenAI</strong>: <a href="https://platform.openai.com/api-keys" target="_blank">Get API key</a> (Free tier available)</li>
                        </ul>
                    </li>
                    <li><strong>Add to .env file on Hostinger:</strong>
                        <pre style="background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;"># For Gemini
GEMINI_API_KEY=your_api_key_here

# OR for Groq
GROQ_API_KEY=your_api_key_here

# OR for OpenAI
OPENAI_API_KEY=your_api_key_here</pre>
                    </li>
                    <li><strong>Generate puzzles:</strong> Use the forms above to generate puzzles manually, or set up automation (see below).</li>
                </ol>
            </div>

            <div class="form-card" style="margin-top: 20px; background: #e3f2fd;">
                <h3>⏰ Automation Options</h3>
                <p><strong>Option 1: Cron Job (Automatic Monthly Generation)</strong></p>
                <p>Set up a cron job on Hostinger to generate puzzles automatically. See <code>cron/generate-monthly-puzzles.php</code></p>
                
                <p style="margin-top: 15px;"><strong>Option 2: Manual Monthly Upload</strong></p>
                <p>Generate puzzles using this interface, then they'll be saved automatically to the database.</p>
            </div>
        </main>
    </div>
</body>
</html>

