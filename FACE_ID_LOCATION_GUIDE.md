# 📍 Face ID Location Guide - Where to Find It

## Registration Page (`/auth/register`)

```
┌─────────────────────────────────────────────────────────┐
│  SkillHarbor Registration                               │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  First Name:  [________________]                        │
│                                                         │
│  Last Name:   [________________]                        │
│                                                         │
│  Email:       [________________]                        │
│                                                         │
│  Password:    [________________]                        │
│  (Password strength meter)                              │
│                                                         │
│  Confirm:     [________________]                        │
│                                                         │
│  Role:        ( ) Student  ( ) Professor                │
│                                                         │
│  ╔═══════════════════════════════════════════════════╗ │
│  ║  👤 Enable Face ID (Optional)         [⚪→🔵]    ║ │
│  ║  Login faster and more securely                   ║ │
│  ║  ─────────────────────────────────────────────    ║ │
│  ║                                                   ║ │
│  ║  [📹 Start Camera]                                ║ │
│  ║                                                   ║ │
│  ║  ┌───────────────────────┐                        ║ │
│  ║  │  📷 Video Preview     │                        ║ │
│  ║  │  (Your face here)     │                        ║ │
│  ║  │  [Face frame corners] │                        ║ │
│  ║  └───────────────────────┘                        ║ │
│  ║                                                   ║ │
│  ║  Progress: ● ● ●                                  ║ │
│  ║                                                   ║ │
│  ║  [📸 Capture Face]                                ║ │
│  ║                                                   ║ │
│  ║  ✅ Face ID captured successfully!                ║ │
│  ╚═══════════════════════════════════════════════════╝ │
│                                                         │
│  ☐ I agree to Terms and Conditions                     │
│                                                         │
│  [Create Account]                                       │
│                                                         │
│  Already have an account? Sign in                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Location:** Between "Role" selection and "Terms" checkbox

**Visual Cues:**
- 🎨 Blue gradient background (harbor-blue → sky-blue)
- 🔲 Dashed border (2px)
- 👤 Face scan icon (left side)
- 🔘 Toggle switch (right side)
- 📹 Camera controls and preview

---

## Login Page (`/auth/login`)

```
┌─────────────────────────────────────────────────────────┐
│  Welcome Back to SkillHarbor                            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Email:       [________________]                        │
│                                                         │
│  Password:    [________________]                        │
│                                                         │
│  ☐ Remember me                      Forgot password?    │
│                                                         │
│  [Sign In]                                              │
│                                                         │
│  ──────────────── Or use ────────────────               │
│                                                         │
│  ╔═══════════════════════════════════════════════════╗ │
│  ║  👤  Sign in with Face ID                    →   ║ │
│  ║      Fast, secure, passwordless login             ║ │
│  ╚═══════════════════════════════════════════════════╝ │
│                                                         │
│  Don't have an account? Create one                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Location:** After "Sign In" button, before "Create account" link

**Visual Cues:**
- 🎨 Blue gradient background
- 👤 Face scan icon (left side)
- ➡️ Arrow icon (right side)
- 📝 "Fast, secure, passwordless login" subtitle
- 🖱️ Clickable card (hover effect)

---

## Face ID Login Page (`/auth/face-login`)

```
┌─────────────────────────────────────────────────────────┐
│  SkillHarbor                                     🌙     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│                   👤                                    │
│              Face ID Login                              │
│      Position your face in the frame to sign in         │
│                                                         │
│          ⚪ Initializing camera...                      │
│                                                         │
│  ┌───────────────────────────────────────────────────┐  │
│  │                                                   │  │
│  │        📷 Camera Preview (Live Video)            │  │
│  │                                                   │  │
│  │          [Animated scanning overlay]             │  │
│  │          [Face frame with corners]               │  │
│  │                                                   │  │
│  │          Position your face in frame             │  │
│  │                                                   │  │
│  └───────────────────────────────────────────────────┘  │
│                                                         │
│              Confidence: ████████░░ 80%                 │
│                                                         │
│  [🎯 Start Face Recognition]                            │
│                                                         │
│  ✅ Face Recognized! (92% match)                        │
│     Redirecting to dashboard...                         │
│                                                         │
│  OR                                                     │
│                                                         │
│  ❌ Face not recognized (65% match)                     │
│     [Try Again]  [Back to Login]                        │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Features:**
- 📹 Auto camera initialization
- 🎯 Start Recognition button
- 📊 Real-time confidence meter
- ✅ Success overlay (green)
- ❌ Error overlay (red)
- ↻ Retry option

---

## File Locations in Code

### Registration Template:
```
templates/pages/auth/register.html.twig
Lines 194-287: Face ID Section
  - Line 194-212: Header with toggle
  - Line 215-283: Capture interface (hidden until toggle)
  - Line 286: Hidden input for face_data
