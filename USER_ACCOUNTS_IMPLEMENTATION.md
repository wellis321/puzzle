# User Accounts and Monetization Implementation

## Overview

This implementation adds user accounts, subscription management, and ad integration to the Daily Mystery puzzle game. Users can play anonymously or create accounts to save progress across devices.

## Features Implemented

### 1. User Authentication System
- User registration with email/password
- Login/logout functionality
- Optional username
- Password hashing (bcrypt)
- Session management

### 2. Account System
- Anonymous play still supported
- Progress migration when creating account
- Profile page with statistics
- Account settings

### 3. Subscription System
- Free tier (with ads)
- Premium subscription ($4.99/month or $49.99/year)
- Stripe payment integration
- Webhook handling for subscription events
- Subscription status tracking

### 4. Premium Features
- **Free Users:**
  - Access to all daily puzzles
  - Basic statistics
  - Detective ranking system
  - Last 7 days of puzzle archive

- **Premium Users:**
  - Ad-free experience
  - Advanced statistics dashboard
  - Full puzzle archive (all puzzles)
  - Leaderboards (top ranked, streaks, most solved)
  - Custom badge themes (future)
  - Priority support (future)

### 5. Ad Integration
- Google AdSense support
- Strategic ad placement:
  - Header banner
  - Between puzzle sections
  - After completion
- Conditional rendering (hidden for premium users)

## Database Changes

### New Tables
1. `users` - User accounts
2. `user_subscriptions` - Subscription tracking
3. `payment_transactions` - Payment history
4. `user_progress_migration` - Track progress migrations

### Modified Tables
- `user_ranks` - Added `user_id` column
- `attempts` - Added `user_id` column
- `completions` - Added `user_id` column, made `session_id` nullable
- `user_sessions` - Added `user_id` column

## Installation Steps

### 1. Database Setup

Run the following SQL files in order:

```sql
-- 1. Create user accounts tables
database/add-user-accounts-fixed.sql

-- 2. Create subscription tables
database/add-subscription-tables.sql
```

**Note:** The `completions` table needs its unique constraint dropped manually:

```sql
ALTER TABLE completions DROP INDEX unique_session_puzzle;
```

Uniqueness is now handled at the application level in `includes/Game.php`.

### 2. Environment Configuration

Add to `.env`:

```env
# Stripe Configuration (optional - for subscriptions)
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_ID_MONTHLY=price_...
STRIPE_PRICE_ID_YEARLY=price_...

# Ad Configuration
ENABLE_ADS=true
AD_PROVIDER=adsense
ADSENSE_CLIENT_ID=ca-pub-...
```

### 3. Stripe Setup (Optional)

1. Install Stripe PHP SDK:
   ```bash
   composer require stripe/stripe-php
   ```
   Or download from: https://github.com/stripe/stripe-php

2. Create products in Stripe Dashboard:
   - Monthly subscription ($4.99/month)
   - Yearly subscription ($49.99/year)

3. Get Price IDs and add to `.env`

4. Set up webhook endpoint:
   - URL: `https://yourdomain.com/api/stripe-webhook.php`
   - Events to listen for:
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`

### 4. Google AdSense Setup (Optional)

1. Sign up for Google AdSense
2. Get your publisher ID (ca-pub-...)
3. Add to `.env` as `ADSENSE_CLIENT_ID`
4. Configure ad units in AdSense dashboard
5. Add ad code to `includes/Ads.php` if needed

## Files Created/Modified

### New Files
- `includes/Auth.php` - Authentication system
- `includes/Subscription.php` - Subscription management
- `includes/Payment.php` - Stripe payment processing
- `includes/Ads.php` - Ad management
- `includes/Stats.php` - Advanced statistics
- `register.php` - Registration page
- `login.php` - User login page
- `logout.php` - Logout handler
- `profile.php` - User profile/dashboard
- `subscribe.php` - Subscription page
- `archive.php` - Puzzle archive (premium)
- `leaderboards.php` - Leaderboards (premium)
- `api/create-checkout-session.php` - Payment API
- `api/stripe-webhook.php` - Stripe webhook handler
- `database/add-user-accounts.sql` - User tables
- `database/add-user-accounts-fixed.sql` - Fixed version
- `database/add-subscription-tables.sql` - Subscription tables

### Modified Files
- `index.php` - Added login/register UI, ad containers, user context
- `includes/Game.php` - Support for both user_id and session_id
- `config.php` - Added Stripe and ad configuration
- `css/style.css` - Added ad container styles

## Usage

### For Users

1. **Anonymous Play:**
   - Users can play immediately without registration
   - Progress is tracked via session
   - Progress can be migrated to an account later

2. **Creating Account:**
   - Click "Sign Up" in header
   - Enter email, optional username, password
   - Progress automatically migrates if session exists

3. **Logging In:**
   - Click "Log In" in header
   - Enter email and password
   - Access profile and premium features

4. **Upgrading to Premium:**
   - Go to Profile page
   - Click "Upgrade to Premium"
   - Choose monthly or yearly plan
   - Complete payment via Stripe

### For Admins

- Monitor subscriptions in `user_subscriptions` table
- View payment transactions in `payment_transactions` table
- Check user accounts in `users` table
- Subscription status updates automatically via webhooks

## Security Considerations

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention (htmlspecialchars)
- ✅ CSRF protection recommended (not implemented yet)
- ✅ Secure session management
- ✅ Payment data never stored (Stripe tokens only)
- ⚠️ Rate limiting not implemented (consider adding)

## Known Limitations

1. **Completions Uniqueness:**
   - MySQL doesn't support COALESCE in UNIQUE constraints
   - Uniqueness is enforced at application level in `Game.php`
   - This means database-level duplicate prevention is not possible
   - Consider adding a database trigger for additional protection

2. **Stripe Library:**
   - Stripe PHP SDK must be installed via Composer or manually
   - Payment features won't work without it
   - Error handling included for missing library

3. **Ad Blockers:**
   - AdSense ads may be blocked by browser extensions
   - No fallback ad system implemented
   - Premium detection relies on subscription status

## Testing Checklist

- [ ] User registration works
- [ ] User login/logout works
- [ ] Anonymous play still works
- [ ] Progress migrates when creating account
- [ ] Subscription purchase flow works (if Stripe configured)
- [ ] Premium features unlock correctly
- [ ] Ads show for free users
- [ ] Ads hidden for premium users
- [ ] Webhook handles subscription events
- [ ] Cancel subscription works
- [ ] Profile page displays correctly
- [ ] Leaderboards work for premium users
- [ ] Archive works (7 days for free, all for premium)

## Future Enhancements

- Email verification
- Password reset functionality
- Social login (Google, Facebook)
- Custom detective badge themes
- Achievement system
- More premium features
- Email notifications
- Referral system
- Analytics dashboard

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Check database schema
4. Verify `.env` configuration
5. Test Stripe webhooks with Stripe CLI

