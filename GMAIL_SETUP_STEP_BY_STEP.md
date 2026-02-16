# Gmail Setup - Complete Step-by-Step Guide

Follow these steps EXACTLY and you'll have Gmail working in 10-15 minutes!

---

## 🎯 What You Need
- A Gmail account (your personal or create a new one)
- 10-15 minutes
- Your phone (for verification)

---

## 📱 STEP 1: Enable 2-Step Verification

### 1.1 - Open Google Account Security
1. Open your browser
2. Go to: **https://myaccount.google.com/security**
3. Sign in with your Gmail account

### 1.2 - Find 2-Step Verification
1. Scroll down to section: **"How you sign in to Google"**
2. Look for **"2-Step Verification"**

### 1.3 - Check Current Status

**If it says "OFF" or "Get Started":**
- Click on "2-Step Verification"
- Click the blue **"GET STARTED"** button
- Proceed to Step 1.4

**If it says "ON":**
- ✅ Great! 2-Step Verification is already enabled
- Skip to **STEP 2** (Generate App Password)

### 1.4 - Enable 2-Step Verification (if OFF)

**You'll be asked to:**

1. **Re-enter your password**
   - Type your Gmail password
   - Click "Next"

2. **Add your phone number**
   - Enter your phone number
   - Choose: "Text message (SMS)" or "Phone call"
   - Click "Next"

3. **Verify your phone**
   - You'll receive a code via SMS/call
   - Enter the 6-digit code
   - Click "Next"

4. **Turn it on**
   - Click "TURN ON" button
   - ✅ Done! 2-Step Verification is now enabled

---

## 🔑 STEP 2: Generate App Password

### 2.1 - Open App Passwords Page
1. Go to: **https://myaccount.google.com/apppasswords**
2. You may need to sign in again

**⚠️ IMPORTANT:** If you get "App passwords not available" error:
- Make sure you completed STEP 1 (2-Step Verification must be ON)
- Wait 5 minutes and try again
- Sign out and sign in again

### 2.2 - Create New App Password

**You'll see a page titled "App passwords"**

1. **Select app:** Click the dropdown
   - Choose **"Mail"**

2. **Select device:** Click the dropdown
   - Choose **"Windows Computer"**
   - OR choose **"Other (Custom name)"** and type: `SkillHarbor`

3. **Click "GENERATE"** button

### 2.3 - Copy Your App Password

**⚠️ VERY IMPORTANT!**

Google will show a yellow box with a **16-character password** like:

```
abcd efgh ijkl mnop
```

**DO THIS NOW:**
1. **SELECT ALL** the password (click and drag)
2. **COPY IT** (Ctrl+C or right-click → Copy)
3. **PASTE IT** somewhere safe:
   - In Notepad
   - In a text file
   - On a piece of paper
   
**You will NOT see this password again!**

4. Click "DONE"

---

## 📝 STEP 3: Format Your App Password

Your app password looks like: `abcd efgh ijkl mnop` (with spaces)

**You need to remove ALL spaces:**
- ❌ Wrong: `abcd efgh ijkl mnop`
- ✅ Correct: `abcdefghijklmnop`

**Example:**
- Google shows: `qrst uvwx yzab cdef`
- You use: `qrstuvwxyzabcdef`

**Write down your 16-character password (no spaces):**
```
_________________________________
(Write your app password here)
```

---

## 💻 STEP 4: Update Your .env File

Now we'll update your SkillHarbor configuration!

### 4.1 - Information You Need

Fill in these details:

**Your Gmail address:**
```
Example: skillharbor.app@gmail.com
Your email: _______________________________
```

**Your App Password (no spaces):**
```
Example: abcdefghijklmnop
Your password: _______________________________
```

### 4.2 - The Configuration Line

Your `MAILER_DSN` line should look like this:

```env
MAILER_DSN=smtp://YOUR_EMAIL@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587
```

**Real Example (with fake credentials):**
```env
MAILER_DSN=smtp://skillharbor.app@gmail.com:abcdefghijklmnop@smtp.gmail.com:587
```

**Your Actual Line (fill in your details):**
```env
MAILER_DSN=smtp://_______________@gmail.com:________________@smtp.gmail.com:587
                  ↑ Your email              ↑ Your app password (16 chars, no spaces)
```

---

## 🔧 STEP 5: Tell Me Your Details

**I need two things from you:**

1. **Your Gmail address:** (e.g., yourname@gmail.com)
   
2. **Your App Password:** (16 characters, no spaces)

**Once you give me these, I'll update your `.env` file for you!**

**⚠️ Security Note:** 
- Your App Password is like a password - keep it private!
- It's safe to share with me to configure your app
- Never commit it to public Git repositories

---

## 🎬 STEP 6: After Configuration

Once I update your `.env`, you'll need to:

```bash
# Clear cache
php bin/console cache:clear

# Test registration
# Go to: http://localhost:8000/register
# Register with a REAL email address
# Check your inbox!
```

---

## ❓ Troubleshooting

### "App passwords not available"
**Solution:**
1. Make sure 2-Step Verification is ON
2. Wait 5-10 minutes
3. Sign out of Google and sign in again
4. Try https://myaccount.google.com/apppasswords again

### "I can't find 2-Step Verification"
**Solution:**
1. Go to: https://myaccount.google.com/security
2. Look under "How you sign in to Google"
3. It should be the second or third option

### "I lost my App Password"
**Solution:**
1. Go back to: https://myaccount.google.com/apppasswords
2. Delete the old one
3. Generate a new one
4. Copy it and tell me

### "Google is asking for my phone"
**Solution:**
- This is normal for 2-Step Verification
- You need to provide your phone number
- You'll receive a code to verify
- This only happens once

---

## 📋 Quick Checklist

Before you tell me your details, make sure:

- [ ] 2-Step Verification is **ON**
- [ ] App Password is **generated**
- [ ] App Password is **copied** (16 characters)
- [ ] App Password has **NO SPACES**
- [ ] You know your **Gmail address**

---

## 🚀 Ready?

**Tell me:**
1. Your Gmail address
2. Your App Password (16 characters, no spaces)

**And I'll update your configuration immediately!**

---

## 🔐 Alternative: Create Dedicated Email (Recommended)

Instead of using your personal Gmail, you could:

1. **Create new Gmail account:**
   - Go to: https://accounts.google.com/signup
   - Create: `skillharbor.notifications@gmail.com` (or similar)
   - Use this for your app

2. **Benefits:**
   - Keeps personal email separate
   - More professional
   - Can share with team without sharing personal email

**Your choice!** Either way works fine.

---

**Ready to continue? Where are you in the process?**
- [ ] Step 1 - Enabling 2-Step Verification
- [ ] Step 2 - Generating App Password  
- [ ] Step 3 - Ready to share details
- [ ] Need help with a specific step
