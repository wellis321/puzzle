# Daily Mystery Puzzle Game

A daily mystery puzzle game where players solve crimes by finding inconsistencies in case reports. Built with PHP and MySQL.

## Quick Start

### Prerequisites
- PHP 7.4+ with MySQL extensions
- MySQL 5.7+ or MariaDB
- Web browser

### Installation Steps

```bash
# 1. Create environment file
cp .env.example .env

# 2. Create database and import schema
mysql -u root -p -e "CREATE DATABASE mystery_puzzle;"
mysql -u root -p mystery_puzzle < database/schema.sql
mysql -u root -p mystery_puzzle < database/seed.sql

# 3. Start PHP server
php -S localhost:8000

# 4. Open browser
# Game: http://localhost:8000
# Admin: http://localhost:8000/admin/ (admin / changeme123)
```

📖 **For detailed setup options and troubleshooting, see [LOCAL_SETUP.md](LOCAL_SETUP.md)**
📖 **For production deployment to Hostinger, see [DEPLOYMENT.md](DEPLOYMENT.md)**

### Project Structure

```
puzzle/
├── admin/              # Admin panel for managing puzzles
│   ├── css/           # Admin styles
│   ├── index.php      # Puzzle list
│   ├── login.php      # Admin login
│   ├── puzzle-edit.php # Create/edit puzzles
│   └── auth.php       # Authentication check
├── api/               # API endpoints
│   └── submit-answer.php
├── css/               # Public-facing styles
│   └── style.css
├── database/          # SQL files
│   ├── schema.sql     # Database structure
│   ├── seed.sql       # Day 1 sample puzzle
│   └── sample-puzzles-week1.sql # Days 2-7
├── includes/          # PHP classes
│   ├── Database.php   # Database connection
│   ├── Session.php    # User session management
│   ├── Puzzle.php     # Puzzle data operations
│   └── Game.php       # Game logic and scoring
├── index.php          # Main game interface
├── .env.example       # Environment config template
├── .env.production    # Production config reference
├── .env               # Your local config (not in git)
└── config.php         # Loads .env and sets constants
```

## Admin Panel Features

### Creating a New Puzzle

1. Log into admin panel
2. Click "Create New Puzzle"
3. Fill in basic information:
   - **Puzzle Date**: When this puzzle should appear
   - **Title**: Short, descriptive title
   - **Difficulty**: Easy, Medium, or Hard
   - **Theme**: e.g., "Office Theft", "Home Burglary"
   - **Case Summary**: 3-4 sentence overview
   - **Report Text**: Full case details (supports basic markdown with `**bold**`)

4. After saving, add:
   - **Statements**: 5-6 clickable options (mark ONE as the correct answer)
   - **Hints**: 2 progressive hints
   - **Solution**: Brief and detailed explanations

### Managing Puzzles

- View all puzzles sorted by date
- Edit existing puzzles
- Delete puzzles (removes all associated data)

## Database Schema

### Core Tables

- **puzzles**: Main puzzle data (date, title, summary, report)
- **statements**: Clickable options for each puzzle
- **hints**: Progressive hints (1-2 per puzzle)
- **solutions**: Explanation and detailed reasoning
- **user_sessions**: Anonymous user tracking via session IDs
- **attempts**: Individual guess attempts
- **completions**: Finished puzzles with scores
- **puzzle_stats**: Aggregate statistics

### Scoring System

- **Perfect Deduction** (🧠): Solved on first attempt
- **Close Call** (🔍): Solved on second attempt
- **Lucky Guess** (😬): Solved on third attempt

## Production Deployment (Hostinger)

**See [DEPLOYMENT.md](DEPLOYMENT.md) for complete deployment instructions.**

Quick summary:
1. Push code to GitHub (`.env` is ignored)
2. Pull from GitHub on Hostinger
3. Create `.env` file on server with production credentials
4. Import database SQL files via Hostinger phpMyAdmin
5. Access your live site!

### Daily Puzzle Management

New puzzles should be created with `puzzle_date` set to future dates. The game automatically shows the puzzle matching today's date.

**Recommended workflow:**
- Create puzzles 1-2 weeks in advance
- Schedule time each week to create new puzzles
- Test puzzles before their scheduled date using the admin preview

## Development Notes

### Adding New Features

- **Database changes**: Create migration SQL files in `database/`
- **New pages**: Follow existing authentication patterns
- **API endpoints**: Use JSON responses, proper error handling
- **Styling**: Maintain mobile-first responsive design

### Testing Locally

- Clear browser cookies to test as a new user
- Use phpMyAdmin to view session data and attempts
- Check error logs in MAMP/logs/ for debugging

## Support

For issues or questions about this codebase, refer to:
- [CLAUDE.md](CLAUDE.md) for project overview
- [sample-puzzle-day1.md](sample-puzzle-day1.md) for puzzle design guidelines
- Database schema comments in `database/schema.sql`
