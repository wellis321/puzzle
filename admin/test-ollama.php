<?php
/**
 * Test Ollama Connection
 * Diagnostic tool to check if Ollama is running and accessible
 */

require_once 'auth.php';
require_once '../config.php';
require_once '../includes/EnvLoader.php';

$localUrl = EnvLoader::get('LOCAL_LLAMA_URL', 'http://localhost:11434');
$localModel = EnvLoader::get('LOCAL_LLAMA_MODEL', 'llama3');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Ollama Connection - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #ccc;
        }
        .test-result.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .test-result.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .command {
            background: #000;
            color: #0f0;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <nav>
                <a href="index.php">Puzzles</a>
                <a href="puzzle-generate.php">AI Generator</a>
                <a href="test-ollama.php" class="active">Test Ollama</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="admin-main">
            <div class="page-header">
                <h2>Test Ollama Connection</h2>
                <a href="puzzle-generate.php" class="btn">← Back to Generator</a>
            </div>

            <div class="form-card">
                <h3>Ollama Configuration</h3>
                <p><strong>URL:</strong> <code><?php echo htmlspecialchars($localUrl); ?></code></p>
                <p><strong>Model:</strong> <code><?php echo htmlspecialchars($localModel); ?></code></p>
                <p><small>Configure these in your <code>.env</code> file with <code>LOCAL_LLAMA_URL</code> and <code>LOCAL_LLAMA_MODEL</code></small></p>
            </div>

            <?php
            // Test 1: Check if Ollama server is accessible
            echo '<div class="form-card">';
            echo '<h3>Test 1: Server Connection</h3>';
            
            $ch = curl_init($localUrl . '/api/tags');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            
            if ($httpCode == 200) {
                echo '<div class="test-result success">';
                echo '✅ Ollama server is running and accessible!';
                echo '</div>';
                
                // Parse available models
                $models = json_decode($response, true);
                if (isset($models['models'])) {
                    echo '<div class="test-result success">';
                    echo '<strong>Available models:</strong><ul style="margin: 10px 0 0 20px;">';
                    foreach ($models['models'] as $model) {
                        $modelName = $model['name'] ?? 'unknown';
                        echo '<li>' . htmlspecialchars($modelName);
                        if ($modelName === $localModel) {
                            echo ' <strong>(configured model)</strong>';
                        }
                        echo '</li>';
                    }
                    echo '</ul></div>';
                }
            } else {
                echo '<div class="test-result error">';
                echo '❌ Cannot connect to Ollama server.';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($curlError ?: "HTTP $httpCode") . '</p>';
                echo '<p><strong>To fix:</strong></p>';
                echo '<ol>';
                echo '<li>Make sure Ollama is installed: <code>which ollama</code></li>';
                echo '<li>Start Ollama server: <div class="command">ollama serve</div></li>';
                echo '<li>Or on macOS: Open Ollama from Applications folder</li>';
                echo '<li>Verify it\'s running: <div class="command">curl ' . htmlspecialchars($localUrl) . '/api/tags</div></li>';
                echo '</ol>';
                echo '</div>';
            }
            
            echo '</div>';
            
            // Test 2: Check if the configured model is available
            if ($httpCode == 200) {
                echo '<div class="form-card">';
                echo '<h3>Test 2: Model Availability</h3>';
                
                $modelAvailable = false;
                if (isset($models['models'])) {
                    foreach ($models['models'] as $model) {
                        if (($model['name'] ?? '') === $localModel) {
                            $modelAvailable = true;
                            break;
                        }
                    }
                }
                
                if ($modelAvailable) {
                    echo '<div class="test-result success">';
                    echo '✅ Model <code>' . htmlspecialchars($localModel) . '</code> is available!';
                    echo '</div>';
                } else {
                    echo '<div class="test-result warning">';
                    echo '⚠️ Model <code>' . htmlspecialchars($localModel) . '</code> is not available.';
                    echo '<p>To download it, run:</p>';
                    echo '<div class="command">ollama pull ' . htmlspecialchars($localModel) . '</div>';
                    echo '</div>';
                }
                
                echo '</div>';
                
                // Test 3: Try a simple generation
                if ($modelAvailable) {
                    echo '<div class="form-card">';
                    echo '<h3>Test 3: Simple Generation Test</h3>';
                    
                    $testPrompt = "Respond with only the word 'SUCCESS' and nothing else.";
                    $testData = [
                        'model' => $localModel,
                        'messages' => [
                            ['role' => 'user', 'content' => $testPrompt]
                        ],
                        'stream' => false
                    ];
                    
                    $ch = curl_init($localUrl . '/api/chat');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    
                    $testResponse = curl_exec($ch);
                    $testHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $testCurlError = curl_error($ch);
                    
                    if ($testHttpCode == 200) {
                        $testResult = json_decode($testResponse, true);
                        $content = $testResult['message']['content'] ?? $testResult['choices'][0]['message']['content'] ?? '';
                        
                        echo '<div class="test-result success">';
                        echo '✅ Generation test successful!';
                        echo '<p><strong>Response:</strong> ' . htmlspecialchars(substr($content, 0, 200)) . '</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="test-result error">';
                        echo '❌ Generation test failed.';
                        echo '<p><strong>Error:</strong> ' . htmlspecialchars($testCurlError ?: $testResponse ?: "HTTP $testHttpCode") . '</p>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            }
            ?>
        </main>
    </div>
</body>
</html>

