<?php
/**
 * Helper endpoint to get or generate API key
 * This is a one-time setup helper - remove or protect after first use
 * 
 * Usage: GET /api/get-api-key.php
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$apiKey = EnvLoader::get('IMAGE_GENERATION_API_KEY', '');

if (empty($apiKey)) {
    // Generate a secure key based on app URL and admin username
    $apiKey = hash('sha256', EnvLoader::get('APP_URL', '') . EnvLoader::get('ADMIN_USERNAME', 'admin') . time());
    
    echo json_encode([
        'api_key' => $apiKey,
        'message' => 'Generated API key. Add this to your .env file as: IMAGE_GENERATION_API_KEY=' . $apiKey,
        'env_line' => 'IMAGE_GENERATION_API_KEY=' . $apiKey,
        'note' => 'This endpoint should be removed or protected after setup'
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'api_key' => $apiKey,
        'message' => 'API key found in .env file'
    ], JSON_PRETTY_PRINT);
}

