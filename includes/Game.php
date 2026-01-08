<?php
/**
 * Game logic class for handling attempts, completions, and scoring
 */
class Game {
    private $db;
    private $sessionId;
    private $userId;
    private $maxAttempts = 3;

    public function __construct($sessionId, $userId = null) {
        $this->db = Database::getInstance()->getConnection();
        $this->sessionId = $sessionId;
        $this->userId = $userId;
    }
    
    /**
     * Get identifier for queries (user_id if logged in, session_id if anonymous)
     */
    private function getIdentifier() {
        return $this->userId ?? $this->sessionId;
    }
    
    /**
     * Build WHERE clause for user identification
     */
    private function getUserWhereClause() {
        if ($this->userId) {
            return "user_id = ?";
        } else {
            return "session_id = ?";
        }
    }

    /**
     * Check if user has already completed today's puzzle
     */
    public function hasCompletedPuzzle($puzzleId) {
        // Check if user_id column exists
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM completions LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM completions
                WHERE {$whereClause} AND puzzle_id = ?
            ");
            $stmt->execute([$identifier, $puzzleId]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking completion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's attempts for a puzzle
     */
    public function getAttempts($puzzleId) {
        // Check if user_id column exists
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM attempts LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM attempts
                WHERE {$whereClause} AND puzzle_id = ?
                ORDER BY attempt_number ASC
            ");
            $stmt->execute([$identifier, $puzzleId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting attempts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get number of attempts used
     */
    public function getAttemptCount($puzzleId) {
        // Check if user_id column exists
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM attempts LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM attempts
                WHERE {$whereClause} AND puzzle_id = ?
            ");
            $stmt->execute([$identifier, $puzzleId]);
            $result = $stmt->fetch();
            return $result ? (int)$result['count'] : 0;
        } catch (PDOException $e) {
            error_log("Error getting attempt count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Record an attempt
     */
    public function recordAttempt($puzzleId, $statementId, $isCorrect) {
        $attemptNumber = $this->getAttemptCount($puzzleId) + 1;

        if ($attemptNumber > $this->maxAttempts) {
            return ['success' => false, 'error' => 'Maximum attempts exceeded'];
        }

        // Insert attempt with user_id if available, otherwise session_id
        if ($this->userId) {
            $stmt = $this->db->prepare("
                INSERT INTO attempts (user_id, puzzle_id, statement_id, attempt_number, is_correct)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->userId, $puzzleId, $statementId, $attemptNumber, $isCorrect]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO attempts (session_id, puzzle_id, statement_id, attempt_number, is_correct)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->sessionId, $puzzleId, $statementId, $attemptNumber, $isCorrect]);
        }

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
     * Force completion record (public method for edge cases)
     * Used when attempts exist but completion wasn't recorded properly
     */
    public function forceCompletion($puzzleId, $attemptsUsed, $solved) {
        // Only create if not already exists
        if (!$this->hasCompletedPuzzle($puzzleId)) {
            $this->recordCompletion($puzzleId, $attemptsUsed, $solved);
        }
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

        // Check if completion already exists
        $existing = $this->getCompletion($puzzleId);
        
        if ($existing) {
            // Update existing completion
            if ($this->userId) {
                $stmt = $this->db->prepare("
                    UPDATE completions 
                    SET attempts_used = ?, solved = ?, score = ?
                    WHERE user_id = ? AND puzzle_id = ?
                ");
                $stmt->execute([$attemptsUsed, $solved, $score, $this->userId, $puzzleId]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE completions 
                    SET attempts_used = ?, solved = ?, score = ?
                    WHERE session_id = ? AND puzzle_id = ? AND (user_id IS NULL)
                ");
                $stmt->execute([$attemptsUsed, $solved, $score, $this->sessionId, $puzzleId]);
            }
        } else {
            // Insert new completion
            if ($this->userId) {
                $stmt = $this->db->prepare("
                    INSERT INTO completions (user_id, puzzle_id, attempts_used, solved, score)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$this->userId, $puzzleId, $attemptsUsed, $solved, $score]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO completions (session_id, puzzle_id, attempts_used, solved, score)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$this->sessionId, $puzzleId, $attemptsUsed, $solved, $score]);
            }
        }

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
        // Check if user_id column exists
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM completions LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM completions
                WHERE {$whereClause} AND puzzle_id = ?
            ");
            $stmt->execute([$identifier, $puzzleId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting completion: " . $e->getMessage());
            return false;
        }
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
        $puzzle = new Puzzle();
        $puzzleData = $puzzle->getPuzzleById($puzzleId);
        
        // Get puzzle date for display
        $puzzleDate = $puzzleData ? date('M j, Y', strtotime($puzzleData['puzzle_date'])) : "Today";
        $difficulty = $puzzleData ? ucfirst($puzzleData['difficulty']) : '';
        
        // Build attempt indicators (using text symbols for shareable format)
        $attemptIcons = '';
        foreach ($attempts as $attempt) {
            $attemptIcons .= $attempt['is_correct'] ? '✓' : '✗';
        }
        
        // Score text
        $scoreText = '';
        if ($completion['solved']) {
            switch ($completion['score']) {
                case 'perfect':
                    $scoreText = 'Perfect Deduction';
                    break;
                case 'close':
                    $scoreText = 'Close Call';
                    break;
                default:
                    $scoreText = 'Solved';
            }
        } else {
            $scoreText = 'Case Closed';
        }
        
        // Create a cleaner, more compact shareable format
        $result = "Daily Mystery - {$puzzleDate}\n";
        $result .= "{$scoreText} {$attemptIcons}\n";
        $result .= "\n" . APP_URL;

        return $result;
    }

    /**
     * Get or create user rank record
     */
    public function getUserRank() {
        try {
            // First, check if table exists by attempting a simple query
            $testStmt = $this->db->query("SELECT 1 FROM user_ranks LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist - return null
            return null;
        }
        
        // Ensure session exists in user_sessions first (required for foreign key)
        try {
            $sessionCheck = $this->db->prepare("SELECT 1 FROM user_sessions WHERE session_id = ?");
            $sessionCheck->execute([$this->sessionId]);
            if (!$sessionCheck->fetch()) {
                // Session doesn't exist, create it
                $createSession = $this->db->prepare("INSERT INTO user_sessions (session_id) VALUES (?)");
                $createSession->execute([$this->sessionId]);
            }
        } catch (PDOException $e) {
            // If user_sessions table doesn't exist, that's okay - ranks won't work but won't crash
            error_log("Warning: user_sessions table may not exist: " . $e->getMessage());
        }
        
        // Check by user_id first, then session_id (only if columns exist)
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM user_ranks LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        $identifier = $this->getIdentifier();
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM user_ranks WHERE {$whereClause}");
            $stmt->execute([$identifier]);
            $rank = $stmt->fetch();
            
            if (!$rank) {
                // Create initial rank record
                // Always provide session_id since it's required (no default value)
                if ($this->userId && $hasUserIdColumn) {
                    // Include both user_id and session_id
                    $stmt = $this->db->prepare("
                        INSERT IGNORE INTO user_ranks (user_id, session_id, rank_name, rank_level)
                        VALUES (?, ?, 'Novice Detective', 1)
                    ");
                    try {
                        $stmt->execute([$this->userId, $this->sessionId]);
                    } catch (PDOException $e) {
                        // If insert fails, just continue
                        error_log("Error creating rank record: " . $e->getMessage());
                    }
                    $stmt = $this->db->prepare("SELECT * FROM user_ranks WHERE user_id = ?");
                    $stmt->execute([$this->userId]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT IGNORE INTO user_ranks (session_id, rank_name, rank_level)
                        VALUES (?, 'Novice Detective', 1)
                    ");
                    try {
                        $stmt->execute([$this->sessionId]);
                    } catch (PDOException $e) {
                        // If insert fails, just continue
                    }
                    $stmt = $this->db->prepare("SELECT * FROM user_ranks WHERE session_id = ?");
                    $stmt->execute([$this->sessionId]);
                }
                $rank = $stmt->fetch();
            }
            
            return $rank;
        } catch (PDOException $e) {
            // Unexpected error, but table exists - return null anyway
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
        
        // Check if user_id column exists in completions table
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM completions LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            // If we can't check, assume it doesn't exist
            $hasUserIdColumn = false;
        }
        
        // Get all completions for this user (by user_id or session_id)
        // Only use user_id if the column exists and user is logged in
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "c.user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "c.session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    p.difficulty
                FROM completions c
                JOIN puzzles p ON c.puzzle_id = p.id
                WHERE {$whereClause}
            ");
            $stmt->execute([$identifier]);
            $completions = $stmt->fetchAll();
        } catch (PDOException $e) {
            // If query fails (e.g., column doesn't exist), return empty stats
            error_log("Error fetching completions for rank update: " . $e->getMessage());
            $completions = [];
        }

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
        
        // Update or insert rank record (using user_id if available, otherwise session_id)
        // Check if user_id column exists in user_ranks table
        $hasUserIdColumnInRanks = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM user_ranks LIKE 'user_id'");
            $hasUserIdColumnInRanks = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumnInRanks = false;
        }
        
        // Always provide session_id since it's required (no default value)
        // Use user_id if available and column exists
        if ($this->userId && $hasUserIdColumnInRanks) {
            // Include both user_id and session_id when user_id column exists
            $stmt = $this->db->prepare("
                INSERT INTO user_ranks (
                    user_id, session_id, rank_name, rank_level,
                    total_completions, easy_completions, medium_completions, hard_completions,
                    perfect_scores, total_attempts, solved_count,
                    current_streak, best_streak, last_activity_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
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
                $this->userId,
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
        } else {
            // Only session_id (old schema or anonymous user)
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
    }

    /**
     * Calculate user's current winning streak (only counts SOLVED cases)
     */
    private function calculateStreak() {
        // Check if user_id column exists in completions table
        $hasUserIdColumn = false;
        try {
            $columnCheck = $this->db->query("SHOW COLUMNS FROM completions LIKE 'user_id'");
            $hasUserIdColumn = $columnCheck->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUserIdColumn = false;
        }
        
        // Get distinct dates with SOLVED completions only, ordered by date descending
        if ($this->userId && $hasUserIdColumn) {
            $whereClause = "c.user_id = ?";
            $identifier = $this->userId;
        } else {
            $whereClause = "c.session_id = ?";
            $identifier = $this->sessionId;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT DATE(c.completed_at) as completion_date
                FROM completions c
                WHERE {$whereClause} AND c.solved = 1
                ORDER BY completion_date DESC
            ");
            $stmt->execute([$identifier]);
            $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If query fails, return empty streak
            error_log("Error calculating streak: " . $e->getMessage());
            $dates = [];
        }
        
        if (empty($dates)) {
            return ['current' => 0];
        }
        
        // Check if there was a win today or yesterday
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
                    break; // No recent wins, streak is 0
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
     * Calculate rank based on statistics (based on WINS, not just completions)
     */
    private function calculateRank($stats, $streak) {
        $solvedCount = $stats['solved_count']; // Use wins, not total completions
        $hardCompletions = $stats['hard_completions'];
        $perfectScores = $stats['perfect_scores'];
        $totalCompletions = $stats['total_completions'];
        $solveRate = $totalCompletions > 0 ? ($solvedCount / $totalCompletions) : 0;
        
        // Rank progression system - based on WINS (solved cases)
        $ranks = [
            1 => ['name' => 'Novice Detective', 'min_wins' => 0],
            2 => ['name' => 'Junior Detective', 'min_wins' => 3],
            3 => ['name' => 'Detective', 'min_wins' => 10],
            4 => ['name' => 'Senior Detective', 'min_wins' => 25],
            5 => ['name' => 'Master Detective', 'min_wins' => 50],
            6 => ['name' => 'Chief Inspector', 'min_wins' => 100],
            7 => ['name' => 'Detective Inspector', 'min_wins' => 200],
            8 => ['name' => 'Sherlock Holmes', 'min_wins' => 300],
            9 => ['name' => 'Hercule Poirot', 'min_wins' => 400],
            10 => ['name' => 'Columbo', 'min_wins' => 500],
        ];
        
        // Determine base level from wins (solved cases)
        $level = 1;
        foreach ($ranks as $rankLevel => $rankInfo) {
            if ($solvedCount >= $rankInfo['min_wins']) {
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
        
        // Long winning streak bonus (only counts wins)
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
        // First check if table exists
        try {
            $testStmt = $this->db->query("SELECT 1 FROM user_ranks LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist
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
                    'solved_count' => 0,
                    'current_streak' => 0
                ],
                'table_missing' => true
            ];
        }
        
        // Recalculate rank to ensure it's up to date
        // This ensures stats are current even if updateUserRank hasn't run yet
        $this->updateUserRank();
        
        $rank = $this->getUserRank();
        
        // If getUserRank returns null but table exists, create default rank data
        if (!$rank) {
            // Table exists but no rank record - return default (will be created on first completion)
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
                    'solved_count' => 0,
                    'current_streak' => 0
                ],
                'table_missing' => false  // Table exists, just no rank record yet
            ];
        }
        
        $stats = [
            'total_completions' => $rank['total_completions'],
            'easy_completions' => $rank['easy_completions'],
            'medium_completions' => $rank['medium_completions'],
            'hard_completions' => $rank['hard_completions'],
            'perfect_scores' => $rank['perfect_scores'],
            'solved_count' => $rank['solved_count'] ?? 0,
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
            // Progress based on wins (solved_count), not total completions
            $progress = $stats['solved_count'] - $current['min'];
            $needed = $next['min'] - $current['min'];
            $percentage = min(100, round(($progress / $needed) * 100));
            
            return [
                'current_rank' => $current['name'],
                'current_level' => $currentLevel,
                'next_rank' => $next['name'],
                'progress' => $progress,
                'needed' => $needed,
                'percentage' => $percentage,
                'stats' => $stats,
                'table_missing' => false
            ];
        } else {
            // Max rank achieved
            return [
                'current_rank' => $current['name'],
                'current_level' => $currentLevel,
                'next_rank' => null,
                'progress' => $stats['solved_count'],
                'needed' => 0,
                'percentage' => 100,
                'stats' => $stats,
                'max_rank' => true,
                'table_missing' => false
            ];
        }
    }
    
    /**
     * Check if user can access whodunit puzzles
     * Unlocks at Rank 3 (Detective level) or 10+ solved cases
     */
    public function canAccessWhodunits() {
        $rankProgress = $this->getRankProgress();
        
        // Check if rank system exists
        if (isset($rankProgress['table_missing']) && $rankProgress['table_missing']) {
            // If no rank system, allow access after 10 solved cases
            // This is a fallback - normally ranks would be required
            return true; // Allow for now, will be refined
        }
        
        // Unlock at Rank 3 (Detective) or higher
        $currentLevel = $rankProgress['current_level'] ?? 1;
        
        // Also check solved count as alternative unlock
        $solvedCount = $rankProgress['stats']['solved_count'] ?? 0;
        
        return $currentLevel >= 3 || $solvedCount >= 10;
    }
    
    /**
     * Get user's whodunit unlock status
     */
    public function getWhodunitUnlockStatus() {
        $canAccess = $this->canAccessWhodunits();
        $rankProgress = $this->getRankProgress();
        
        if ($canAccess) {
            return [
                'unlocked' => true,
                'reason' => 'Access granted'
            ];
        }
        
        // Determine what's needed
        $currentLevel = $rankProgress['current_level'] ?? 1;
        $solvedCount = $rankProgress['stats']['solved_count'] ?? 0;
        
        $neededLevel = 3;
        $neededWins = 10;
        
        $levelProgress = $neededLevel - $currentLevel;
        $winsProgress = $neededWins - $solvedCount;
        
        return [
            'unlocked' => false,
            'reason' => $levelProgress <= $winsProgress 
                ? "Reach Rank 3 (Detective) - {$levelProgress} more rank(s) needed"
                : "Solve {$winsProgress} more case(s)",
            'current_level' => $currentLevel,
            'needed_level' => $neededLevel,
            'current_wins' => $solvedCount,
            'needed_wins' => $neededWins
        ];
    }
}
