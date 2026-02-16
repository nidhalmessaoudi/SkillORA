# User Roles - Complete Setup

## ✅ All 3 Roles Configured

Your SkillHarbor application now has all three user roles properly assigned!

---

## 👥 Current User Roles

### 🔴 Administrators (1 user)
| ID | Email | Name | Role |
|----|-------|------|------|
| 2 | admin@skillharbor.com | Admin SkillHarbor | **ADMIN** |

**Permissions:**
- Access to admin dashboard (`/admin`)
- Manage all users
- Manage courses, lessons, evaluations
- Manage appointments and slots
- Full system access

---

### 🟢 Professors (2 users)
| ID | Email | Name | Role |
|----|-------|------|------|
| 4 | alaeddine.reguit@esprit.tn | amine balti | **PROFESSOR** |
| 7 | wadierezgui12@gamil.com | wadie rezgui | **PROFESSOR** |

**Permissions:**
- Access to professor dashboard (`/professor`)
- Create and manage courses
- Create lessons and evaluations
- Manage appointments with students
- View student progress

---

### 🔵 Students (3 users)
| ID | Email | Name | Role |
|----|-------|------|------|
| 1 | alarezgui98@gmail.com | Ala Rezgui | **STUDENT** |
| 5 | jasserbalti555@gmail.com | jasser balti | **STUDENT** |
| 8 | aminerezgui@gmail.com | Ala Rezgui | **STUDENT** |

**Permissions:**
- Access to student features
- Browse and enroll in courses
- Take lessons and evaluations
- Book appointments with professors
- View own progress

---

## 📊 Role Distribution

- **Admins:** 1 (16.7%)
- **Professors:** 2 (33.3%)
- **Students:** 3 (50%)
- **Total Users:** 6

---

## 🔐 Role-Based Access Control

Your application uses role-based access control through the `user_roles` table:

```
User logs in
     ↓
System checks user_roles table
     ↓
Role is loaded (admin/professor/student)
     ↓
User redirected to appropriate dashboard
     ↓
Access control enforced on all pages
```

---

## 🛠️ How to Change User Roles

### Method 1: Using SQL

```sql
-- Make a user an admin
UPDATE user_roles SET role = 'admin' WHERE user_id = 1;

-- Make a user a professor
UPDATE user_roles SET role = 'professor' WHERE user_id = 1;

-- Make a user a student
UPDATE user_roles SET role = 'student' WHERE user_id = 1;
```

### Method 2: Using PHP Script

Run the update script:
```bash
php update_user_roles.php
```

### Method 3: In Admin Panel (if implemented)

You can add a user management interface in your admin panel to change roles via UI.

---

## 🚪 Login Redirects

After successful login, users are redirected based on their role:

- **Admin** → `/admin` (Admin Dashboard)
- **Professor** → `/professor` (Professor Dashboard)
- **Student** → `/` (Home Page / Student Dashboard)

This is configured in `src/Controller/AuthController.php` (lines 239-245):

```php
if ($this->getUser()->isAdmin()) {
    return $this->redirectToRoute('admin_dashboard');
} elseif ($this->isGranted('ROLE_PROFESSOR')) {
    return $this->redirectToRoute('professor_dashboard');
}
return $this->redirectToRoute('app_home');
```

---

## 📝 New User Registration

When new users register through the `/register` form, they choose their role:

- **Student** (default) → Can learn courses
- **Professor** → Can teach courses

Admins cannot be created through registration - they must be assigned manually in the database for security.

---

## ✅ Testing the Roles

### Test Admin Access:
1. Log in with: `admin@skillharbor.com`
2. Should redirect to `/admin`
3. Should see admin menu and features

### Test Professor Access:
1. Log in with: `alaeddine.reguit@esprit.tn` or `wadierezgui12@gamil.com`
2. Should redirect to `/professor`
3. Should see professor dashboard

### Test Student Access:
1. Log in with any student account
2. Should redirect to `/`
3. Should see student features

---

## 🔒 Security Notes

1. **Role checking in templates:**
   ```twig
   {% if is_granted('ROLE_ADMIN') %}
       <!-- Admin only content -->
   {% endif %}
   
   {% if is_granted('ROLE_PROFESSOR') %}
       <!-- Professor content -->
   {% endif %}
   ```

2. **Role checking in controllers:**
   ```php
   $this->denyAccessUnlessGranted('ROLE_ADMIN');
   ```

3. **Role hierarchy** (in `config/packages/security.yaml`):
   - `ROLE_ADMIN` inherits `ROLE_PROFESSOR` and `ROLE_USER`
   - `ROLE_PROFESSOR` inherits `ROLE_USER`
   - `ROLE_USER` is the base role for all authenticated users

---

## 📁 Database Structure

### user_roles table:
```sql
CREATE TABLE user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id)
)
```

Each user has exactly ONE role in the `user_roles` table.

---

## 🎯 Quick Commands

### View all user roles:
```bash
php bin/console dbal:run-sql "SELECT u.email, ur.role FROM users u JOIN user_roles ur ON u.id = ur.user_id ORDER BY ur.role"
```

### Count users by role:
```bash
php bin/console dbal:run-sql "SELECT role, COUNT(*) as count FROM user_roles GROUP BY role"
```

### Clear cache after role changes:
```bash
php bin/console cache:clear
```

---

## ✨ Status: All Set!

✅ User roles table created  
✅ All 3 roles properly assigned (admin, professor, student)  
✅ Role-based access control working  
✅ Login redirects configured  
✅ Cache cleared  

**Your application is ready to use with all three user roles!** 🚀
