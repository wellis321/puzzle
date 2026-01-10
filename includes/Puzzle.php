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
     * @param string|null $puzzleType Optional puzzle type filter ('standard', 'whodunit')
     * @return array|false Returns puzzle array or false if not found
     */
    public function getPuzzleByDate($date, $difficulty = null, $puzzleType = null) {
        // Check if puzzle_type column exists
        $hasPuzzleTypeColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM puzzles LIKE 'puzzle_type'");
            $hasPuzzleTypeColumn = $columnCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasPuzzleTypeColumn = false;
        }
        
        if ($difficulty) {
            if ($hasPuzzleTypeColumn && $puzzleType) {
                // Check for specific puzzle_type
                $stmt = $this->db->prepare("
                    SELECT * FROM puzzles 
                    WHERE puzzle_date = ? AND difficulty = ? AND puzzle_type = ?
                    LIMIT 1
                ");
                $stmt->execute([$date, $difficulty, $puzzleType]);
            } else {
                // Standard check (backward compatible)
                $stmt = $this->db->prepare("
                    SELECT * FROM puzzles 
                    WHERE puzzle_date = ? AND difficulty = ?
                    LIMIT 1
                ");
                $stmt->execute([$date, $difficulty]);
            }
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
        // Check if image columns exist first (for backward compatibility)
        try {
            $stmt = $this->db->prepare("SELECT explanation, detailed_reasoning, image_path, image_prompt FROM solutions WHERE puzzle_id = ?");
            $stmt->execute([$puzzleId]);
            $result = $stmt->fetch();
            
            // Ensure image_prompt and image_path keys exist even if NULL
            if ($result) {
                if (!isset($result['image_path'])) {
                    $result['image_path'] = null;
                }
                if (!isset($result['image_prompt'])) {
                    $result['image_prompt'] = null;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            // If columns don't exist, try without them
            $stmt = $this->db->prepare("SELECT explanation, detailed_reasoning FROM solutions WHERE puzzle_id = ?");
            $stmt->execute([$puzzleId]);
            $result = $stmt->fetch();
            if ($result) {
                $result['image_path'] = null;
                $result['image_prompt'] = null;
            }
            return $result;
        }
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
        // Check if puzzle_type column exists
        $hasPuzzleTypeColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM puzzles LIKE 'puzzle_type'");
            $hasPuzzleTypeColumn = $columnCheck->rowCount() > 0;
        } catch (Exception $e) {
            $hasPuzzleTypeColumn = false;
        }
        
        // Build query based on available columns
        if ($hasPuzzleTypeColumn && isset($data['puzzle_type'])) {
            $stmt = $this->db->prepare("
                INSERT INTO puzzles (puzzle_date, title, difficulty, puzzle_type, theme, case_summary, report_text)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['puzzle_date'],
                $data['title'],
                $data['difficulty'],
                $data['puzzle_type'],
                $data['theme'],
                $data['case_summary'],
                $data['report_text']
            ]);
        } else {
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
        }
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
    public function createSolution($puzzleId, $explanation, $detailedReasoning = '', $imagePath = null, $imagePrompt = null) {
        // Check if image columns exist
        try {
            $testStmt = $this->db->query("SELECT image_path, image_prompt FROM solutions LIMIT 1");
            $hasImageColumns = true;
        } catch (PDOException $e) {
            $hasImageColumns = false;
        }
        
        if ($hasImageColumns) {
            $stmt = $this->db->prepare("
                INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning, image_path, image_prompt)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    explanation = VALUES(explanation),
                    detailed_reasoning = VALUES(detailed_reasoning),
                    image_path = VALUES(image_path),
                    image_prompt = VALUES(image_prompt)
            ");
            return $stmt->execute([$puzzleId, $explanation, $detailedReasoning, $imagePath, $imagePrompt]);
        } else {
            // Fallback for databases without image columns
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
    
    /**
     * Get witness statements for a whodunit puzzle
     */
    public function getWitnessStatements($puzzleId) {
        // Check if witness_statements table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM witness_statements LIMIT 1");
        } catch (PDOException $e) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM witness_statements
            WHERE puzzle_id = ?
            ORDER BY statement_order ASC
        ");
        $stmt->execute([$puzzleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get suspect profiles for a whodunit puzzle
     */
    public function getSuspectProfiles($puzzleId) {
        // Check if suspect_profiles table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM suspect_profiles LIMIT 1");
        } catch (PDOException $e) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM suspect_profiles
            WHERE puzzle_id = ?
            ORDER BY profile_order ASC
        ");
        $stmt->execute([$puzzleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create a witness statement for a whodunit puzzle
     */
    public function createWitnessStatement($puzzleId, $order, $witnessName, $statementText) {
        // Check if witness_statements table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM witness_statements LIMIT 1");
        } catch (PDOException $e) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO witness_statements (puzzle_id, witness_name, statement_text, statement_order)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$puzzleId, $witnessName, $statementText, $order]);
    }
    
    /**
     * Create a suspect profile for a whodunit puzzle
     */
    public function createSuspectProfile($puzzleId, $order, $suspectName, $profileText) {
        // Check if suspect_profiles table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM suspect_profiles LIMIT 1");
        } catch (PDOException $e) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO suspect_profiles (puzzle_id, suspect_name, profile_text, profile_order)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$puzzleId, $suspectName, $profileText, $order]);
    }
    
    /**
     * Check if puzzle is a whodunit
     */
    public function isWhodunit($puzzleId) {
        $puzzle = $this->getPuzzleById($puzzleId);
        if (!$puzzle) {
            return false;
        }
        
        // Check if puzzle_type column exists
        if (isset($puzzle['puzzle_type'])) {
            return $puzzle['puzzle_type'] === 'whodunit';
        }
        
        // Fallback: check if it has witness statements
        $witnesses = $this->getWitnessStatements($puzzleId);
        return !empty($witnesses);
    }
    
    /**
     * Get all available whodunit puzzles for a user (filtered by unlock level)
     */
    public function getAvailableWhodunits($userRankLevel = 0) {
        // Check if puzzle_type column exists
        try {
            $testStmt = $this->db->query("SHOW COLUMNS FROM puzzles LIKE 'puzzle_type'");
            if ($testStmt->rowCount() === 0) {
                return [];
            }
        } catch (PDOException $e) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM puzzles
            WHERE puzzle_type = 'whodunit'
            AND (unlock_level IS NULL OR unlock_level <= ?)
            ORDER BY puzzle_date DESC
        ");
        $stmt->execute([$userRankLevel]);
        return $stmt->fetchAll();
    }
}
