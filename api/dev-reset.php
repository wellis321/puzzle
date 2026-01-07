<?php
// DEV MODE ONLY: Reset puzzle progress
header('Content-Type: application/json');

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Session.php';

// Only allow in development
if (EnvLoader::get('APP_ENV') !== 'development') {
    http_response_code(403);
    echo json_encode(['error' => 'Not available in production']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $puzzleId = (int)$input['puzzle_id'];

    $session = new Session();
    $sessionId = $session->getSessionId();

    $db = Database::getInstance()->getConnection();

    // Delete attempts for this puzzle and session
    $stmt = $db->prepare("DELETE FROM attempts WHERE session_id = ? AND puzzle_id = ?");
    $stmt->execute([$sessionId, $puzzleId]);

    // Delete completion record
    $stmt = $db->prepare("DELETE FROM completions WHERE session_id = ? AND puzzle_id = ?");
    $stmt->execute([$sessionId, $puzzleId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
