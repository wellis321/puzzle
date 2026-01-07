<?php
require_once 'config.php';
require_once 'includes/Auth.php';
require_once 'includes/Subscription.php';
require_once 'includes/Payment.php';

$auth = new Auth();

// Redirect if not logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $auth->getUserId();
$subscription = new Subscription();
$payment = new Payment();

$success = '';
$error = '';
$isPremium = $subscription->isPremium($userId);

// Handle successful checkout
if (isset($_GET['success']) && isset($_GET['session_id'])) {
    try {
        // The webhook should have already processed this, but we can verify
        $subscriptionDetails = $subscription->getSubscriptionDetails($userId);
        if ($subscriptionDetails && $subscriptionDetails['status'] === 'active') {
            $success = "Subscription activated successfully! Welcome to Premium!";
            $isPremium = true;
        }
    } catch (Exception $e) {
        $error = "Subscription activated, but there was an error updating your account. Please refresh or contact support.";
    }
}

// Handle cancellation request
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    try {
        $subscription->cancelSubscription($userId);
        $success = "Subscription will be canceled at the end of the current billing period.";
        $isPremium = false;
    } catch (Exception $e) {
        $error = "Error canceling subscription: " . $e->getMessage();
    }
}

// Handle plan selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $planType = $_POST['plan_type'] ?? 'monthly';
    
    try {
        $checkoutSession = $payment->createCheckoutSession($userId, $planType);
        header('Location: ' . $checkoutSession->url);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <style>
        .subscribe-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
        }
        .plan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        .plan-card {
            background: white;
            border: 3px solid #ddd;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s, border-color 0.3s;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            border-color: #8b4513;
        }
        .plan-card.featured {
            border-color: #ffd700;
            background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%);
        }
        .plan-name {
            font-size: 24px;
            font-weight: 700;
            color: #8b4513;
            margin-bottom: 10px;
        }
        .plan-price {
            font-size: 48px;
            font-weight: 700;
            color: #8b4513;
            margin: 20px 0;
        }
        .plan-price span {
            font-size: 18px;
            color: #666;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
            text-align: left;
        }
        .plan-features li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .plan-features li:before {
            content: "✓ ";
            color: #2e7d32;
            font-weight: 700;
            margin-right: 10px;
        }
        .btn-subscribe {
            width: 100%;
            padding: 15px;
            background: #8b4513;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-subscribe:hover {
            background: #6b3413;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-top">
                <div class="header-left">
                    <h1><a href="index.php" style="color: #8b4513; text-decoration: none;"><?php echo APP_NAME; ?></a></h1>
                </div>
                <div class="header-right">
                    <a href="profile.php" style="color: #8b4513; text-decoration: none; margin-right: 15px;">Profile</a>
                    <a href="index.php" style="color: #8b4513; text-decoration: none;">← Back to Game</a>
                </div>
            </div>
        </header>

        <main class="subscribe-container">
            <div style="text-align: center; margin-bottom: 40px;">
                <h1 style="color: #8b4513; font-size: 36px;">Upgrade to Premium</h1>
                <p style="color: #666; font-size: 18px;">Unlock advanced features and enjoy an ad-free experience</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($isPremium): ?>
                <div class="alert alert-success">
                    <h3>You already have Premium!</h3>
                    <p>Enjoy all the premium features. <a href="profile.php">View your profile</a></p>
                </div>
            <?php else: ?>
                <div class="plan-grid">
                    <div class="plan-card">
                        <div class="plan-name">Monthly</div>
                        <div class="plan-price">$4.99<span>/month</span></div>
                        <ul class="plan-features">
                            <li>Ad-free experience</li>
                            <li>Advanced statistics</li>
                            <li>Full puzzle archive</li>
                            <li>Leaderboards access</li>
                            <li>Custom badge themes</li>
                            <li>Priority support</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="plan_type" value="monthly">
                            <button type="submit" name="subscribe" class="btn-subscribe">Subscribe Monthly</button>
                        </form>
                    </div>

                    <div class="plan-card featured">
                        <div style="background: #ffd700; color: #8b4513; padding: 5px; border-radius: 4px; margin-bottom: 10px; font-weight: 700;">BEST VALUE</div>
                        <div class="plan-name">Yearly</div>
                        <div class="plan-price">$49.99<span>/year</span></div>
                        <div style="color: #2e7d32; font-weight: 600; margin-bottom: 20px;">Save 17% vs monthly!</div>
                        <ul class="plan-features">
                            <li>All Monthly features</li>
                            <li>Ad-free experience</li>
                            <li>Advanced statistics</li>
                            <li>Full puzzle archive</li>
                            <li>Leaderboards access</li>
                            <li>Custom badge themes</li>
                            <li>Priority support</li>
                        </ul>
                        <form method="POST">
                            <input type="hidden" name="plan_type" value="yearly">
                            <button type="submit" name="subscribe" class="btn-subscribe">Subscribe Yearly</button>
                        </form>
                    </div>
                </div>

                <div style="text-align: center; color: #666; margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <p><strong>Free users enjoy:</strong> All daily puzzles, basic statistics, detective ranking system</p>
                    <p style="margin-top: 10px;">All subscriptions include a 7-day money-back guarantee.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

