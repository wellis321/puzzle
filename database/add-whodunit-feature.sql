-- Whodunit Feature Database Migration
-- Adds support for comprehensive murder mystery puzzles with witness statements

-- Add puzzle_type and unlock_level to puzzles table
ALTER TABLE puzzles 
ADD COLUMN puzzle_type ENUM('standard', 'whodunit') DEFAULT 'standard' AFTER difficulty,
ADD COLUMN unlock_level INT NULL COMMENT 'Minimum rank level required to access (NULL = always available)' AFTER puzzle_type,
ADD INDEX idx_puzzle_type (puzzle_type);

-- Create witness_statements table for whodunit puzzles
CREATE TABLE IF NOT EXISTS witness_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_id INT NOT NULL,
    witness_name VARCHAR(100) NOT NULL,
    statement_text TEXT NOT NULL,
    statement_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    INDEX idx_puzzle_id (puzzle_id),
    INDEX idx_statement_order (statement_order)
) ENGINE=InnoDB;

-- Add suspect_name to statements table for whodunit suspect identification
ALTER TABLE statements 
ADD COLUMN suspect_name VARCHAR(100) NULL COMMENT 'Name of suspect this statement implicates (for whodunits)' AFTER category;

-- Create suspect_profiles table for whodunit puzzles
CREATE TABLE IF NOT EXISTS suspect_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    puzzle_id INT NOT NULL,
    suspect_name VARCHAR(100) NOT NULL,
    profile_text TEXT NOT NULL,
    profile_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (puzzle_id) REFERENCES puzzles(id) ON DELETE CASCADE,
    INDEX idx_puzzle_id (puzzle_id),
    INDEX idx_profile_order (profile_order)
) ENGINE=InnoDB;

-- Add index for faster whodunit queries
CREATE INDEX idx_whodunit_unlock ON puzzles (puzzle_type, unlock_level);

