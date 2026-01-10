# AI Puzzle Generation Guide

## Overview

The puzzle generation system uses AI to automatically create puzzles. You can generate puzzles manually or set up automated monthly generation.

## 🤖 AI Providers

### 1. **Anthropic Claude 3.5 Sonnet** (Recommended - Highest Quality)
- **Quality**: Excellent - best for complex puzzles
- **Speed**: Fast
- **Cost**: Pay-as-you-go (check Anthropic pricing)
- **Get API key**: https://console.anthropic.com
- **Important**: Claude Pro subscription does NOT include API access - requires separate Anthropic Console account
- **Setup**: 
  1. Sign up at https://console.anthropic.com
  2. Navigate to API Keys section
  3. Create new API key
  4. Add to `.env` as `ANTHROPIC_API_KEY=your_key_here`

### 2. **Google Gemini** (Best Free Option)
- **Free tier**: 15 requests per minute
- **Cost**: Completely free for reasonable usage
- **Speed**: Fast
- **Get API key**: https://makersuite.google.com/app/apikey

### 3. **Groq** (Fastest Free Option)
- **Free tier**: Very generous (30+ requests/minute)
- **Cost**: Free
- **Speed**: Extremely fast (uses specialized inference chips)
- **Get API key**: https://console.groq.com/keys

### 4. **OpenAI GPT-3.5**
- **Free tier**: $5 credit on signup
- **Cost**: ~$0.002 per puzzle after free tier
- **Get API key**: https://platform.openai.com/api-keys

**Recommendation**: For best quality, use **Claude 3.5 Sonnet**. For free options, start with **Gemini** - it's completely free and works great!

## 🚀 Quick Start

### Step 1: Get API Key

**For Claude (Recommended):**
1. Go to https://console.anthropic.com
2. Sign up or sign in
3. Navigate to API Keys section
4. Create new API key
5. Copy your key
6. **Note**: Claude Pro subscription does NOT include API access - requires separate account

**For Gemini (Free):**
1. Go to https://makersuite.google.com/app/apikey
2. Sign in with Google
3. Click "Create API Key"
4. Copy your key

### Step 2: Add to .env File

On Hostinger, edit your `.env` file:

```env
# For Claude (Recommended)
ANTHROPIC_API_KEY=your_api_key_here

# Optional: Set default provider
AI_PROVIDER=claude
```

Or for Gemini:
```env
GEMINI_API_KEY=your_api_key_here
AI_PROVIDER=gemini
```

Or for Groq:
```env
GROQ_API_KEY=your_api_key_here
AI_PROVIDER=groq
```

### Step 3: Generate Puzzles

1. Log into admin panel: `https://yourdomain.com/admin/`
2. Click "AI Generator" in the menu
3. Select date and difficulty
4. Click "Generate All 3 Difficulties"
5. Wait ~30-60 seconds (3 API calls)
6. Done! Puzzles are saved automatically

## 📅 Automation Options

### Option 1: Manual Monthly Upload (Easiest)

1. **Once per month**: Log into admin panel
2. **Generate for next month**: Select dates 30 days ahead
3. **Click "Generate All 3 Difficulties"** for each date
4. **Done!** Puzzles are saved automatically

**Time required**: ~15 minutes per month (30 dates × 3 difficulties = 90 puzzles)

### Option 2: Automated Cron Job (Fully Automatic)

Set up a cron job to automatically generate puzzles monthly.

**On Hostinger:**

1. **Via cPanel Cron Jobs**:
   - Go to Hostinger cPanel
   - Find "Cron Jobs"
   - Add new cron job:
     ```
     Frequency: Once per month (Day 1, 2:00 AM)
     Command: /usr/bin/php /home/username/public_html/cron/generate-monthly-puzzles.php
     ```

2. **Via SSH** (if available):
   ```bash
   crontab -e
   # Add this line:
   0 2 1 * * /usr/bin/php /path/to/puzzle/cron/generate-monthly-puzzles.php
   ```

**The script will:**
- Generate puzzles for next 30 days
- Create Easy, Medium, Hard for each date
- Skip dates that already have puzzles
- Log results to `cron/generation-log.txt`

## 🎯 Best Practices

### For Best Quality Puzzles:

1. **Review Generated Puzzles**: After generation, review a few to ensure quality
2. **Edit if Needed**: Use the admin panel to tweak puzzles
3. **Test Play**: Try solving generated puzzles yourself
4. **Adjust Prompts**: If needed, edit `AIPuzzleGenerator.php` to improve prompts

### Rate Limiting:

- **Gemini**: 15 requests/minute (script automatically waits 4 seconds)
- **Groq**: 30+ requests/minute (no waiting needed)
- **Manual generation**: Better for small batches

### Cost Management:

- **Free tier is generous**: Gemini/Groq free tiers are enough for daily puzzles
- **Monitor usage**: Check API usage dashboards periodically
- **Cache generated puzzles**: Don't regenerate same date unnecessarily

## 🔧 Configuration

### Change Default AI Provider

In `.env`:
```env
AI_PROVIDER=claude  # or gemini, groq, openai
```

### Adjust Generation Days

In `cron/generate-monthly-puzzles.php`:
```php
$generateDaysAhead = 30; // Change to desired days
```

### Customize Prompts

Edit `includes/AIPuzzleGenerator.php` - modify the `buildPrompt()` method to change how puzzles are generated.

## 🐛 Troubleshooting

### "API key not found"
- Check `.env` file exists on server
- Verify API key is correct
- Make sure no extra spaces around the key

### "Rate limit exceeded"
- Gemini: Wait 1 minute between batches
- Use Groq for faster generation
- Reduce batch size in manual generation

### "Invalid JSON response"
- AI sometimes returns malformed JSON
- Try generating again (usually works on retry)
- Consider switching providers

### Cron job not running
- Check cron job syntax is correct
- Verify PHP path (`which php` or `/usr/bin/php`)
- Check file permissions (script should be executable)
- View logs: `cron/generation-log.txt`

## 📊 Usage Stats

**Daily Puzzle Requirements:**
- 3 puzzles per day (Easy, Medium, Hard)
- 90 puzzles per month
- ~90 API calls per month

**Free Tier Limits:**
- **Gemini**: 15/min = 900/hour = Plenty for monthly batch
- **Groq**: 30+/min = Even better
- **Cost**: $0/month ✅

## 🎨 Customization

### Create Your Own Puzzle Themes

Edit the prompt in `AIPuzzleGenerator.php` to:
- Add specific themes
- Change difficulty descriptions
- Adjust puzzle structure
- Modify output format

### Add More Providers

To add more AI providers, extend `AIPuzzleGenerator.php` and add new `callXXX()` methods.

## 📝 Notes

- Generated puzzles are saved directly to database
- You can edit generated puzzles anytime via admin panel
- Generation preserves puzzle structure (statements, hints, solutions)
- All three difficulties use the same case but different difficulty levels
- The AI is prompted to vary themes automatically

