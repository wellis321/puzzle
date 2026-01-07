-- SIMPLE MIGRATION: Allow multiple puzzles per date with different difficulties
-- This version skips trying to drop old indexes and just adds what's needed
-- Date: 2024

USE mystery_puzzle;

-- Step 1: Add the composite unique constraint
-- This is the KEY change - allows 3 puzzles per date (easy, medium, hard)
-- If you get error "#1061 - Duplicate key name", the constraint already exists - SKIP THIS STEP
-- ALTER TABLE puzzles 
-- ADD CONSTRAINT unique_puzzle_date_difficulty UNIQUE (puzzle_date, difficulty);

-- Step 2: Add index on difficulty for filtering
-- If this fails with "Duplicate key", that's fine - it means the index already exists
CREATE INDEX idx_difficulty ON puzzles (difficulty);

-- DONE! The unique constraint already indexes puzzle_date, so you don't need idx_puzzle_date
-- unless you specifically want a non-unique index for performance reasons.

-- ============================================
-- VERIFICATION
-- ============================================
-- Run this to verify everything is set up correctly:
-- SHOW INDEX FROM puzzles;
-- 
-- You should see:
-- - unique_puzzle_date_difficulty (UNIQUE on puzzle_date, difficulty) ✓
-- - idx_difficulty (INDEX on difficulty) ✓

