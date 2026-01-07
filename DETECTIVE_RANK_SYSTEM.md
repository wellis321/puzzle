# Detective Rank System

## Overview
The detective rank system tracks user progress and awards ranks based on puzzle completions. Users progress from **Novice Detective** all the way up to legendary ranks like **Columbo** and **Sherlock Holmes**.

## Rank Progression

### Rank Levels

1. **Novice Detective** - Starting rank (0 completions)
2. **Junior Detective** - 3 completions
3. **Detective** - 10 completions
4. **Senior Detective** - 25 completions
5. **Master Detective** - 50 completions
6. **Chief Inspector** - 100 completions
7. **Detective Inspector** - 200 completions
8. **Sherlock Holmes** - 300 completions
9. **Hercule Poirot** - 400 completions
10. **Columbo** - 500 completions (MAX RANK)

### Special Bonuses

Rank can be boosted by:
- **Hard Puzzle Master**: 50+ hard completions with 80%+ solve rate = +1 level
- **Perfect Score Expert**: 100+ perfect scores = +1 level
- **Streak Champion**: 30+ day streak = +1 level

## Statistics Tracked

The system tracks:
- **Total Completions**: All puzzles completed (across all difficulties)
- **Easy Completions**: Easy puzzle completions
- **Medium Completions**: Medium puzzle completions
- **Hard Completions**: Hard puzzle completions
- **Perfect Scores**: Puzzles solved on first attempt
- **Solve Count**: Number of puzzles actually solved (not just attempted)
- **Current Streak**: Consecutive days with at least one completion
- **Best Streak**: Longest consecutive day streak achieved

## Display

The rank badge appears in the header showing:
- Current rank name
- Progress bar to next rank
- Total cases completed
- Current streak

## Database Setup

Run the migration to add the ranks table:
```sql
-- Run: database/add-ranks-table.sql
```

## Automatic Updates

Ranks are automatically updated when:
- User completes a puzzle (any difficulty)
- User solves a puzzle correctly
- User achieves a perfect score

## User Experience

Users see their rank prominently displayed and can track their progress. The system encourages:
- Daily play (streak tracking)
- Completing all three difficulties each day
- Improving accuracy (perfect scores)
- Taking on harder challenges (hard puzzle bonus)

## Motivation

The rank system provides:
- **Long-term goals**: Work toward Columbo rank (500 completions)
- **Daily motivation**: Maintain streaks
- **Skill recognition**: Higher ranks for better performance
- **Community status**: Visible rank display

