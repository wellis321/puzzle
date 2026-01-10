-- Update unique constraint to allow both standard and whodunit puzzles
-- for the same date and difficulty
-- 
-- This allows:
-- - One standard puzzle per date/difficulty
-- - One whodunit puzzle per date/difficulty
-- - Both can exist simultaneously for the same date/difficulty

-- Step 1: Set puzzle_type for existing puzzles (if column exists)
-- This ensures all existing puzzles are marked as 'standard'
UPDATE puzzles 
SET puzzle_type = 'standard' 
WHERE puzzle_type IS NULL 
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'puzzles' 
            AND COLUMN_NAME = 'puzzle_type');

-- Step 2: Drop the old unique constraint
-- Check if it exists first
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'puzzles' 
    AND CONSTRAINT_NAME = 'unique_puzzle_date_difficulty'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE puzzles DROP INDEX unique_puzzle_date_difficulty',
    'SELECT "Constraint unique_puzzle_date_difficulty does not exist - skipping drop"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add new unique constraint including puzzle_type
-- This only works if puzzle_type column exists
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'puzzles' 
    AND COLUMN_NAME = 'puzzle_type'
);

SET @sql = IF(@column_exists > 0,
    'ALTER TABLE puzzles ADD CONSTRAINT unique_puzzle_date_difficulty_type UNIQUE (puzzle_date, difficulty, puzzle_type)',
    'SELECT "puzzle_type column does not exist - run add-whodunit-feature.sql first"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification
-- Run this to check the constraint was updated:
-- SHOW INDEX FROM puzzles WHERE Key_name = 'unique_puzzle_date_difficulty_type';


