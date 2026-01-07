# Local Development Setup Guide

This guide will help you run Simple Data Cleaner locally on your machine.

## Prerequisites

1. **PHP 7.4 or higher** (with extensions: `pdo`, `pdo_mysql`, `mbstring`)
2. **MySQL 5.7+ or MariaDB 10.2+**
3. **Composer** (optional, for PHP dependencies if needed)
4. **Web browser** (Chrome, Firefox, Safari, or Edge)

## Quick Start

### Option 1: Using PHP Built-in Server (Simplest)

This is the easiest way to run the app locally without configuring a web server.



# 2. Start PHP's built-in web server
php -S localhost:8000
```

Then open your browser and go to: **http://localhost:8000**

### Option 2: Using a Local Web Server (XAMPP, MAMP, Laravel Valet, etc.)

1. Copy the project folder to your web server directory:
   - **XAMPP**: `C:\xampp\htdocs\validation\` (Windows) or `/Applications/XAMPP/htdocs/validation/` (Mac)
   - **MAMP**: `/Applications/MAMP/htdocs/validation/`
   - **Laravel Valet**: Already served if in Valet directory

2. Access via: `http://localhost/validation` (or your configured domain)

## Database Setup

### 1. Create Database

```sql
CREATE DATABASE simple_data_cleaner;
```

### 2. Import Schema

```bash
mysql -u root -p simple_data_cleaner < database/schema.sql
```

Or using MySQL command line:
```sql
mysql -u root -p
USE simple_data_cleaner;
SOURCE /path/to/database/schema.sql;
```

### 3. Create Environment File

Create a `.env` file in the root directory:

```bash
cp .env.example .env  # If you have an example file
# Or create .env manually
```

Add your database credentials to `.env`:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=simple_data_cleaner
DB_USER=root
DB_PASS=your_password

# Application
APP_URL=http://localhost:8000
APP_ENV=local
APP_DEBUG=true

# Email Configuration (Optional - for password reset)
MAIL_SMTP_HOST=smtp.example.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USER=your_email@example.com
MAIL_SMTP_PASS=your_email_password
MAIL_FROM_ADDRESS=noreply@localhost
MAIL_FROM_NAME=Simple Data Cleaner

# Stripe (Optional - for payment features)
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

## Stripe Configuration (Optional)

If you want to test payment/subscription features:

1. Copy the example config:
   ```bash
   cp config/stripe_config.example.php config/stripe_config.php
   ```

2. Edit `config/stripe_config.php` with your Stripe test keys

3. Get test keys from: https://dashboard.stripe.com/test/apikeys

## Configuration Files

### Database Config

The app automatically loads from `.env` file. The `config/database.php` file uses:
- `DB_HOST` (default: localhost)
- `DB_NAME` (default: simple_data_cleaner)
- `DB_USER` (default: root)
- `DB_PASS` (required)

### Email Config

For password reset and email verification to work, configure SMTP in `.env`:
- `MAIL_SMTP_HOST`
- `MAIL_SMTP_PORT`
- `MAIL_SMTP_USER`
- `MAIL_SMTP_PASS`

**Note**: For local development, you might skip email configuration initially - the app will still work but password reset won't send emails.

## Running the Application

### Start PHP Server

```bash
# From project root
php -S localhost:8000
```

### Access the Application

Open your browser:
- **Main app**: http://localhost:8000
- **Validation rules**: http://localhost:8000/validation-rules.php

## Testing Without Authentication

If you want to test the data cleaning features without setting up authentication:

1. The data cleaning functionality works **100% client-side** - no server required
2. You can open `index.php` directly in a browser (though some features may need a server)
3. For full functionality, you'll need:
   - User authentication (requires database)
   - Subscription management (requires Stripe - optional)

## Troubleshooting

### Database Connection Errors

**Error**: `PDOException: could not find driver`

**Solution**: Install PHP MySQL extension
```bash
# macOS (Homebrew)
brew install php@8.1
# Or install php-mysql extension

# Ubuntu/Debian
sudo apt-get install php-mysql

# Windows (XAMPP)
# Enable extension in php.ini: extension=pdo_mysql
```

### .env File Not Loading

**Error**: Database credentials not found

**Solution**:
1. Ensure `.env` file exists in root directory
2. Check file permissions: `chmod 644 .env`
3. Verify format: `KEY=value` (no spaces around `=`)

### Permission Errors

**Error**: Can't write to logs/ directory

**Solution**:
```bash
chmod -R 755 logs/
# Or create logs directory if missing
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

## Development Tips

### Enable Debug Mode

In `.env`:
```env
APP_DEBUG=true
APP_ENV=local
```

### View Errors

PHP errors will display if `APP_DEBUG=true`. For production, set to `false`.

### Test Data Cleaning

The app works entirely client-side! You can:
1. Open http://localhost:8000
2. Upload a CSV file
3. Clean data without database/authentication

### Skip Authentication for Testing

To bypass login temporarily, you could modify `includes/Auth.php` or create a test user:

```sql
INSERT INTO users (email, password_hash, email_verified, created_at)
VALUES ('test@example.com', '$2y$10$hashedpassword', 1, NOW());
```

## File Structure

```
validation/
├── .env                    # Environment variables (create this)
├── config/                 # Configuration files
│   ├── database.php
│   ├── email.php
│   └── stripe_config.php
├── database/
│   └── schema.sql         # Import this to set up database
├── includes/              # Core PHP classes
├── assets/                # Static assets
├── validators.js          # Client-side validation
├── fileProcessor.js       # File processing
├── app.js                 # Main app logic
└── index.php              # Main entry point
```

## Quick Test

Once everything is set up:

1. **Start server**: `php -S localhost:8000`
2. **Open browser**: http://localhost:8000
3. **Test file upload**: Upload a CSV with UK phone numbers
4. **Verify cleaning**: Check that phone numbers are formatted correctly

## Next Steps

- ✅ Database set up and schema imported
- ✅ `.env` file configured
- ✅ PHP server running
- ✅ Application accessible in browser
- ⚠️ Stripe setup (optional, for payments)
- ⚠️ Email setup (optional, for password reset)

## Need Help?

- Check PHP error logs: Usually in terminal when using built-in server
- Check browser console: For JavaScript errors
- Check database connection: Verify credentials in `.env`
- Review `CLAUDE.md`: For architecture details

## Notes

- **Data Processing**: All file cleaning happens client-side in the browser - no server processing needed!
- **Database**: Only used for user accounts and subscriptions, not for data files
- **Privacy**: User data files never leave the browser - GDPR compliant by design
