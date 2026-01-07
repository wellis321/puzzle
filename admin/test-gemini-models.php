<?php
/**
 * Test Gemini API Models
 * Use this to check which models are available with your API key
 */

require_once 'auth.php';
require_once '../config.php';
require_once '../includes/EnvLoader.php';

$apiKey = EnvLoader::get('GEMINI_API_KEY');

if (empty($apiKey)) {
    die("GEMINI_API_KEY not found in .env file");
}

echo "<h2>Testing Gemini API Models</h2>";
echo "<p>API Key: " . substr($apiKey, 0, 10) . "...</p>";

// Models and versions to test
$modelsToTest = [
    ['model' => 'gemini-pro', 'version' => 'v1beta'],
    ['model' => 'gemini-pro', 'version' => 'v1'],
    ['model' => 'gemini-1.5-flash', 'version' => 'v1beta'],
    ['model' => 'gemini-1.5-flash', 'version' => 'v1'],
    ['model' => 'gemini-1.5-flash-latest', 'version' => 'v1'],
    ['model' => 'gemini-1.5-pro', 'version' => 'v1'],
    ['model' => 'gemini-1.5-pro-latest', 'version' => 'v1'],
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Model</th><th>Version</th><th>Status</th><th>Response</th></tr>";

foreach ($modelsToTest as $test) {
    $model = $test['model'];
    $version = $test['version'];
    $url = "https://generativelanguage.googleapis.com/{$version}/models/{$model}:generateContent?key=" . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Say "test" if you can read this.']
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status = ($httpCode === 200) ? "✅ WORKS" : "❌ FAILED";
    $responseShort = substr($response, 0, 200);
    
    echo "<tr>";
    echo "<td>{$model}</td>";
    echo "<td>{$version}</td>";
    echo "<td>{$status} (HTTP {$httpCode})</td>";
    echo "<td style='font-size: 11px;'><pre>" . htmlspecialchars($responseShort) . "</pre></td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>To List All Available Models:</h3>";
echo "<p>Visit: <a href='https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey . "' target='_blank'>List Models API</a></p>";

?>

