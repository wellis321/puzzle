# Hostinger Database Migration Guide

## Quick Setup (Recommended)

For a fresh installation on Hostinger, use the **complete setup file**:

### 1. Run Complete Setup SQL

**IMPORTANT**: On Hostinger, you MUST select your database first before running SQL!

**Steps:**
1. Log into Hostinger phpMyAdmin
2. In the left sidebar, **click on your database name** (e.g., `u248320297_mystery`)
3. Click the **"SQL"** tab at the top
4. Use this file: `database/hostinger-setup-no-create.sql` (recommended)
   - OR use `hostinger-full-setup.sql` but skip the CREATE DATABASE line
5. Paste the SQL and click **"Go"**

**Use this file:**
```
database/hostinger-setup-no-create.sql
```

This single file includes:
- ✅ All core game tables
- ✅ Multi-difficulty support (Easy, Medium, Hard)
- ✅ Detective rank system
- ✅ All indexes and foreign keys

**Steps:**
1. Log into Hostinger control panel
2. Go to **Databases** → **phpMyAdmin**
3. Select your database (or create a new one)
4. Click **Import** tab
5. Choose file: `hostinger-full-setup.sql`
6. Click **Go**

---

## Upgrading Existing Database

If you already have the database set up and need to add new features:

### Scenario 1: Adding Multi-Difficulty Support

If your database was created before multi-difficulty support:

1. Run: `database/migration-multi-difficulty-simple.sql`
   - Adds composite unique constraint for (date, difficulty)
   - Allows 3 puzzles per date

### Scenario 2: Adding Detective Rank System

If your database doesn't have the rank system:

1. Run: `database/add-ranks-table.sql`
   - Creates `user_ranks` table
   - Tracks user progress and ranks

---

## Complete Migration Checklist

### New Installation
- [ ] Create database in Hostinger control panel
- [ ] Run `hostinger-full-setup.sql` in phpMyAdmin
- [ ] Verify all tables created successfully
- [ ] Optionally import sample puzzles:
  - [ ] `seed.sql` (Day 1)
  - [ ] `sample-puzzles-week1.sql` (Days 2-7)

### Upgrading Existing Installation
- [ ] Backup current database first!
- [ ] Check which features are missing:
  - [ ] Multi-difficulty support → run `migration-multi-difficulty-simple.sql`
  - [ ] Rank system → run `add-ranks-table.sql`
- [ ] Test application after each migration
- [ ] Verify existing data still works

---

## Verification Queries

After running the setup, verify everything is correct:

```sql
-- Check all tables exist
SHOW TABLES;

-- Should show:
-- puzzles, statements, hints, solutions, user_sessions, 
-- attempts, completions, puzzle_stats, user_ranks

-- Check puzzle table has correct indexes
SHOW INDEX FROM puzzles;
-- Should show: unique_puzzle_date_difficulty, idx_puzzle_date, idx_difficulty

-- Check user_ranks table exists
DESCRIBE user_ranks;
-- Should show all rank tracking columns
```

---

## Troubleshooting

### "Table already exists" errors
- If tables already exist, you're likely upgrading
- Use individual migration files instead of full setup
- Or drop existing tables first (⚠️ **BACKUP FIRST!**)

### Foreign key errors
- Make sure all tables are created in order
- Check that InnoDB engine is being used
- Verify all foreign key references are correct

### Permission errors
- Ensure database user has CREATE and ALTER privileges
- Check Hostinger database user permissions

---

## After Database Setup

1. **Create `.env` file** on Hostinger with database credentials
2. **Test the application**:
   - Visit your site
   - Check if puzzles load
   - Try solving a puzzle
   - Check if rank badge appears
3. **Create admin account** via `.env` file:
   ```
   ADMIN_USERNAME=admin
   ADMIN_PASSWORD=your_secure_password
   ```
4. **Start creating puzzles** in the admin panel!

---

## File Reference

| File | Purpose | When to Use |
|------|---------|-------------|
| `hostinger-full-setup.sql` | Complete fresh installation | New deployment |
| `schema.sql` | Core tables only | Manual setup |
| `add-ranks-table.sql` | Add rank system | Upgrade existing DB |
| `migration-multi-difficulty-simple.sql` | Add multi-difficulty | Upgrade existing DB |
| `seed.sql` | Sample puzzle (Day 1) | Optional test data |
| `sample-puzzles-week1.sql` | Sample puzzles (Days 2-7) | Optional test data |

