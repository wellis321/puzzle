-- Quick Fix: Drop the existing idx_puzzle_date index if you got the duplicate key error
-- Run this FIRST, then continue with the rest of migration-multi-difficulty.sql

USE mystery_puzzle;

-- Drop the existing index if it exists
ALTER TABLE puzzles DROP INDEX idx_puzzle_date;

-- Now continue with the rest of migration-multi-difficulty.sql from Step 2 onwards

