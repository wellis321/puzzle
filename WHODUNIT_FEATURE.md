# Whodunit Feature Implementation

## Overview
Whodunits are special, comprehensive murder mystery puzzles with witness statements. They unlock based on user progression and provide a deeper, more immersive experience.

## Database Schema Changes

### 1. Add puzzle_type to puzzles table
- Add `puzzle_type ENUM('standard', 'whodunit') DEFAULT 'standard'`
- Add `unlock_level INT NULL` to track minimum rank level required

### 2. New witness_statements table
- Stores witness statements for whodunit puzzles
- Links to puzzles with foreign key
- Includes witness_name, statement_text, statement_order

### 3. Update statements table
- Add `suspect_name VARCHAR(100) NULL` for whodunit suspect identification
- Statements can directly reference which suspect they implicate

## Unlock Mechanism

### Option 1: Rank-Based (Recommended)
- Unlock at Rank 3 (Detective) or higher
- Alternative: Unlock after solving 10+ cases

### Option 2: Case-Type Based
- Always available for murder-themed puzzles
- User can toggle between standard and whodunit view

### Option 3: Hybrid
- Rank 3+: Whodunit option appears for murder cases
- Premium users: Access to all whodunits regardless of rank

## Whodunit Structure

1. **Case Summary**: Full murder scenario
2. **Witness Statements**: 4-6 witness statements with names
3. **Suspect Profiles**: Brief profiles of 4-5 suspects
4. **Evidence Statements**: 6-8 statements (one reveals the killer)
5. **Solution**: Comprehensive explanation revealing the killer

## Implementation Steps

1. Database migration for new tables/columns
2. Update Puzzle class methods
3. Create Whodunit class (extends Puzzle behavior)
4. Update puzzle generation AI prompts
5. Create whodunit display template
6. Add unlock logic in Game class
7. Update admin interface for whodunit management


