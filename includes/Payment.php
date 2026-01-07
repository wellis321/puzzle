<?php
/**
 * Payment Processing System
 * Handles Stripe payment integration
 * 
 * Requires Stripe PHP SDK: composer require stripe/stripe-php
 * Or download from: https://github.com/stripe/stripe-php
 */

class Payment {
    private $stripeSecretKey;
    private $stripePublicKey;
    private $stripeLoaded = false;
    
    public function __construct() {
        require_once __DIR__ . '/EnvLoader.php';
        $this->stripeSecretKey = EnvLoader::get('STRIPE_SECRET_KEY', '');
        $this->stripePublicKey = EnvLoader::get('STRIPE_PUBLIC_KEY', '');
        
        // Try to load Stripe library
        $stripePaths = [
            __DIR__ . '/../vendor/stripe/stripe-php/init.php',
            __DIR__ . '/../stripe-php/init.php',
        ];
        
        foreach ($stripePaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $this->stripeLoaded = true;
                break;
            }
        }
        
        // If composer autoload exists, try that
        if (!$this->stripeLoaded && file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $this->stripeLoaded = class_exists('\Stripe\Stripe');
        }
    }
    
    private function checkStripeLoaded() {
        if (!$this->stripeLoaded) {
            throw new Exception("Stripe PHP SDK not found. Install via: composer require stripe/stripe-php");
        }
    }
    
    /**
     * Create Stripe checkout session for subscription
     */
    public function createCheckoutSession($userId, $planType = 'monthly') {
        $this->checkStripeLoaded();
        
        if (empty($this->stripeSecretKey)) {
            throw new Exception("Stripe secret key not configured");
        }
        
        // Set Stripe API key
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        
        // Determine price ID based on plan type
        // These would be configured in Stripe dashboard
        $priceIds = [
            'monthly' => EnvLoader::get('STRIPE_PRICE_ID_MONTHLY', ''),
            'yearly' => EnvLoader::get('STRIPE_PRICE_ID_YEARLY', '')
        ];
        
        $priceId = $priceIds[$planType] ?? $priceIds['monthly'];
        
        if (empty($priceId)) {
            throw new Exception("Stripe price ID not configured for {$planType} plan");
        }
        
        // Get user email
        require_once __DIR__ . '/Auth.php';
        $auth = new Auth();
        $user = $auth->getCurrentUser();
        if (!$user || $user['id'] != $userId) {
            throw new Exception("User not found");
        }
        
        // Create or retrieve Stripe customer
        $customerId = $this->getOrCreateCustomer($userId, $user['email']);
        
        // Create checkout session
        $session = \Stripe\Checkout\Session::create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => EnvLoader::get('APP_URL', 'http://localhost') . '/subscribe.php?success=true&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => EnvLoader::get('APP_URL', 'http://localhost') . '/subscribe.php?canceled=true',
            'metadata' => [
                'user_id' => $userId,
                'plan_type' => $planType
            ]
        ]);
        
        return $session;
    }
    
    /**
     * Get or create Stripe customer for user
     */
    private function getOrCreateCustomer($userId, $email) {
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance()->getConnection();
        
        // Check if customer already exists
        $stmt = $db->prepare("SELECT stripe_customer_id FROM user_subscriptions WHERE user_id = ? AND stripe_customer_id IS NOT NULL LIMIT 1");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();
        
        if ($existing && !empty($existing['stripe_customer_id'])) {
            return $existing['stripe_customer_id'];
        }
        
        // Create new customer
        $this->checkStripeLoaded();
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'metadata' => [
                'user_id' => $userId
            ]
        ]);
        
        return $customer->id;
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature) {
        $this->checkStripeLoaded();
        
        $webhookSecret = EnvLoader::get('STRIPE_WEBHOOK_SECRET', '');
        
        if (empty($webhookSecret)) {
            throw new Exception("Webhook secret not configured");
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
            return $event;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new Exception("Invalid webhook signature: " . $e->getMessage());
        }
    }
    
    /**
     * Get Stripe public key for client-side
     */
    public function getPublicKey() {
        return $this->stripePublicKey;
    }
}

