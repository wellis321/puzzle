<?php
header('Content-Type: application/json');

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Session.php';
require_once '../includes/Puzzle.php';
require_once '../includes/Game.php';

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['puzzle_id']) || !isset($input['statement_id'])) {
        throw new Exception('Missing required parameters');
    }

    $puzzleId = (int)$input['puzzle_id'];
    $statementId = (int)$input['statement_id'];

    // Initialize classes
    $session = new Session();
    $puzzle = new Puzzle();
    $game = new Game($session->getSessionId());

    // Check if puzzle exists
    $puzzleData = $puzzle->getPuzzleById($puzzleId);
    if (!$puzzleData) {
        throw new Exception('Puzzle not found');
    }

    // Check if already completed
    if ($game->hasCompletedPuzzle($puzzleId)) {
        throw new Exception('Puzzle already completed');
    }

    // Check answer
    $isCorrect = $puzzle->checkAnswer($statementId);

    // Record attempt
    $result = $game->recordAttempt($puzzleId, $statementId, $isCorrect);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
