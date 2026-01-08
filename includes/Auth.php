<?php
/**
 * User Authentication System
 * Handles user registration, login, and session management
 */

require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initSession();
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Register a new user account
     */
    public function register($email, $password, $username = null) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email already registered");
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, username)
            VALUES (?, ?, ?)
        ");
        
        try {
            $stmt->execute([$email, $passwordHash, $username]);
            $userId = $this->db->lastInsertId();
            
            // Automatically log in the new user
            $this->login($email, $password);
            
            // Migrate anonymous progress if session exists
            if (isset($_SESSION['puzzle_session_id'])) {
                $this->linkAnonymousProgress($_SESSION['puzzle_session_id'], $userId);
            }
            
            return $userId;
        } catch (PDOException $e) {
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        // #region agent log
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:74','message'=>'Login attempt','data'=>['email'=>$email],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // #region agent log
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:79','message'=>'User lookup result','data'=>['userFound'=>$user!==false,'userId'=>$user['id']??null],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid email or password");
        }
        
        // Update last login
        try {
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
        } catch (PDOException $e) {
            // #region agent log
            file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:90','message'=>'ERROR updating last_login','data'=>['error'=>$e->getMessage()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'B'])."\n", FILE_APPEND);
            // #endregion
            error_log("Failed to update last_login: " . $e->getMessage());
            // Continue anyway - not critical
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];
        
        // #region agent log
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:98','message'=>'Session set, checking migration','data'=>['userId'=>$user['id'],'hasPuzzleSession'=>isset($_SESSION['puzzle_session_id']),'puzzleSessionId'=>$_SESSION['puzzle_session_id']??null],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion
        
        // Migrate anonymous progress if session exists
        if (isset($_SESSION['puzzle_session_id'])) {
            try {
                $this->linkAnonymousProgress($_SESSION['puzzle_session_id'], $user['id']);
                // #region agent log
                file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:104','message'=>'Progress migration completed','data'=>['userId'=>$user['id']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'])."\n", FILE_APPEND);
                // #endregion
            } catch (Exception $e) {
                // #region agent log
                file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:107','message'=>'ERROR in progress migration','data'=>['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'C'])."\n", FILE_APPEND);
                // #endregion
                error_log("Progress migration failed: " . $e->getMessage());
                // Continue anyway - migration is optional
            }
        }
        
        // Link session to user (only if user_sessions table has user_id column)
        if (isset($_SESSION['puzzle_session_id'])) {
            try {
                // Check if user_id column exists first
                $checkColumn = $this->db->query("SHOW COLUMNS FROM user_sessions LIKE 'user_id'");
                if ($checkColumn->rowCount() > 0) {
                    $linkStmt = $this->db->prepare("
                        UPDATE user_sessions SET user_id = ? WHERE session_id = ?
                    ");
                    $linkStmt->execute([$user['id'], $_SESSION['puzzle_session_id']]);
                    // #region agent log
                    file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:123','message'=>'Session linked to user','data'=>['userId'=>$user['id']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'D'])."\n", FILE_APPEND);
                    // #endregion
                } else {
                    // #region agent log
                    file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:127','message'=>'user_id column does not exist in user_sessions - skipping link','data'=>[],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'D'])."\n", FILE_APPEND);
                    // #endregion
                }
            } catch (PDOException $e) {
                // #region agent log
                file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:130','message'=>'ERROR linking session','data'=>['error'=>$e->getMessage()],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'D'])."\n", FILE_APPEND);
                // #endregion
                error_log("Failed to link session: " . $e->getMessage());
                // Continue anyway - linking is optional
            }
        }
        
        // #region agent log
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', json_encode(['timestamp'=>time()*1000,'location'=>'Auth.php:137','message'=>'Login completed successfully','data'=>['userId'=>$user['id']],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A'])."\n", FILE_APPEND);
        // #endregion
        
        return $user;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_email']);
        unset($_SESSION['user_username']);
        // Keep puzzle_session_id for anonymous play
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Link anonymous session progress to user account
     */
    public function linkAnonymousProgress($sessionId, $userId) {
        try {
            // Check if migration table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'user_progress_migration'");
            if ($checkTable->rowCount() === 0) {
                error_log("user_progress_migration table does not exist - skipping migration");
                return;
            }
            
            // Check if already migrated
            $checkStmt = $this->db->prepare("
                SELECT id FROM user_progress_migration 
                WHERE session_id = ? AND user_id = ?
            ");
            $checkStmt->execute([$sessionId, $userId]);
            if ($checkStmt->fetch()) {
                return; // Already migrated
            }
            
            // Check if user_id column exists in completions before migrating
            $checkColumn = $this->db->query("SHOW COLUMNS FROM completions LIKE 'user_id'");
            if ($checkColumn->rowCount() > 0) {
                // Migrate completions
                $this->db->prepare("
                    UPDATE completions 
                    SET user_id = ?, session_id = NULL 
                    WHERE session_id = ? AND user_id IS NULL
                ")->execute([$userId, $sessionId]);
            }
            
            // Check if user_id column exists in attempts
            $checkColumn = $this->db->query("SHOW COLUMNS FROM attempts LIKE 'user_id'");
            if ($checkColumn->rowCount() > 0) {
                // Migrate attempts
                $this->db->prepare("
                    UPDATE attempts 
                    SET user_id = ?, session_id = NULL 
                    WHERE session_id = ? AND user_id IS NULL
                ")->execute([$userId, $sessionId]);
            }
            
            // Check if user_id column exists in user_ranks
            $checkColumn = $this->db->query("SHOW COLUMNS FROM user_ranks LIKE 'user_id'");
            if ($checkColumn->rowCount() > 0) {
                // Migrate user_ranks
                $this->db->prepare("
                    UPDATE user_ranks 
                    SET user_id = ?, session_id = NULL 
                    WHERE session_id = ? AND user_id IS NULL
                ")->execute([$userId, $sessionId]);
            }
            
            // Record migration
            $migrationStmt = $this->db->prepare("
                INSERT INTO user_progress_migration (user_id, session_id)
                VALUES (?, ?)
            ");
            $migrationStmt->execute([$userId, $sessionId]);
            
        } catch (PDOException $e) {
            error_log("Error migrating progress: " . $e->getMessage());
            // Don't throw - migration failure shouldn't block login
        }
    }
    
    /**
     * Get user's display name
     */
    public function getDisplayName() {
        if ($this->isLoggedIn()) {
            return $_SESSION['user_username'] ?? $_SESSION['user_email'] ?? 'User';
        }
        return null;
    }
}

