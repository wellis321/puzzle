# Local Development Setup Guide

This guide will help you run Daily Mystery locally on your machine.

## Prerequisites

1. **PHP 7.4 or higher** (with extensions: `pdo`, `pdo_mysql`, `mbstring`)
2. **MySQL 5.7+ or MariaDB 10.2+**
3. **Web browser** (Chrome, Firefox, Safari, or Edge)

## Quick Start

### Option 1: Using PHP Built-in Server (Simplest)

This is the easiest way to run the app locally without configuring a web server.

```bash
# 1. Navigate to project directory
cd /Users/wellis/Desktop/Cursor/puzzle

# 2. Create environment file
cp .env.example .env

# 3. Start PHP's built-in web server
php -S localhost:8000
```

Then open your browser and go to:
- **Main game**: http://localhost:8000
- **Admin panel**: http://localhost:8000/admin/

**Default admin credentials**: admin / changeme123

### Option 2: Using MAMP

1. Copy the project folder to MAMP's htdocs directory:
   ```bash
   cp -r /Users/wellis/Desktop/Cursor/puzzle /Applications/MAMP/htdocs/
   ```

2. Create environment file:
   ```bash
   cd /Applications/MAMP/htdocs/puzzle
   cp .env.example .env
   ```

3. Access via: http://localhost:8888/puzzle/

## Database Setup

### 1. Create Database

**Using MySQL command line:**
```bash
mysql -u root -p
```

Then run:
```sql
CREATE DATABASE mystery_puzzle;
```

**Or use phpMyAdmin:**
- MAMP: http://localhost:8888/phpMyAdmin
- Standalone: http://localhost/phpMyAdmin

### 2. Import Schema

**Option A: Command line**
```bash
# Navigate to project directory
cd /Users/wellis/Desktop/Cursor/puzzle

# Import schema
mysql -u root -p mystery_puzzle < database/schema.sql

# Import Day 1 puzzle
mysql -u root -p mystery_puzzle < database/seed.sql

# (Optional) Import Week 1 puzzles
mysql -u root -p mystery_puzzle < database/sample-puzzles-week1.sql
```

**Option B: phpMyAdmin**
1. Open phpMyAdmin
2. Select `mystery_puzzle` database
3. Click "Import"
4. Upload and import in order:
   - `database/schema.sql`
   - `database/seed.sql`
   - `database/sample-puzzles-week1.sql` (optional)

### 3. Configure Environment File

Edit `.env` file with your database credentials:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=mystery_puzzle
DB_USER=root
DB_PASS=root
DB_PORT=3306

# Application Settings
APP_NAME="Daily Mystery"
APP_URL=http://localhost:8000

# Admin Credentials
ADMIN_USERNAME=admin
ADMIN_PASSWORD=changeme123

# Timezone
TIMEZONE=Europe/London

# Environment
APP_ENV=development
```

**Important**:
- If using **PHP built-in server**: Use `DB_PORT=3306`
- If using **MAMP**: Use `DB_PORT=8889` and `APP_URL=http://localhost:8888/puzzle`

## Running the Application

### Start PHP Built-in Server

```bash
# From project root
cd /Users/wellis/Desktop/Cursor/puzzle
php -S localhost:8000
```

### Access the Application

Open your browser:
- **Play the game**: http://localhost:8000
- **Admin panel**: http://localhost:8000/admin/
- **Login**: admin / changeme123

## Configuration Files

The app uses a `.env` file for all configuration:

- `DB_HOST` - Database host (usually localhost)
- `DB_NAME` - Database name (mystery_puzzle)
- `DB_USER` - Database username (usually root)
- `DB_PASS` - Database password
- `DB_PORT` - MySQL port (3306 standard, 8889 for MAMP)
- `APP_URL` - Your local URL
- `ADMIN_USERNAME` - Admin panel username
- `ADMIN_PASSWORD` - Admin panel password (plain text, will be hashed automatically)
- `TIMEZONE` - Your timezone (Europe/London for Glasgow)
- `APP_ENV` - Environment (development or production)

## Troubleshooting

### Database Connection Errors

**Error**: `PDOException: could not find driver`

**Solution**: Install PHP MySQL extension
```bash
# macOS (Homebrew)
brew install php@8.1

# Ubuntu/Debian
sudo apt-get install php-mysql

# Check if extension is enabled
php -m | grep pdo_mysql
```

### .env File Not Found

**Error**: `.env file not found`

**Solution**:
```bash
cp .env.example .env
```

Make sure you're in the project root directory.

