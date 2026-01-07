<?php
/**
 * API Endpoint: Get puzzles that need images
 * Returns puzzles with solutions but no images
 * 
 * Usage: GET /api/get-puzzles-for-images.php?api_key=YOUR_SECRET_KEY
 * 
 * Returns JSON with puzzle data needed for image generation
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Puzzle.php';

// Get API key from environment or use default
$expectedApiKey = EnvLoader::get('IMAGE_GENERATION_API_KEY', '');
if (empty($expectedApiKey)) {
    // Generate a secure default if not set
    $expectedApiKey = hash('sha256', EnvLoader::get('APP_URL', '') . EnvLoader::get('ADMIN_USERNAME', 'admin'));
}

// Authenticate request
$providedApiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($providedApiKey) || $providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Valid API key required. Add IMAGE_GENERATION_API_KEY to .env or use the generated key.'
    ]);
    exit;
}

try {
    $puzzle = new Puzzle();
    $db = Database::getInstance()->getConnection();
    
    // Get puzzles with solutions but no images (or missing image files)
    $stmt = $db->query("
        SELECT 
            p.id,
            p.puzzle_date,
            p.title,
            p.difficulty,
            p.theme,
            p.case_summary,
            s.explanation,
            s.detailed_reasoning,
            s.image_path
        FROM puzzles p
        INNER JOIN solutions s ON s.puzzle_id = p.id
        WHERE s.image_path IS NULL 
           OR s.image_path = ''
        ORDER BY p.puzzle_date DESC, FIELD(p.difficulty, 'easy', 'medium', 'hard')
        LIMIT 50
    ");
    
    $puzzles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build result with suggested prompts
    $result = [];
    foreach ($puzzles as $p) {
        // Check if image file actually exists
        $imageExists = false;
        if (!empty($p['image_path'])) {
            $fullPath = __DIR__ . '/../' . $p['image_path'];
            $imageExists = file_exists($fullPath);
        }
        
        // Only include if image doesn't exist
        if (!$imageExists) {
            // Build image generation prompt context
            $theme = $p['theme'] ?? 'mystery';
            $title = $p['title'] ?? 'case';
            
            $imagePrompt = "A detailed, realistic illustration of a {$theme} mystery scene. ";
            $imagePrompt .= "Style: noir detective aesthetic, dramatic lighting, vintage crime scene investigation. ";
            $imagePrompt .= "Scene shows clues and evidence related to: {$title}. ";
            $imagePrompt .= "Mood: mysterious, intriguing, professional crime investigation. ";
            $imagePrompt .= "Color palette: muted tones with dramatic shadows, film noir style. ";
            $imagePrompt .= "No text, no people visible, focus on evidence and scene details.";
            
            $result[] = [
                'puzzle_id' => (int)$p['id'],
                'puzzle_date' => $p['puzzle_date'],
                'title' => $p['title'],
                'difficulty' => $p['difficulty'],
                'theme' => $p['theme'],
                'case_summary' => $p['case_summary'],
                'explanation' => $p['explanation'],
                'detailed_reasoning' => $p['detailed_reasoning'] ?? '',
                'suggested_prompt' => $imagePrompt,
                'api_submit_url' => rtrim(EnvLoader::get('APP_URL', ''), '/') . '/api/submit-puzzle-image.php'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($result),
        'puzzles' => $result
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}

