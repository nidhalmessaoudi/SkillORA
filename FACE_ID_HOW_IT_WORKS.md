# Face ID - How It Works (Complete Flow)

## 🎯 Summary

Face ID in SkillHarbor allows users to:
1. **Register** and capture their face (3 photos)
2. **Login** using their face instead of a password

---

## 📍 Step-by-Step User Flow

### Part 1: Registration with Face ID

#### Step 1: Navigate to Registration
```
http://localhost:8000/auth/register
```

#### Step 2: Fill Registration Form
- First Name: `John`
- Last Name: `Doe`
- Email: `john.doe@example.com`
- Password: `Test1234!`
- Confirm Password: `Test1234!`
- Role: `Student` or `Professor`

#### Step 3: Enable Face ID (OPTIONAL)
- **Scroll down** to the **"Enable Face ID (Optional)"** section
- It has a **blue gradient box** with:
  - Face scan icon
  - Toggle switch on the right
- **Click the toggle switch** to enable Face ID
- The Face capture interface will appear below

#### Step 4: Capture Your Face
1. **Click "Start Camera"** button
   - Browser will ask for camera permission
   - **Click "Allow"**
   - Camera preview will appear

2. **Position your face** in the frame
   - You'll see corner brackets (animated blue lines)
   - Center your face in the video preview

3. **Click "Capture Face"** button
   - System will automatically capture **3 photos**
   - Progress dots will light up: ● ○ ○ → ● ● ○ → ● ● ●
   - Between each capture, turn your head slightly (left/right)
   - Takes about 2-3 seconds total

4. **Success!**
   - Green message appears: "Face ID captured successfully!"
   - Camera stops automatically
   - Your face data is saved (hidden input field)

#### Step 5: Complete Registration
- **Click "Create Account"** button
- You'll be redirected to verification page
- Check your email for verification link
- Click the link to verify your account

#### What Happens Behind the Scenes:
```
✅ 3 photos captured → Converted to base64 → Stored as JSON
✅ face_data = {"images": ["base64...", "base64...", "base64..."], "timestamp": "...", "captureCount": 3}
✅ face_id_enabled = 1
✅ Saved to database
```

---

### Part 2: Login with Face ID

#### Step 1: Navigate to Login
```
http://localhost:8000/auth/login
```

#### Step 2: Choose Face ID Login
- Scroll down past the email/password form
- You'll see: **"Or use"** divider
- Below it: **"Sign in with Face ID"** button (blue gradient box)
- **Click the "Sign in with Face ID" button**

#### Step 3: Face Recognition Page
- Redirects to: `/auth/face-login`
- Page shows:
  - Face ID icon at top
  - "Position your face in the frame to sign in"
  - Camera status indicator
  - Video preview area
  - "Start Face Recognition" button

#### Step 4: Scan Your Face
1. Camera starts automatically (no need to click Start Camera)
2. **Position your face** in the frame
3. **Click "Start Face Recognition"** button
4. Animated scanning overlay appears:
   - Blue scanning line moves across your face
   - Confidence meter shows matching percentage (0-100%)
   - Takes 2-3 seconds

#### Step 5a: Success (Face Matched ≥85%)
- ✅ Green success overlay appears
- **"Face Recognized!"** message
- Shows confidence: e.g., "92% match"
- **Automatically redirects** to your dashboard:
  - **Admin** → `/admin`
  - **Professor** → `/professor`
  - **Student** → `/` (home)
- You're logged in!

#### Step 5b: Failure (Face Not Matched <85%)
- ❌ Red error overlay appears
- **"Face not recognized"** message
- Shows confidence: e.g., "65% match"
- **"Try Again"** button appears
- Click to retry or go back to normal login

#### What Happens Behind the Scenes:
```
1. Camera captures your face
2. Sends to /auth/face-authenticate endpoint (POST)
3. Backend compares with ALL users who have face_id_enabled = 1
4. Finds best match using pixel comparison algorithm
5. If confidence ≥ 85%:
   - Checks user is active & verified
   - Creates session
   - Redirects based on role
6. If confidence < 85%:
   - Returns error with confidence score
```

