<?php
/**
 * Submit Generated Puzzle API
 * Accepts a complete puzzle (from generate-puzzle-quality.php or n8n) and saves it to database
 * 
 * This is the final step in the n8n workflow - upload the validated puzzle
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../includes/Puzzle.php';

// API key check
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
if (empty($apiKey)) {
    // Try from Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        $apiKey = $matches[1];
    }
}

$requiredKey = EnvLoader::get('IMAGE_GENERATION_API_KEY', '');

if (empty($requiredKey) || $apiKey !== $requiredKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

try {
    // Get puzzle data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        // Try POST data
        $input = $_POST;
    }
    
    if (empty($input['puzzle']) && empty($input)) {
        throw new Exception("No puzzle data provided");
    }
    
    // Handle nested puzzle structure or flat structure
    $puzzleData = $input['puzzle'] ?? $input;
    
    // Validate required fields
    $required = ['puzzle_date', 'title', 'difficulty', 'theme', 'case_summary', 'report_text', 'statements', 'hints', 'solution'];
    foreach ($required as $field) {
        if (!isset($puzzleData[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    $puzzle = new Puzzle();
    
    // Check if puzzle already exists
    $existing = $puzzle->getPuzzleByDate($puzzleData['puzzle_date'], $puzzleData['difficulty']);
    if ($existing) {
        echo json_encode([
            'success' => false,
            'error' => 'Puzzle already exists for this date and difficulty',
            'puzzle_id' => $existing['id']
        ]);
        exit;
    }
    
    // Create puzzle
    $puzzleId = $puzzle->createPuzzle([
        'puzzle_date' => $puzzleData['puzzle_date'],
        'title' => $puzzleData['title'],
        'difficulty' => $puzzleData['difficulty'],
        'theme' => $puzzleData['theme'],
        'case_summary' => $puzzleData['case_summary'],
        'report_text' => $puzzleData['report_text']
    ]);
    
    // Save statements (preserve order, don't shuffle here - already randomized if needed)
    foreach ($puzzleData['statements'] as $order => $stmt) {
        $puzzle->createStatement(
            $puzzleId, 
            $order + 1, 
            $stmt['text'], 
            $stmt['is_correct'] ?? false, 
            $stmt['category'] ?? 'general'
        );
    }
    
    // Save hints
    foreach ($puzzleData['hints'] as $order => $hint) {
        $puzzle->createHint($puzzleId, $order + 1, $hint);
    }
    
    // Save solution
    $imagePath = $puzzleData['solution_image']['path'] ?? null;
    $imagePrompt = $puzzleData['solution_image']['prompt'] ?? null;
    
    $puzzle->createSolution(
        $puzzleId,
        $puzzleData['solution']['explanation'],
        $puzzleData['solution']['detailed_reasoning'] ?? '',
        $imagePath,
        $imagePrompt
    );
    
    echo json_encode([
        'success' => true,
        'puzzle_id' => $puzzleId,
        'message' => 'Puzzle submitted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

