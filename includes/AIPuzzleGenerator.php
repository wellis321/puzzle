<?php
/**
 * AI Puzzle Generator
 * Supports multiple AI providers: Gemini, Groq, OpenAI, Local Llama (Ollama)
 */

class AIPuzzleGenerator {
    private $provider;
    private $apiKey;
    private $baseUrl;
    private $model;

    public function __construct($provider = 'gemini') {
        $this->provider = $provider;
        $this->loadApiKey();
        $this->setBaseUrl();
    }
    
    /**
     * Initialize Puzzle class for checking recent puzzles
     */
    private function getPuzzleInstance() {
        require_once __DIR__ . '/Puzzle.php';
        return new Puzzle();
    }

    private function loadApiKey() {
        switch ($this->provider) {
            case 'gemini':
                $this->apiKey = EnvLoader::get('GEMINI_API_KEY');
                break;
            case 'groq':
                $this->apiKey = EnvLoader::get('GROQ_API_KEY');
                break;
            case 'openai':
                $this->apiKey = EnvLoader::get('OPENAI_API_KEY');
                break;
            case 'local':
            case 'llama':
                // Local Llama doesn't require an API key, but we'll use a placeholder
                $this->apiKey = 'local';
                break;
            default:
                throw new Exception("Unknown AI provider: {$this->provider}");
        }

        if (empty($this->apiKey) && !in_array($this->provider, ['local', 'llama'])) {
            throw new Exception("API key not found for {$this->provider}. Add {$this->provider}_API_KEY to .env file.");
        }
    }

    private function setBaseUrl() {
        switch ($this->provider) {
            case 'gemini':
                // Try different model names - start with latest
                // Available models: gemini-pro, gemini-1.5-flash, gemini-1.5-pro
                // Use v1 instead of v1beta for newer models
                $this->model = 'gemini-1.5-flash-latest';
                $this->baseUrl = 'https://generativelanguage.googleapis.com/v1/models/' . $this->model . ':generateContent';
                break;
            case 'groq':
                $this->baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
                break;
            case 'openai':
                $this->baseUrl = 'https://api.openai.com/v1/chat/completions';
                break;
            case 'local':
            case 'llama':
                // Local Llama (Ollama default, or custom URL)
                $localUrl = EnvLoader::get('LOCAL_LLAMA_URL', 'http://localhost:11434');
                // Default to llama3 if available, otherwise llama3.2:3b (smaller, faster)
                $this->model = EnvLoader::get('LOCAL_LLAMA_MODEL', 'llama3');
                $this->baseUrl = rtrim($localUrl, '/') . '/api/chat';
                break;
        }
    }

    public function generatePuzzle($date, $difficulty, $generateImage = false, $avoidSimilar = true) {
        // Increase execution time for local Llama (can be slow)
        if (in_array($this->provider, ['local', 'llama'])) {
            set_time_limit(180); // 3 minutes per puzzle
        }
        
        // Get recent puzzles to avoid similarity
        $recentPuzzles = [];
        if ($avoidSimilar) {
            $recentPuzzles = $this->getRecentPuzzles($date, 14); // Check last 14 days
        }
        
        $prompt = $this->buildPrompt($difficulty, $recentPuzzles);
        $response = $this->callAI($prompt);
        $puzzle = $this->parseResponse($response, $difficulty);
        
        // Store any image generation errors
        $puzzle['image_generation_error'] = null;
        
        // Generate image if requested (after text is generated)
        if ($generateImage && isset($puzzle['solution'])) {
            try {
                $imageData = $this->generateSolutionImage($puzzle);
                if ($imageData) {
                    $puzzle['solution_image'] = $imageData;
                } else {
                    $puzzle['image_generation_error'] = 'Image generation returned no data';
                }
            } catch (Exception $e) {
                // Store error message so it can be displayed to user
                $puzzle['image_generation_error'] = $e->getMessage();
                error_log("Image generation failed: " . $e->getMessage());
            }
        }
        
        return $puzzle;
    }
    
