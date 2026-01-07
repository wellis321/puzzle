# N8N Workflow for Automated Image Generation

This guide shows how to set up an automated workflow using n8n (or similar tools) to generate images locally and upload them to your puzzle website.

## Overview

The workflow:
1. **Trigger**: Checks your website for puzzles needing images
2. **Generate**: Uses your local image generation model (Stable Diffusion, etc.)
3. **Upload**: Sends the generated image back to your website

## Step 1: Get Your API Key

First, add an API key to your `.env` file:

```env
IMAGE_GENERATION_API_KEY=your-super-secret-key-here-make-it-long-and-random
```

Or if you don't set it, the system will auto-generate one based on your APP_URL. Check the API response to see what key to use.

## Step 2: API Endpoints

### Get Puzzles Needing Images
**URL:** `GET /api/get-puzzles-for-images.php?api_key=YOUR_KEY`

**Response:**
```json
{
  "success": true,
  "count": 5,
  "puzzles": [
    {
      "puzzle_id": 10,
      "puzzle_date": "2026-01-07",
      "title": "The Aurora Initiative",
      "difficulty": "easy",
      "theme": "Renewable Energy Research Facility",
      "case_summary": "...",
      "explanation": "...",
      "suggested_prompt": "A detailed, realistic illustration...",
      "api_submit_url": "https://yoursite.com/api/submit-puzzle-image.php"
    }
  ]
}
```

### Submit Generated Image
**URL:** `POST /api/submit-puzzle-image.php`  
**Headers:** `X-API-Key: YOUR_KEY`  
**Body:** `multipart/form-data`
- `puzzle_id`: (required) Puzzle ID
- `image`: (required) Image file
- `prompt`: (optional) Prompt used

**Response:**
```json
{
  "success": true,
  "message": "Image uploaded and saved successfully",
  "puzzle_id": 10,
  "image_path": "images/solutions/solution_10_abc123_1234567890.png"
}
```

## Step 3: N8N Workflow Setup

### Workflow Structure

```
1. HTTP Request (GET puzzles)
   ↓
2. Split In Batches (process each puzzle)
   ↓
3. HTTP Request (POST to local image generator)
   ↓
4. Wait for Image Generation
   ↓
5. Read Binary File
   ↓
6. HTTP Request (POST image to website)
```

### Node 1: Get Puzzles
**Type:** HTTP Request  
**Method:** GET  
**URL:** `https://yoursite.com/api/get-puzzles-for-images.php?api_key=YOUR_KEY`

### Node 2: Split Puzzles
**Type:** Split In Batches  
**Batch Size:** 1 (process one at a time)

### Node 3: Call Local Image Generator
**Type:** HTTP Request  
**Method:** POST  
**URL:** `http://localhost:7860/api/predict` (or your local Stable Diffusion endpoint)  
**Body:**
```json
{
  "data": [
    "{{ $json.puzzle_id }}",
    "{{ $json.suggested_prompt }}",
    1024,
    1024,
    20,
    7.5,
    "DPMSolverMultistepScheduler",
    false,
    1
  ]
}
```

### Node 4: Process Response
**Type:** Function  
Extract image data from your image generator's response

### Node 5: Submit to Website

**Option A: Using JSON/Base64 (Recommended for n8n)**
**Type:** HTTP Request  
**Method:** POST  
**URL:** `https://yoursite.com/api/submit-puzzle-image.php`  
**Headers:**
```
X-API-Key: YOUR_KEY
Content-Type: application/json
```
**Body (JSON):**
```json
{
  "puzzle_id": {{ $json.puzzle_id }},
  "image_base64": "data:image/png;base64,{{ $base64_image }}",
  "prompt": "{{ $json.suggested_prompt }}"
}
```

**Option B: Using Multipart Form Data**
**Type:** HTTP Request  
**Method:** POST  
**URL:** `https://yoursite.com/api/submit-puzzle-image.php`  
**Headers:**
```
X-API-Key: YOUR_KEY
```
**Body (Form Data):**
- `puzzle_id`: `{{ $json.puzzle_id }}`
- `image`: `{{ $binary.data }}` (from image generator)
- `prompt`: `{{ $json.suggested_prompt }}`

## Step 4: Alternative - Direct File System Access

If your n8n is on the same machine as your image generator:

1. Generate images to a shared folder
2. Use n8n to watch that folder
3. Upload images via the API when detected

### Workflow:
```
1. Watch Folder (new images)
   ↓
2. Extract Puzzle ID from filename (solution_PUZZLEID_*.png)
   ↓
3. Read Image File
   ↓
4. HTTP Request (POST to submit endpoint)
```

## Step 5: Scheduling

Set up a schedule to run periodically:
- **Manual**: Run when you generate new puzzles
- **Scheduled**: Every hour/day to check for new puzzles
- **Webhook**: Trigger when new puzzle is created (advanced)

## Security Notes

1. **API Key**: Keep your `IMAGE_GENERATION_API_KEY` secret
2. **HTTPS**: Use HTTPS for API calls in production
3. **Rate Limiting**: Consider adding rate limiting if needed
4. **Local Network**: If n8n is on localhost, you can use `http://localhost` for image generation

## Testing

Test the endpoints manually first:

```bash
# Get puzzles
curl "https://yoursite.com/api/get-puzzles-for-images.php?api_key=YOUR_KEY"

# Submit image
curl -X POST \
  -H "X-API-Key: YOUR_KEY" \
  -F "puzzle_id=10" \
  -F "image=@/path/to/image.png" \
  -F "prompt=Your prompt here" \
  "https://yoursite.com/api/submit-puzzle-image.php"
```

## Troubleshooting

- **401 Unauthorized**: Check your API key matches `.env` file
- **File upload fails**: Check `images/solutions/` directory permissions
- **No puzzles returned**: All puzzles may already have images, or no solutions exist yet

