<?php
/**
 * Subscription Management System
 * Handles subscription status checking and management
 */

require_once __DIR__ . '/Database.php';

class Subscription {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check if user has active premium subscription
     */
    public function isPremium($userId) {
        if (!$userId) {
            return false;
        }
        
        // Check user's subscription status
        $stmt = $this->db->prepare("
            SELECT subscription_status, subscription_expires_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Check if premium and not expired
        if ($user['subscription_status'] === 'premium') {
            // If expiration date is set, check if it's in the future
            if ($user['subscription_expires_at']) {
                $expiresAt = strtotime($user['subscription_expires_at']);
                if ($expiresAt < time()) {
                    // Expired - update status
                    $this->updateSubscriptionStatus($userId, 'expired');
                    return false;
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Get subscription details for a user
     */
    public function getSubscriptionDetails($userId) {
        if (!$userId) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                u.subscription_status,
                u.subscription_expires_at,
                us.*
            FROM users u
            LEFT JOIN user_subscriptions us ON u.id = us.user_id AND us.status = 'active'
            WHERE u.id = ?
            ORDER BY us.started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Update user's subscription status
     */
    public function updateSubscriptionStatus($userId, $status, $expiresAt = null) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET subscription_status = ?, 
                subscription_expires_at = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $expiresAt, $userId]);
    }
    
    /**
     * Create or update subscription record
     */
    public function createSubscription($userId, $stripeSubscriptionId, $stripeCustomerId, $planType = 'monthly', $status = 'active') {
        // Calculate expiration date
        $expiresAt = null;
        $periodEnd = null;
        
        if ($planType === 'monthly') {
            $expiresAt = date('Y-m-d', strtotime('+1 month'));
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 month'));
        } elseif ($planType === 'yearly') {
            $expiresAt = date('Y-m-d', strtotime('+1 year'));
            $periodEnd = date('Y-m-d H:i:s', strtotime('+1 year'));
        }
        
        // Update user subscription status
        $this->updateSubscriptionStatus($userId, 'premium', $expiresAt);
        
        // Create subscription record
        $stmt = $this->db->prepare("
            INSERT INTO user_subscriptions (
                user_id, stripe_subscription_id, stripe_customer_id,
                status, plan_type, started_at, expires_at,
                current_period_start, current_period_end
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                stripe_subscription_id = VALUES(stripe_subscription_id),
                stripe_customer_id = VALUES(stripe_customer_id),
                status = VALUES(status),
                plan_type = VALUES(plan_type),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end),
                expires_at = VALUES(expires_at),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $userId,
            $stripeSubscriptionId,
            $stripeCustomerId,
            $status,
            $planType,
            $expiresAt,
            $periodEnd
        ]);
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription($userId) {
        // Mark subscription as canceled
        $stmt = $this->db->prepare("
            UPDATE user_subscriptions 
            SET status = 'canceled',
                canceled_at = NOW(),
                cancel_at_period_end = TRUE,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        
        // Update user status to expire at period end
        // (Status will be updated to 'expired' when period ends via webhook)
    }
    
    /**
     * Process webhook event from Stripe
     */
    public function processWebhookEvent($event) {
        $eventType = $event['type'];
        $subscriptionData = $event['data']['object'];
        
        switch ($eventType) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdate($subscriptionData);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($subscriptionData);
                break;
        }
    }
    
    private function handleSubscriptionUpdate($subscription) {
        $stripeSubscriptionId = $subscription['id'];
        $stripeCustomerId = $subscription['customer'];
        $status = $subscription['status'];
        
        // Find user by customer ID
        $stmt = $this->db->prepare("
            SELECT user_id FROM user_subscriptions 
            WHERE stripe_customer_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$stripeCustomerId]);
        $sub = $stmt->fetch();
        
        if (!$sub) {
            error_log("Subscription not found for customer: " . $stripeCustomerId);
            return;
        }
        
        $userId = $sub['user_id'];
        $planType = $subscription['items']['data'][0]['price']['recurring']['interval'] === 'year' ? 'yearly' : 'monthly';
        
        // Map Stripe status to our status
        $ourStatus = 'active';
        if (in_array($status, ['canceled', 'unpaid', 'past_due'])) {
            $ourStatus = $status === 'canceled' ? 'canceled' : 'past_due';
        }
        
        // Update subscription
        $expiresAt = date('Y-m-d', $subscription['current_period_end']);
        $this->updateSubscriptionStatus($userId, $ourStatus === 'active' ? 'premium' : 'expired', $expiresAt);
        
        // Update subscription record
        $updateStmt = $this->db->prepare("
            UPDATE user_subscriptions 
            SET status = ?,
                current_period_end = FROM_UNIXTIME(?),
                expires_at = ?,
                cancel_at_period_end = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE stripe_subscription_id = ?
        ");
        $updateStmt->execute([
            $ourStatus,
            $subscription['current_period_end'],
            $expiresAt,
            $subscription['cancel_at_period_end'] ? 1 : 0,
            $stripeSubscriptionId
        ]);
    }
    
    private function handleSubscriptionDeleted($subscription) {
        $stripeSubscriptionId = $subscription['id'];
        $stripeCustomerId = $subscription['customer'];
        
        // Find user
        $stmt = $this->db->prepare("
            SELECT user_id FROM user_subscriptions 
            WHERE stripe_subscription_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$stripeSubscriptionId]);
        $sub = $stmt->fetch();
        
        if ($sub) {
            $userId = $sub['user_id'];
            $this->updateSubscriptionStatus($userId, 'expired', null);
            
            $updateStmt = $this->db->prepare("
                UPDATE user_subscriptions 
                SET status = 'canceled',
                    canceled_at = NOW(),
                    updated_at = CURRENT_TIMESTAMP
                WHERE stripe_subscription_id = ?
            ");
            $updateStmt->execute([$stripeSubscriptionId]);
        }
    }
}

