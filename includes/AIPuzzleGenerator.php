<?php
/**
 * AI Puzzle Generator
 * Supports multiple AI providers: Claude (Anthropic), Gemini, Groq, OpenAI, Local Llama (Ollama)
 */

class AIPuzzleGenerator {
    private $provider;
    private $apiKey;
    private $baseUrl;
    private $model;

    public function __construct($provider = 'claude') {
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
            case 'claude':
                $this->apiKey = EnvLoader::get('ANTHROPIC_API_KEY');
                break;
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
            $envKeyName = strtoupper($this->provider) . '_API_KEY';
            if ($this->provider === 'claude') {
                $envKeyName = 'ANTHROPIC_API_KEY';
            }
            throw new Exception("API key not found for {$this->provider}. Add {$envKeyName} to .env file.");
        }
    }

    private function setBaseUrl() {
        switch ($this->provider) {
            case 'claude':
                // Use Claude 3.5 Sonnet (latest version)
                $this->model = 'claude-3-5-sonnet-20241022';
                $this->baseUrl = 'https://api.anthropic.com/v1/messages';
                break;
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

    public function generatePuzzle($date, $difficulty, $generateImage = false, $avoidSimilar = true, $puzzleType = 'standard') {
        // Increase execution time for local Llama (can be slow)
        if (in_array($this->provider, ['local', 'llama'])) {
            set_time_limit(180); // 3 minutes per puzzle
        }
        
        // Get recent puzzles to avoid similarity
        $recentPuzzles = [];
        if ($avoidSimilar) {
            $recentPuzzles = $this->getRecentPuzzles($date, 14); // Check last 14 days
        }
        
        // Use different prompt builder for whodunits
        if ($puzzleType === 'whodunit') {
            $prompt = $this->buildWhodunitPrompt($difficulty, $recentPuzzles);
        } else {
            $prompt = $this->buildPrompt($difficulty, $recentPuzzles);
        }
        
        $response = $this->callAI($prompt);
        $puzzle = $this->parseResponse($response, $difficulty);
        
        // Add puzzle type to response
        $puzzle['puzzle_type'] = $puzzleType;
        
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

        $prompt = "Create a UNIQUE and ORIGINAL mystery puzzle case file in JSON format. The puzzle should be a \"one detail doesn't fit\" style mystery where players must find one statement that contradicts the case summary or report.

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
2. Write a case summary (2-3 sentences) that sets a unique scene and provides key facts/timeline
3. Create a detailed report with multiple sections (use **section_name** for headers) that includes specific details, times, dates, locations, or facts
4. Include 5-6 statements/facts as options - ALL but ONE must be CONSISTENT with the case summary and report
5. ONE statement must directly CONTRADICT information explicitly stated in the case summary or report
6. The contradiction MUST be clear and provable by referencing specific facts from the summary/report
7. Create 2 progressive hints that guide players toward noticing the contradiction
8. CRITICAL: Provide a detailed solution with BOTH explanation and detailed_reasoning fields

CRITICAL - CONTRADICTION REQUIREMENTS:
The incorrect statement (is_correct: true) MUST:
- Directly contradict a specific fact stated in the case summary or incident report
- Conflict with timeline, numbers, names, locations, or other concrete facts mentioned
- Be provably wrong by comparing it to the summary/report text
- Examples of good contradictions:
  * Report says \"incident at 2:00 PM\" but statement says \"incident at 3:00 PM\"
  * Report says \"3 people present\" but statement says \"4 people present\"
  * Report says \"south entrance\" but statement says \"north entrance\"
- AVOID vague contradictions like \"unexpected\" vs \"expected\" or subjective interpretations
- The contradiction must be FACTUAL and CLEAR when reading the summary/report

SOLUTION REQUIREMENTS (VERY IMPORTANT):
- \"explanation\": Must clearly state (2-4 sentences):
  * Which statement is contradictory
  * What specific fact in the summary/report it contradicts
  * Why this makes it the wrong answer
- \"detailed_reasoning\": Must provide (4-8 sentences):
  * Quote or reference the specific fact from summary/report that is contradicted
  * Explain exactly how the incorrect statement conflicts with this fact
  * Walk through why all other statements are consistent with the facts
  * Conclude why this is clearly the contradictory statement

IMPORTANT - CORRECT ANSWER PLACEMENT:
- The correct answer (is_correct: true) should be placed RANDOMLY among the statements
- Do NOT always put it first, second, or last - vary the position each time
- Include 5-6 statements total, with exactly ONE having is_correct: true
- The contradictory statement must be clearly identifiable when comparing to summary/report

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
     * Build prompt for whodunit murder mystery puzzles
     */
    private function buildWhodunitPrompt($difficulty, $recentPuzzles = []) {
        $difficultyInstructions = [
            'easy' => 'Easy: Make the killer obvious from witness statements. Include clear clues pointing to one suspect.',
            'medium' => 'Medium: Make the killer moderately difficult to identify. Include red herrings and conflicting evidence.',
            'hard' => 'Hard: Make the killer very difficult to identify. Include complex alibis, multiple suspects with motives, and subtle clues.'
        ];

        // Build variety instructions
        $varietyInstructions = $this->buildVarietyInstructions($recentPuzzles);
        
        $prompt = "Create a comprehensive WHODUNIT murder mystery puzzle in JSON format. This is a full-blown murder mystery where players must identify the killer based on witness statements and evidence.

CRITICAL VARIETY REQUIREMENTS:
{$varietyInstructions}

DIFFICULTY: {$difficultyInstructions[$difficulty]}

WHODUNIT STRUCTURE:
You must create a complete murder mystery case with the following components:

1. CASE SUMMARY (2-4 sentences):
   - Describe the murder scene, victim, and basic circumstances
   - Set the scene and establish key facts
   - Make it engaging and atmospheric

2. SUSPECT PROFILES (4-5 suspects required):
   - Create 4-5 distinct suspects with names
   - Each suspect needs: name, relationship to victim, motive, basic background
   - One suspect is the killer (make this solvable through evidence)
   - Format: Array of objects with 'suspect_name' and 'profile_text'

3. WITNESS STATEMENTS (4-6 statements required):
   - Each statement should be from a named witness
   - Include what they saw, heard, or know
   - Mix of alibis, observations, timelines, and suspicious behavior
   - Some statements should point to the killer, others are red herrings
   - Format: Array of objects with 'witness_name' and 'statement_text'

4. INCIDENT REPORT:
   - Detailed report with sections (use **section_name** for headers)
   - Include: Crime scene details, timeline, forensic evidence, witness accounts summary
   - Multiple sections with specific facts, times, locations, and evidence

5. EVIDENCE STATEMENTS (6-8 statements required):
   - These are the clickable options players must analyze
   - CRITICAL: Evidence statements MUST ONLY reference information already mentioned in the case summary, incident report, suspect profiles, or witness statements
   - DO NOT introduce new locations, objects, or facts that weren't already described
   - DO NOT add new information like \"had a troubled past\", \"was secretly working on\", \"used in a previous argument\" unless these were mentioned in the source material
   - Each statement must be PLAUSIBLE EVIDENCE that could point to a suspect - not filler or obviously irrelevant information
   - ALL statements should be based on actual facts from the report, witness statements, or suspect profiles
   - ONE statement must be the \"smoking gun\" that reveals or strongly implicates the killer by connecting existing evidence
   - Other statements should reference REAL evidence from the report that points to wrong suspects (these are red herrings but must be plausible)
   - Red herrings should still be legitimate evidence (like fabric matching a dress, timeline events, witness observations) that just happens to point to the wrong person
   - The correct statement (is_correct: true) should be the giveaway about who the killer was by connecting multiple pieces of evidence
   - Include 'suspect_name' field in the correct statement with the killer's name
   - Example of GOOD evidence: \"The torn fabric matched Vivian's dress mentioned in the crime scene report\" (references existing evidence from report)
   - Example of GOOD red herring: \"Witnesses saw James and Vivian talking in the library at 10:00 PM\" (real event from timeline, but doesn't prove guilt)
   - Example of BAD evidence: \"Thomas had a troubled past\" (new information not in report)
   - Example of BAD evidence: \"A hidden safe in the study contained...\" (introduces new location/object not mentioned in the report)
   - Example of BAD evidence: \"Alexander used this phrase in a previous argument\" (new information not in report - stick to what's in the timeline/evidence)

6. HINTS (2 hints):
   - Progressive hints guiding players toward the correct suspect/evidence

7. SOLUTION:
   - Must explicitly identify the killer
   - Explain WHY the correct evidence statement reveals the killer
   - Provide detailed reasoning connecting all the clues

REQUIRED JSON STRUCTURE:
{
  \"title\": \"[Creative murder mystery title]\",
  \"theme\": \"[Theme, e.g., 'Mansion Murder', 'Theater Crime']\",
  \"case_summary\": \"[2-4 sentence murder scene description]\",
  \"suspect_profiles\": [
    {\"suspect_name\": \"[Name]\", \"profile_text\": \"[Background, relationship, motive]\"}
  ],
  \"witness_statements\": [
    {\"witness_name\": \"[Name]\", \"statement_text\": \"[What they witnessed]\"}
  ],
  \"report_text\": \"[Detailed incident report with **section headers**]\",
  \"statements\": [
    {\"text\": \"[Evidence statement that reveals/implicates killer]\", \"is_correct\": true, \"category\": \"evidence\", \"suspect_name\": \"[Name of killer]\"},
    {\"text\": \"[Other evidence statement]\", \"is_correct\": false, \"category\": \"evidence\"}
  ],
  \"hints\": [\"[First hint]\", \"[Second hint]\"],
  \"solution\": {
    \"explanation\": \"[2-4 sentences identifying the killer and why]\",
    \"detailed_reasoning\": \"[4-8 sentences explaining how evidence points to the killer]\"
  }
}

CRITICAL REQUIREMENTS:
- The killer must be solvable through the evidence statements
- The correct statement (is_correct: true) MUST reveal or strongly implicate the killer by connecting evidence from the report
- Include the killer's name in the suspect_name field of the correct statement
- EVIDENCE STATEMENTS MUST ONLY REFERENCE INFORMATION FROM: case_summary, report_text, suspect_profiles, or witness_statements
- DO NOT introduce new locations (e.g., \"a hidden safe\", \"the basement\"), new objects, or new facts not mentioned in the source material
- DO NOT add background information like \"had a troubled past\", \"was secretly working on\", \"used in previous arguments\" unless explicitly mentioned in the source material
- ALL evidence statements must reference REAL information from the report (timeline events, forensic evidence, witness observations)
- Red herrings should reference actual evidence from the report that just happens to point to the wrong suspect
- Make ALL statements plausible and relevant - no filler or obviously irrelevant information
- Each statement should make players think \"this could be the evidence that solves it\" - not \"why is this here?\"
- Players must be able to verify all evidence statements by checking them against the provided case summary, report, and witness statements
- If you mention a location, object, fact, or background detail in an evidence statement, it MUST have been mentioned first in the case summary, report, suspect profiles, or witness statements
- Quality check: Every statement should be plausible evidence that could contribute to solving the case

Generate the JSON now:";

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
            case 'claude':
                return $this->callClaude($prompt);
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

    private function callClaude($prompt) {
        $data = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];

        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $response;
            
            if (isset($errorData['error'])) {
                $errorMessage = $errorData['error']['message'] ?? $response;
                $errorType = $errorData['error']['type'] ?? '';
                
                // Provide helpful error messages
                if ($httpCode === 401) {
                    throw new Exception("Invalid Anthropic API key. Please check your ANTHROPIC_API_KEY in .env file. Note: Claude Pro subscription does not include API access - you need a separate Anthropic Console account at https://console.anthropic.com");
                } elseif ($httpCode === 429) {
                    throw new Exception("Anthropic API rate limit reached. Please try again later or check your API usage limits.");
                }
            }
            
            throw new Exception("Claude API error (HTTP {$httpCode}): " . $errorMessage);
        }

        $result = json_decode($response, true);
        
        // Anthropic returns content as an array of blocks
        if (isset($result['content'][0]['text'])) {
            return $result['content'][0]['text'];
        }
        
        throw new Exception("Unexpected Claude API response format");
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
        // Groq models: Updated list of currently available models
        // Removed deprecated: mixtral-8x7b-32768, gemma2-9b-it
        if ($this->provider === 'groq') {
            $groqModels = [
                'llama-3.3-70b-versatile',
                'llama-3.1-70b-versatile',
                'llama-3.1-8b-instant',
                // Removed 'llama-3.2-3b-versatile' - model doesn't exist
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
        
        // Enhanced JSON cleaning function with Unicode support
        $json_clean_string = function($str) {
            // Remove BOM if present
            if (substr($str, 0, 3) === "\xEF\xBB\xBF") {
                $str = substr($str, 3);
            }
            
            // Remove ASCII control characters except newlines, tabs, and carriage returns
            $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
            
            // Decode HTML entities
            $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Fix common encoding issues
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            
            // Remove all Unicode control, format, and surrogate characters
            // This catches Unicode control characters that ASCII-only regex misses
            // \p{Cc} = Unicode Control characters
            // \p{Cf} = Format characters (like zero-width spaces)
            // \p{Cs} = Surrogate characters
            $str = preg_replace('/[\p{Cc}\p{Cf}\p{Cs}]/u', '', $str);
            
            return $str;
        };
        
        // Apply cleaning
        $jsonStr = $json_clean_string($jsonStr);
        
        // #region agent log
        $logData = json_encode(['location' => 'AIPuzzleGenerator.php:806', 'message' => 'About to parse JSON', 'data' => ['json_length' => strlen($jsonStr), 'first_300' => substr($jsonStr, 0, 300), 'last_300' => substr($jsonStr, -300), 'hypothesisId' => 'A,B'], 'timestamp' => time(), 'sessionId' => 'debug-session', 'runId' => 'run1']);
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', $logData . "\n", FILE_APPEND);
        // #endregion
        
        $puzzle = json_decode($jsonStr, true);
        
        // #region agent log
        $parseError = json_last_error();
        $parseErrorMsg = json_last_error_msg();
        $logData = json_encode(['location' => 'AIPuzzleGenerator.php:812', 'message' => 'JSON decode result', 'data' => ['error_code' => $parseError, 'error_msg' => $parseErrorMsg, 'puzzle_is_null' => ($puzzle === null), 'puzzle_type' => gettype($puzzle), 'puzzle_keys' => is_array($puzzle) ? array_keys($puzzle) : 'not_array', 'has_title' => is_array($puzzle) ? isset($puzzle['title']) : false, 'hypothesisId' => 'A,B'], 'timestamp' => time(), 'sessionId' => 'debug-session', 'runId' => 'run1']);
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', $logData . "\n", FILE_APPEND);
        // #endregion

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try more aggressive cleaning - remove all non-printable characters
            $jsonStr = preg_replace('/[\p{Cc}\p{Cf}\p{Cs}]/u', '', $jsonStr);
            
            $puzzle = json_decode($jsonStr, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Last resort: try to extract just the JSON object
                if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $jsonStr, $matches)) {
                    $jsonStr = $matches[0];
                    $puzzle = json_decode($jsonStr, true);
                }
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Log the problematic JSON for debugging
                    error_log("JSON parsing failed. Error: " . json_last_error_msg() . ". JSON length: " . strlen($jsonStr) . ". First 500 chars: " . substr($jsonStr, 0, 500));
                    throw new Exception("Invalid JSON from AI: " . json_last_error_msg() . " (after cleaning). Response length: " . strlen($jsonStr) . " chars.");
                }
            }
        }

        // #region agent log
        $logData = json_encode(['location' => 'AIPuzzleGenerator.php:838', 'message' => 'Before validation - check parsed puzzle structure', 'data' => ['puzzle_is_null' => ($puzzle === null), 'puzzle_is_array' => is_array($puzzle), 'puzzle_keys' => is_array($puzzle) ? array_keys($puzzle) : 'not_array', 'puzzle_count' => is_array($puzzle) ? count($puzzle) : 0, 'json_length' => strlen($jsonStr), 'last_200_chars' => substr($jsonStr, -200), 'hypothesisId' => 'A,B'], 'timestamp' => time(), 'sessionId' => 'debug-session', 'runId' => 'run1']);
        file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', $logData . "\n", FILE_APPEND);
        // #endregion
        
        // Validate required fields (different for whodunits)
        $isWhodunit = isset($puzzle['suspect_profiles']) || isset($puzzle['witness_statements']);
        
        $required = ['title', 'theme', 'case_summary', 'report_text', 'statements', 'hints', 'solution'];
        if ($isWhodunit) {
            $required[] = 'suspect_profiles';
            $required[] = 'witness_statements';
        }
        
        // #region agent log
        $missingFields = [];
        foreach ($required as $field) {
            if (!isset($puzzle[$field])) {
                $missingFields[] = $field;
            }
        }
        if (!empty($missingFields)) {
            $logData = json_encode(['location' => 'AIPuzzleGenerator.php:847', 'message' => 'Missing required fields detected', 'data' => ['missing_fields' => $missingFields, 'present_fields' => is_array($puzzle) ? array_keys($puzzle) : 'not_array', 'hypothesisId' => 'A,B'], 'timestamp' => time(), 'sessionId' => 'debug-session', 'runId' => 'run1']);
            file_put_contents('/Users/wellis/Desktop/Cursor/puzzle/.cursor/debug.log', $logData . "\n", FILE_APPEND);
        }
        // #endregion
        
        foreach ($required as $field) {
            if (!isset($puzzle[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Ensure whodunit-specific data is present
        if ($isWhodunit) {
            if (empty($puzzle['suspect_profiles']) || !is_array($puzzle['suspect_profiles'])) {
                throw new Exception("Whodunit puzzles require suspect_profiles array");
            }
            if (empty($puzzle['witness_statements']) || !is_array($puzzle['witness_statements'])) {
                throw new Exception("Whodunit puzzles require witness_statements array");
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
        
        // Validate that the solution explanation is coherent and references the contradiction
        $correctStatement = null;
        foreach ($puzzle['statements'] as $stmt) {
            if ($stmt['is_correct']) {
                $correctStatement = $stmt['text'];
                break;
            }
        }
        
        // Ensure solution makes sense and clearly explains the contradiction
        if ($correctStatement && !empty($puzzle['solution']['explanation'])) {
            $explanationLower = strtolower($puzzle['solution']['explanation']);
            $hasContradictionWords = (
                strpos($explanationLower, 'contradict') !== false ||
                strpos($explanationLower, "doesn't fit") !== false ||
                strpos($explanationLower, 'does not fit') !== false ||
                strpos($explanationLower, 'inconsistent') !== false ||
                strpos($explanationLower, 'wrong') !== false ||
                strpos($explanationLower, 'incorrect') !== false ||
                strpos($explanationLower, 'conflicts') !== false
            );
            
            if (!$hasContradictionWords) {
                // Enhance explanation to be clearer about the contradiction
                $puzzle['solution']['explanation'] = "The statement contradicts the facts presented in the case. " . trim($puzzle['solution']['explanation']);
                error_log("Warning: Enhanced solution explanation to clarify contradiction.");
            }
        }
        
        // DEEP LOGICAL VALIDATION: Verify the puzzle actually makes sense
        $validationResult = $this->validatePuzzleLogic($puzzle);
        if (!$validationResult['valid']) {
            // Log validation warnings/issues
            error_log("Puzzle validation warnings: " . implode("; ", $validationResult['warnings']));
            // Store validation results in puzzle for external systems (like n8n) to check
            $puzzle['validation'] = $validationResult;
        } else {
            $puzzle['validation'] = ['valid' => true, 'warnings' => []];
        }

        return $puzzle;
    }
    
    /**
     * Deep logical validation: Verify puzzle makes sense
     * Checks if the correct statement actually contradicts the summary/report
     */
    private function validatePuzzleLogic($puzzle) {
        $warnings = [];
        $isValid = true;
        
        // Get the correct statement
        $correctStatement = null;
        foreach ($puzzle['statements'] as $stmt) {
            if ($stmt['is_correct']) {
                $correctStatement = $stmt;
                break;
            }
        }
        
        if (!$correctStatement) {
            return ['valid' => false, 'warnings' => ['No correct statement found']];
        }
        
        // Combine all source material for analysis (for whodunits, include suspect profiles and witness statements)
        $isWhodunit = isset($puzzle['suspect_profiles']) || isset($puzzle['witness_statements']);
        
        $allText = strtolower($puzzle['case_summary'] . ' ' . $puzzle['report_text']);
        
        // For whodunits, also include suspect profiles and witness statements as source material
        if ($isWhodunit) {
            $witnessText = '';
            if (isset($puzzle['witness_statements']) && is_array($puzzle['witness_statements'])) {
                foreach ($puzzle['witness_statements'] as $witness) {
                    $witnessText .= ' ' . ($witness['statement_text'] ?? '');
                }
            }
            
            $suspectText = '';
            if (isset($puzzle['suspect_profiles']) && is_array($puzzle['suspect_profiles'])) {
                foreach ($puzzle['suspect_profiles'] as $suspect) {
                    $suspectText .= ' ' . ($suspect['profile_text'] ?? '');
                }
            }
            
            $allText .= $witnessText . ' ' . $suspectText;
        }
        
        $correctText = strtolower($correctStatement['text']);
        
        // Extract key facts from summary/report (times, numbers, locations, dates, objects, entities)
        $facts = $this->extractFacts($allText);
        
        // Extract facts from correct statement
        $statementFacts = $this->extractFacts($correctText);
        
        // For whodunits, check if evidence statements reference new information not in source material
        if ($isWhodunit) {
            $newEntitiesWarning = $this->checkForNewEntities($allText, $puzzle['statements']);
            if (!empty($newEntitiesWarning)) {
                $warnings = array_merge($warnings, $newEntitiesWarning);
            }
        }
        
        // Check for contradictions
        $contradictionsFound = [];
        
        // Time contradictions
        if (!empty($facts['times']) && !empty($statementFacts['times'])) {
            foreach ($facts['times'] as $reportTime) {
                foreach ($statementFacts['times'] as $stmtTime) {
                    if ($reportTime !== $stmtTime) {
                        $contradictionsFound[] = "Time mismatch: report says '{$reportTime}' but statement says '{$stmtTime}'";
                    }
                }
            }
        }
        
        // Number contradictions (counts, quantities)
        if (!empty($facts['numbers']) && !empty($statementFacts['numbers'])) {
            foreach ($facts['numbers'] as $reportNum => $reportContext) {
                foreach ($statementFacts['numbers'] as $stmtNum => $stmtContext) {
                    // Check if same type of number (people, items, etc.)
                    if (stripos($reportContext, 'people') !== false && stripos($stmtContext, 'people') !== false) {
                        if ($reportNum !== $stmtNum) {
                            $contradictionsFound[] = "Count mismatch: report mentions {$reportNum} people but statement says {$stmtNum}";
                        }
                    } elseif (stripos($reportContext, 'minute') !== false && stripos($stmtContext, 'minute') !== false) {
                        if ($reportNum !== $stmtNum) {
                            $contradictionsFound[] = "Duration mismatch: report says {$reportNum} minutes but statement says {$stmtNum}";
                        }
                    }
                }
            }
        }
        
        // Location contradictions (basic check)
        $locationKeywords = ['entrance', 'exit', 'door', 'room', 'building', 'north', 'south', 'east', 'west', 'left', 'right'];
        foreach ($locationKeywords as $keyword) {
            if (stripos($allText, $keyword) !== false && stripos($correctText, $keyword) !== false) {
                // Check if they're different
                preg_match_all('/\b' . preg_quote($keyword, '/') . '\s+(\w+)/i', $allText, $reportMatches);
                preg_match_all('/\b' . preg_quote($keyword, '/') . '\s+(\w+)/i', $correctText, $stmtMatches);
                if (!empty($reportMatches[1]) && !empty($stmtMatches[1])) {
                    $reportLocation = strtolower(implode(' ', $reportMatches[1]));
                    $stmtLocation = strtolower(implode(' ', $stmtMatches[1]));
                    if ($reportLocation !== $stmtLocation) {
                        $contradictionsFound[] = "Location mismatch involving '{$keyword}'";
                    }
                }
            }
        }
        
        // Check if solution explanation references the contradiction
        $solutionText = strtolower($puzzle['solution']['explanation'] . ' ' . ($puzzle['solution']['detailed_reasoning'] ?? ''));
        $referencesSpecificFact = false;
        foreach ($facts['times'] as $time) {
            if (stripos($solutionText, $time) !== false) {
                $referencesSpecificFact = true;
                break;
            }
        }
        if (!$referencesSpecificFact && !empty($facts['numbers'])) {
            foreach (array_keys($facts['numbers']) as $num) {
                if (stripos($solutionText, (string)$num) !== false) {
                    $referencesSpecificFact = true;
                    break;
                }
            }
        }
        
        // Build validation result
        if (empty($contradictionsFound)) {
            $warnings[] = "No clear factual contradiction detected. The puzzle may rely on subjective interpretation rather than factual mismatch.";
            // Don't mark as invalid - sometimes contradictions are subtle
        } else {
            // Good - found contradictions
            $puzzle['detected_contradictions'] = $contradictionsFound;
        }
        
        if (!$referencesSpecificFact && empty($contradictionsFound)) {
            $warnings[] = "Solution explanation does not reference specific facts from the report (times, numbers, locations).";
        }
        
        // Check if other statements are consistent
        $otherStatementsConsistent = true;
        foreach ($puzzle['statements'] as $stmt) {
            if (!$stmt['is_correct']) {
                $stmtFacts = $this->extractFacts(strtolower($stmt['text']));
                // Basic check: if statement has times, they should match report times (or at least not contradict)
                if (!empty($facts['times']) && !empty($stmtFacts['times'])) {
                    foreach ($facts['times'] as $reportTime) {
                        foreach ($stmtFacts['times'] as $stmtTime) {
                            // Other statements should be consistent with report
                            if ($reportTime !== $stmtTime) {
                                $warnings[] = "Non-correct statement may contradict report time: '{$stmtTime}' vs '{$reportTime}'";
                            }
                        }
                    }
                }
            }
        }
        
        return [
            'valid' => empty($warnings) || count($warnings) < 2, // Allow 1 warning
            'warnings' => $warnings,
            'contradictions_detected' => $contradictionsFound,
            'references_specific_facts' => $referencesSpecificFact
        ];
    }
    
    /**
     * Extract facts (times, numbers, locations) from text
     */
    private function extractFacts($text) {
        $facts = [
            'times' => [],
            'numbers' => [],
            'locations' => []
        ];
        
        // Extract times (HH:MM format or "X o'clock", "X PM", etc.)
        preg_match_all('/\b(\d{1,2}):(\d{2})\b/i', $text, $timeMatches);
        foreach ($timeMatches[0] as $time) {
            $facts['times'][] = $time;
        }
        
        // Extract numbers with context (for quantity contradictions)
        preg_match_all('/\b(\d+)\s+(\w+(?:\s+\w+){0,2})\b/i', $text, $numberMatches, PREG_SET_ORDER);
        foreach ($numberMatches as $match) {
            $num = $match[1];
            $context = $match[2];
            $facts['numbers'][$num] = $context;
        }
        
        // Extract location keywords
        preg_match_all('/\b(north|south|east|west|left|right|entrance|exit)\s+(\w+)?/i', $text, $locationMatches, PREG_SET_ORDER);
        foreach ($locationMatches as $match) {
            $facts['locations'][] = trim($match[0]);
        }
        
        return $facts;
    }
    
    /**
     * Check if evidence statements reference entities/locations/objects not mentioned in source material
     * For whodunits, this ensures players can verify all evidence from the provided information
     */
    private function checkForNewEntities($sourceText, $statements) {
        $warnings = [];
        $sourceLower = strtolower($sourceText);
        
        // Extract key entities from source: locations, objects, containers
        $locationKeywords = ['room', 'study', 'garden', 'office', 'kitchen', 'bedroom', 'safe', 'drawer', 'desk', 'cabinet', 'basement', 'attic', 'garage', 'hall', 'ballroom', 'library'];
        $containerKeywords = ['safe', 'drawer', 'desk', 'cabinet', 'box', 'envelope', 'folder', 'briefcase', 'bag', 'pocket'];
        
        // Check each statement
        foreach ($statements as $index => $stmt) {
            $stmtText = strtolower($stmt['text'] ?? '');
            
            // Check for new locations/containers
            foreach ($locationKeywords as $keyword) {
                if (stripos($stmtText, $keyword) !== false && stripos($sourceLower, $keyword) === false) {
                    $warnings[] = "Statement #" . ($index + 1) . " references '{$keyword}' which is not mentioned in the case summary, report, suspect profiles, or witness statements.";
                    break; // Only warn once per statement
                }
            }
            
            // Check for new containers (like "safe", "drawer")
            foreach ($containerKeywords as $keyword) {
                if (stripos($stmtText, $keyword) !== false && stripos($sourceLower, $keyword) === false) {
                    $warnings[] = "Statement #" . ($index + 1) . " introduces a new object/location '{$keyword}' that wasn't mentioned in the source material. Evidence must reference existing information.";
                    break;
                }
            }
            
            // Check for "found in" or "discovered in" new locations
            if (stripos($stmtText, 'found in') !== false || stripos($stmtText, 'discovered in') !== false) {
                // Extract what was found
                if (preg_match('/(?:found|discovered) in (?:the |a )?(\w+(?:\s+\w+){0,2})/i', $stmtText, $matches)) {
                    $location = strtolower($matches[1]);
                    // Check if this location was mentioned in source (allow common ones)
                    if (stripos($sourceLower, $location) === false && !in_array($location, ['crime scene', 'garden', 'office', 'room', 'study'])) {
                        // Check if it matches any location keyword
                        foreach ($locationKeywords as $keyword) {
                            if (stripos($location, $keyword) !== false) {
                                $warnings[] = "Statement #" . ($index + 1) . " introduces discovery in '{$location}' which may not have been mentioned in the source material.";
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        return $warnings;
    }
}

