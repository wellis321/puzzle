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
     */
    public function getTodaysPuzzle() {
        $stmt = $this->db->prepare("
            SELECT * FROM puzzles
            WHERE puzzle_date = CURDATE()
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch();
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
     */
    public function getPuzzleByDate($date) {
        $stmt = $this->db->prepare("SELECT * FROM puzzles WHERE puzzle_date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch();
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
            ORDER BY puzzle_date DESC
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
}
