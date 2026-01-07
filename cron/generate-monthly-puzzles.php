<?php
/**
 * Automated Puzzle Generation Script
 * Run this monthly via cron to generate puzzles for the next month
 * 
 * Cron setup (runs on 1st of each month at 2 AM):
 * 0 2 1 * * /usr/bin/php /path/to/puzzle/cron/generate-monthly-puzzles.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Puzzle.php';
require_once __DIR__ . '/../includes/AIPuzzleGenerator.php';

// Configuration
$provider = EnvLoader::get('AI_PROVIDER', 'gemini'); // gemini, groq, or openai
$generateDaysAhead = 30; // Generate puzzles for next 30 days
$generateImages = EnvLoader::get('GENERATE_IMAGES', 'false') === 'true'; // Set to 'true' in .env to enable image generation

try {
    $puzzle = new Puzzle();
    $generator = new AIPuzzleGenerator($provider);
    
    $generated = [];
    $errors = [];
    $skipped = [];
    
    // Generate puzzles for next month
    for ($i = 1; $i <= $generateDaysAhead; $i++) {
        $targetDate = date('Y-m-d', strtotime("+{$i} days"));
        
        // Check if puzzles already exist for this date
        $existing = $puzzle->getPuzzlesByDate($targetDate);
        if (count($existing) >= 3) {
            $skipped[] = $targetDate;
            continue; // Already has all 3 difficulties
        }
        
        // Generate all 3 difficulties
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            // Check if this difficulty already exists
            $existingPuzzle = $puzzle->getPuzzleByDate($targetDate, $difficulty);
            if ($existingPuzzle) {
                continue; // Skip if already exists
            }
            
            try {
                $generatedPuzzle = $generator->generatePuzzle($targetDate, $difficulty, $generateImages);
                
                if ($generatedPuzzle) {
                    $puzzleId = $puzzle->createPuzzle([
                        'puzzle_date' => $targetDate,
                        'title' => $generatedPuzzle['title'],
                        'difficulty' => $difficulty,
                        'theme' => $generatedPuzzle['theme'],
                        'case_summary' => $generatedPuzzle['case_summary'],
                        'report_text' => $generatedPuzzle['report_text']
                    ]);
                    
                    // Save statements - SHUFFLE them first to randomize position of correct answer
                    $statements = $generatedPuzzle['statements'];
                    // Shuffle array while preserving keys (to maintain is_correct mapping)
                    $keys = array_keys($statements);
                    shuffle($keys);
                    $shuffledStatements = [];
                    foreach ($keys as $key) {
                        $shuffledStatements[] = $statements[$key];
                    }
                    // Now save in shuffled order
                    foreach ($shuffledStatements as $order => $stmt) {
                        $puzzle->createStatement(
                            $puzzleId, 
                            $order + 1, 
                            $stmt['text'], 
                            $stmt['is_correct'] ?? false,
                            $stmt['category'] ?? 'general'
                        );
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
                        $generatedPuzzle['solution']['detailed_reasoning'] ?? '',
                        $imagePath,
                        $imagePrompt
                    );
                    
                    $generated[] = "{$targetDate} ({$difficulty})";
                    
                    // Rate limiting: wait 4 seconds between requests (for Gemini's 15/min limit)
                    if ($provider === 'gemini') {
                        sleep(4);
                    }
                }
            } catch (Exception $e) {
                $errors[] = "{$targetDate} ({$difficulty}): " . $e->getMessage();
            }
        }
    }
    
    // Log results
    $log = date('Y-m-d H:i:s') . " - Puzzle Generation Complete\n";
    $log .= "Generated: " . count($generated) . " puzzles\n";
    $log .= "Skipped: " . count($skipped) . " dates (already exist)\n";
    $log .= "Errors: " . count($errors) . "\n";
    
    if (!empty($generated)) {
        $log .= "\nGenerated puzzles:\n" . implode("\n", $generated) . "\n";
    }
    
    if (!empty($errors)) {
        $log .= "\nErrors:\n" . implode("\n", $errors) . "\n";
    }
    
    // Save log (optional)
    file_put_contents(__DIR__ . '/generation-log.txt', $log . "\n", FILE_APPEND);
    
    // Output for cron
    echo $log;
    
} catch (Exception $e) {
    $error = date('Y-m-d H:i:s') . " - Fatal Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/generation-log.txt', $error, FILE_APPEND);
    echo $error;
    exit(1);
}

