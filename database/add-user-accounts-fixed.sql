-- Add User Accounts System (Fixed for MySQL compatibility)
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
-- Check if user_id column exists, if not add it
SET @dbname = DATABASE();
SET @tablename = 'user_ranks';
SET @columnname = 'user_id';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER session_id, ADD INDEX idx_user_id (user_id), ADD CONSTRAINT fk_user_ranks_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update attempts table to support user_id
SET @columnname = 'user_id';
SET @tablename = 'attempts';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER session_id, ADD INDEX idx_user_id (user_id), ADD CONSTRAINT fk_attempts_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update completions table to support user_id
-- First drop existing unique constraint if it exists
SET @tablename = 'completions';
SET @constraint = 'unique_session_puzzle';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (CONSTRAINT_NAME = @constraint)
    ) > 0,
    CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @constraint),
    'SELECT 1'
));
PREPARE dropIfExists FROM @preparedStatement;
EXECUTE dropIfExists;
DEALLOCATE PREPARE dropIfExists;

-- Add user_id column
SET @columnname = 'user_id';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER session_id, ADD INDEX idx_user_id (user_id), ADD CONSTRAINT fk_completions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Make session_id nullable
ALTER TABLE completions MODIFY COLUMN session_id VARCHAR(255) NULL;

-- Note: We cannot use COALESCE in a UNIQUE constraint in MySQL
-- Instead, application logic will ensure uniqueness:
-- - If user_id is set, use user_id + puzzle_id as unique
-- - If session_id is set, use session_id + puzzle_id as unique
-- This is enforced in the Game class

-- Update user_sessions to optionally link to user_id
SET @columnname = 'user_id';
SET @tablename = 'user_sessions';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER session_id, ADD INDEX idx_user_id (user_id), ADD CONSTRAINT fk_user_sessions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

