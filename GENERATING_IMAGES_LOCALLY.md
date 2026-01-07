# Generating Images Locally with Stable Diffusion

Since you've hit OpenAI billing limits, here's how to generate images locally and upload them to your puzzles.

## Option 1: Using Automatic1111 WebUI (Easiest)

1. **Install Automatic1111 Stable Diffusion WebUI:**
   ```bash
   git clone https://github.com/AUTOMATIC1111/stable-diffusion-webui
   cd stable-diffusion-webui
   ./webui.sh
   ```

2. **Generate Images:**
   - Open http://localhost:7860 in your browser
   - Use prompts like: "A detailed, realistic illustration of a [THEME] mystery scene. Style: noir detective aesthetic, dramatic lighting, vintage crime scene investigation. Scene shows clues and evidence related to: [PUZZLE TITLE]. Mood: mysterious, intriguing, professional crime investigation."
   - Generate at 1024x1024 resolution
   - Save the image

3. **Upload via Admin Panel:**
   - Go to `admin/puzzle-edit.php?id=X`
   - Use the "Upload Locally Generated Image" section
   - Select your generated image and upload

## Option 2: Using Python Script

Create a simple Python script to generate images programmatically:

```python
from diffusers import StableDiffusionPipeline
import torch

pipe = StableDiffusionPipeline.from_pretrained("runwayml/stable-diffusion-v1-5")
pipe = pipe.to("cuda" if torch.cuda.is_available() else "cpu")

prompt = "A detailed, realistic illustration of a mystery scene. Style: noir detective aesthetic..."
image = pipe(prompt).images[0]
image.save(f"solution_image_{puzzle_id}.png")
```

## Option 3: Using Replicate API (Free Tier Available)

If you want to automate it, you could integrate Replicate's Stable Diffusion API which has a free tier.

## Recommended Workflow

1. Generate puzzle without images (uncheck "Generate solution image")
2. For each puzzle, generate image locally using Stable Diffusion with a prompt like:
   ```
   A detailed, realistic illustration of a [PUZZLE THEME] mystery scene. 
   Style: noir detective aesthetic, dramatic lighting, vintage crime scene investigation. 
   Scene shows clues and evidence related to: [PUZZLE TITLE]. 
   Mood: mysterious, intriguing, professional crime investigation. 
   Color palette: muted tones with dramatic shadows, film noir style. 
   No text, no people visible, focus on evidence and scene details.
   ```
3. Upload via the admin panel
4. Optionally add the prompt you used in the "Image Prompt" field for reference

## Image Specifications

- **Format:** PNG, JPEG, GIF, or WebP
- **Recommended Size:** 1024x1024 pixels
- **Style:** Noir/detective aesthetic, dramatic lighting
- **Content:** Evidence, clues, crime scene details (no people or text)