    /**
     * Get recent puzzles to avoid similarity
     */
    private function getRecentPuzzles($currentDate, $daysBack = 14) {
        try {
            $puzzle = $this->getPuzzleInstance();
            $recentPuzzles = [];
            
            // Check puzzles from the last N days
            for ($i = 1; $i <= $daysBack; $i++) {
                $checkDate = date('Y-m-d', strtotime($currentDate . " -{$i} days"));
                $puzzles = $puzzle->getPuzzlesByDate($checkDate);
                if (!empty($puzzles)) {
                    foreach ($puzzles as $p) {
                        $recentPuzzles[] = [
                            'title' => $p['title'] ?? '',
                            'theme' => $p['theme'] ?? '',
                            'date' => $checkDate
                        ];
                    }
                }
            }
            
            // Also check puzzles from the same date (other difficulties)
            $sameDatePuzzles = $puzzle->getPuzzlesByDate($currentDate);
            if (!empty($sameDatePuzzles)) {
                foreach ($sameDatePuzzles as $p) {
                    $recentPuzzles[] = [
                        'title' => $p['title'] ?? '',
                        'theme' => $p['theme'] ?? '',
                        'date' => $currentDate
                    ];
                }
            }
            
            return $recentPuzzles;
        } catch (Exception $e) {
            // If error, return empty array - better to generate than fail
            error_log("Error fetching recent puzzles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate an image for the solution based on puzzle content
     * Public method for use in admin pages
     * Always uses OpenAI DALL-E for image generation (regardless of text generation provider)
     */
    public function generateSolutionImage($puzzle) {
        // Check for OpenAI API key (required for DALL-E image generation)
        $openaiKey = EnvLoader::get('OPENAI_API_KEY');
        if (empty($openaiKey)) {
            throw new Exception("OpenAI API key not found. Add OPENAI_API_KEY to .env file for image generation. Note: Image generation requires OpenAI (DALL-E) even if you use Groq or Gemini for text generation.");
        }
        
        // Create a prompt for image generation based on the puzzle
        $imagePrompt = $this->buildImagePrompt($puzzle);
        
        // Always use DALL-E for image generation (regardless of $this->provider which is for text)
        return $this->generateImageWithDALLE($imagePrompt);
    }
    
    /**
     * Build a prompt for image generation based on puzzle content
     */
    private function buildImagePrompt($puzzle) {
        $theme = $puzzle['theme'] ?? 'mystery';
        $title = $puzzle['title'] ?? 'case';
        $explanation = $puzzle['solution']['explanation'] ?? '';
        
        // Extract key elements for image
        $prompt = "A detailed, realistic illustration of a {$theme} mystery scene. ";
        $prompt .= "Style: noir detective aesthetic, dramatic lighting, vintage crime scene investigation. ";
        $prompt .= "Scene shows clues and evidence related to: {$title}. ";
        $prompt .= "Mood: mysterious, intriguing, professional crime investigation. ";
        $prompt .= "Color palette: muted tones with dramatic shadows, film noir style. ";
        $prompt .= "No text, no people visible, focus on evidence and scene details.";
        
        return $prompt;
    }
    
    /**
     * Generate image using DALL-E (OpenAI)
     */
    private function generateImageWithDALLE($prompt) {
        $apiKey = EnvLoader::get('OPENAI_API_KEY');
        if (empty($apiKey)) {
            throw new Exception("OpenAI API key not found. Add OPENAI_API_KEY to .env for image generation.");
        }
        
        $url = 'https://api.openai.com/v1/images/generations';
        
        $data = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            
            // Check for specific billing/quota errors
            if (isset($errorData['error'])) {
                $errorCode = $errorData['error']['code'] ?? '';
                $errorMessage = $errorData['error']['message'] ?? '';
                
                if ($errorCode === 'billing_hard_limit_reached' || strpos($errorMessage, 'billing') !== false || strpos($errorMessage, 'limit') !== false) {
                    throw new Exception("OpenAI billing/quota limit reached. Please check your OpenAI account billing settings or use a different API key. Images will be skipped for now.");
                }
                
                throw new Exception("DALL-E API error: " . $errorMessage . " (Code: {$errorCode})");
            }
            
            throw new Exception("DALL-E API error (HTTP {$httpCode}): " . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['data'][0]['url'])) {
            throw new Exception("Unexpected DALL-E response format");
        }
        
        // Download and save the image
        return $this->downloadAndSaveImage($result['data'][0]['url'], $prompt);
    }
    
    /**
     * Download image from URL and save to server
     */
    private function downloadAndSaveImage($imageUrl, $prompt) {
        // Create images directory if it doesn't exist
        $imagesDir = __DIR__ . '/../images/solutions';
        if (!is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'solution_' . uniqid() . '_' . time() . '.png';
        $filepath = $imagesDir . '/' . $filename;
        
        // Download image
        $imageData = file_get_contents($imageUrl);
        if ($imageData === false) {
            throw new Exception("Failed to download image from URL");
        }
        
        // Save to disk
        if (file_put_contents($filepath, $imageData) === false) {
            throw new Exception("Failed to save image to disk");
        }
        
        // Return relative path for database storage
        return [
            'path' => 'images/solutions/' . $filename,
            'prompt' => $prompt
        ];
    }

    private function buildPrompt($difficulty, $recentPuzzles = []) {
        $difficultyInstructions = [
            'easy' => 'Easy: Make the inconsistency obvious. Use simple language and clear clues. The wrong detail should stand out.',
            'medium' => 'Medium: Make the inconsistency moderately difficult to spot. Include some red herrings. Require careful reading.',
            'hard' => 'Hard: Make the inconsistency very subtle. Include multiple red herrings. Require deep analysis and cross-referencing.'
        ];

        // Build variety instructions
        $varietyInstructions = $this->buildVarietyInstructions($recentPuzzles);
        
        // Diverse theme suggestions
        $themePool = [
            'Corporate Espionage', 'Museum Artifact Theft', 'Luxury Yacht Disappearance',
            'Tech Company Data Breach', 'Ancient Library Fire', 'Celebrity Stalker Case',
            'Suburban Break-In Mystery', 'Cryptocurrency Heist', 'Antique Shop Robbery',
            'Wildlife Park Incident', 'Vintage Car Theft', 'Rare Stamp Collection',
            'Wine Cellar Sabotage', 'Private Investigator Case', 'Archaeological Site Theft',
            'Vintage Jewelry Heist', 'Corporate Fraud Investigation', 'Rare Book Theft',
            'Hotel Room Break-In', 'Private Collection Disappearance', 'Art Gallery Heist',
            'Stolen Formula Mystery', 'Disappearing Witness Case', 'Time Capsule Theft',
            'Laboratory Break-In', 'Shipment Interception', 'VIP Event Security Breach'
        ];
        
        // Select a random theme suggestion (AI can use it or create something similar but different)
        $suggestedTheme = $themePool[array_rand($themePool)];

        $prompt = "Create a UNIQUE and ORIGINAL mystery puzzle case file in JSON format. The puzzle should be a \"one detail doesn't fit\" style mystery where players must find one statement that contradicts the others.

CRITICAL VARIETY REQUIREMENTS:
{$varietyInstructions}

DIFFICULTY: {$difficultyInstructions[$difficulty]}

THEME GUIDANCE:
- Consider using themes like: {$suggestedTheme}, or create something COMPLETELY DIFFERENT
- AVOID repetitive themes like \"Missing Heirloom\", \"Family Estate Theft\", \"Jewelry Store Theft\" if similar puzzles exist recently
- Think creatively: Corporate mysteries, Technology crimes, Nature/outdoor settings, Unique locations, Unusual objects or concepts
- Make the theme DISTINCTIVE and memorable

TITLE REQUIREMENTS:
- Create a UNIQUE, creative title
- Avoid generic titles like \"The Missing [Object]\", \"The [Location] Theft\" if similar titles exist
- Use specific, intriguing titles: \"The Midnight Algorithm\", \"The Vanishing Point\", \"Operation Blueprint\", \"The Silent Protocol\"
- Make titles memorable and distinctive

SCENARIO REQUIREMENTS:
1. Create an engaging, ORIGINAL mystery scenario - be creative!
2. Write a case summary (2-3 sentences) that sets a unique scene
3. Create a detailed report with multiple sections (use **section_name** for headers)
4. Include 5-6 statements/facts (one must be incorrect/contradictory)
5. Make the incorrect statement subtle but logically inconsistent with the others
6. Create 2 progressive hints
7. CRITICAL: Provide a detailed solution with BOTH explanation and detailed_reasoning fields

SOLUTION REQUIREMENTS (VERY IMPORTANT):
- \"explanation\": Must be a brief but clear explanation (2-4 sentences) of why the incorrect statement doesn't fit
- \"detailed_reasoning\": Must be a comprehensive step-by-step analysis (4-8 sentences) that:
  * Points out the specific contradiction
  * Explains how the incorrect statement conflicts with other facts
  * Details the logical reasoning process
  * Provides a thorough walkthrough of why this is the answer

IMPORTANT - CORRECT ANSWER PLACEMENT:
- The correct answer (is_correct: true) should be placed RANDOMLY among the statements
- Do NOT always put it first, second, or last - vary the position each time
- Include 5-6 statements total, with exactly ONE having is_correct: true

Return ONLY valid JSON in this exact format:
{
  \"title\": \"Unique Case Title\",
  \"theme\": \"Distinctive Theme Name\",
  \"case_summary\": \"2-3 sentence summary\",
  \"report_text\": \"**Section Name**: Content\\n\\n**Another Section**: More content\",
  \"statements\": [
    {\"text\": \"Statement text\", \"is_correct\": false, \"category\": \"witness\"},
    {\"text\": \"Another statement\", \"is_correct\": false, \"category\": \"evidence\"},
    {\"text\": \"The contradictory statement - PLACE THIS RANDOMLY, NOT ALWAYS HERE\", \"is_correct\": true, \"category\": \"timeline\"},
    {\"text\": \"More statements\", \"is_correct\": false, \"category\": \"physical\"},
    {\"text\": \"Additional facts\", \"is_correct\": false, \"category\": \"background\"}
  ],
  \"hints\": [
    \"First hint text\",
    \"Second hint text\"
  ],
  \"solution\": {
    \"explanation\": \"A clear, brief explanation (2-4 sentences) of why this statement doesn't fit with the other facts.\",
    \"detailed_reasoning\": \"A comprehensive step-by-step analysis (4-8 sentences) that explains the contradiction, how it conflicts with other statements, and provides thorough reasoning for why this is the correct answer.\"
  }
}

CRITICAL: Exactly ONE statement must have \"is_correct\": true - place it at a RANDOM position (not always first or last). Make it challenging but solvable.";

        return $prompt;
    }
    
    /**
     * Build instructions to avoid similarity with recent puzzles
     */
    private function buildVarietyInstructions($recentPuzzles) {
        if (empty($recentPuzzles)) {
            return "Create something completely original and unique!";
        }
        
        // Extract themes and titles from recent puzzles
        $recentThemes = [];
        $recentTitles = [];
        
        foreach ($recentPuzzles as $p) {
            if (!empty($p['theme'])) {
                $recentThemes[] = $p['theme'];
            }
            if (!empty($p['title'])) {
                $recentTitles[] = $p['title'];
            }
        }
        
        $instructions = "IMPORTANT - AVOID SIMILARITY:\n";
        
        if (!empty($recentThemes)) {
            $uniqueThemes = array_unique($recentThemes);
            $themesList = implode('", "', array_slice($uniqueThemes, 0, 10)); // Limit to 10 to avoid prompt bloat
            $instructions .= "- DO NOT use similar themes to these recent puzzles: \"{$themesList}\"\n";
            $instructions .= "- Create a COMPLETELY DIFFERENT theme that feels fresh and unique\n";
        }
        
        if (!empty($recentTitles)) {
            $uniqueTitles = array_unique($recentTitles);
            $titlesList = implode('", "', array_slice($uniqueTitles, 0, 10));
            $instructions .= "- DO NOT create titles similar to: \"{$titlesList}\"\n";
            $instructions .= "- Use a CREATIVE, DISTINCTIVE title that stands out\n";
        }
        
        $instructions .= "- Think outside the box: Use different settings, crime types, and scenarios\n";
        $instructions .= "- Be creative with locations, objects, and circumstances\n";
        
        return $instructions;
    }

    private function callAI($prompt) {
        switch ($this->provider) {
            case 'gemini':
                return $this->callGemini($prompt);
            case 'groq':
            case 'openai':
                return $this->callOpenAICompatible($prompt);
            case 'local':
            case 'llama':
                return $this->callLocalLlama($prompt);
            default:
                throw new Exception("Unsupported provider: {$this->provider}");
        }
    }

    private function callGemini($prompt) {
        // Try different model/version combinations
        $modelsToTry = [
            'gemini-1.5-flash-latest' => 'v1',
            'gemini-1.5-flash' => 'v1',
            'gemini-1.5-pro' => 'v1',
            'gemini-pro' => 'v1beta'
        ];
        
        $lastError = null;
        
        foreach ($modelsToTry as $model => $version) {
            $url = "https://generativelanguage.googleapis.com/{$version}/models/{$model}:generateContent?key=" . $this->apiKey;
            
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                
                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    return $result['candidates'][0]['content']['parts'][0]['text'];
                }
            } else {
                $lastError = "Model {$model} (HTTP {$httpCode}): " . $response;
            }
        }
        
        throw new Exception("All Gemini models failed. Last error: " . $lastError);
    }

    private function callOpenAICompatible($prompt) {
        // Groq models: mixtral-8x7b-32768 was decommissioned
        // Try multiple current models
        if ($this->provider === 'groq') {
            $groqModels = [
                'llama-3.1-70b-versatile',
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
                'gemma2-9b-it'
            ];
            
            // Try each model until one works
            foreach ($groqModels as $tryModel) {
                try {
                    return $this->callGroqWithModel($prompt, $tryModel);
                } catch (Exception $e) {
                    // Try next model
                    continue;
                }
            }
            throw new Exception("All Groq models failed. Last error: " . (isset($e) ? $e->getMessage() : "Unknown error"));
        }
        
        // OpenAI
        $model = 'gpt-3.5-turbo';
        return $this->callGroqWithModel($prompt, $model, 'openai');
    }
    
    private function callGroqWithModel($prompt, $model, $providerType = 'groq') {
        $url = ($providerType === 'groq') 
            ? 'https://api.groq.com/openai/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            throw new Exception("API error (HTTP {$httpCode}): " . $response);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected API response format");
        }

        return $result['choices'][0]['message']['content'];
    }
    
    /**
     * Call local Llama model (Ollama or compatible API)
     */
    private function callLocalLlama($prompt) {
        // Increase execution time limit for local generation (can be slow)
        // Local models may take 60-180 seconds depending on model size and prompt complexity
        set_time_limit(180); // 3 minutes
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'stream' => false,
            'options' => [
                'temperature' => 0.7
            ]
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        // Set longer timeouts for local generation (local models can be slow)
        curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 minutes total
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds to connect

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // Check for timeout errors
        if ($response === false && strpos($curlError, 'timeout') !== false) {
            throw new Exception("Local Llama request timed out after 180 seconds. The model may be too slow for this prompt. Try:\n" .
                "1. Use a smaller/faster model (e.g., llama3.2:3b instead of llama3)\n" .
                "2. Reduce prompt complexity\n" .
                "3. Check if Ollama is processing: ollama ps");
        }

        if ($httpCode !== 200) {
            $errorMsg = '';
            $responseData = json_decode($response, true);
            
            // Connection refused - Ollama not running
            if ($httpCode == 0 || !empty($curlError)) {
                if (strpos($curlError, 'Failed to connect') !== false || strpos($curlError, 'Connection refused') !== false) {
                    $errorMsg = "Ollama is not running. To start Ollama:\n";
                    $errorMsg .= "1. Run: ollama serve (or start Ollama from Applications on macOS)\n";
                    $errorMsg .= "2. In another terminal, verify it's running: curl http://localhost:11434/api/tags\n";
                    $errorMsg .= "3. Pull the model if needed: ollama pull " . $this->model . "\n";
                    $errorMsg .= "4. Try generating again.";
                } else {
                    $errorMsg = $curlError;
                }
            } elseif ($httpCode == 404 && isset($responseData['error']) && strpos($responseData['error'], 'not found') !== false) {
                // Model not found
                $errorMsg = "Model '" . $this->model . "' is not available.\n\n";
                $errorMsg .= "To fix this:\n";
                $errorMsg .= "1. Pull the model: ollama pull " . $this->model . "\n";
                $errorMsg .= "2. Or use an existing model by setting LOCAL_LLAMA_MODEL in .env\n";
                $errorMsg .= "3. Check available models: ollama list\n\n";
                $errorMsg .= "Common models: llama3, llama3.2:3b, llama3.1:8b, mistral, phi3";
            } else {
                $errorMsg = $response;
            }
            
            throw new Exception("Local Llama API error (HTTP {$httpCode}): " . $errorMsg);
        }

        $result = json_decode($response, true);
        
        // Ollama returns the message content directly
        if (isset($result['message']['content'])) {
            return $result['message']['content'];
        }
        
        // Fallback: try alternative response format
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        throw new Exception("Unexpected local Llama response format: " . substr($response, 0, 200));
    }

    private function parseResponse($response, $difficulty) {
        // Extract JSON from response (might have markdown code blocks)
        $jsonMatch = [];
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $jsonMatch)) {
            $jsonStr = $jsonMatch[1];
        } elseif (preg_match('/(\{.*\})/s', $response, $jsonMatch)) {
            $jsonStr = $jsonMatch[1];
        } else {
            throw new Exception("Could not extract JSON from AI response");
        }

        $puzzle = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON from AI: " . json_last_error_msg());
        }

