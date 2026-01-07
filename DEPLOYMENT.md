# Deployment Guide

## Local Development Setup (MAMP)

### 1. Create your .env file

```bash
cp .env.example .env
```

The default `.env.example` is already configured for MAMP:
- DB_HOST=localhost
- DB_PORT=8889 (MAMP default)
- DB_USER=root
- DB_PASS=root

### 2. Copy to MAMP htdocs

```bash
cp -r /Users/wellis/Desktop/Cursor/puzzle /Applications/MAMP/htdocs/
```

### 3. Set up the database

1. Open phpMyAdmin: http://localhost:8888/phpMyAdmin
2. Import `database/schema.sql`
3. Import `database/seed.sql` (Day 1 puzzle)
4. Optional: Import `database/sample-puzzles-week1.sql` (Days 2-7)

### 4. Access the app

- Public game: http://localhost:8888/puzzle/
- Admin panel: http://localhost:8888/puzzle/admin/
  - Username: admin
  - Password: changeme123

---

## Production Deployment (Hostinger via GitHub)

### Option 1: Manual GitHub to Hostinger

1. **Push to GitHub**

   ```bash
   cd /Users/wellis/Desktop/Cursor/puzzle
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin your-github-repo-url
   git push -u origin main
   ```

2. **On Hostinger: Pull from GitHub**

   Via Hostinger File Manager or SSH:
   ```bash
   cd public_html
   git clone your-github-repo-url .
   ```

3. **Create .env file on Hostinger**

   Use Hostinger File Manager to create a new file called `.env`:

   ```
   DB_HOST=localhost
   DB_NAME=u123456789_mystery
   DB_USER=u123456789_user
   DB_PASS=your_db_password
   DB_PORT=3306

   APP_NAME="Daily Mystery"
   APP_URL=https://yourdomain.com

   ADMIN_USERNAME=admin
   ADMIN_PASSWORD=your_secure_password

   TIMEZONE=America/New_York
   APP_ENV=production
   ```

4. **Set up database on Hostinger**

   - Go to Hostinger control panel → Databases
   - Create new MySQL database
   - Use Hostinger's phpMyAdmin to import:
     - **RECOMMENDED**: `database/hostinger-full-setup.sql` (complete setup with all features)
     - **OR manually**: 
       - `database/schema.sql`
       - `database/add-ranks-table.sql` (for detective ranks)
     - **Optional sample data**:
       - `database/seed.sql` (Day 1 puzzle)
       - `database/sample-puzzles-week1.sql` (Days 2-7)

5. **Set file permissions** (if needed)

   ```bash
   chmod 755 public_html
   chmod 644 .env
   ```

### Option 2: Hostinger GitHub Integration

If Hostinger supports automatic GitHub deployment:

1. **Connect GitHub repository in Hostinger control panel**
   - Go to Git section in Hostinger
   - Connect your GitHub account
   - Select repository and branch

2. **Add .env file manually** (do NOT commit .env to GitHub)
   - Use File Manager to create `.env` with production values

3. **Set up database** (same as Option 1, step 4)

---

## Environment File Reference

### Local Development (.env)
```
DB_HOST=localhost
DB_PORT=8889
DB_USER=root
DB_PASS=root
APP_ENV=development
```

### Production (.env on server)
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_hostinger_db_name
DB_USER=your_hostinger_db_user
DB_PASS=your_hostinger_db_password
APP_URL=https://yourdomain.com
APP_ENV=production
```

---

## Important Security Notes

1. **Never commit .env to GitHub** - It's already in `.gitignore`
2. **Change admin password** - Use a strong password in production
3. **Use HTTPS** - Enable SSL in Hostinger (usually free with Let's Encrypt)
4. **Database backups** - Set up automatic backups in Hostinger

---

## Updating Production

### When you make changes:

```bash
# Commit and push to GitHub
git add .
git commit -m "Your changes"
git push

# On Hostinger (via SSH or File Manager Git pull)
cd public_html
git pull origin main
```

**Important**: The `.env` file on the server will NOT be overwritten during git pull because it's in `.gitignore`.

---

## Troubleshooting

### "Class 'EnvLoader' not found"
- Make sure `config.php` and `includes/EnvLoader.php` are uploaded
- Check file permissions

### ".env file not found"
- Create `.env` file manually on server
- Copy contents from `.env.example` and update values

### Database connection failed
- Verify `.env` database credentials match Hostinger's database settings
- Check DB_PORT (usually 3306 for Hostinger, 8889 for MAMP)
- Ensure database exists and is accessible

### 500 Internal Server Error
- Check `.env` file exists and is readable
- Check PHP error logs in Hostinger control panel
- Verify all PHP files uploaded correctly
