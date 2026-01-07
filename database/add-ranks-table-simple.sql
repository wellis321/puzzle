-- Add Detective Rank System (Simple Version - No Foreign Key)
-- Run this if the foreign key constraint causes issues

CREATE TABLE IF NOT EXISTS user_ranks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    rank_name VARCHAR(50) NOT NULL DEFAULT 'Novice Detective',
    rank_level INT NOT NULL DEFAULT 1,
    total_completions INT DEFAULT 0,
    easy_completions INT DEFAULT 0,
    medium_completions INT DEFAULT 0,
    hard_completions INT DEFAULT 0,
    perfect_scores INT DEFAULT 0,
    total_attempts INT DEFAULT 0,
    solved_count INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    best_streak INT DEFAULT 0,
    last_activity_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_rank_level (rank_level)
) ENGINE=InnoDB;

