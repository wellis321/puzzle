<?php
/**
 * Puzzle class for managing daily puzzles
 */
class Puzzle {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get today's puzzle
     * @param string|null $difficulty Optional difficulty filter ('easy', 'medium', 'hard')
     * @return array|false Returns puzzle array or false if not found
     */
    public function getTodaysPuzzle($difficulty = null) {
        if ($difficulty) {
            $stmt = $this->db->prepare("
                SELECT * FROM puzzles
                WHERE puzzle_date = CURDATE() AND difficulty = ?
                LIMIT 1
            ");
            $stmt->execute([$difficulty]);
        } else {
            // Default to medium if no difficulty specified (backward compatibility)
            $stmt = $this->db->prepare("
                SELECT * FROM puzzles
                WHERE puzzle_date = CURDATE() AND difficulty = 'medium'
                LIMIT 1
            ");
            $stmt->execute();
        }
        return $stmt->fetch();
    }

    /**
     * Get all puzzles for today (all difficulty levels)
     * @return array Array of puzzle arrays
     */
    public function getTodaysPuzzles() {
        $stmt = $this->db->prepare("
            SELECT * FROM puzzles
            WHERE puzzle_date = CURDATE()
            ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get puzzle by ID
     */
    public function getPuzzleById($puzzleId) {
        $stmt = $this->db->prepare("SELECT * FROM puzzles WHERE id = ?");
        $stmt->execute([$puzzleId]);
        return $stmt->fetch();
    }

    /**
     * Get puzzle by date
     * @param string $date Date in YYYY-MM-DD format
     * @param string|null $difficulty Optional difficulty filter
     * @return array|false Returns puzzle array or false if not found
     */
    public function getPuzzleByDate($date, $difficulty = null) {
        if ($difficulty) {
            $stmt = $this->db->prepare("
                SELECT * FROM puzzles 
                WHERE puzzle_date = ? AND difficulty = ?
                LIMIT 1
            ");
            $stmt->execute([$date, $difficulty]);
        } else {
            // Default to medium if no difficulty specified
            $stmt = $this->db->prepare("
                SELECT * FROM puzzles 
                WHERE puzzle_date = ? AND difficulty = 'medium'
                LIMIT 1
            ");
            $stmt->execute([$date]);
        }
        return $stmt->fetch();
    }

    /**
     * Get all puzzles for a specific date (all difficulty levels)
     * @param string $date Date in YYYY-MM-DD format
     * @return array Array of puzzle arrays
     */
    public function getPuzzlesByDate($date) {
        $stmt = $this->db->prepare("
            SELECT * FROM puzzles
            WHERE puzzle_date = ?
            ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    /**
     * Get all statements for a puzzle
     */
    public function getStatements($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM statements
            WHERE puzzle_id = ?
            ORDER BY statement_order ASC
        ");
        $stmt->execute([$puzzleId]);
        return $stmt->fetchAll();
    }

    /**
     * Get hints for a puzzle
     */
    public function getHints($puzzleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM hints
            WHERE puzzle_id = ?
            ORDER BY hint_order ASC
        ");
        $stmt->execute([$puzzleId]);
        return $stmt->fetchAll();
    }

    /**
     * Get solution for a puzzle
     */
    public function getSolution($puzzleId) {
        $stmt = $this->db->prepare("SELECT * FROM solutions WHERE puzzle_id = ?");
        $stmt->execute([$puzzleId]);
        return $stmt->fetch();
    }

    /**
     * Check if a statement is the correct answer
     */
    public function checkAnswer($statementId) {
        $stmt = $this->db->prepare("SELECT is_correct_answer FROM statements WHERE id = ?");
        $stmt->execute([$statementId]);
        $result = $stmt->fetch();
        return $result ? $result['is_correct_answer'] : false;
    }

    /**
     * Get all puzzles (for admin)
     */
    public function getAllPuzzles() {
        $stmt = $this->db->query("
            SELECT p.*,
                   (SELECT COUNT(*) FROM statements WHERE puzzle_id = p.id) as statement_count
            FROM puzzles p
            ORDER BY puzzle_date DESC, FIELD(difficulty, 'easy', 'medium', 'hard')
        ");
        return $stmt->fetchAll();
    }

    /**
     * Create a new puzzle
     */
    public function createPuzzle($data) {
        $stmt = $this->db->prepare("
            INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['puzzle_date'],
            $data['title'],
            $data['difficulty'],
            $data['theme'],
            $data['case_summary'],
            $data['report_text']
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Update a puzzle
     */
    public function updatePuzzle($puzzleId, $data) {
        $stmt = $this->db->prepare("
            UPDATE puzzles
            SET puzzle_date = ?, title = ?, difficulty = ?, theme = ?,
                case_summary = ?, report_text = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['puzzle_date'],
            $data['title'],
            $data['difficulty'],
            $data['theme'],
            $data['case_summary'],
            $data['report_text'],
            $puzzleId
        ]);
    }

    /**
     * Delete a puzzle
     */
    public function deletePuzzle($puzzleId) {
        $stmt = $this->db->prepare("DELETE FROM puzzles WHERE id = ?");
        return $stmt->execute([$puzzleId]);
    }

    /**
     * Create a statement for a puzzle
     */
    public function createStatement($puzzleId, $order, $text, $isCorrect, $category = 'general') {
        $stmt = $this->db->prepare("
            INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$puzzleId, $order, $text, $isCorrect ? 1 : 0, $category]);
    }

    /**
     * Create a hint for a puzzle
     */
    public function createHint($puzzleId, $order, $hintText) {
        $stmt = $this->db->prepare("
            INSERT INTO hints (puzzle_id, hint_order, hint_text)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$puzzleId, $order, $hintText]);
    }

    /**
     * Create solution for a puzzle
     */
    public function createSolution($puzzleId, $explanation, $detailedReasoning = '') {
        $stmt = $this->db->prepare("
            INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                explanation = VALUES(explanation),
                detailed_reasoning = VALUES(detailed_reasoning)
        ");
        return $stmt->execute([$puzzleId, $explanation, $detailedReasoning]);
    }
}
