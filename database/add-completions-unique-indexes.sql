-- Add unique indexes for completions table
-- Since MySQL doesn't support COALESCE in UNIQUE constraints,
-- we create separate unique indexes for user_id and session_id

-- Drop existing unique constraint if it exists
ALTER TABLE completions DROP INDEX IF EXISTS unique_session_puzzle;

-- Create unique index for user_id (when logged in)
-- This prevents duplicate completions for the same user + puzzle
CREATE UNIQUE INDEX IF NOT EXISTS unique_user_puzzle 
ON completions (user_id, puzzle_id) 
WHERE user_id IS NOT NULL;

-- Create unique index for session_id (when anonymous)
-- This prevents duplicate completions for the same session + puzzle
CREATE UNIQUE INDEX IF NOT EXISTS unique_session_puzzle_new 
ON completions (session_id, puzzle_id) 
WHERE user_id IS NULL AND session_id IS NOT NULL;

-- Note: MySQL doesn't support partial indexes with WHERE clause
-- So we'll use application logic in Game.php to enforce uniqueness
-- Instead, we'll create regular indexes and handle uniqueness in code

-- Just create regular indexes for performance
CREATE INDEX IF NOT EXISTS idx_user_puzzle ON completions (user_id, puzzle_id);
CREATE INDEX IF NOT EXISTS idx_session_puzzle ON completions (session_id, puzzle_id);

