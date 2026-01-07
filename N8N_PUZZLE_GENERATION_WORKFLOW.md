# N8N Automated Puzzle Generation Workflow

This workflow automates high-quality puzzle generation with validation, retry logic, image generation, and automatic upload.

## Architecture Overview

```
n8n Workflow:
1. Loop through dates (7 days ahead)
2. For each difficulty (easy, medium, hard):
   a. Call generate-puzzle-quality.php (with retry loop)
   b. Validate quality score
   c. If quality insufficient, retry (max 3 attempts)
   d. Generate image if puzzle passes
   e. Submit puzzle via submit-puzzle.php
3. Schedule daily execution
```

## API Endpoints

### 1. Generate Puzzle with Quality Validation
**Endpoint:** `api/generate-puzzle-quality.php`

**Method:** GET or POST

**Parameters:**
- `api_key` (required) - Your API key from `.env` `IMAGE_GENERATION_API_KEY`
- `date` (optional) - Date in YYYY-MM-DD format (default: today)
- `difficulty` (optional) - easy, medium, or hard (default: medium)
- `provider` (optional) - gemini, groq, openai, local, llama (default: local)
- `generate_image` (optional) - true/false (default: false)
- `max_retries` (optional) - Number of retry attempts (default: 3)
- `min_quality_score` (optional) - Minimum quality score 0.0-1.0 (default: 0.7)

**Response:**
```json
{
  "success": true,
  "puzzle": {
    "title": "...",
    "theme": "...",
    "case_summary": "...",
    "report_text": "...",
    "statements": [...],
    "hints": [...],
    "solution": {...},
    "validation": {
      "valid": true,
      "warnings": [],
      "contradictions_detected": [...],
      "references_specific_facts": true
    }
  },
  "quality_score": 0.85,
  "attempts": 2,
  "ready_for_upload": true
}
```

### 2. Submit Generated Puzzle
**Endpoint:** `api/submit-puzzle.php`

**Method:** POST (JSON)

**Headers:**
- `Authorization: Bearer YOUR_API_KEY` OR
- Include `api_key` in JSON body

**Body:**
```json
{
  "api_key": "your-api-key",
  "puzzle": {
    "puzzle_date": "2026-01-15",
    "title": "The Case Title",
    "difficulty": "medium",
    "theme": "Theme Name",
    "case_summary": "...",
    "report_text": "...",
    "statements": [
      {"text": "...", "is_correct": false, "category": "general"},
      {"text": "...", "is_correct": true, "category": "timeline"}
    ],
    "hints": ["Hint 1", "Hint 2"],
    "solution": {
      "explanation": "...",
      "detailed_reasoning": "..."
    },
    "solution_image": {
      "path": "images/solutions/...",
      "prompt": "..."
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "puzzle_id": 123,
  "message": "Puzzle submitted successfully"
}
```

## Quality Score Calculation

The quality score (0.0 to 1.0) is based on:

- **Validation Results (40%)**: Whether puzzle passes logical validation
- **Contradiction Detection (20% bonus)**: If factual contradictions are detected
- **Specific Fact References (10% bonus)**: If solution references specific facts
- **Solution Quality (30%)**: Explanation and reasoning completeness
- **Statement Count (20%)**: Should have 5-6 statements
- **Required Fields (10%)**: All fields present

**Minimum recommended score: 0.7** (adjustable via parameter)

## N8N Workflow Setup

### Step 1: Basic Structure

1. **Schedule Trigger**
   - Schedule: Daily at 2:00 AM (or your preferred time)
   - This ensures puzzles are ready before players need them

2. **Calculate Dates Loop**
   - Generate dates for next 7 days
   - Use "Function" node or "Range" node

### Step 2: Generate Puzzle Loop

For each date and difficulty combination:

1. **HTTP Request: Generate Puzzle**
   - Method: POST
   - URL: `https://your-domain.com/api/generate-puzzle-quality.php`
   - Body (form-data or JSON):
     ```json
     {
       "api_key": "{{$env.API_KEY}}",
       "date": "{{$json.date}}",
       "difficulty": "{{$json.difficulty}}",
       "provider": "local",
       "generate_image": true,
       "max_retries": 3,
       "min_quality_score": 0.75
     }
     ```

2. **IF Node: Check Quality**
   - Condition: `{{$json.quality_score}} >= 0.75`
   - True path: Continue to image generation
   - False path: Retry or log warning

3. **IF Node: Retry Logic** (if quality insufficient)
   - Condition: `{{$json.attempts}} < 3`
   - True path: Loop back to generate (with delay)
   - False path: Use best attempt or skip

