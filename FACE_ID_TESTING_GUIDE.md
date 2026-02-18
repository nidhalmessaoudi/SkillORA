# Face ID Testing Guide - SkillHarbor

## Overview
This guide helps you test the Face ID authentication system that has been implemented in SkillHarbor.

## Prerequisites
- ✅ Database columns added (`face_data`, `face_id_enabled`)
- ✅ Face ID JavaScript (`public/js/face-capture.js`) 
- ✅ Routes configured (`/auth/face-login`, `/auth/face-authenticate`, `/auth/face-complete`)
- ✅ Templates created (registration, login, face-login pages)
- 🎥 **Working webcam/camera required**
- 🌐 Modern browser (Chrome, Firefox, Safari, Edge)

## Testing Flow

### Part 1: Face ID Registration

1. **Start the Development Server**
   ```bash
   cd "C:\Users\alare\OneDrive\Desktop\skillharbor (5)\skillharbor (3)\skillharbor"
   php -S localhost:8000 -t public
   ```

2. **Navigate to Registration Page**
   - Open browser: `http://localhost:8000/auth/register`
   - Fill in registration form:
     - First Name: `Test`
     - Last Name: `FaceID`
     - Email: `faceid.test@skillharbor.com`
     - Password: `Test1234!`

3. **Enable Face ID**
   - Scroll down to "Face ID (Optional)" section
   - Toggle the "Enable Face ID for quick login" switch ✅
   - The Face ID capture container should appear

4. **Start Camera**
   - Click the "Start Camera" button
   - Browser will ask for camera permission - **Click "Allow"**
   - Camera preview should appear with animated face frame overlay

5. **Capture Face Data**
   - Click "Capture Face" button
   - System will automatically capture **3 photos** from different angles
   - Watch the progress dots (3 dots below video)
   - Each capture happens ~800ms apart
   - Success message appears: "✓ Face ID registered successfully!"

6. **Complete Registration**
   - Click "Create Account" button
   - You should be redirected to verification page
   - Check email for verification link
   - Click verification link

7. **Verify Database**
   ```bash
   php bin/console doctrine:query:sql "SELECT id, email, face_id_enabled, LEFT(face_data, 50) as face_data_preview FROM users WHERE email = 'faceid.test@skillharbor.com'"
   ```
   Expected output:
   - `face_id_enabled`: 1
   - `face_data`: JSON string starting with `{"images":[...`

### Part 2: Face ID Login

1. **Navigate to Login Page**
   - Go to: `http://localhost:8000/auth/login`
   - Look for "Sign in with Face ID" button (blue gradient)
   - Click it

2. **Face Recognition Page**
   - Should redirect to: `/auth/face-login`
   - Page shows:
     - Face ID icon in blue gradient circle
     - "Position your face in the frame to sign in"
     - Camera status indicator
     - Video preview area

3. **Start Recognition**
   - Camera should initialize automatically
   - Click "Start Face Recognition" button when ready
   - System captures your face
   - Animated scanning overlay appears
   - Confidence meter shows matching percentage (0-100%)

4. **Authentication Results**

   **On Success (≥85% confidence):**
   - ✅ Success overlay appears with checkmark
   - "Face Recognized!" message
   - Shows confidence percentage
   - Auto-redirects based on role:
     - Admin → `/admin`
     - Professor → `/professor`
     - Student → `/` (home)

   **On Failure (<85% confidence):**
   - ❌ Error overlay appears
   - "Face not recognized" message
   - Shows confidence percentage
   - "Try Again" button to retry

### Part 3: Edge Cases & Error Testing

#### Test 1: Camera Permission Denied
1. Enable Face ID toggle on registration
2. Click "Start Camera"
3. Click "Block" on browser permission prompt
4. Expected: Alert message "Unable to access camera. Please allow camera access..."

#### Test 2: Unverified User with Face ID
1. Register with Face ID but don't verify email
2. Try to login with Face ID
3. Expected: Error message "Please verify your email before logging in"

#### Test 3: No Face ID Users
1. Try Face ID login when no users have Face ID enabled
2. Expected: Error message "No Face ID users found. Please register with Face ID first."

#### Test 4: Low Confidence Match
1. Register with Face ID
2. Cover part of your face or use poor lighting during login
3. Expected: Error with confidence percentage shown

