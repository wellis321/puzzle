-- Migration: Allow multiple puzzles per date with different difficulties (SAFE VERSION)
-- This version handles the case where indexes might already exist
-- Run this migration to update existing database schema
-- Date: 2024

USE mystery_puzzle;

-- Step 1: Remove the unique constraint on puzzle_date
-- The index name for UNIQUE constraints is typically the column name
ALTER TABLE puzzles DROP INDEX puzzle_date;

-- Step 2: Manually drop idx_puzzle_date if it exists
-- If you get "Unknown key name 'idx_puzzle_date'", ignore the error and continue
-- Uncomment the next line if you know the index exists:
-- ALTER TABLE puzzles DROP INDEX idx_puzzle_date;

-- Step 3: Add a composite unique constraint on (puzzle_date, difficulty)
-- This allows up to 3 puzzles per date (one for each difficulty level)
ALTER TABLE puzzles 
ADD CONSTRAINT unique_puzzle_date_difficulty UNIQUE (puzzle_date, difficulty);

-- Step 4: Try to create index on puzzle_date
-- If you get "Duplicate key name 'idx_puzzle_date'", skip this step
-- The unique constraint already provides indexing on puzzle_date
-- Uncomment only if you need a separate non-unique index:
-- CREATE INDEX idx_puzzle_date ON puzzles (puzzle_date);

-- Step 5: Try to create index on difficulty
-- If you get "Duplicate key name 'idx_difficulty'", that's fine - skip it
CREATE INDEX idx_difficulty ON puzzles (difficulty);

-- Note: If you have existing puzzles with the same date, you may need to:
-- 1. Update them to have different difficulties, or
-- 2. Update their dates to be unique before running this migration

