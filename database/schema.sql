-- Daily Mystery Puzzle Database Schema
-- Run this in phpMyAdmin to set up the database

CREATE DATABASE IF NOT EXISTS mystery_puzzle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mystery_puzzle;

-- Puzzles table: stores all daily puzzles
CREATE TABLE IF NOT EXISTS puzzles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_date DATE NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    theme VARCHAR(100),
    case_summary TEXT NOT NULL,
    report_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_puzzle_date (puzzle_date)
) ENGINE=InnoDB;

-- Statements table: stores the individual clickable statements for each puzzle
CREATE TABLE IF NOT EXISTS statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_id INT NOT NULL,
    statement_order INT NOT NULL,
    statement_text TEXT NOT NULL,
    is_correct_answer BOOLEAN DEFAULT FALSE,
    category VARCHAR(50), -- e.g., 'timeline', 'physical_evidence', 'witness'
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    INDEX idx_puzzle_id (puzzle_id)
) ENGINE=InnoDB;

-- Hints table: progressive hints for each puzzle
CREATE TABLE IF NOT EXISTS hints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_id INT NOT NULL,
    hint_order INT NOT NULL, -- 1 = first hint, 2 = second hint
    hint_text TEXT NOT NULL,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    INDEX idx_puzzle_id (puzzle_id)
) ENGINE=InnoDB;

-- Solution explanation table
CREATE TABLE IF NOT EXISTS solutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_id INT NOT NULL UNIQUE,
    explanation TEXT NOT NULL,
    detailed_reasoning TEXT,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User sessions table: track anonymous users via session/cookie
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB;

-- Attempts table: track user attempts at puzzles
CREATE TABLE IF NOT EXISTS attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    puzzle_id INT NOT NULL,
    statement_id INT NOT NULL,
    attempt_number INT NOT NULL, -- 1, 2, or 3
    is_correct BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE,
    INDEX idx_session_puzzle (session_id, puzzle_id),
    INDEX idx_puzzle_id (puzzle_id)
) ENGINE=InnoDB;

-- Completions table: track completed puzzles
CREATE TABLE IF NOT EXISTS completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    puzzle_id INT NOT NULL,
    attempts_used INT NOT NULL,
    solved BOOLEAN DEFAULT FALSE,
    score ENUM('perfect', 'close', 'lucky') DEFAULT 'lucky',
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_puzzle (session_id, puzzle_id),
    INDEX idx_session_id (session_id),
    INDEX idx_puzzle_id (puzzle_id)
) ENGINE=InnoDB;

-- Statistics table: aggregate stats for display
CREATE TABLE IF NOT EXISTS puzzle_stats (
    puzzle_id INT PRIMARY KEY,
    total_attempts INT DEFAULT 0,
    total_completions INT DEFAULT 0,
    total_solved INT DEFAULT 0,
    avg_attempts DECIMAL(3,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE
) ENGINE=InnoDB;
