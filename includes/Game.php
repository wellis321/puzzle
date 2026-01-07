<?php
/**
 * Game logic class for handling attempts, completions, and scoring
 */
class Game {
    private $db;
    private $sessionId;
    private $maxAttempts = 3;

    public function __construct($sessionId) {
        $this->db = Database::getInstance()->getConnection();
        $this->sessionId = $sessionId;
    }

    /**
     * Check if user has already completed today's puzzle
     */
    public function hasCompletedPuzzle($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM completions
            WHERE session_id = ? AND puzzle_id = ?
        ");
        $stmt->execute([$this->sessionId, $puzzleId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get user's attempts for a puzzle
     */
    public function getAttempts($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM attempts
            WHERE session_id = ? AND puzzle_id = ?
            ORDER BY attempt_number ASC
        ");
        $stmt->execute([$this->sessionId, $puzzleId]);
        return $stmt->fetchAll();
    }

    /**
     * Get number of attempts used
     */
    public function getAttemptCount($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM attempts
            WHERE session_id = ? AND puzzle_id = ?
        ");
        $stmt->execute([$this->sessionId, $puzzleId]);
        $result = $stmt->fetch();
        return $result['count'];
    }

    /**
     * Record an attempt
     */
    public function recordAttempt($puzzleId, $statementId, $isCorrect) {
        $attemptNumber = $this->getAttemptCount($puzzleId) + 1;

        if ($attemptNumber > $this->maxAttempts) {
            return ['success' => false, 'error' => 'Maximum attempts exceeded'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO attempts (session_id, puzzle_id, statement_id, attempt_number, is_correct)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->sessionId, $puzzleId, $statementId, $attemptNumber, $isCorrect]);

        // If correct or out of attempts, mark as complete
        if ($isCorrect || $attemptNumber >= $this->maxAttempts) {
            $this->recordCompletion($puzzleId, $attemptNumber, $isCorrect);
        }

        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'attempt_number' => $attemptNumber,
            'attempts_remaining' => $this->maxAttempts - $attemptNumber
        ];
    }

    /**
     * Record puzzle completion
     */
    private function recordCompletion($puzzleId, $attemptsUsed, $solved) {
        // Determine score
        $score = 'lucky';
        if ($solved) {
            if ($attemptsUsed === 1) {
                $score = 'perfect';
            } elseif ($attemptsUsed === 2) {
                $score = 'close';
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO completions (session_id, puzzle_id, attempts_used, solved, score)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                attempts_used = VALUES(attempts_used),
                solved = VALUES(solved),
                score = VALUES(score)
        ");
        $stmt->execute([$this->sessionId, $puzzleId, $attemptsUsed, $solved, $score]);

        // Update puzzle stats
        $this->updatePuzzleStats($puzzleId);
    }

    /**
     * Update aggregate puzzle statistics
     */
    private function updatePuzzleStats($puzzleId) {
        $stmt = $this->db->prepare("
            INSERT INTO puzzle_stats (puzzle_id, total_attempts, total_completions, total_solved, avg_attempts)
            SELECT
                puzzle_id,
                COUNT(*) as total_attempts,
                COUNT(DISTINCT session_id) as total_completions,
                SUM(CASE WHEN solved = 1 THEN 1 ELSE 0 END) as total_solved,
                AVG(attempts_used) as avg_attempts
            FROM completions
            WHERE puzzle_id = ?
            GROUP BY puzzle_id
            ON DUPLICATE KEY UPDATE
                total_attempts = VALUES(total_attempts),
                total_completions = VALUES(total_completions),
                total_solved = VALUES(total_solved),
                avg_attempts = VALUES(avg_attempts)
        ");
        $stmt->execute([$puzzleId]);
    }

    /**
     * Get user's completion record for a puzzle
     */
    public function getCompletion($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM completions
            WHERE session_id = ? AND puzzle_id = ?
        ");
        $stmt->execute([$this->sessionId, $puzzleId]);
        return $stmt->fetch();
    }

    /**
     * Get shareable result string
     */
    public function getShareableResult($puzzleId, $puzzleNumber) {
        $completion = $this->getCompletion($puzzleId);
        if (!$completion) {
            return null;
        }

        $attempts = $this->getAttempts($puzzleId);
        $result = "[CASE] Case #{$puzzleNumber}";

        if ($completion['solved']) {
            $result .= " - Solved\n";
        } else {
            $result .= " - Unsolved\n";
        }

        // Create result grid
        foreach ($attempts as $attempt) {
            if ($attempt['is_correct']) {
                $result .= "[✓]";
            } else {
                $result .= "[✗]";
            }
        }

        $result .= "\n\nPlay tomorrow's case at " . APP_URL;

        return $result;
    }
}
