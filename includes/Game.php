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
        
        // Update user rank after completion
        $this->updateUserRank();
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

    /**
     * Get or create user rank record
     */
    public function getUserRank() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
            $stmt->execute([$this->sessionId]);
            $rank = $stmt->fetch();
            
            if (!$rank) {
                // Create initial rank record
                $stmt = $this->db->prepare("
                    INSERT INTO user_ranks (session_id, rank_name, rank_level)
                    VALUES (?, 'Novice Detective', 1)
                ");
                $stmt->execute([$this->sessionId]);
                
                // Fetch the newly created record
                $stmt = $this->db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
                $stmt->execute([$this->sessionId]);
                $rank = $stmt->fetch();
            }
            
            return $rank;
        } catch (PDOException $e) {
            // Table doesn't exist yet - return null to indicate ranks not set up
            // User needs to run the migration: database/add-ranks-table.sql
            return null;
        }
    }

    /**
     * Update user rank based on completion statistics
     */
    public function updateUserRank() {
        // Check if ranks table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM user_ranks LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist - skip rank update
            return;
        }
        
        // Get all completions for this user
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                p.difficulty
            FROM completions c
            JOIN puzzles p ON c.puzzle_id = p.id
            WHERE c.session_id = ?
        ");
        $stmt->execute([$this->sessionId]);
        $completions = $stmt->fetchAll();

        // Calculate statistics
        $stats = [
            'total_completions' => count($completions),
            'easy_completions' => 0,
            'medium_completions' => 0,
            'hard_completions' => 0,
            'perfect_scores' => 0,
            'solved_count' => 0,
            'total_attempts' => 0
        ];

        foreach ($completions as $completion) {
            $stats['total_attempts'] += $completion['attempts_used'];
            if ($completion['solved']) {
                $stats['solved_count']++;
                if ($completion['score'] === 'perfect') {
                    $stats['perfect_scores']++;
                }
            }
            
            switch ($completion['difficulty']) {
                case 'easy':
                    $stats['easy_completions']++;
                    break;
                case 'medium':
                    $stats['medium_completions']++;
                    break;
                case 'hard':
                    $stats['hard_completions']++;
                    break;
            }
        }

        // Calculate current streak (consecutive days with at least one completion)
        $streak = $this->calculateStreak();
        
        // Determine rank based on statistics
        $rankData = $this->calculateRank($stats, $streak);
        
        // Get current best streak before updating
        $currentRank = $this->getUserRank();
        $currentBestStreak = $currentRank ? $currentRank['best_streak'] : 0;
        $newBestStreak = max($currentBestStreak, $streak['current']);
        
        // Update or insert rank record
        $stmt = $this->db->prepare("
            INSERT INTO user_ranks (
                session_id, rank_name, rank_level,
                total_completions, easy_completions, medium_completions, hard_completions,
                perfect_scores, total_attempts, solved_count,
                current_streak, best_streak, last_activity_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE
                rank_name = VALUES(rank_name),
                rank_level = VALUES(rank_level),
                total_completions = VALUES(total_completions),
                easy_completions = VALUES(easy_completions),
                medium_completions = VALUES(medium_completions),
                hard_completions = VALUES(hard_completions),
                perfect_scores = VALUES(perfect_scores),
                total_attempts = VALUES(total_attempts),
                solved_count = VALUES(solved_count),
                current_streak = VALUES(current_streak),
                best_streak = VALUES(best_streak),
                last_activity_date = CURDATE(),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $this->sessionId,
            $rankData['name'],
            $rankData['level'],
            $stats['total_completions'],
            $stats['easy_completions'],
            $stats['medium_completions'],
            $stats['hard_completions'],
            $stats['perfect_scores'],
            $stats['total_attempts'],
            $stats['solved_count'],
            $streak['current'],
            $newBestStreak
        ]);
    }

    /**
     * Calculate user's current streak
     */
    private function calculateStreak() {
        // Get distinct dates with completions, ordered by date descending
        $stmt = $this->db->prepare("
            SELECT DISTINCT DATE(c.completed_at) as completion_date
            FROM completions c
            WHERE c.session_id = ?
            ORDER BY completion_date DESC
        ");
        $stmt->execute([$this->sessionId]);
        $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($dates)) {
            return ['current' => 0];
        }
        
        // Check if there was activity today or yesterday
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $currentStreak = 0;
        $expectedDate = $today;
        
        foreach ($dates as $dateRow) {
            $completionDate = $dateRow['completion_date'];
            
            // Allow today or yesterday to start/continue streak
            if ($currentStreak === 0) {
                if ($completionDate === $today || $completionDate === $yesterday) {
                    $currentStreak = 1;
                    $expectedDate = $completionDate === $today ? $yesterday : date('Y-m-d', strtotime($completionDate . ' -1 day'));
                    continue;
                } else {
                    break; // No recent activity, streak is 0
                }
            }
            
            // Check if this date continues the streak
            if ($completionDate === $expectedDate) {
                $currentStreak++;
                $expectedDate = date('Y-m-d', strtotime($completionDate . ' -1 day'));
            } else {
                break; // Streak broken
            }
        }
        
        return ['current' => $currentStreak];
    }

    /**
     * Calculate rank based on statistics
     */
    private function calculateRank($stats, $streak) {
        $totalCompletions = $stats['total_completions'];
        $hardCompletions = $stats['hard_completions'];
        $perfectScores = $stats['perfect_scores'];
        $solvedCount = $stats['solved_count'];
        $solveRate = $totalCompletions > 0 ? ($solvedCount / $totalCompletions) : 0;
        
        // Rank progression system
        $ranks = [
            1 => ['name' => 'Novice Detective', 'min_completions' => 0],
            2 => ['name' => 'Junior Detective', 'min_completions' => 3],
            3 => ['name' => 'Detective', 'min_completions' => 10],
            4 => ['name' => 'Senior Detective', 'min_completions' => 25],
            5 => ['name' => 'Master Detective', 'min_completions' => 50],
            6 => ['name' => 'Chief Inspector', 'min_completions' => 100],
            7 => ['name' => 'Detective Inspector', 'min_completions' => 200],
            8 => ['name' => 'Sherlock Holmes', 'min_completions' => 300],
            9 => ['name' => 'Hercule Poirot', 'min_completions' => 400],
            10 => ['name' => 'Columbo', 'min_completions' => 500],
        ];
        
        // Determine base level from completions
        $level = 1;
        foreach ($ranks as $rankLevel => $rankInfo) {
            if ($totalCompletions >= $rankInfo['min_completions']) {
                $level = $rankLevel;
            } else {
                break;
            }
        }
        
        // Special rank bonuses
        // If they have many hard completions and high solve rate, boost rank
        if ($hardCompletions >= 50 && $solveRate >= 0.8) {
            $level = min($level + 1, 10);
        }
        
        // Perfect score bonus
        if ($perfectScores >= 100) {
            $level = min($level + 1, 10);
        }
        
        // Long streak bonus
        if ($streak['current'] >= 30) {
            $level = min($level + 1, 10);
        }
        
        return [
            'name' => $ranks[$level]['name'],
            'level' => $level
        ];
    }

    /**
     * Get rank progress to next level
     */
    public function getRankProgress() {
        $rank = $this->getUserRank();
        
        // If ranks table doesn't exist, return default values
        if (!$rank) {
            return [
                'current_rank' => 'Novice Detective',
                'current_level' => 1,
                'next_rank' => 'Junior Detective',
                'progress' => 0,
                'needed' => 3,
                'percentage' => 0,
                'stats' => [
                    'total_completions' => 0,
                    'easy_completions' => 0,
                    'medium_completions' => 0,
                    'hard_completions' => 0,
                    'perfect_scores' => 0,
                    'current_streak' => 0
                ],
                'table_missing' => true
            ];
        }
        
        $stats = [
            'total_completions' => $rank['total_completions'],
            'easy_completions' => $rank['easy_completions'],
            'medium_completions' => $rank['medium_completions'],
            'hard_completions' => $rank['hard_completions'],
            'perfect_scores' => $rank['perfect_scores'],
            'current_streak' => $rank['current_streak']
        ];
        
        $rankLevels = [
            1 => ['name' => 'Novice Detective', 'min' => 0, 'next' => 3],
            2 => ['name' => 'Junior Detective', 'min' => 3, 'next' => 10],
            3 => ['name' => 'Detective', 'min' => 10, 'next' => 25],
            4 => ['name' => 'Senior Detective', 'min' => 25, 'next' => 50],
            5 => ['name' => 'Master Detective', 'min' => 50, 'next' => 100],
            6 => ['name' => 'Chief Inspector', 'min' => 100, 'next' => 200],
            7 => ['name' => 'Detective Inspector', 'min' => 200, 'next' => 300],
            8 => ['name' => 'Sherlock Holmes', 'min' => 300, 'next' => 400],
            9 => ['name' => 'Hercule Poirot', 'min' => 400, 'next' => 500],
            10 => ['name' => 'Columbo', 'min' => 500, 'next' => null],
        ];
        
        $currentLevel = $rank['rank_level'];
        $current = $rankLevels[$currentLevel];
        $next = $currentLevel < 10 ? $rankLevels[$currentLevel + 1] : null;
        
        if ($next) {
            $progress = $stats['total_completions'] - $current['min'];
            $needed = $next['min'] - $current['min'];
            $percentage = min(100, round(($progress / $needed) * 100));
            
            return [
                'current_rank' => $current['name'],
                'current_level' => $currentLevel,
                'next_rank' => $next['name'],
                'progress' => $progress,
                'needed' => $needed,
                'percentage' => $percentage,
                'stats' => $stats
            ];
        } else {
            // Max rank achieved
            return [
                'current_rank' => $current['name'],
                'current_level' => $currentLevel,
                'next_rank' => null,
                'progress' => $stats['total_completions'],
                'needed' => 0,
                'percentage' => 100,
                'stats' => $stats,
                'max_rank' => true
            ];
        }
    }
}
