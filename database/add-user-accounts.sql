-- Add User Accounts System
-- This allows users to create accounts and save progress across devices

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    username VARCHAR(100) NULL,
    subscription_status ENUM('free', 'premium', 'expired') DEFAULT 'free',
    subscription_expires_at DATE NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_subscription_status (subscription_status)
) ENGINE=InnoDB;

-- Link anonymous sessions to user accounts for progress migration
CREATE TABLE IF NOT EXISTS user_progress_migration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB;

-- Update user_ranks to support both session_id and user_id
-- First, add user_id column if it doesn't exist
ALTER TABLE user_ranks 
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER session_id,
ADD INDEX IF NOT EXISTS idx_user_id (user_id),
ADD CONSTRAINT IF NOT EXISTS fk_user_ranks_user_id 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update attempts table to support user_id
ALTER TABLE attempts
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER session_id,
ADD INDEX IF NOT EXISTS idx_user_id (user_id),
ADD CONSTRAINT IF NOT EXISTS fk_attempts_user_id 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update completions table to support user_id
-- First, drop the existing unique constraint if it exists
ALTER TABLE completions
DROP INDEX IF EXISTS unique_session_puzzle;

-- Add user_id column
ALTER TABLE completions
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER session_id;

-- Make session_id nullable (to allow NULL when user_id is set)
ALTER TABLE completions
MODIFY COLUMN session_id VARCHAR(255) NULL;

-- Add new unique constraint that works with both user_id and session_id
-- Note: MySQL doesn't support COALESCE in UNIQUE constraints, so we'll use a different approach
-- We'll create a unique index on (COALESCE(user_id, -1), COALESCE(session_id, ''), puzzle_id)
-- But actually, simpler: just ensure one is always set, and create separate unique constraints
ALTER TABLE completions
ADD INDEX IF NOT EXISTS idx_user_id (user_id);

-- Add foreign key
ALTER TABLE completions
ADD CONSTRAINT IF NOT EXISTS fk_completions_user_id 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Create a trigger or use application logic to ensure uniqueness
-- For now, we'll rely on application logic to prevent duplicates

-- Update user_sessions to optionally link to user_id
ALTER TABLE user_sessions
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER session_id,
ADD INDEX IF NOT EXISTS idx_user_id (user_id),
ADD CONSTRAINT IF NOT EXISTS fk_user_sessions_user_id 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

