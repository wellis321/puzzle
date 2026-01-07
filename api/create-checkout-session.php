<?php
header('Content-Type: application/json');

require_once '../config.php';
require_once '../includes/Auth.php';
require_once '../includes/Payment.php';

try {
    // Check authentication
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $planType = $input['plan_type'] ?? 'monthly';
    
    if (!in_array($planType, ['monthly', 'yearly'])) {
        throw new Exception('Invalid plan type');
    }
    
    $payment = new Payment();
    $session = $payment->createCheckoutSession($auth->getUserId(), $planType);
    
    echo json_encode([
        'sessionId' => $session->id,
        'url' => $session->url
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

