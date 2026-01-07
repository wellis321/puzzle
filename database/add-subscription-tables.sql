-- Add Subscription System
-- Tracks user subscriptions and payment history

CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    status ENUM('active', 'canceled', 'past_due', 'unpaid', 'trialing') DEFAULT 'active',
    plan_type ENUM('monthly', 'yearly') DEFAULT 'monthly',
    started_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NULL,
    canceled_at TIMESTAMP NULL,
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_stripe_subscription_id (stripe_subscription_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Track payment transactions
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    amount INT NOT NULL, -- Amount in cents
    currency VARCHAR(3) DEFAULT 'usd',
    status ENUM('pending', 'succeeded', 'failed', 'refunded') DEFAULT 'pending',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_stripe_payment_intent_id (stripe_payment_intent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

