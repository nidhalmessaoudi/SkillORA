# Email Verification System - Complete Setup Guide

## Overview

Your SkillHarbor application now has a complete email verification system! When users sign up, they receive a beautiful verification email and must verify their email address before they can log in.

---

## ✅ What Was Implemented

### 1. **Email Verification Workflow**
- ✅ When users register, they automatically receive a verification email
- ✅ Users cannot log in until they verify their email address
- ✅ Verification links expire after 24 hours for security
- ✅ Beautiful, branded email template that matches your site design
- ✅ Existing users in your database were automatically marked as verified

### 2. **Database Changes**
- ✅ Added `verification_token` column to store unique verification tokens
- ✅ Added `verification_token_expires_at` column to track expiration
- ✅ All 6 existing users were automatically set as verified

### 3. **Security Features**
- ✅ Users with unverified emails are blocked from logging in
- ✅ Clear error message shown when unverified users try to log in
- ✅ Tokens expire after 24 hours
- ✅ Secure random token generation (64 characters)

---

## 📧 Email Configuration

### Current Setup (Development/Testing)

Your `.env` file is currently configured for **development mode**:

```env
MAILER_DSN=smtp://localhost:1025
MAILER_FROM_ADDRESS=noreply@skillharbor.com
MAILER_FROM_NAME=SkillHarbor
```

**What this means:**
- Emails are NOT actually sent to users
- Emails are captured locally for testing
- You need to run a mail testing tool to see the emails

### 🔧 Testing Emails in Development

To see the verification emails during development, you have several options:

#### **Option 1: MailHog (Recommended)**

1. Download MailHog: https://github.com/mailhog/MailHog/releases
2. Run MailHog: `MailHog.exe` (Windows) or `./MailHog` (Mac/Linux)
3. Open http://localhost:8025 in your browser
4. Register a new user in your app
5. Check MailHog to see the verification email

#### **Option 2: Mailtrap (Online Service)**

1. Sign up at https://mailtrap.io (free)
2. Get your SMTP credentials from Mailtrap
3. Update your `.env`:
   ```env
   MAILER_DSN=smtp://YOUR_USERNAME:YOUR_PASSWORD@smtp.mailtrap.io:2525
   ```
4. Emails will appear in your Mailtrap inbox

#### **Option 3: Symfony Mailer Logger**

Emails are already logged in: `var/log/dev.log`
Search for "Email:" to see email content.

---

## 🚀 Production Setup (Real Email Sending)

### Using Gmail (Easy Setup)

1. **Enable 2-Step Verification** on your Gmail account
2. **Create an App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a password for "Mail"
3. **Update your `.env`**:
   ```env
   MAILER_DSN=smtp://your-email@gmail.com:YOUR_APP_PASSWORD@smtp.gmail.com:587
   MAILER_FROM_ADDRESS=noreply@skillharbor.com
   MAILER_FROM_NAME=SkillHarbor
   ```

### Using SendGrid (Professional)

1. Sign up at https://sendgrid.com
2. Create an API key
3. Update your `.env`:
   ```env
   MAILER_DSN=sendgrid://YOUR_API_KEY@default
   MAILER_FROM_ADDRESS=noreply@skillharbor.com
   MAILER_FROM_NAME=SkillHarbor
   ```

### Using Mailgun

1. Sign up at https://mailgun.com
2. Get your API credentials
3. Update your `.env`:
   ```env
   MAILER_DSN=mailgun://KEY:DOMAIN@default?region=us
   MAILER_FROM_ADDRESS=noreply@skillharbor.com
   MAILER_FROM_NAME=SkillHarbor
   ```

### Using Amazon SES

1. Sign up for AWS SES
2. Verify your domain/email
3. Update your `.env`:
   ```env
   MAILER_DSN=ses+smtp://ACCESS_KEY:SECRET_KEY@default?region=us-east-1
   MAILER_FROM_ADDRESS=noreply@skillharbor.com
   MAILER_FROM_NAME=SkillHarbor
   ```

---

## 📁 Files Created/Modified

### New Files Created:

1. **`templates/emails/verify-email.html.twig`**
   - Beautiful HTML email template
   - Responsive design for mobile/desktop
   - Matches your SkillHarbor branding
   - Includes security warnings and features list

2. **`src/Security/UserChecker.php`** (modified)
   - Blocks unverified users from logging in
   - Shows clear error message

3. **`add_verification_columns.php`**
   - Database migration script
   - Sets existing users as verified
   - Already executed successfully

4. **`update_email_verification.sql`**
   - SQL backup/reference file
   - Can be used to manually update database if needed

### Modified Files:

1. **`src/Entity/User.php`**
   - Added `verification_token` field
   - Added `verification_token_expires_at` field
   - Added `isVerificationTokenValid()` method

2. **`src/Controller/AuthController.php`**
   - Added `sendVerificationEmail()` method
   - Modified `register()` to send verification email
   - Updated `verifyEmail()` route to handle token verification
   - Added `resendVerification()` route for resending emails

3. **`.env`**
   - Added mailer configuration

---

## 🎨 Email Design

The verification email includes:

