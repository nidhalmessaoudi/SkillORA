# Gmail Email Setup - Step by Step Guide

## 🎯 Goal
Configure your SkillHarbor application to send real verification emails using Gmail.

---

## 📋 Prerequisites
- A Gmail account (or Google Workspace account)
- 10 minutes of your time

---

## 🔐 Step 1: Enable 2-Step Verification on Gmail

1. **Go to your Google Account:**
   - Visit: https://myaccount.google.com/security
   - Or click your profile picture → "Manage your Google Account" → "Security"

2. **Enable 2-Step Verification:**
   - Scroll down to "How you sign in to Google"
   - Click on "2-Step Verification"
   - Click "GET STARTED"
   - Follow the prompts (you'll need your phone)
   - Complete the setup

⚠️ **Important:** You MUST enable 2-Step Verification before you can create App Passwords!

---

## 🔑 Step 2: Create an App Password

1. **After 2-Step Verification is enabled, go to:**
   - https://myaccount.google.com/apppasswords
   - Or: Google Account → Security → 2-Step Verification → App passwords (at the bottom)

2. **Create a new App Password:**
   - In "Select app" dropdown → Choose "Mail"
   - In "Select device" dropdown → Choose "Windows Computer" (or "Other")
   - If you chose "Other", give it a name like "SkillHarbor"
   - Click "GENERATE"

3. **Copy the App Password:**
   - Google will show you a 16-character password like: `abcd efgh ijkl mnop`
   - **COPY THIS PASSWORD NOW** - you won't see it again!
   - Example: `qrst uvwx yzab cdef`

---

## 📝 Step 3: Update Your .env File

**IMPORTANT:** I'll help you update the `.env` file, but first, tell me:

### Option A: Use Your Personal Gmail

If you want to use your personal Gmail (e.g., yourname@gmail.com):

1. What is your Gmail address? (e.g., yourname@gmail.com)
2. What is the App Password you generated? (16 characters)

### Option B: Create a Dedicated Email

**Recommended for production!** Create a new Gmail account specifically for your application:

1. Create new Gmail: `skillharbor.app@gmail.com` (or similar)
2. Enable 2-Step Verification on this account
3. Generate App Password for this account
4. Use this for sending emails

---

## 🔧 Step 4: Configuration Format

Once you have your Gmail address and App Password, your `.env` should look like this:

```env
###> symfony/mailer ###
MAILER_DSN=smtp://YOUR_EMAIL@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587
MAILER_FROM_ADDRESS=noreply@skillharbor.com
MAILER_FROM_NAME=SkillHarbor
###< symfony/mailer ###
```

**Example (with fake credentials):**
```env
MAILER_DSN=smtp://skillharbor.app@gmail.com:abcdefghijklmnop@smtp.gmail.com:587
MAILER_FROM_ADDRESS=noreply@skillharbor.com
MAILER_FROM_NAME=SkillHarbor
```

**IMPORTANT NOTES:**
- Remove all spaces from the App Password (e.g., `abcd efgh ijkl mnop` becomes `abcdefghijklmnop`)
- Don't use your regular Gmail password - ONLY use the App Password!
- Don't share this file - the App Password is sensitive!

---

## ⚙️ Step 5: Apply Changes

After updating `.env`:

```bash
# Clear cache
php bin/console cache:clear

# Test by registering a new user
# The verification email should arrive in a real email inbox!
```

---

## 📧 Gmail Sending Limits

Be aware of Gmail's sending limits:

- **Free Gmail:** 500 emails per day
- **Google Workspace:** 2,000 emails per day

For a small application, this is more than enough!

---

## ✅ Step 6: Test It

1. Register a new test user with a real email address
2. Check the email inbox (might take 1-2 minutes)
3. Look in Spam folder if you don't see it
4. Click the verification link
5. Success! ✨

---

## 🐛 Troubleshooting

### "Authentication failed"
- Make sure you're using the **App Password**, not your regular password
- App Password should be 16 characters with no spaces
- 2-Step Verification must be enabled

### "Connection refused"
- Check your internet connection
- Gmail SMTP is: `smtp.gmail.com:587`
- Make sure port 587 is not blocked by firewall

### "Emails going to Spam"
- This is normal for new senders
- Recipients should mark your email as "Not Spam"
- For production, consider using a professional email service (SendGrid, etc.)

### "Less secure app access" error
- You should NOT use "Less secure app access"
- Use App Passwords instead (which requires 2-Step Verification)

---

## 🔒 Security Best Practices

1. ✅ **Never commit `.env` to Git**
   - Add `.env` to `.gitignore`
   - Use `.env.example` for template

2. ✅ **Use dedicated email for app**
   - Don't use your personal Gmail
   - Create `yourapp@gmail.com` or similar

3. ✅ **Rotate App Passwords**
   - If compromised, revoke in Google Account settings
   - Generate a new one

4. ✅ **For production, use professional service**
   - SendGrid: $19.95/month for 100k emails
   - Amazon SES: $0.10 per 1,000 emails
   - Mailgun, Postmark, etc.

---

## 📞 Need Help?

If you're stuck on any step, I can help! Just tell me:
1. Which step you're on
2. What error you're seeing (if any)
3. Whether 2-Step Verification is enabled

---

## 🎯 Quick Summary

1. Enable 2-Step Verification on Gmail ✓
2. Generate App Password ✓
3. Update `.env` with: `smtp://YOUR_EMAIL:APP_PASSWORD@smtp.gmail.com:587` ✓
4. Clear cache ✓
5. Test registration ✓

---

**Ready to update your `.env` file?** 

Let me know your Gmail address and App Password, and I'll update it for you!
