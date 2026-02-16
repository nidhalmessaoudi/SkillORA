# Quick Start Guide - Email Verification

## 🚀 Get Started in 3 Steps

### Step 1: Install MailHog (for testing)

Download and run MailHog to capture emails during development:

**Windows:**
```bash
# Download from: https://github.com/mailhog/MailHog/releases/latest
# Run the executable: MailHog.exe
```

**Mac:**
```bash
brew install mailhog
mailhog
```

**Linux:**
```bash
wget https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_linux_amd64
chmod +x MailHog_linux_amd64
./MailHog_linux_amd64
```

Then open: http://localhost:8025

---

### Step 2: Update Your .env (ALREADY DONE!)

Your `.env` file is already configured:

```env
MAILER_DSN=smtp://localhost:1025
MAILER_FROM_ADDRESS=noreply@skillharbor.com
MAILER_FROM_NAME=SkillHarbor
```

---

### Step 3: Test It!

1. **Start your Symfony server:**
   ```bash
   symfony serve
   # or
   php -S localhost:8000 -t public
   ```

2. **Start MailHog** (in another terminal):
   ```bash
   MailHog.exe
   ```

3. **Register a new user:**
   - Go to: http://localhost:8000/register
   - Fill in the form
   - Click "Create account"

4. **Check MailHog:**
   - Open: http://localhost:8025
   - You'll see the verification email!

5. **Click the verification link** in the email

6. **Try to log in** - It should work! ✅

---

## 🎯 What's Already Done

✅ Symfony Mailer installed  
✅ Database updated (verification columns added)  
✅ All existing users marked as verified  
✅ Beautiful email template created  
✅ Verification routes configured  
✅ Security checker blocks unverified users  
✅ Cache cleared  

---

## 📧 For Production (Real Emails)

### Using Gmail:

1. Get a Gmail App Password:
   - Google Account → Security → 2-Step Verification → App passwords
   
2. Update `.env`:
   ```env
   MAILER_DSN=smtp://your-email@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587
   ```

### Using SendGrid (Recommended for production):

1. Sign up at https://sendgrid.com
2. Create API key
3. Update `.env`:
   ```env
   MAILER_DSN=sendgrid://YOUR_API_KEY@default
   ```

---

## 🔧 Useful Commands

### Clear cache:
```bash
php bin/console cache:clear
```

### Manually verify a user in database:
```sql
UPDATE users SET is_verified = 1 WHERE email = 'user@example.com';
```

### Check email logs:
```bash
tail -f var/log/dev.log | grep -i email
```

---

## 📁 Important Files

| File | Purpose |
|------|---------|
| `templates/emails/verify-email.html.twig` | Email template (beautiful design!) |
| `src/Controller/AuthController.php` | Sends verification emails |
| `src/Security/UserChecker.php` | Blocks unverified users |
| `src/Entity/User.php` | Has verification fields |
| `.env` | Email configuration |

---

## 🐛 Common Issues

**"Can't connect to localhost:1025"**
→ MailHog is not running. Start it first!

**"Emails not appearing"**
→ Check http://localhost:8025 (MailHog web interface)

**"User can't log in after registering"**
→ This is correct! They need to verify email first.

**"Want to skip verification for testing"**
→ In database: `UPDATE users SET is_verified = 1 WHERE email = 'test@example.com';`

---

## ✨ That's It!

Your email verification system is ready to use. Just start MailHog and test it out!

For full details, see: `EMAIL_VERIFICATION_SETUP.md`