        // Validate required fields
        $required = ['title', 'theme', 'case_summary', 'report_text', 'statements', 'hints', 'solution'];
        foreach ($required as $field) {
            if (!isset($puzzle[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Ensure solution has explanation and detailed_reasoning
        if (!isset($puzzle['solution']['explanation']) || empty($puzzle['solution']['explanation'])) {
            $puzzle['solution']['explanation'] = "The statement doesn't fit because it contradicts the other facts presented in the case.";
        }
        if (!isset($puzzle['solution']['detailed_reasoning']) || empty($puzzle['solution']['detailed_reasoning'])) {
            $puzzle['solution']['detailed_reasoning'] = $puzzle['solution']['explanation'];
        }

        // Normalize is_correct values (handle string "true"/"false" from AI)
        $correctIndex = -1;
        foreach ($puzzle['statements'] as $index => &$stmt) {
            // Convert string "true"/"false" to boolean
            if (isset($stmt['is_correct'])) {
                if (is_string($stmt['is_correct'])) {
                    $stmt['is_correct'] = strtolower($stmt['is_correct']) === 'true' || $stmt['is_correct'] === '1';
                } else {
                    $stmt['is_correct'] = (bool)$stmt['is_correct'];
                }
                
                if ($stmt['is_correct'] && $correctIndex === -1) {
                    $correctIndex = $index;
                }
            } else {
                $stmt['is_correct'] = false;
            }
        }
        unset($stmt); // Break reference
        
        // Validate exactly one correct statement
        $correctCount = 0;
        foreach ($puzzle['statements'] as $stmt) {
            if ($stmt['is_correct']) {
                $correctCount++;
            }
        }

        if ($correctCount !== 1) {
            // Auto-fix: pick a RANDOM statement as correct (not always the first!)
            // This prevents predictable "always option 1" patterns
            foreach ($puzzle['statements'] as &$stmt) {
                $stmt['is_correct'] = false;
            }
            unset($stmt);
            
            if (!empty($puzzle['statements'])) {
                // Pick a random index, preferring middle options for variety
                $numStatements = count($puzzle['statements']);
                $randomIndex = rand(0, $numStatements - 1);
                $puzzle['statements'][$randomIndex]['is_correct'] = true;
                
                // Log this so we know when auto-fix is happening
                error_log("AI Puzzle Generator: Auto-fixed correct answer to statement " . ($randomIndex + 1) . " (was: " . ($correctCount === 0 ? "none marked" : "multiple marked") . ")");
            }
        }

        return $puzzle;
    }
}

