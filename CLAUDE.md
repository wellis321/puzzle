# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a daily puzzle game in the crime/mystery genre, designed to be similar to Wordle in mechanics and habit-forming qualities. The core concept is to provide one puzzle per day that takes 5-10 minutes to solve using logic-based deduction.

## Game Concepts Under Consideration

Three main concepts are being explored:

1. **Daily Case**: Players answer 3-5 questions (who, motive, key clue) based on 5-8 clues. Limited guesses with clues revealed on wrong answers.

2. **The Missing Detail**: Find the single inconsistent statement in a crime report. 3 tries max, hints unlock after mistakes. Most Wordle-like approach.

3. **Yes/No Detective**: Solve the case by asking 5 yes/no questions that narrow down the solution, followed by a final guess.

## Core Design Requirements

### Gameplay Constraints
- **One puzzle per day** - daily habit-forming mechanic
- **5-10 minutes max** playtime
- **Simple input only** - tap/click/select (mobile + web compatible)
- **Clear win or loss** state
- **Shareable result** without spoilers (emoji grid format)
- **Skill-based, not trivia-based** - solvable by logic alone

### Puzzle Design Principles
- Solvable in ≤6 logical steps
- Every clue must matter (no meaningless red herrings)
- No outside knowledge required
- Should feel obvious in hindsight when solution is explained
- Test with non-gamers for accessibility

### Post-Game Experience
- Show step-by-step reveal of the solution
- Display the logical path to the answer
- Score categories: "Perfect Deduction", "Close Call", "Lucky Guess"
- Countdown timer to next puzzle
- Shareable emoji grid result (no spoilers)

## Target Audience
- True-crime fans
- Puzzle enthusiasts
- Players seeking low-stress, clever games
- Mobile and web users
