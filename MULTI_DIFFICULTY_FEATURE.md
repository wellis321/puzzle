# Multi-Difficulty Feature Implementation

## Overview
The puzzle system now supports **three puzzles per day**, one for each difficulty level (Easy, Medium, Hard). Users can select which difficulty they want to play, and all three can be completed on the same day.

## Database Changes

### Migration Required
**IMPORTANT**: Before using this feature, you must run the database migration:

```sql
-- Run this file:
database/migration-multi-difficulty.sql
```

This migration:
- Removes the unique constraint on `puzzle_date`
- Adds a composite unique constraint on `(puzzle_date, difficulty)`
- Adds an index on `difficulty` for performance

### Schema Updates
The `schema.sql` file has been updated for new installations. The `puzzles` table now allows:
- Multiple puzzles per date (up to 3: one for each difficulty)
- Unique constraint: `(puzzle_date, difficulty)`

## Code Changes

### Puzzle.php Class
Added new methods:
- `getTodaysPuzzle($difficulty = null)` - Get today's puzzle for specific difficulty (defaults to 'medium')
- `getTodaysPuzzles()` - Get all puzzles for today (all difficulties)
- `getPuzzleByDate($date, $difficulty = null)` - Get puzzle by date and optional difficulty
- `getPuzzlesByDate($date)` - Get all puzzles for a specific date

Updated:
- `getAllPuzzles()` - Now orders by date DESC, then by difficulty (easy, medium, hard)

### index.php (Main Game Page)
Changes:
- Accepts `?difficulty=easy|medium|hard` URL parameter
- Shows difficulty selector tabs when multiple puzzles available
- Displays checkmark on completed difficulty tabs
- Defaults to 'medium' difficulty if not specified

### Admin Interface
Updates:
- Puzzle list now groups puzzles by date with headers
- Shows count of difficulties per date (e.g., "3 difficulties")
- Puzzles sorted by date DESC, then by difficulty order

### CSS
New styles added:
- `.difficulty-selector` - Container for difficulty tabs
- `.difficulty-tabs` - Flex container for tab buttons
- `.difficulty-tab` - Individual difficulty tab styles
- `.difficulty-tab.active` - Active tab styling
- `.difficulty-tab.completed` - Completed puzzle indicator

## User Experience

### Difficulty Selection
When multiple puzzles are available for today:
1. User sees a difficulty selector with tabs (Easy, Medium, Hard)
2. Each tab shows the difficulty name
3. Completed puzzles show a checkmark (✓)
4. Active tab is highlighted with a border
5. Clicking a tab loads that difficulty's puzzle

### URL Parameters
- `?difficulty=easy` - Load easy puzzle
- `?difficulty=medium` - Load medium puzzle (default)
- `?difficulty=hard` - Load hard puzzle

## Creating Puzzles

When creating puzzles in the admin panel:
1. You can create up to 3 puzzles per date
2. Each puzzle must have a unique `(date, difficulty)` combination
3. You don't need to create all three - users will only see available difficulties

### Recommended Workflow
1. Create Easy puzzle for a date
2. Create Medium puzzle for the same date
3. Create Hard puzzle for the same date
4. All three will appear as selectable tabs on that date

## Backward Compatibility

- Existing single-puzzle-per-date setup still works
- If only one puzzle exists for a date, no difficulty selector is shown
- `getTodaysPuzzle()` without parameters defaults to 'medium' for backward compatibility
- Old URLs without difficulty parameter default to 'medium'

## Testing Checklist

- [ ] Run migration script on existing database
- [ ] Create puzzles with same date but different difficulties
- [ ] Verify difficulty selector appears when multiple puzzles exist
- [ ] Test switching between difficulties
- [ ] Verify completion checkmarks appear correctly
- [ ] Test with only one puzzle per date (no selector should appear)
- [ ] Test admin panel puzzle listing (should group by date)
- [ ] Verify dev mode puzzle selector shows difficulty

## Notes

- The system gracefully handles missing difficulties (e.g., if only Easy and Hard exist)
- Users can complete all three difficulties on the same day
- Each difficulty maintains separate completion tracking
- The default difficulty is 'medium' to maintain consistency

