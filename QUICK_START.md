# SkillHarbor - Quick Start Guide

## 🚀 Start Development Server

```bash
cd "C:\Users\alare\OneDrive\Desktop\skillharbor (5)\skillharbor (3)\skillharbor"
php -S localhost:8000 -t public
```

Then open: **http://localhost:8000**

---

## 🔑 Test Accounts

### Admin Account
- **Email:** `admin@skillharbor.com`
- **Password:** `Test1234!`
- **Dashboard:** `/admin`

### Professor Account
- **Email:** `alaeddine.reguit@esprit.tn`
- **Password:** `Test1234!`
- **Dashboard:** `/professor`

### Student Account
- **Email:** `nidhalmessaoudi@gmail.com`
- **Password:** `Test1234!`
- **Dashboard:** `/` (home)

---

## 🎯 Key Features Implemented

### ✅ Email Verification System
- Gmail SMTP configured (alarezgui98@gmail.com)
- Beautiful HTML email templates
- 24-hour token expiration
- Resend verification option

**Test:** Register new account → Check email → Click verification link

---

### ✅ User Roles System
- **3 Roles:** Admin, Professor, Student
- Role-based access control
- Different dashboards per role
- 6 users already have roles assigned

**Test:** Login with different accounts → Different dashboards

---

### ✅ Face ID Authentication ⭐ NEW
- Toggle to enable during registration
- 3-photo capture system
- Animated face scanning interface
- 85% confidence threshold
- Role-based redirect after login

**Test Face ID:**

1. **Register with Face ID:**
   ```
   http://localhost:8000/auth/register
   ↓
   Fill form
   ↓
   Enable "Face ID" toggle
   ↓
   Click "Start Camera" → Allow permission
   ↓
   Click "Capture Face" → 3 photos taken
   ↓
   Submit registration
   ```

2. **Login with Face ID:**
   ```
   http://localhost:8000/auth/login
   ↓
   Click "Sign in with Face ID"
   ↓
   Camera starts automatically
   ↓
   Click "Start Face Recognition"
   ↓
   Face matched → Redirect to dashboard
   ```

---

## 📁 Important Files

### Backend
- `src/Controller/AuthController.php` - Registration & login
- `src/Controller/FaceAuthController.php` - Face ID authentication
- `src/Entity/User.php` - User entity with face_data

### Frontend
- `templates/pages/auth/register.html.twig` - Registration with Face ID
- `templates/pages/auth/login.html.twig` - Login with Face ID button
- `templates/pages/auth/face-login.html.twig` - Face scanning page
- `public/js/face-capture.js` - Face capture functionality

### Database Scripts
- `add_face_id_columns.php` - ✅ Executed
- `add_verification_columns.php` - ✅ Executed
- `create_user_roles_table.php` - ✅ Executed
- `update_user_roles.php` - ✅ Executed

---

## 🗄️ Database Checks

```bash
# Check all users
php bin/console doctrine:query:sql "SELECT id, email, role, is_verified, face_id_enabled FROM users"

# Check Face ID users
php bin/console doctrine:query:sql "SELECT id, email FROM users WHERE face_id_enabled = 1"

# Check user roles
php bin/console doctrine:query:sql "SELECT users.email, user_roles.role FROM users JOIN user_roles ON users.id = user_roles.user_id"
```

---

## 🔧 Common Commands

### Clear Cache
```bash
php bin/console cache:clear
```

### Check Routes
```bash
php bin/console debug:router
```

### Run Database Query
```bash
php bin/console doctrine:query:sql "YOUR SQL HERE"
```

### Fix Cache Permissions (Windows)
```bash
fix_cache.bat
```

---

## 🎨 Face ID Features

### During Registration:
- ✅ Optional toggle switch
- ✅ Manual camera start (not auto)
- ✅ Live video preview
- ✅ Animated face frame overlay
- ✅ 3-photo capture with progress dots
- ✅ Success confirmation

### During Login:
- ✅ Beautiful "Sign in with Face ID" button
- ✅ Dedicated scanning page
- ✅ Automatic camera initialization
- ✅ Animated scanning overlay
- ✅ Real-time confidence meter
- ✅ Success/error animations
- ✅ Auto-redirect based on role

---

## 🌐 Important URLs

- **Home:** http://localhost:8000
- **Login:** http://localhost:8000/auth/login
- **Register:** http://localhost:8000/auth/register
- **Face ID Login:** http://localhost:8000/auth/face-login
- **Admin Dashboard:** http://localhost:8000/admin
- **Professor Dashboard:** http://localhost:8000/professor

---

## 📚 Documentation

- **Face ID Testing Guide:** `FACE_ID_TESTING_GUIDE.md`
- **Face ID Ready to Test:** `FACE_ID_READY_TO_TEST.md`
- **Face ID Technical Docs:** `FACE_ID_SYSTEM_COMPLETE.md`
- **Email Verification Setup:** `EMAIL_VERIFICATION_SETUP.md`
- **User Roles Documentation:** `USER_ROLES_FINAL.md`

---

## ⚠️ Troubleshooting

### Server won't start
```bash
# Check if port 8000 is in use
netstat -ano | findstr :8000

# Use different port
php -S localhost:8080 -t public
```

### Cache errors
```bash
# Clear cache
php bin/console cache:clear

# Or use the batch script
fix_cache.bat
```

### Database errors
```bash
# Check connection
php bin/console doctrine:query:sql "SELECT 1"

# Verify .env database settings
DATABASE_URL="mysql://root:@127.0.0.1:3306/skillharbor"
```

### Camera not working
1. Grant browser permission (click padlock in address bar)
2. Try different browser (Chrome recommended)
3. Check camera is not used by another app
4. Use HTTPS in production (required for camera API)

---

## ✨ What Makes Face ID Special

1. **Creative UI/UX:**
   - Gradient backgrounds (Harbor blue → Sky blue)
   - Animated SVG overlays
   - Pulsing effects during scanning
   - Smooth transitions

2. **User-Friendly:**
   - Optional during registration
   - Manual camera activation (privacy-first)
   - Clear progress indicators
   - Helpful error messages

3. **Secure:**
   - Requires email verification
   - Account must be active
   - 85% confidence threshold
   - Session-based authentication
   - Role-based access

4. **Smart:**
   - Captures 3 photos for better accuracy
   - Face matching algorithm
   - Automatic redirect based on role
   - Graceful error handling

---

## 🚀 Ready to Test!

1. Start server: `php -S localhost:8000 -t public`
2. Open browser: `http://localhost:8000`
3. Register with Face ID or use existing accounts
4. Test Face ID login flow
5. Check documentation for detailed guides

**Everything is ready! Happy coding!** 🎉