- ✅ SkillHarbor logo and branding
- ✅ Personalized greeting with user's first name
- ✅ Large "Verify Email Address" button
- ✅ Expiration notice (24 hours)
- ✅ Features list (what they'll get after verifying)
- ✅ Alternative link if button doesn't work
- ✅ Security warning for users who didn't sign up
- ✅ Professional footer with social links
- ✅ Responsive design for mobile devices
- ✅ Beautiful gradient colors matching your site

---

## 🔐 How It Works

### 1. User Registration Flow

```
User fills registration form
        ↓
Account created (is_verified = false)
        ↓
Verification email sent with unique token
        ↓
User receives email
        ↓
User clicks verification link
        ↓
Token validated & account marked as verified
        ↓
User can now log in
```

### 2. Login Attempt (Unverified User)

```
User tries to log in
        ↓
UserChecker runs before authentication
        ↓
Checks if email is verified
        ↓
If NOT verified → Shows error:
"Please verify your email address before signing in. 
Check your inbox for the verification link."
        ↓
User checks email and verifies
```

### 3. Token Security

- Tokens are 64 characters long (cryptographically secure)
- Tokens expire after 24 hours
- Token is deleted after successful verification
- Invalid/expired tokens show clear error messages

---

## 🧪 Testing the System

### Test Registration (New User):

1. **Start MailHog or Mailtrap** (see options above)

2. **Register a new user:**
   - Go to: http://localhost:8000/register
   - Fill in the form with a test email
   - Submit

3. **Check for email:**
   - MailHog: http://localhost:8025
   - Mailtrap: Check your inbox
   - Logs: `var/log/dev.log`

4. **Verify the email:**
   - Click the verification link in the email
   - Should redirect to login with success message

5. **Try to log in:**
   - Before verification: Should show error
   - After verification: Should log in successfully

### Test Existing Users:

All existing users (alarezgui98@gmail.com, admin@skillharbor.com, etc.) are already verified and can log in normally.

---

## 🐛 Troubleshooting

### "Emails are not being sent"

**Check:**
1. Is MailHog/Mailtrap running?
2. Is `MAILER_DSN` correctly set in `.env`?
3. Check logs: `var/log/dev.log`
4. Clear cache: `php bin/console cache:clear`

### "Connection refused on port 1025"

**Solution:**
- MailHog is not running
- Start MailHog or switch to Mailtrap

### "Users can't log in after registering"

**This is correct!** They need to verify their email first.

**Solution for testing:**
- Use MailHog to see the verification email
- Or manually set user as verified in database:
  ```sql
  UPDATE users SET is_verified = 1 WHERE email = 'test@example.com';
  ```

### "Verification link doesn't work"

**Check:**
1. Token hasn't expired (24 hours)
2. URL is complete (includes /verify-email/{token})
3. Check error messages in logs

---

## 📊 Database Statistics

Current status of your users:

| Metric | Count |
|--------|-------|
| Total Users | 6 |
| Verified Users | 6 |
| Unverified Users | 0 |

All existing users were automatically marked as verified, so they can continue logging in without any issues.

---

## 🔄 Resending Verification Emails

Users can request a new verification email if:
- The original email expired
- They didn't receive the email
- They deleted the email

**How to implement:**

The route is already created: `/resend-verification`

To add a link on the login page, add this to `templates/pages/auth/login.html.twig`:

```html
<p class="text-sm text-center text-navy-500 mt-4">
    Didn't receive the verification email?
    <button type="button" onclick="resendVerification()" class="text-harbor-600 hover:underline">
        Resend verification email
    </button>
</p>

<script>
function resendVerification() {
    const email = prompt('Enter your email address:');
    if (email) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ path('auth_resend_verification') }}';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'email';
        input.value = email;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
```

---

## 🎯 Next Steps

### For Development:
1. ✅ Install MailHog to test emails
2. ✅ Register a test user
3. ✅ Verify the email through MailHog
4. ✅ Confirm login works after verification

### For Production:
1. 🔲 Choose an email service (Gmail, SendGrid, etc.)
2. 🔲 Update `MAILER_DSN` in `.env` with production credentials
3. 🔲 Test with real email addresses
4. 🔲 Consider adding a "Resend Verification" link on login page
5. 🔲 Monitor email delivery rates

---

## 📞 Support & Resources

- **Symfony Mailer Docs:** https://symfony.com/doc/current/mailer.html
- **Email Testing Tools:**
  - MailHog: https://github.com/mailhog/MailHog
  - Mailtrap: https://mailtrap.io
  - MailCatcher: https://mailcatcher.me

---

## ✨ Summary

Your email verification system is **fully functional and ready to use**! 

**What happens now:**
- ✅ New users register → Receive verification email
- ✅ Users click link → Email verified
- ✅ Users can log in → Access granted
- ✅ Existing users → Already verified, no changes needed

**To start using it:**
1. Set up MailHog for testing (or use Gmail for production)
2. Update `MAILER_DSN` in `.env` file
3. Clear cache: `php bin/console cache:clear`
4. Test with a new user registration

**Email design preview:**
The email is beautiful, professional, and matches your SkillHarbor branding with blue gradients, clear call-to-action buttons, and responsive design.

---

Good luck with your project! 🚀
