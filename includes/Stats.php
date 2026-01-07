<?php
/**
 * Advanced Statistics System
 * Provides detailed analytics for premium users
 */

class Stats {
    private $db;
    private $userId;
    
    public function __construct($userId) {
        $this->db = Database::getInstance()->getConnection();
        $this->userId = $userId;
    }
    
    /**
     * Get comprehensive user statistics
     */
    public function getUserStats() {
        if (!$this->userId) {
            return null;
        }
        
        // Get all completions
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                p.difficulty,
                p.puzzle_date,
                DATE(c.completed_at) as completion_date,
                HOUR(c.completed_at) as completion_hour
            FROM completions c
            JOIN puzzles p ON c.puzzle_id = p.id
            WHERE c.user_id = ?
            ORDER BY c.completed_at DESC
        ");
        $stmt->execute([$this->userId]);
        $completions = $stmt->fetchAll();
        
        // Calculate statistics
        $stats = [
            'total_completions' => count($completions),
            'solved_count' => 0,
            'failed_count' => 0,
            'win_rate' => 0,
            'by_difficulty' => [
                'easy' => ['total' => 0, 'solved' => 0, 'failed' => 0],
                'medium' => ['total' => 0, 'solved' => 0, 'failed' => 0],
                'hard' => ['total' => 0, 'solved' => 0, 'failed' => 0]
            ],
            'by_score' => [
                'perfect' => 0,
                'close' => 0,
                'lucky' => 0
            ],
            'avg_attempts' => 0,
            'by_hour' => array_fill(0, 24, 0),
            'by_day_of_week' => array_fill(0, 7, 0),
            'streak_info' => [
                'current' => 0,
                'best' => 0,
                'longest_win' => 0,
                'longest_loss' => 0
            ]
        ];
        
        $totalAttempts = 0;
        $solvedCompletions = [];
        $failedCompletions = [];
        
        foreach ($completions as $completion) {
            if ($completion['solved']) {
                $stats['solved_count']++;
                $solvedCompletions[] = $completion;
            } else {
                $stats['failed_count']++;
                $failedCompletions[] = $completion;
            }
            
            // By difficulty
            $difficulty = $completion['difficulty'];
            $stats['by_difficulty'][$difficulty]['total']++;
            if ($completion['solved']) {
                $stats['by_difficulty'][$difficulty]['solved']++;
            } else {
                $stats['by_difficulty'][$difficulty]['failed']++;
            }
            
            // By score
            if ($completion['solved'] && isset($stats['by_score'][$completion['score']])) {
                $stats['by_score'][$completion['score']]++;
            }
            
            // Average attempts
            $totalAttempts += $completion['attempts_used'];
            
            // By hour of day
            if ($completion['completion_hour'] !== null) {
                $hour = (int)$completion['completion_hour'];
                $stats['by_hour'][$hour]++;
            }
            
            // By day of week
            if ($completion['completion_date']) {
                $dayOfWeek = date('w', strtotime($completion['completion_date']));
                $stats['by_day_of_week'][$dayOfWeek]++;
            }
        }
        
        // Calculate win rate
        if ($stats['total_completions'] > 0) {
            $stats['win_rate'] = round(($stats['solved_count'] / $stats['total_completions']) * 100, 1);
        }
        
        // Calculate average attempts
        if ($stats['total_completions'] > 0) {
            $stats['avg_attempts'] = round($totalAttempts / $stats['total_completions'], 2);
        }
        
        // Get streak info
        $streakInfo = $this->calculateStreaks($completions);
        $stats['streak_info'] = $streakInfo;
        
        // Win rate by difficulty
        foreach ($stats['by_difficulty'] as $difficulty => &$data) {
            if ($data['total'] > 0) {
                $data['win_rate'] = round(($data['solved'] / $data['total']) * 100, 1);
            } else {
                $data['win_rate'] = 0;
            }
        }
        unset($data);
        
        return $stats;
    }
    
    /**
     * Calculate streak information
     */
    private function calculateStreaks($completions) {
        if (empty($completions)) {
            return ['current' => 0, 'best' => 0, 'longest_win' => 0, 'longest_loss' => 0];
        }
        
        // Group by date
        $byDate = [];
        foreach ($completions as $completion) {
            $date = $completion['completion_date'];
            if (!isset($byDate[$date])) {
                $byDate[$date] = ['solved' => false, 'failed' => false];
            }
            if ($completion['solved']) {
                $byDate[$date]['solved'] = true;
            } else {
                $byDate[$date]['failed'] = true;
            }
        }
        
        // Sort dates
        krsort($byDate);
        
        // Calculate current streak
        $currentStreak = 0;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        foreach ($byDate as $date => $result) {
            if ($date === $today || $date === $yesterday) {
                if ($result['solved']) {
                    $currentStreak = 1;
                    break;
                }
            }
        }
        
        // Calculate best streak and longest win/loss streaks
        $bestStreak = 0;
        $currentWinStreak = 0;
        $currentLossStreak = 0;
        $longestWin = 0;
        $longestLoss = 0;
        
        foreach ($byDate as $date => $result) {
            if ($result['solved']) {
                $currentWinStreak++;
                $currentLossStreak = 0;
                $longestWin = max($longestWin, $currentWinStreak);
                $bestStreak = max($bestStreak, $currentWinStreak);
            } elseif ($result['failed']) {
                $currentLossStreak++;
                $currentWinStreak = 0;
                $longestLoss = max($longestLoss, $currentLossStreak);
            }
        }
        
        return [
            'current' => $currentStreak,
            'best' => $bestStreak,
            'longest_win' => $longestWin,
            'longest_loss' => $longestLoss
        ];
    }
    
    /**
     * Get performance by time of day
     */
    public function getPerformanceByHour() {
        if (!$this->userId) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                HOUR(c.completed_at) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN c.solved = 1 THEN 1 ELSE 0 END) as solved
            FROM completions c
            WHERE c.user_id = ?
            GROUP BY HOUR(c.completed_at)
            ORDER BY hour
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }
}

