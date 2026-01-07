<?php
/**
 * API Endpoint: Submit generated image for a puzzle
 * 
 * Usage: POST /api/submit-puzzle-image.php
 * Headers: X-API-Key: YOUR_SECRET_KEY
 * Body: multipart/form-data
 *   - puzzle_id: (required) The puzzle ID
 *   - image: (required) Image file (JPEG, PNG, GIF, WebP)
 *   - prompt: (optional) The prompt used to generate the image
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Puzzle.php';

// Get API key from environment
$expectedApiKey = EnvLoader::get('IMAGE_GENERATION_API_KEY', '');
if (empty($expectedApiKey)) {
    $expectedApiKey = hash('sha256', EnvLoader::get('APP_URL', '') . EnvLoader::get('ADMIN_USERNAME', 'admin'));
}

// Authenticate request
$providedApiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($providedApiKey) || $providedApiKey !== $expectedApiKey) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Valid API key required'
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method not allowed',
        'message' => 'Only POST requests are allowed'
    ]);
    exit;
}

try {
    // Support both multipart/form-data and JSON input
    $input = [];
    $isJson = false;
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        // JSON input (for base64 images)
        $isJson = true;
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
    } else {
        // Multipart form data (traditional file upload)
        $input = $_POST;
    }
    
    // Validate puzzle_id
    $puzzleId = isset($input['puzzle_id']) ? (int)$input['puzzle_id'] : 0;
    if ($puzzleId <= 0) {
        throw new Exception('Invalid puzzle_id');
    }
    
    // Verify puzzle exists
    $puzzle = new Puzzle();
    $puzzleData = $puzzle->getPuzzleById($puzzleId);
    if (!$puzzleData) {
        throw new Exception('Puzzle not found');
    }
    
    // Check if solution exists
    $solution = $puzzle->getSolution($puzzleId);
    if (!$solution) {
        throw new Exception('Puzzle solution not found. Create solution first.');
    }
    
    // Handle image upload (multipart or base64)
    $imageData = null;
    $fileType = null;
    $extension = 'png';
    
    if ($isJson) {
        // JSON with base64 image
        if (empty($input['image_base64'])) {
            throw new Exception('image_base64 is required for JSON requests');
        }
        
        // Decode base64 image
        $base64Data = $input['image_base64'];
        
        // Handle data URIs: data:image/png;base64,...
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }
        
        $imageData = base64_decode($base64Data);
        if ($imageData === false) {
            throw new Exception('Invalid base64 image data');
        }
        
        // Determine file type from extension or data
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        $fileType = $mimeTypes[strtolower($extension)] ?? 'image/png';
        
        // Validate file size (max 10MB)
        if (strlen($imageData) > 10 * 1024 * 1024) {
            throw new Exception('Image too large. Maximum size is 10MB');
        }
    } else {
        // Traditional multipart file upload
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Image file is required and must be uploaded successfully');
        }
        
        $file = $_FILES['image'];
        $fileType = mime_content_type($file['tmp_name']);
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: JPEG, PNG, GIF, WebP');
        }
        
        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size is 10MB');
        }
        
        $imageData = file_get_contents($file['tmp_name']);
        
        // Get extension from uploaded file
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            // Try to determine extension from mime type
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            $extension = $mimeToExt[$fileType] ?? 'png';
        }
    }
    
    // Create images directory if it doesn't exist
    $imagesDir = __DIR__ . '/../images/solutions';
    if (!is_dir($imagesDir)) {
        mkdir($imagesDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'solution_' . $puzzleId . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $imagesDir . '/' . $filename;
    
    // Save image data
    if (file_put_contents($filepath, $imageData) === false) {
        throw new Exception('Failed to save image file. Check directory permissions.');
    }
    
    // Save path to database
    $relativePath = 'images/solutions/' . $filename;
    $imagePrompt = !empty($input['prompt']) ? $input['prompt'] : null;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE solutions 
        SET image_path = ?, image_prompt = ?
        WHERE puzzle_id = ?
    ");
    $stmt->execute([$relativePath, $imagePrompt, $puzzleId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded and saved successfully',
        'puzzle_id' => $puzzleId,
        'image_path' => $relativePath,
        'file_size' => filesize($filepath),
        'file_type' => $fileType
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Upload failed',
        'message' => $e->getMessage()
    ]);
}