### Wrong Database Port

**Error**: `Connection refused` or `Can't connect to MySQL server`

**Solution**: Check your MySQL port
```bash
# Find MySQL port
mysql -u root -p -e "SHOW VARIABLES LIKE 'port';"
```

Update `DB_PORT` in `.env`:
- Standard MySQL: `3306`
- MAMP: `8889`
- XAMPP: Usually `3306`

### Permission Errors

**Error**: Can't write to session files

**Solution**:
```bash
# Create necessary directories
mkdir -p logs
chmod 755 logs
```

### PHP Version Issues

**Check PHP version**:
```bash
php -v
```

**Minimum**: PHP 7.4+
**Recommended**: PHP 8.0+

### Admin Login Not Working

**Issue**: Can't log into admin panel

**Solution**:
1. Check credentials in `.env`:
   ```env
   ADMIN_USERNAME=admin
   ADMIN_PASSWORD=changeme123
   ```

2. Password is auto-hashed by the app, so use plain text in `.env`

### Database Already Exists Error

**Error**: `Database already exists`

**Solution**: Either:
1. Drop and recreate:
   ```sql
   DROP DATABASE mystery_puzzle;
   CREATE DATABASE mystery_puzzle;
   ```

2. Or skip the CREATE DATABASE line in schema.sql

## Development Tips

### Enable Debug Mode

In `.env`:
```env
APP_ENV=development
```

This will show PHP errors in the browser.

### View Database

Use phpMyAdmin or command line:
```bash
mysql -u root -p mystery_puzzle
```

Then:
```sql
SHOW TABLES;
SELECT * FROM puzzles;
SELECT * FROM statements;
```

### Test as New User

Clear your browser cookies to test as a new player:
- Chrome/Firefox: DevTools → Application → Cookies → Delete
- Safari: Develop → Empty Caches

### Change Admin Password

Edit `.env`:
```env
ADMIN_PASSWORD=your_new_password
```

Password will be automatically hashed when you log in.

## File Structure

```
puzzle/
├── .env                    # Environment variables (create this)
├── .env.example            # Environment template
├── config.php              # Loads .env and sets constants
├── database/
│   ├── schema.sql         # Database structure - import first
│   ├── seed.sql           # Day 1 puzzle - import second
│   └── sample-puzzles-week1.sql # Days 2-7 - optional
├── includes/              # Core PHP classes
│   ├── Database.php       # Database connection
│   ├── Session.php        # User session management
│   ├── Puzzle.php         # Puzzle operations
│   ├── Game.php           # Game logic
│   └── EnvLoader.php      # Environment file loader
├── admin/                 # Admin panel
│   ├── login.php          # Admin login
│   ├── index.php          # Puzzle list
│   └── puzzle-edit.php    # Create/edit puzzles
├── api/
│   └── submit-answer.php  # Answer submission endpoint
├── css/
│   └── style.css          # Public game styles
└── index.php              # Main game interface
```

## Quick Test

Once everything is set up:

1. **Start server**: `php -S localhost:8000`
2. **Open browser**: http://localhost:8000
3. **Play puzzle**: Try to solve today's mystery
4. **Check admin**: http://localhost:8000/admin/ (login: admin / changeme123)
5. **Create puzzle**: Add a new puzzle for tomorrow

## Creating Your First Puzzle

1. Log into admin panel
2. Click "Create New Puzzle"
3. Fill in:
   - Date (tomorrow's date)
   - Title, difficulty, theme
   - Case summary and report
4. After saving, add:
   - 5-6 statements (mark ONE as correct)
   - 2 hints
   - Solution explanation

## Next Steps

- ✅ Database set up and schema imported
- ✅ `.env` file configured
- ✅ PHP server running
- ✅ Application accessible in browser
- ✅ Admin panel working
- 📝 Create your first puzzle!

## Need Help?

- Check PHP error logs: Displayed in terminal when using built-in server
- Check browser console: Press F12 for JavaScript errors
- Check database connection: Verify credentials in `.env`
- Review `CLAUDE.md`: For game design principles
- Review `README.md`: For project overview
- Review `DEPLOYMENT.md`: For production deployment

## Notes

- **Daily Puzzles**: The app shows puzzles based on `puzzle_date` column
- **Timezone**: Set correctly in `.env` (Europe/London for Glasgow)
- **Anonymous Users**: Tracked via PHP sessions (no login required to play)
- **Admin Panel**: Secure area for managing puzzles
- **Database**: Stores puzzles, attempts, and completion statistics
