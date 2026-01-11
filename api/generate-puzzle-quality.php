<?php
/**
 * Quality-Assured Puzzle Generation API
 * Designed for n8n workflows with retry logic and validation
 * 
 * This endpoint:
 * 1. Generates a puzzle
 * 2. Validates it meets quality standards
 * 3. Retries if quality is insufficient
 * 4. Generates image if requested
 * 5. Returns puzzle data for upload
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../includes/Auth.php';
require_once '../includes/Puzzle.php';
require_once '../includes/AIPuzzleGenerator.php';

// Simple API key check (for n8n)
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$requiredKey = EnvLoader::get('IMAGE_GENERATION_API_KEY', '');

if (empty($requiredKey) || $apiKey !== $requiredKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

try {
    // Get parameters
    $date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
    $difficulty = $_GET['difficulty'] ?? $_POST['difficulty'] ?? 'medium';
    $provider = $_GET['provider'] ?? $_POST['provider'] ?? 'local';
    $generateImage = isset($_GET['generate_image']) ? (bool)$_GET['generate_image'] : (isset($_POST['generate_image']) ? (bool)$_POST['generate_image'] : false);
    $maxRetries = (int)($_GET['max_retries'] ?? $_POST['max_retries'] ?? 3);
    $minQualityScore = (float)($_GET['min_quality_score'] ?? $_POST['min_quality_score'] ?? 0.7);
    
    // Validate inputs
    if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
        throw new Exception("Invalid difficulty: must be easy, medium, or hard");
    }
    
    if (!in_array($provider, ['gemini', 'groq', 'openai', 'local', 'llama'])) {
        throw new Exception("Invalid provider");
    }
    
    // Check if puzzle already exists
    $puzzle = new Puzzle();
    $existing = $puzzle->getPuzzleByDate($date, $difficulty);
    if ($existing) {
        echo json_encode([
            'success' => false,
            'error' => 'Puzzle already exists for this date and difficulty',
            'puzzle_id' => $existing['id']
        ]);
        exit;
    }
    
    // Generate puzzle with retry logic
    $generator = new AIPuzzleGenerator($provider);
    $generatedPuzzle = null;
    $attempts = 0;
    $bestScore = 0;
    $bestPuzzle = null;
    
    while ($attempts < $maxRetries) {
        $attempts++;
        
        try {
            $attemptPuzzle = $generator->generatePuzzle($date, $difficulty, $generateImage);
            
            // Calculate quality score
            $qualityScore = calculateQualityScore($attemptPuzzle);
            
            // Track best attempt
            if ($qualityScore > $bestScore) {
                $bestScore = $qualityScore;
                $bestPuzzle = $attemptPuzzle;
            }
            
            // If quality is sufficient, use it
            if ($qualityScore >= $minQualityScore) {
                $generatedPuzzle = $attemptPuzzle;
                break;
            }
            
            // Log attempt
            error_log("Puzzle generation attempt {$attempts}: quality score {$qualityScore} (need {$minQualityScore})");
            
        } catch (Exception $e) {
            error_log("Puzzle generation attempt {$attempts} failed: " . $e->getMessage());
            if ($attempts >= $maxRetries) {
                throw $e;
            }
        }
    }
    
    // Use best puzzle if we didn't get one that meets threshold
    if (!$generatedPuzzle && $bestPuzzle) {
        $generatedPuzzle = $bestPuzzle;
    }
    
    if (!$generatedPuzzle) {
        throw new Exception("Failed to generate puzzle after {$maxRetries} attempts");
    }
    
    // Calculate final quality score
    $finalScore = calculateQualityScore($generatedPuzzle);
    
    // Return puzzle data (ready for upload via submit-puzzle.php)
    echo json_encode([
        'success' => true,
        'puzzle' => $generatedPuzzle,
        'quality_score' => $finalScore,
        'attempts' => $attempts,
        'validation' => $generatedPuzzle['validation'] ?? null,
        'ready_for_upload' => $finalScore >= $minQualityScore
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Calculate quality score for a puzzle (0.0 to 1.0)
 */
function calculateQualityScore($puzzle) {
    $score = 0.0;
    $maxScore = 0.0;
    
    // Check validation results
    if (isset($puzzle['validation'])) {
        $validation = $puzzle['validation'];
        $maxScore += 0.4;
        if ($validation['valid']) {
            $score += 0.4;
        } else {
            // Partial credit based on warnings
            $warningCount = count($validation['warnings'] ?? []);
            if ($warningCount === 0) {
                $score += 0.4;
            } elseif ($warningCount === 1) {
                $score += 0.2;
            }
        }
        
        // Bonus for detecting contradictions
        if (!empty($validation['contradictions_detected'])) {
            $score += 0.2;
            $maxScore += 0.2;
        }
        
        // Bonus for referencing specific facts
        if (isset($validation['references_specific_facts']) && $validation['references_specific_facts']) {
            $score += 0.1;
            $maxScore += 0.1;
        }
    }
    
    // Check solution quality
    $maxScore += 0.3;
    if (!empty($puzzle['solution']['explanation'])) {
        $explanationLength = strlen($puzzle['solution']['explanation']);
        if ($explanationLength > 50 && $explanationLength < 500) {
            $score += 0.2;
        }
    }
    if (!empty($puzzle['solution']['detailed_reasoning'])) {
        $reasoningLength = strlen($puzzle['solution']['detailed_reasoning']);
        if ($reasoningLength > 100) {
            $score += 0.1;
        }
    }
    
    // Check statement count (should have 5-6)
    $maxScore += 0.2;
    $statementCount = count($puzzle['statements'] ?? []);
    if ($statementCount >= 5 && $statementCount <= 6) {
        $score += 0.2;
    } elseif ($statementCount >= 4) {
        $score += 0.1;
    }
    
    // Check all required fields
    $maxScore += 0.1;
    $requiredFields = ['title', 'theme', 'case_summary', 'report_text', 'statements', 'hints', 'solution'];
    $missingFields = 0;
    foreach ($requiredFields as $field) {
        if (empty($puzzle[$field])) {
            $missingFields++;
        }
    }
    if ($missingFields === 0) {
        $score += 0.1;
    }
    
    // Normalize score (0.0 to 1.0)
    if ($maxScore > 0) {
        $score = min(1.0, $score / $maxScore);
    }
    
    return round($score, 2);
}

