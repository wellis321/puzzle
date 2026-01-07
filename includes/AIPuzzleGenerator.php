<?php
/**
 * AI Puzzle Generator
 * Supports multiple AI providers: Gemini, Groq, OpenAI
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
            default:
                throw new Exception("Unknown AI provider: {$this->provider}");
        }

        if (empty($this->apiKey)) {
            throw new Exception("API key not found for {$this->provider}. Add {$this->provider}_API_KEY to .env file.");
        }
    }

    private function setBaseUrl() {
        switch ($this->provider) {
            case 'gemini':
                // Use gemini-1.5-flash (fast and free, recommended)
                // Alternative: gemini-1.5-pro for more complex tasks
                $this->model = 'gemini-1.5-flash';
                $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';
                break;
            case 'groq':
                $this->baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
                break;
            case 'openai':
                $this->baseUrl = 'https://api.openai.com/v1/chat/completions';
                break;
        }
    }

    public function generatePuzzle($date, $difficulty) {
        $prompt = $this->buildPrompt($difficulty);
        $response = $this->callAI($prompt);
        return $this->parseResponse($response, $difficulty);
    }

    private function buildPrompt($difficulty) {
        $difficultyInstructions = [
            'easy' => 'Easy: Make the inconsistency obvious. Use simple language and clear clues. The wrong detail should stand out.',
            'medium' => 'Medium: Make the inconsistency moderately difficult to spot. Include some red herrings. Require careful reading.',
            'hard' => 'Hard: Make the inconsistency very subtle. Include multiple red herrings. Require deep analysis and cross-referencing.'
        ];

        return "Create a mystery puzzle case file in JSON format. The puzzle should be a \"one detail doesn't fit\" style mystery where players must find one statement that contradicts the others.

Difficulty: {$difficultyInstructions[$difficulty]}

Requirements:
1. Create an engaging mystery scenario (theft, disappearance, etc.)
2. Write a case summary (2-3 sentences)
3. Create a detailed report with multiple sections (use **section_name** for headers)
4. Include 5-6 statements/facts (one must be incorrect/contradictory)
5. Make the incorrect statement subtle but logically inconsistent with the others
6. Create 2 progressive hints
7. Provide a solution explanation

Return ONLY valid JSON in this exact format:
{
  \"title\": \"Case Title\",
  \"theme\": \"Theme (e.g., Office Theft)\",
  \"case_summary\": \"2-3 sentence summary\",
  \"report_text\": \"**Section Name**: Content\\n\\n**Another Section**: More content\",
  \"statements\": [
    {\"text\": \"Statement text\", \"is_correct\": false, \"category\": \"witness\"},
    {\"text\": \"Another statement\", \"is_correct\": false, \"category\": \"evidence\"},
    {\"text\": \"The contradictory statement\", \"is_correct\": true, \"category\": \"timeline\"}
  ],
  \"hints\": [
    \"First hint text\",
    \"Second hint text\"
  ],
  \"solution\": {
    \"explanation\": \"Why the statement doesn't fit\",
    \"detailed_reasoning\": \"Detailed step-by-step explanation\"
  }
}

Make sure exactly ONE statement has \"is_correct\": true. Make it challenging but solvable.";
    }

    private function callAI($prompt) {
        switch ($this->provider) {
            case 'gemini':
                return $this->callGemini($prompt);
            case 'groq':
            case 'openai':
                return $this->callOpenAICompatible($prompt);
            default:
                throw new Exception("Unsupported provider: {$this->provider}");
        }
    }

    private function callGemini($prompt) {
        $url = $this->baseUrl . '?key=' . $this->apiKey;
        
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
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Gemini API error (HTTP {$httpCode}): " . $response);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Unexpected Gemini response format");
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    private function callOpenAICompatible($prompt) {
        $model = ($this->provider === 'groq') ? 'mixtral-8x7b-32768' : 'gpt-3.5-turbo';
        
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

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API error (HTTP {$httpCode}): " . $response);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected API response format");
        }

        return $result['choices'][0]['message']['content'];
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

        // Validate exactly one correct statement
        $correctCount = 0;
        foreach ($puzzle['statements'] as $stmt) {
            if (isset($stmt['is_correct']) && $stmt['is_correct']) {
                $correctCount++;
            }
        }

        if ($correctCount !== 1) {
            // Auto-fix: mark first as correct if none, or mark only first as correct
            foreach ($puzzle['statements'] as &$stmt) {
                $stmt['is_correct'] = false;
            }
            if (!empty($puzzle['statements'])) {
                $puzzle['statements'][0]['is_correct'] = true;
            }
        }

        return $puzzle;
    }
}