```

### Login Template:
```
templates/pages/auth/login.html.twig
Lines 153-177: Face ID Login Option
  - Link to: path('auth_face_login')
```

### Face Login Template:
```
templates/pages/auth/face-login.html.twig
Full page dedicated to face scanning
  - Camera preview
  - Scanning animation
  - Recognition logic
```

---

## How to Navigate

### To See Face ID in Registration:
1. Go to: `http://localhost:8000/auth/register`
2. **Scroll down** past password fields
3. Look for **blue gradient box**
4. Title: **"Enable Face ID (Optional)"**
5. **Toggle switch** on the right

### To See Face ID in Login:
1. Go to: `http://localhost:8000/auth/login`
2. **Scroll down** past the Sign In button
3. Look for **"Or use"** divider
4. Below it: **"Sign in with Face ID"** card
5. **Click the card** to go to face scanning page

### Direct Access to Face ID Login:
```
http://localhost:8000/auth/face-login
```
(This page is directly accessible)

---

## Visual Search Tips

### In Registration - Look For:
- ✨ **Blue gradient box** (most distinctive)
- 🔲 **Dashed border** (2px, blue)
- 👤 **Face scan icon** (circular, white background)
- 🔘 **Toggle switch** (iOS-style, right side)
- 📝 Text: **"Enable Face ID (Optional)"**

### In Login - Look For:
- 📏 **Divider line** with "Or use" text
- 🎨 **Blue gradient card** (clickable)
- 👤 **Face scan icon**
- ➡️ **Arrow icon** on right
- 📝 Text: **"Sign in with Face ID"**

---

## Screenshot Descriptions

### Registration Face ID Section:
```
┌────────────────────────────────────────────────┐
│ 👤 Enable Face ID (Optional)         [Toggle] │
│ Login faster and more securely                 │
│ ··················································· │
│                                                │
│ When toggle ON:                                │
│ ├─ [Start Camera] button                       │
│ ├─ Video preview area                          │
│ ├─ Progress dots: ● ● ●                        │
│ ├─ [Capture Face] button                       │
│ └─ Success message (after capture)             │
│                                                │
└────────────────────────────────────────────────┘
```

### Login Face ID Button:
```
┌────────────────────────────────────────────────┐
│ ──────────────── Or use ──────────────────     │
│                                                │
│ ┌────────────────────────────────────────────┐ │
│ │ 👤  Sign in with Face ID            →     │ │
│ │     Fast, secure, passwordless login      │ │
│ └────────────────────────────────────────────┘ │
└────────────────────────────────────────────────┘
```

---

## Browser Testing

### Chrome (Recommended):
1. Open DevTools (F12)
2. Go to Console tab
3. Should see no errors
4. Camera permission prompt should appear

### Firefox:
1. Works same as Chrome
2. Camera icon appears in address bar
3. Click to manage permissions

### Edge:
1. Same as Chrome (Chromium-based)
2. Camera permission in site settings

---

## Mobile Testing

Face ID works on mobile browsers too!

### On Phone:
1. Use front-facing camera
2. Same registration flow
3. Same login flow
4. Touch-friendly buttons

---

## Quick Verification

**Is Face ID working?**

✅ Registration page:
- [ ] Blue gradient box visible
- [ ] Toggle switch works
- [ ] Camera starts when enabled
- [ ] Can capture 3 photos

✅ Login page:
- [ ] "Sign in with Face ID" button visible
- [ ] Clicking goes to /auth/face-login
- [ ] Camera starts automatically
- [ ] Face recognition works

---

**Everything is visible and accessible!** 🎉

**Start Server:**
```bash
cd "C:\Users\alare\OneDrive\Desktop\skillharbor (5)\skillharbor (3)\skillharbor"
php -S localhost:8000 -t public
```

**Then visit:**
- Registration: `http://localhost:8000/auth/register`
- Login: `http://localhost:8000/auth/login`
- Face Login: `http://localhost:8000/auth/face-login`
