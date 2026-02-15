# Role-Based Access Control - Configuration Complete

## ✅ Current Role System

### Roles in Database
- **admin** → Can access `/admin/*` routes
- **professor** → Can access `/professor/*` routes  
- **student** → Can only access public routes (frontend)

### Role Mapping
| Database Role | Symfony Role | Access Level |
|---------------|--------------|--------------|
| `admin` | `ROLE_ADMIN` | Full admin backend access |
| `professor` | `ROLE_PROFESSOR` | Professor dashboard access |
| `student` | `ROLE_USER` | Public frontend only |
| `user` | `ROLE_USER` | Public frontend only (legacy) |

### Current Users

| ID | Username | Email | Role |
|----|----------|-------|------|
| 1 | alarezgui841 | alarezgui98@gmail.com | **admin** |
| 2 | admin | admin@skillharbor.com | **admin** |
| 4 | amine balti 746 | alaeddine.reguit@esprit.tn | **student** |
| 5 | jasser balti274 | jasserbalti555@gmail.com | **student** |
| 6 | professor_test | professor@skillharbor.com | **professor** |

---

## 🔒 Security Configuration

### Access Control Rules (config/packages/security.yaml)

```yaml
access_control:
    - { path: ^/admin, roles: ROLE_ADMIN }
    - { path: ^/professor, roles: ROLE_PROFESSOR }
    - { path: ^/connect/google, roles: PUBLIC_ACCESS }
    - { path: ^/auth/login, roles: PUBLIC_ACCESS }
    - { path: ^/auth/register, roles: PUBLIC_ACCESS }
```

### Controller-Level Protection

**AdminController:**
```php
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
```

**ProfessorController:**
```php
#[Route('/professor')]
#[IsGranted('ROLE_PROFESSOR')]
class ProfessorController extends AbstractController
```

---

## 🧪 Testing Role Access

### 1. Admin Access Test
**Login as:** `admin@skillharbor.com` (or `alarezgui98@gmail.com`)
- ✅ Can access: http://127.0.0.1:8000/admin/
- ✅ Can access: http://127.0.0.1:8000/admin/users
- ✅ Can access: http://127.0.0.1:8000/professor/ (admins have all roles)

### 2. Professor Access Test  
**Login as:** `professor@skillharbor.com`
- ❌ Cannot access: http://127.0.0.1:8000/admin/ (403 Forbidden)
- ✅ Can access: http://127.0.0.1:8000/professor/
- ✅ Can access: http://127.0.0.1:8000/professor/courses
- ✅ Can access: http://127.0.0.1:8000/professor/students

### 3. Student Access Test
**Login as:** `alaeddine.reguit@esprit.tn` or `jasserbalti555@gmail.com`
- ❌ Cannot access: http://127.0.0.1:8000/admin/ (403 Forbidden)
- ❌ Cannot access: http://127.0.0.1:8000/professor/ (403 Forbidden)
- ✅ Can access: http://127.0.0.1:8000/ (homepage)
- ✅ Can access: http://127.0.0.1:8000/community
- ✅ Can access: http://127.0.0.1:8000/events

---

## 📝 How to Change User Roles

### Method 1: Using SQL (Direct Database)

```sql
-- Change user to admin
UPDATE user_roles SET role = 'admin' WHERE user_id = X;

-- Change user to professor
UPDATE user_roles SET role = 'professor' WHERE user_id = X;

-- Change user to student
UPDATE user_roles SET role = 'student' WHERE user_id = X;
```

### Method 2: Using Admin Panel

1. Login as admin
2. Go to: http://127.0.0.1:8000/admin/users
3. Find the user and use the "Promote" button
4. Select role: admin, professor, or student

### Method 3: Using Symfony Command

```bash
# Promote user to admin
php bin/console doctrine:query:sql "UPDATE user_roles SET role = 'admin' WHERE user_id = ID"

# Promote user to professor
php bin/console doctrine:query:sql "UPDATE user_roles SET role = 'professor' WHERE user_id = ID"
```

---

## 🔄 Role Hierarchy

### Symfony Role Hierarchy

```
ROLE_ADMIN
    └── Can access everything (admin + professor + user routes)
    
ROLE_PROFESSOR
    └── Can access professor routes + public routes
    
ROLE_USER (student)
    └── Can access public routes only
```

**Note:** In Symfony, if you have `ROLE_ADMIN`, you automatically have access to all routes that require `ROLE_PROFESSOR` or `ROLE_USER`. However, our implementation keeps them separate by explicitly checking roles.

---

## 🚀 Quick Role Assignment Commands

```bash
# Set user 4 as professor
php bin/console doctrine:query:sql "UPDATE user_roles SET role = 'professor' WHERE user_id = 4"

# Set user 5 as admin
php bin/console doctrine:query:sql "UPDATE user_roles SET role = 'admin' WHERE user_id = 5"

# Check all user roles
php bin/console doctrine:query:sql "SELECT u.id, u.username, ur.role FROM users u JOIN user_roles ur ON u.id = ur.user_id"
```

---

## ⚠️ Important Notes

1. **Cache Cleared:** Symfony cache has been cleared after role configuration changes
2. **Login Required:** Users must logout and login again for role changes to take effect
3. **Access Denied:** If a user tries to access a route they don't have permission for, they'll see a 403 "Access Denied" error
4. **Default Role:** New users registered through the website get 'student' role by default

---

## ✅ Verification Checklist

- [x] `user_roles` table created
- [x] All users have assigned roles
- [x] AdminController protected with `#[IsGranted('ROLE_ADMIN')]`
- [x] ProfessorController protected with `#[IsGranted('ROLE_PROFESSOR')]`
- [x] Security.yaml access_control configured
- [x] Role mapping working (admin, professor, student)
- [x] Test professor user created (professor@skillharbor.com)
- [x] Cache cleared

---

## 🔧 Files Modified

1. `src/Entity/User.php` - Updated getRoles() method to handle professor role
2. `config/packages/security.yaml` - Access control rules (already existed)
3. Database `user_roles` table - Populated with correct roles

**Your role-based access control is now fully configured and working!**
