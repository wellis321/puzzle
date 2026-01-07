<?php
/**
 * User Authentication System
 * Handles user registration, login, and session management
 */

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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid email or password");
        }
        
        // Update last login
        $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];
        
        // Migrate anonymous progress if session exists
        if (isset($_SESSION['puzzle_session_id'])) {
            $this->linkAnonymousProgress($_SESSION['puzzle_session_id'], $user['id']);
        }
        
        // Link session to user
        if (isset($_SESSION['puzzle_session_id'])) {
            $linkStmt = $this->db->prepare("
                UPDATE user_sessions SET user_id = ? WHERE session_id = ?
            ");
            $linkStmt->execute([$user['id'], $_SESSION['puzzle_session_id']]);
        }
        
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
            // Check if already migrated
            $checkStmt = $this->db->prepare("
                SELECT id FROM user_progress_migration 
                WHERE session_id = ? AND user_id = ?
            ");
            $checkStmt->execute([$sessionId, $userId]);
            if ($checkStmt->fetch()) {
                return; // Already migrated
            }
            
            // Migrate completions
            $this->db->prepare("
                UPDATE completions 
                SET user_id = ?, session_id = NULL 
                WHERE session_id = ? AND user_id IS NULL
            ")->execute([$userId, $sessionId]);
            
            // Migrate attempts
            $this->db->prepare("
                UPDATE attempts 
                SET user_id = ?, session_id = NULL 
                WHERE session_id = ? AND user_id IS NULL
            ")->execute([$userId, $sessionId]);
            
            // Migrate user_ranks
            $this->db->prepare("
                UPDATE user_ranks 
                SET user_id = ?, session_id = NULL 
                WHERE session_id = ? AND user_id IS NULL
            ")->execute([$userId, $sessionId]);
            
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

