# Whodunit Feature - Implementation Complete

## Overview
The whodunit feature adds comprehensive murder mystery puzzles with suspect profiles and witness statements. These puzzles unlock based on user progression.

## What's Been Implemented

### 1. Database Schema
- **Migration file**: `database/add-whodunit-feature.sql`
- Adds `puzzle_type` column to `puzzles` table (ENUM: 'standard', 'whodunit')
- Adds `unlock_level` column to `puzzles` table (for future rank-based unlocking)
- Creates `witness_statements` table for storing witness statements
- Creates `suspect_profiles` table for storing suspect information
- Adds `suspect_name` column to `statements` table (to link evidence to suspects)

### 2. Backend Logic

#### Game Class (`includes/Game.php`)
- `canAccessWhodunits()` - Checks if user qualifies (Rank 3+ or 10+ wins)
- `getWhodunitUnlockStatus()` - Returns detailed unlock status

#### Puzzle Class (`includes/Puzzle.php`)
- `getWitnessStatements($puzzleId)` - Retrieves witness statements
- `getSuspectProfiles($puzzleId)` - Retrieves suspect profiles
- `createWitnessStatement()` - Saves witness statements
- `createSuspectProfile()` - Saves suspect profiles
- `isWhodunit($puzzleId)` - Checks if puzzle is a whodunit
- `getAvailableWhodunits($userRankLevel)` - Lists available whodunits
- `createPuzzle()` - Updated to support `puzzle_type` parameter

#### AI Puzzle Generator (`includes/AIPuzzleGenerator.php`)
- `buildWhodunitPrompt()` - New prompt builder for whodunit format
- `generatePuzzle()` - Updated to accept `puzzleType` parameter
- `parseResponse()` - Updated to validate whodunit-specific data

### 3. Frontend Display (`index.php`)
- Detects whodunit puzzles automatically
- Shows unlock message if user doesn't qualify
- Displays suspect profiles in case summary section
- Shows witness statements as separate navigation section
- Adds "WHODUNIT" badge to whodunit puzzles
- Integrates seamlessly with existing puzzle flow

### 4. Admin Interface (`admin/puzzle-generate.php`)
- Added "Puzzle Type" dropdown to generation form
- Options: "Standard Mystery" (default) or "Whodunit (Murder Mystery)"
- Automatically saves witness statements and suspect profiles
- Success messages indicate whodunit creation
- Notes about longer generation time for whodunits

## Unlock Mechanism

**Default Unlock Criteria:**
- Rank 3 (Detective) or higher
- OR 10+ solved cases

This can be customized by modifying `Game::canAccessWhodunits()` or setting `unlock_level` in the database.

## Usage Instructions

### For Admins (Generating Whodunits)

1. **Run the database migration first:**
   ```sql
   -- Execute database/add-whodunit-feature.sql in your database
   ```

2. **Generate a whodunit puzzle:**
   - Go to Admin Panel → AI Generator
   - Select date, difficulty, and AI provider
   - Choose "Whodunit (Murder Mystery)" from Puzzle Type dropdown
   - Click "Generate Single Puzzle"
   - Wait 2-5 minutes (whodunits take longer to generate)

3. **The system will automatically:**
   - Create the puzzle with `puzzle_type = 'whodunit'`
   - Save suspect profiles
   - Save witness statements
   - Link evidence statements to suspects (via `suspect_name`)

### For Players

1. **Unlock whodunits:**
   - Reach Rank 3 (Detective) by solving puzzles
   - OR solve 10+ cases
   - Whodunits will automatically appear in your puzzle list

2. **Playing whodunits:**
   - Same gameplay as standard puzzles
   - Additional sections: Suspect Profiles and Witness Statements
   - Evidence statements contain clues pointing to the killer
   - Solution reveals the killer and explains the evidence

## Data Structure

### Whodunit Puzzle JSON Format (from AI)
```json
{
  "title": "The Mansion Murder",
  "theme": "Mansion Murder",
  "case_summary": "A wealthy businessman was found dead...",
  "suspect_profiles": [
    {
      "suspect_name": "John Smith",
      "profile_text": "Victim's business partner, had financial disputes..."
    }
  ],
  "witness_statements": [
    {
      "witness_name": "Jane Doe",
      "statement_text": "I heard arguing from the study around 9 PM..."
    }
  ],
  "report_text": "**Crime Scene**: ...",
  "statements": [
    {
      "text": "John Smith was seen leaving the mansion at 9:15 PM",
      "is_correct": true,
      "category": "evidence",
      "suspect_name": "John Smith"
    }
  ],
  "hints": [...],
  "solution": {
    "explanation": "...",
    "detailed_reasoning": "..."
  }
}
```

## Next Steps (Optional Enhancements)

1. **Manual unlock_level setting** - Add UI to set unlock_level per puzzle
2. **Whodunit archive page** - Dedicated page showing all available whodunits
3. **Premium whodunits** - Extra-special whodunits for premium subscribers
4. **Whodunit editing** - Enhanced admin interface for editing suspect profiles and witness statements

## Notes

- Whodunit generation takes 2-5 minutes (more complex prompts)
- All whodunit data is optional - if tables don't exist, system gracefully degrades
- Existing puzzles remain standard type (backward compatible)
- Unlock mechanism can be customized per puzzle via `unlock_level` column