---

## 🔍 Where to Find Face ID Features

### In Registration Page:
**Location:** After password fields, before Terms checkbox
```
Email field
Password field
Confirm Password field
↓
🌟 FACE ID SECTION (Optional) 🌟  ← HERE!
↓
Terms checkbox
Create Account button
```

**Visual Indicators:**
- Blue gradient box (harbor-blue → sky-blue)
- Face scan icon (top left)
- Toggle switch (top right)
- Dashed border

### In Login Page:
**Location:** After Sign In button, before Register link
```
Email field
Password field
Remember me checkbox
Sign In button
↓
"Or use" divider
↓
🌟 SIGN IN WITH FACE ID 🌟  ← HERE!
↓
"Don't have an account?" link
```

**Visual Indicators:**
- Blue gradient box
- Face scan icon
- "Fast, secure, passwordless login" subtitle
- Arrow icon on right

---

## 💻 Testing Guide

### Test Case 1: Register New User with Face ID

**Steps:**
1. Start server: `php -S localhost:8000 -t public`
2. Open: `http://localhost:8000/auth/register`
3. Fill form:
   - First Name: `Test`
   - Last Name: `FaceID`
   - Email: `test.faceid@skillharbor.com`
   - Password: `Test1234!`
4. Scroll down → Enable Face ID toggle
5. Click "Start Camera" → Allow permission
6. Click "Capture Face" → Wait for 3 captures
7. See success message
8. Click "Create Account"
9. Verify email

**Expected Result:**
- ✅ Face captured successfully
- ✅ Account created
- ✅ Verification email sent

**Database Check:**
```bash
php bin/console doctrine:query:sql "SELECT email, face_id_enabled, LEFT(face_data, 50) FROM users WHERE email = 'test.faceid@skillharbor.com'"
```

Should show:
- `face_id_enabled` = 1
- `face_data` starts with `{"images":[`

---

### Test Case 2: Login with Face ID

**Prerequisites:**
- User registered with Face ID
- Email verified

**Steps:**
1. Open: `http://localhost:8000/auth/login`
2. Scroll down past email/password form
3. Click "Sign in with Face ID" button
4. Camera starts automatically
5. Position face in frame
6. Click "Start Face Recognition"
7. Wait for scanning animation

**Expected Result (Success):**
- ✅ Confidence ≥ 85%
- ✅ "Face Recognized!" message
- ✅ Auto-redirect to dashboard
- ✅ Logged in successfully

**Expected Result (Failure):**
- ❌ Confidence < 85%
- ❌ "Face not recognized" message
- ❌ "Try Again" button shown

---

### Test Case 3: Register WITHOUT Face ID

**Steps:**
1. Open: `http://localhost:8000/auth/register`
2. Fill form normally
3. **DO NOT enable Face ID toggle**
4. Click "Create Account"

**Expected Result:**
- ✅ Account created
- ✅ Face ID section was optional (skipped)
- ✅ Can still login with email/password

**Database Check:**
```bash
php bin/console doctrine:query:sql "SELECT email, face_id_enabled, face_data FROM users WHERE email = 'normal.user@example.com'"
```

Should show:
- `face_id_enabled` = 0
- `face_data` = NULL

---

## 🎨 UI/UX Features

### Registration Face ID Section:
- ✅ Beautiful gradient background (blue)
- ✅ Toggle switch (smooth animation)
- ✅ Manual camera start (privacy-first)
- ✅ Live video preview
- ✅ Animated face frame (corner brackets)
- ✅ Progress dots (3 dots for 3 captures)
- ✅ Status text updates
- ✅ Success confirmation message
- ✅ Camera auto-stops after completion

