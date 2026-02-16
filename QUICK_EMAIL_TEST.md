# Quick Email Test - Two Options

## 🚀 Option 1: Test NOW with MailHog (5 minutes)

This lets you test the email verification system immediately without any Gmail setup!

### Step 1: Download MailHog
- **Windows:** https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_windows_amd64.exe
- Just download and run the `.exe` file

### Step 2: Run MailHog
1. Double-click `MailHog_windows_amd64.exe`
2. A command window will open (keep it running!)
3. Open browser: http://localhost:8025
4. You should see MailHog web interface

### Step 3: Your .env is Already Configured!
Your `.env` already has:
```
MAILER_DSN=smtp://localhost:1025
```
This is correct for MailHog!

### Step 4: Test It
1. **Clear cache:**
   ```bash
   php bin/console cache:clear
   ```

2. **Register a new test user:**
   - Go to: http://localhost:8000/register
   - Use ANY email (doesn't need to be real): `test@example.com`
   - Fill the form and submit

3. **Check MailHog:**
   - Open: http://localhost:8025
   - You'll see the verification email appear!
   - Click the email to read it
   - Click the verification link inside

4. **Success!** ✅

---

## 📧 Option 2: Setup Gmail (15 minutes)

Follow the steps in `GMAIL_SETUP_GUIDE.md`:

1. Enable 2-Step Verification: https://myaccount.google.com/security
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Update `.env` with your credentials
4. Clear cache and test

---

## 🎯 My Recommendation

**For NOW:** Use MailHog (Option 1) to test and see how everything works

**For LATER:** Set up Gmail (Option 2) when you want real emails sent

---

## 📸 MailHog Screenshot

When you open http://localhost:8025, you'll see:
- List of all emails sent by your app
- Click any email to view it
- Beautiful HTML preview of your verification emails
- No emails actually sent to real addresses (safe for testing!)

---

## ⚡ Super Quick Start

**Just want to test RIGHT NOW?**

1. Download: https://github.com/mailhog/MailHog/releases/download/v1.0.1/MailHog_windows_amd64.exe
2. Run it (double-click)
3. Open browser: http://localhost:8025
4. Register new user in your app
5. Watch email appear in MailHog!

**That's it!** Your `.env` is already configured for this.

---

## 🔄 Switch Between MailHog and Gmail

**Using MailHog (testing):**
```env
MAILER_DSN=smtp://localhost:1025
```

**Using Gmail (production):**
```env
MAILER_DSN=smtp://your-email@gmail.com:your-app-password@smtp.gmail.com:587
```

Just change this line in `.env` and clear cache!

---

## 💡 Which Should You Use Now?

| Feature | MailHog | Gmail |
|---------|---------|-------|
| Setup Time | 2 minutes | 15 minutes |
| Real emails | ❌ No | ✅ Yes |
| Safe for testing | ✅ Yes | ⚠️ Be careful |
| Email limit | ∞ Unlimited | 500/day |
| Internet required | ❌ No | ✅ Yes |
| **Recommendation** | **START HERE** | Use for production |

---

## 🎬 Next Steps

1. **Try MailHog now** (while you read about Gmail setup)
2. **See your beautiful verification emails** in MailHog
3. **When ready, switch to Gmail** for real emails

Both options work perfectly with your app! 🚀