4. **HTTP Request: Submit Puzzle**
   - Method: POST
   - URL: `https://your-domain.com/api/submit-puzzle.php`
   - Headers: `Authorization: Bearer {{$env.API_KEY}}`
   - Body: Full puzzle object from generate step

### Step 3: Error Handling

- Add "Error Trigger" nodes to catch failures
- Log errors to a monitoring system
- Send notifications if generation fails

### Step 4: Image Generation

Images are generated automatically if:
- `generate_image: true` is set
- Puzzle quality score meets threshold
- OpenAI API key is configured

## Example N8N Workflow Structure

```
┌─────────────────┐
│  Schedule       │ Daily at 2:00 AM
│  (Cron)         │
└────────┬────────┘
         │
┌────────▼────────┐
│  Function:      │ Generate date array (next 7 days)
│  Create Dates   │
└────────┬────────┘
         │
┌────────▼────────┐
│  Loop:          │ For each date
│  Date Loop      │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼────┐ ┌──▼──────┐ ┌───────┐
│ Easy   │ │ Medium  │ │ Hard  │
└───┬────┘ └──┬──────┘ └───┬───┘
    │         │            │
    └────┬────┴──────┬─────┘
         │           │
    ┌────▼───────────▼────┐
    │  Generate Puzzle    │ (with retry loop)
    │  HTTP Request       │
    └────┬────────────────┘
         │
    ┌────▼────────┐
    │ Check       │ Quality score >= 0.75?
    │ Quality     │
    └────┬────────┘
         │
    ┌────▼────────┐
    │ Generate    │ Image (if needed)
    │ Image       │
    └────┬────────┘
         │
    ┌────▼────────┐
    │ Submit      │ Upload to database
    │ Puzzle      │
    └─────────────┘
```

## Configuration

### Environment Variables

Add to your `.env`:
```env
IMAGE_GENERATION_API_KEY=your-secure-random-key-here
```

Generate a secure key:
```bash
openssl rand -hex 32
```

### N8N Environment Variables

Set in n8n:
- `API_KEY`: Your `IMAGE_GENERATION_API_KEY` value
- `BASE_URL`: Your website URL (e.g., `https://your-domain.com`)

## Quality Assurance Features

### Automatic Validation

The system now checks:
- ✅ Correct statement actually contradicts summary/report
- ✅ Contradictions are factual (times, numbers, locations)
- ✅ Solution references specific facts
- ✅ Other statements are consistent
- ✅ Solution explanation is clear

### Retry Logic

If quality is insufficient:
1. Retry generation (up to `max_retries` times)
2. Track best attempt
3. Use best if threshold not met (with warning)
4. Skip puzzle if all attempts fail

### Logging

All validation results are logged:
- Quality scores
- Validation warnings
- Detected contradictions
- Retry attempts

Check your PHP error log or server logs for details.

## Benefits of This Approach

1. **Automated**: Runs daily without manual intervention
2. **Quality-Assured**: Validates puzzles meet standards
3. **Retry Logic**: Automatically retries poor-quality puzzles
4. **Image Generation**: Includes images automatically
5. **Scalable**: Can generate weeks/months ahead
6. **Local Generation**: Use local Llama to avoid API costs
7. **Monitoring**: Built-in quality metrics and logging

## Testing the Workflow

### Test Single Puzzle Generation

```bash
curl -X POST "https://your-domain.com/api/generate-puzzle-quality.php" \
  -d "api_key=YOUR_KEY" \
  -d "date=2026-01-15" \
  -d "difficulty=medium" \
  -d "provider=local" \
  -d "generate_image=true" \
  -d "max_retries=3" \
  -d "min_quality_score=0.7"
```

### Test Puzzle Submission

```bash
curl -X POST "https://your-domain.com/api/submit-puzzle.php" \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d @puzzle-data.json
```

## Troubleshooting

### Low Quality Scores

If puzzles consistently score below threshold:
1. Check validation warnings in response
2. Adjust `min_quality_score` lower (0.6) for testing
3. Review AI prompt - may need refinement
4. Check if AI provider is working correctly

### Generation Failures

1. Check API key is correct
2. Verify database connection
3. Check file permissions for image uploads
4. Review error logs

### Image Generation Issues

1. Verify OpenAI API key is set
2. Check billing/quota limits
3. Ensure `images/solutions/` directory exists and is writable

## Next Steps

1. Set up n8n workflow following structure above
2. Test with single puzzle first
3. Gradually increase to full week generation
4. Monitor quality scores and adjust thresholds
5. Set up alerts for failures