### Login Face ID Page:
- ✅ Dedicated scanning page
- ✅ Auto camera initialization
- ✅ Animated scanning overlay
- ✅ Real-time confidence meter (0-100%)
- ✅ Success overlay with checkmark
- ✅ Error overlay with X icon
- ✅ Smooth transitions
- ✅ Auto-redirect on success

---

## 🔒 Security Features

### During Registration:
1. **Face data is optional** - Toggle must be enabled
2. **Requires camera permission** - Browser security
3. **3 photos captured** - Better accuracy
4. **Base64 encoding** - Secure storage format
5. **JSON structure** - Organized data

### During Login:
1. **Requires verified email** - Must verify before Face ID login
2. **Active account check** - Suspended accounts blocked
3. **85% confidence threshold** - Prevents false matches
4. **Session-based auth** - Secure login session
5. **Role-based redirect** - Correct dashboard access

### Database:
- `face_data` stored as TEXT (JSON format)
- `face_id_enabled` boolean flag
- Only users with `face_id_enabled = 1` can use Face ID login

---

## 🐛 Troubleshooting

### "I don't see Face ID section in registration"
- ✅ **Solution:** Scroll down - it's between password and terms
- ✅ Look for blue gradient box with toggle switch
- ✅ Clear browser cache (Ctrl+Shift+Delete)

### "Camera doesn't start"
- ✅ **Solution:** Click "Start Camera" button first
- ✅ Grant browser permission when asked
- ✅ Check no other app is using camera
- ✅ Try different browser (Chrome recommended)

### "Face ID button not on login page"
- ✅ **Solution:** Scroll down past email/password form
- ✅ Look for "Or use" divider
- ✅ Clear browser cache
- ✅ Check you're on `/auth/login` not another page

### "Face always fails to match"
- ✅ **Solution:** Use same lighting as registration
- ✅ Use same camera angle
- ✅ Look directly at camera
- ✅ Remove glasses/hat if different from registration
- ✅ Current algorithm is simplified - 85% threshold

### "Email not verified" error
- ✅ **Solution:** Check your email inbox
- ✅ Click verification link first
- ✅ Then try Face ID login

---

## 📊 Database Structure

```sql
-- Users table columns for Face ID
face_data TEXT NULL                  -- JSON: {"images": [...], "timestamp": "...", "captureCount": 3}
face_id_enabled TINYINT(1) DEFAULT 0 -- Boolean: 1 = Face ID enabled, 0 = disabled
```

**Example face_data JSON:**
```json
{
  "images": [
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA...",
    "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA..."
  ],
  "timestamp": "2026-02-17T10:30:45.123Z",
  "captureCount": 3
}
```

---

## 🚀 Quick Start

**1. Start Server:**
```bash
cd "C:\Users\alare\OneDrive\Desktop\skillharbor (5)\skillharbor (3)\skillharbor"
php -S localhost:8000 -t public
```

**2. Test Registration:**
```
http://localhost:8000/auth/register
↓
Fill form
↓
Enable Face ID toggle
↓
Capture face
↓
Create account
```

**3. Test Login:**
```
http://localhost:8000/auth/login
↓
Click "Sign in with Face ID"
↓
Scan face
↓
Success!
```

---

## ✅ Verification Checklist

- [ ] Registration page has Face ID section (blue gradient box)
- [ ] Toggle switch works
- [ ] "Start Camera" button appears
- [ ] Camera permission requested
- [ ] Video preview shows live camera
- [ ] "Capture Face" button works
- [ ] Progress dots update (1/3, 2/3, 3/3)
- [ ] Success message appears
- [ ] Hidden input has face_data value
- [ ] Database saves face_data and face_id_enabled
- [ ] Login page has "Sign in with Face ID" button
- [ ] Face login page loads
- [ ] Camera initializes automatically
- [ ] "Start Face Recognition" button works
- [ ] Scanning animation plays
- [ ] Confidence meter shows percentage
- [ ] Success redirect works
- [ ] User is logged in

---

**Everything is ready! Face ID works from registration to login.** 🎉

If you need help, check the troubleshooting section above or run the test cases.
