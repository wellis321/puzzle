-- Migration: Allow multiple puzzles per date with different difficulties
-- Run this migration to update existing database schema
-- Date: 2024
--
-- IMPORTANT: Run each section separately in phpMyAdmin.
-- If a command fails with "does not exist", that's fine - just continue to the next step.

USE mystery_puzzle;

-- ============================================
-- STEP 1: Find and remove the old unique constraint
-- ============================================
-- First, let's see what indexes/constraints exist on puzzle_date
-- Run this query to see what you have:
-- SHOW INDEX FROM puzzles WHERE Column_name = 'puzzle_date';

-- Try these one at a time until one works (ignore errors about non-existent indexes):
-- ALTER TABLE puzzles DROP INDEX puzzle_date;
-- ALTER TABLE puzzles DROP INDEX idx_puzzle_date;
-- 
-- OR if you see a constraint name from SHOW INDEX, use:
-- ALTER TABLE puzzles DROP INDEX <constraint_name_from_show_index>;

-- ============================================
-- STEP 2: Add the new composite unique constraint
-- ============================================
-- This is the MOST IMPORTANT step - this allows multiple puzzles per date
ALTER TABLE puzzles 
ADD CONSTRAINT unique_puzzle_date_difficulty UNIQUE (puzzle_date, difficulty);

-- ============================================
-- STEP 3: Add indexes (optional but recommended)
-- ============================================
-- These are for performance. Skip if you get "Duplicate key" errors.

-- Index on puzzle_date (the unique constraint already indexes it, but this is non-unique for performance)
-- CREATE INDEX idx_puzzle_date ON puzzles (puzzle_date);

-- Index on difficulty
CREATE INDEX idx_difficulty ON puzzles (difficulty);

-- ============================================
-- VERIFICATION
-- ============================================
-- After running the migration, verify with:
-- SHOW INDEX FROM puzzles;
-- 
-- You should see:
-- - unique_puzzle_date_difficulty (UNIQUE on puzzle_date, difficulty)
-- - idx_difficulty (INDEX on difficulty)
-- - idx_puzzle_date (optional, INDEX on puzzle_date)
