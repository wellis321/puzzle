-- =====================================================
-- COMPLETE DATABASE SETUP FOR HOSTINGER DEPLOYMENT
-- =====================================================
-- This file contains all tables and migrations needed
-- for the complete Daily Mystery Puzzle application
-- 
-- Run this ONCE in phpMyAdmin on Hostinger to set up
-- the entire database with all features:
-- - Multi-difficulty support (Easy, Medium, Hard)
-- - Detective rank system
-- - All core game tables
-- =====================================================

-- NOTE: On Hostinger, you MUST select your database first in phpMyAdmin
-- before running this SQL file. Do NOT try to create the database here.
-- 
-- Steps:
-- 1. In phpMyAdmin, click on your database name in the left sidebar
-- 2. Click the "SQL" tab
-- 3. Paste and run this file
-- 
-- The database should already be created via Hostinger control panel

-- Remove or comment out the CREATE DATABASE line - Hostinger users don't have permission
-- CREATE DATABASE IF NOT EXISTS mystery_puzzle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE mystery_puzzle;
-- 
-- Instead, just make sure you've selected your database in phpMyAdmin first!

-- =====================================================
-- CORE GAME TABLES
-- =====================================================

-- Puzzles table: stores all daily puzzles
-- Supports up to 3 puzzles per date (easy, medium, hard)
CREATE TABLE IF NOT EXISTS puzzles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    theme VARCHAR(100),
    case_summary TEXT NOT NULL,
    report_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_puzzle_date_difficulty (puzzle_date, difficulty),
    INDEX idx_puzzle_date (puzzle_date),
    INDEX idx_difficulty (difficulty)
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
    image_path VARCHAR(255) NULL,
    image_prompt TEXT NULL,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    INDEX idx_solution_image (image_path)
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

-- =====================================================
-- DETECTIVE RANK SYSTEM
-- =====================================================

-- User ranks table: tracks user progress and calculates detective ranks
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
    FOREIGN KEY (session_id) REFERENCES user_sessions(session_id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_rank_level (rank_level)
) ENGINE=InnoDB;

-- =====================================================
-- ADMIN TABLES (for authentication)
-- =====================================================

-- Note: Admin users are handled via .env file configuration
-- No database table needed for admin authentication

-- =====================================================
-- SETUP COMPLETE
-- =====================================================
-- 
-- Next steps:
-- 1. Create your .env file on Hostinger with database credentials
-- 2. Optionally import sample puzzles from:
--    - database/seed.sql (Day 1 puzzle)
--    - database/sample-puzzles-week1.sql (Days 2-7)
-- 3. Log into admin panel and create new puzzles
-- 
-- =====================================================

