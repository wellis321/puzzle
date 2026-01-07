<?php
/**
 * Session management class for tracking anonymous users
 */
class Session {
    private $db;
    private $sessionId;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initSession();
    }

    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user has a session ID
        if (!isset($_SESSION['puzzle_session_id'])) {
            // Generate new session ID
            $_SESSION['puzzle_session_id'] = $this->generateSessionId();
            $this->createSessionRecord($_SESSION['puzzle_session_id']);
        }

        $this->sessionId = $_SESSION['puzzle_session_id'];
    }

    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }

    private function createSessionRecord($sessionId) {
        $stmt = $this->db->prepare("INSERT INTO user_sessions (session_id) VALUES (?)");
        $stmt->execute([$sessionId]);
    }

    public function getSessionId() {
        return $this->sessionId;
    }
}