### Part 4: Browser Console Debugging

**Open Developer Tools (F12) and check:**

1. **Console Tab:**
   - No errors during camera initialization
   - Face capture events logged
   - No 404 errors for `/js/face-capture.js`
   - No manifest.json errors

2. **Network Tab:**
   - POST request to `/auth/face-authenticate` returns JSON
   - Status 200 for successful match
   - Status 401 for failed match

3. **Application/Storage Tab:**
   - Session storage contains `face_auth_user_id` during authentication
   - Session cleared after redirect

## Common Issues & Solutions

### Issue: Camera doesn't start
**Solution:**
- Check browser permissions (Settings → Privacy → Camera)
- Try different browser (Chrome recommended)
- Ensure no other app is using the camera
- Restart browser

### Issue: "Asset manifest file does not exist" error
**Solution:**
- Verify `/js/face-capture.js` exists in `public/js/`
- Check template uses direct path: `<script src="/js/face-capture.js"></script>`
- NOT using `{{ asset() }}` function

### Issue: Face always fails to match
**Solution:**
- Current algorithm is simplified (pixel comparison)
- Ensure same lighting conditions during registration and login
- Ensure same camera angle
- Consider implementing Face-API.js for better accuracy

### Issue: Page redirects to login after Face ID
**Solution:**
- Check session data is being stored correctly
- Verify `auth_face_complete` route is working
- Check if user is verified and active in database

## Manual Database Verification

```bash
# Check Face ID users
php bin/console doctrine:query:sql "SELECT id, email, first_name, last_name, face_id_enabled FROM users WHERE face_id_enabled = 1"

# View face data structure (first 200 chars)
php bin/console doctrine:query:sql "SELECT id, email, LEFT(face_data, 200) as face_data FROM users WHERE face_id_enabled = 1"

# Disable Face ID for testing
php bin/console doctrine:query:sql "UPDATE users SET face_id_enabled = 0, face_data = NULL WHERE email = 'faceid.test@skillharbor.com'"
```

## Testing Checklist

- [ ] Registration page loads without errors
- [ ] Face ID toggle works
- [ ] Start Camera button appears
- [ ] Camera permission prompt appears
- [ ] Camera preview shows live video
- [ ] Capture Face button works
- [ ] Progress dots update (3 captures)
- [ ] Success message appears after capture
- [ ] Face data saved to database
- [ ] Login page shows Face ID button
- [ ] Face ID login page loads without errors
- [ ] Camera initializes automatically
- [ ] Start Face Recognition works
- [ ] Scanning animation appears
- [ ] Confidence meter updates
- [ ] Success redirect works for matched face
- [ ] Error message shows for unmatched face
- [ ] Role-based redirect works (Admin/Professor/Student)

## Next Steps for Production

1. **Implement Face-API.js**
   - Download: https://github.com/justadudewhohacks/face-api.js
   - Replace simplified comparison algorithm
   - Add face detection landmarks

2. **Add Liveness Detection**
   - Request user to blink
   - Request small head movements
   - Prevents photo spoofing

3. **HTTPS Requirement**
   - Camera API requires HTTPS in production
   - Set up SSL certificate

4. **Privacy & Security**
   - Add privacy policy for biometric data
   - Encrypt face_data column
   - Add face data deletion option
   - GDPR compliance

5. **User Management**
   - Add Face ID enable/disable in profile settings
   - Allow face data re-registration
   - Show last Face ID login timestamp

## Security Notes

⚠️ **Current Implementation:**
- Simplified face matching algorithm (pixel comparison)
- 85% confidence threshold
- Base64 image storage in database
- Session-based authentication

🔒 **Production Requirements:**
- Use proper face recognition library (Face-API.js, AWS Rekognition, Azure Face API)
- Implement liveness detection
- Encrypt face data at rest
- Add rate limiting on authentication attempts
- Log all Face ID authentication attempts
- Implement 2FA option (Face ID + verification code)

## Support

If you encounter issues during testing:
1. Check browser console for errors (F12)
2. Verify Symfony cache is clear: `php bin/console cache:clear`
3. Check database connectivity
4. Ensure camera is working (test in other apps)
5. Try different browser

---

**Last Updated:** 2026-02-17
**Status:** Ready for Testing ✅
