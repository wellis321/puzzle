<?php
/**
 * Stripe Webhook Handler
 * Processes Stripe subscription events
 */

require_once '../config.php';
require_once '../includes/Subscription.php';
require_once '../includes/Payment.php';

// Get raw POST body
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $payment = new Payment();
    $event = $payment->verifyWebhookSignature($payload, $sigHeader);
    
    $subscription = new Subscription();
    $subscription->processWebhookEvent($event);
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log("Stripe webhook error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

