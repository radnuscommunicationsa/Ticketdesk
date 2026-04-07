# 🔍 TicketDesk - Complete Code Analysis Report

**Date:** April 7, 2026
**Analyzed by:** Claude Code
**Project:** TicketDesk IT Support Portal
**Total PHP Files:** 45 files

---

## 📊 **EXECUTIVE SUMMARY**

### ✅ **What's Working Well:**
- Modern, clean UI with responsive design
- CSRF protection on all forms
- Password hashing with `password_hash()`
- Prepared statements prevent SQL injection
- Session management with regeneration
- File upload validation (size, type, MIME check)
- Good separation of concerns (admin/employee interfaces)
- Comprehensive ticket tracking with logs

### 🚨 **Critical Issues (Fix Immediately):**
1. ❌ **Dangerous backdoor file** (`resetpass.php`) - anyone can reset admin password
2. ❌ **15+ test/debug files** in production (exposes internal structure)
3. ❌ **Gmail SMTP blocked** on Railway - password reset doesn't work
4. ❌ **No rate limiting** - brute force attacks possible
5. ❌ **Weak password policy** (min 6 chars, no complexity enforcement)
6. ❌ **Error leakage** - raw DB errors shown to users
7. ❌ **Missing .htaccess** in uploads folder (security risk)

### 📈 **Medium Issues:**
- Inconsistent validation rules
- No input rate limiting
- Hard-coded email addresses
- Duplicate code in modals
- No pagination on ticket lists
- Missing database indexes on some foreign keys
- No audit trail for employee changes
- No email templates management

---

## 🔴 **CRITICAL ISSUES - FIX NOW**

### **1. DANGEROUS BACKDOOR FILE**

**File:** `resetpass.php`
```php
<?php
$newPassword = password_hash('password', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE employees SET password = ?, status = 'active', role = 'admin' WHERE id = 1");
```
**Risk:** ANYONE can access this URL and reset the admin password to "password"
**Impact:** Complete system compromise
**Action:** **DELETE IMMEDIATELY**

```bash
rm resetpass.php
git commit -m "Remove dangerous backdoor file"
git push railway main
```

---

### **2. TEST/DEBUG FILES IN PRODUCTION**

**Files to DELETE:**
```
test_email_config.php
test_smtp_detailed.php
test_smtp_live.php
test_smtp_direct.php
test_password_reset.php
diagnose_email.php
diagnose_password_reset.php
railway_email_test.php
railway_debug_password.php
railway_fix_guide.php
check_email_db.php
fix_email_db.php
check_js_errors.php
create_password_reset_table.php
migrate_password_reset.php
one_click_email_setup.php
setup_sendmail.php
test_env.php
test_simple_email.php
test_mobile.php
create_table.php
debug_config.php
```

**Why:** These expose database structure, email configuration, debugging info to attackers.

**Action:** Remove all test files before deploying to production.

---

### **3. GMAIL SMTP BLOCKED ON RAILWAY**

**Problem:** Railway's network blocks connections to `smtp.gmail.com:587/465`
**Error:** `fsockopen(): Unable to connect to smtp.gmail.com:465 (Connection timed out)`

**Solution A (RECOMMENDED):** Use SendGrid (free, works on Railway)
1. Sign up: https://signup.sendgrid.com/
2. Create API key with "Mail Send" permission
3. Add to Railway Variables: `SENDGRID_API_KEY=SG.your-key`
4. Remove SMTP_* variables
5. Redeploy

**Solution B:** Move to different hosting (VPS/shared hosting that allows SMTP)

**Solution C:** Implement Gmail API (requires OAuth 2.0, complex)

---

### **4. NO RATE LIMITING**

**Problem:** Anyone can repeatedly:
- Try to log in (brute force passwords)
- Request password resets (email bombing)
- Create tickets (spam)
- Register employees (if registration is added)

**Missing:** Rate limiting on:
- `login.php`
- `forgot_password.php`
- `employee/raise_ticket.php`
- `admin/employees.php` (add employee)

**Fix:** Implement throttling using sessions or Redis:
```php
// Example simple rate limiting
$key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = ['count' => 0, 'first' => time()];
}
$_SESSION[$key]['count']++;
if ($_SESSION[$key]['count'] > 5 && (time() - $_SESSION[$key]['first']) < 300) {
    die('Too many attempts. Try again in 5 minutes.');
}
```

---

### **5. WEAK PASSWORD POLICY**

**Current Requirements:**
- **Employee passwords** (min 6 chars): `admin/employees.php` line 101
- **Reset passwords** (min 8 chars, basic regex): `reset_password.php` lines 29-43
- **Profile change** (min 6 chars): `employee/profile.php` line 48

**Problems:**
- ❌ 6 characters is too weak (can be cracked in seconds)
- ❌ No special character requirement
- ❌ No check against common passwords
- ❌ No password strength meter when creating employees

**Recommended Minimum:**
- 12 characters for admin accounts
- 10 characters for employee accounts
- Require: uppercase, lowercase, number, special character
- Check against common password list
- Show real-time strength meter

**Fix in `admin/employees.php`:**
```php
if (strlen($data['password']) < 10) {
    $errors['password'] = 'Password must be at least 10 characters.';
}
if (!preg_match('/[A-Z]/', $data['password'])) {
    $errors['password'] = 'Password must contain at least one uppercase letter.';
}
// Add more checks...
```

---

### **6. ERROR INFORMATION LEAKAGE**

**Problem:** Raw database errors are displayed to users in multiple files:

**admin/employees.php:**
```php
$error = 'Database error: ' . $e->getMessage();  // Line 146, 251
```

**admin/tickets.php:**
```php
$error = 'Failed to create ticket: ' . $e->getMessage();  // Line 68
```

**admin/assets.php:**
```php
$error = 'Asset code already exists.'; // Still reveals DB structure
```

**Risk:** Attackers can see table names, column names, constraints, and database structure.

**Fix:** Log errors server-side, show generic message:
```php
} catch (PDOException $e) {
    error_log('DB Error in employees.php: ' . $e->getMessage() . ' | Data: ' . json_encode($data));
    $error = 'An error occurred. Please try again or contact support.';
}
```

---

### **7. MISSING .HTACCESS IN UPLOADS FOLDER**

**Uploads folder:** `uploads/`
**Checked:** `.htaccess` file **EXISTS** ✅

**Content is good:**
- Blocks PHP execution
- Prevents directory listing
- Only allows image/document extensions

**BUT** - Files stored in web-accessible directory. Consider:
- Store outside web root: `../uploads/` instead of `./uploads/`
- Serve via PHP script with authentication checks
- Add `X-Content-Type-Options: nosniff` headers

---

## 🟡 **MEDIUM ISSUES - SHOULD FIX**

### **8. Race Condition in Ticket Number Generation**

**File:** `includes/config.php` line 107
```php
function generateTicketNo($pdo) {
    $row = $pdo->query("SELECT COUNT(*) as cnt FROM tickets")->fetch();
    return 'TKT-' . (1000 + $row['cnt'] + 1);
}
```

**Problem:** Two users creating tickets simultaneously could get same number.

**Fix:** Use database AUTO_INCREMENT as ticket number base:
```sql
-- Add a separate sequence table
CREATE TABLE ticket_seq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prefix VARCHAR(10) DEFAULT 'TKT'
);

-- Or use the tickets table's auto-increment
INSERT INTO tickets (...) VALUES (...);
$ticket_no = 'TKT-' . str_pad($pdo->lastInsertId(), 6, '0', STR_PAD_LEFT);
```

---

### **9. Inconsistent Employee ID Validation**

**admin/employees.php** (line 74):
```php
if (!preg_match('/^[a-zA-Z0-9\-]+$/', $data['emp_id'])) {  // Allows hyphens
```

**employee/raise_ticket.php** (line 16):
```php
} elseif (!preg_match('/^[A-Z0-9\-]+$/i', $identifier)) {  // Allows hyphens, case insensitive
```

**Fix:** Standardize validation across all files:
```php
// Common pattern: letters, numbers, hyphens only
if (!preg_match('/^[A-Za-z0-9\-]+$/', $emp_id)) {
    $errors['emp_id'] = 'Employee ID can only contain letters, numbers, and hyphens.';
}
```

---

### **10. Hard-Coded Email Address**

**Locations:**
- `forgot_password.php` line 200: `radnuscommunicationsa@gmail.com`
- `login.php` line 132: `radnuscommunicationsa@gmail.com`
- `forgot_password.php` line 200 again

**Fix:** Add to config.php:
```php
define('SUPPORT_EMAIL', 'radnuscommunicationsa@gmail.com');
// Or use environment variable
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'radnuscommunicationsa@gmail.com');
```

Then replace all hard-coded occurrences with `SUPPORT_EMAIL`.

---

### **11. Ticket Number Display Format**

Current: `TKT-1001`, `TKT-1002` (starts at 1001)

**Issue:** Reveals total ticket count (easy to guess business volume)

**Consider:**
- Use random numbers instead of sequential
- Or add year prefix: `TKT-2025-0001`
- Or use UUIDs

---

### **12. No Email Template Management**

Emails are hard-coded in `config.php` functions. If you want to change email content, you must edit code.

**Fix:** Move email templates to database or separate template files:
```php
// templates/password_reset.html
// templates/password_reset.txt
```

Or admin panel to edit templates.

---

### **13. Missing Database Indexes**

From `database.sql`, tables have indexes, but check:

**notifications table:** Has indexes on `emp_id`, `is_read`, `created_at` ✅

**password_reset_tokens:** Has indexes on `token`, `emp_id`, `expires_at` ✅

**tickets table:** Need to verify indexes exist:
```sql
-- Should have:
INDEX idx_tickets_emp_id (emp_id)
INDEX idx_tickets_status (status)
INDEX idx_tickets_priority (priority)
INDEX idx_tickets_created_at (created_at)
```

**Add if missing:**
```sql
CREATE INDEX idx_tickets_emp_id ON tickets(emp_id);
CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_priority ON tickets(priority);
```

---

### **14. No Audit Trail for Employee Changes**

When admin edits an employee, **no log is created**.

**For security/compliance**, track:
- Who changed employee data
- What fields were changed
- When changes occurred

**Add:**
```php
// In admin/employees.php edit action
logAdminAction('employee_updated', [
    'employee_id' => $edit_id,
    'changed_fields' => array_diff_assoc($newData, $oldData),
    'admin_id' => $_SESSION['user_id']
]);
```

---

### **15. File Upload - No getimagesize() Validation**

**File:** `employee/raise_ticket.php` lines 80-125

**Good:** MIME type validation with `finfo_file()`

**Missing:** For images, should also validate with `getimagesize()` to ensure it's a real image:
```php
if (in_array($ext, ['jpg','jpeg','png','gif'])) {
    $size_check = getimagesize($file['tmp_name']);
    if (!$size_check) {
        $errors['attachment'] = 'File is not a valid image.';
        continue;
    }
}
```

---

## 🟢 **LOW PRIORITY / NICE TO HAVE**

### **16. No Pagination on Employee List**

**File:** `admin/employees.php`
- Fetches ALL employees at once
- Works fine for small companies (<1000 employees)
- Will be slow with 10,000+ employees

**Fix:** Add pagination:
```php
$page = $_GET['page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$employees = $pdo->prepare("SELECT ... LIMIT ? OFFSET ?")->execute([$per_page, $offset]);
```

---

### **17. Duplicate Modal Code**

Multiple files have identical modal HTML structure:
- `admin/tickets.php` - Add Ticket modal
- `admin/employees.php` - Add/Edit Employee modals
- `admin/assets.php` - Add/Edit Asset modals

**Consider:** Create reusable modal component functions:
```php
function render_modal($id, $title, $content, $size = 'medium') { ... }
```

But current code is readable and working, so **low priority**.

---

### **18. No Email Delivery Status Tracking**

When email is sent, you don't track:
- Was it delivered?
- Was it opened?
- Did it bounce?

**Fix:** Add email_logs table:
```sql
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255),
    subject VARCHAR(500),
    method ENUM('smtp','sendgrid','mail'),
    status ENUM('sent','failed','bounced','opened'),
    error_message TEXT,
    sent_at DATETIME,
    opened_at DATETIME
);
```

---

### **19. No Search Optimization**

Search queries use `LIKE '%keyword%'` which is slow:
- `admin/tickets.php` line 86
- `admin/assets.php` line 112
- `admin/employees.php` (no search currently)

**Fix:** Use FULLTEXT indexes for better performance:
```sql
ALTER TABLE tickets ADD FULLTEXT idx_fulltext_search (subject, description);
-- Then use: MATCH(subject, description) AGAINST(?) instead of LIKE
```

---

### **20. Password Reset Token Cleanup**

Old tokens pile up in database forever.

**Add cron job or cleanup on login:**
```php
// Clean expired tokens daily
$pdo->exec("DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used = 1");
```

---

## ✅ **WHAT'S DONE WELL (Keep These!)**

1. ✅ **CSRF protection** - All forms have tokens
2. ✅ **Password hashing** - Uses `password_hash()`, not plaintext
3. ✅ **SQL injection prevention** - Prepared statements everywhere
4. ✅ **XSS prevention** - Consistent `htmlspecialchars()` sanitization
5. ✅ **Session security** - `session_regenerate_id()` on login
6. ✅ **Input validation** - Required fields, length checks, regex patterns
7. ✅ **File upload security** - Extension + MIME validation, random filenames
8. ✅ **Authorization checks** - `requireAdmin()`, `requireLogin()` used properly
9. ✅ **Error logging** - `error_log()` used throughout
10. ✅ **Responsive design** - Works on mobile (from `MOBILE_DASHBOARD_FIXES.md`)
11. ✅ **Accessibility** - Proper labels, alt text, semantic HTML
12. ✅ **Clean UI** - Modern design, good UX, consistent styling
13. ✅ **Activity logging** - `ticket_logs` table tracks ticket changes
14. ✅ **Notifications system** - Database-driven, marked read/unread
15. ✅ **Avatar colors** - Nice visual touch with `avatarColor()` function

---

## 🏗️ **ARCHITECTURE OBSERVATIONS**

### **Strengths:**
- Clear separation: index.php redirects → login/employee/admin
- Centralized config in `includes/config.php`
- Consistent layout: topbar + sidebar + main content
- Reusable helper functions (`sanitize()`, `csrf_input()`, avatar/initials)
- Proper use of prepared statements throughout
- Good database schema with foreign keys

### **Areas for Refactoring:**
- Modal HTML duplicated across admin files (could extract to partial)
- Sidebar included separately for admin vs employee (good!)
- Some queries could be optimized with better indexes
- Mix of inline CSS and external stylesheet
- JavaScript scattered in multiple files (could consolidate)

---

## 🗄️ **DATABASE SCHEMA REVIEW**

### **✅ Good:**
- Foreign keys with proper `ON DELETE CASCADE`
- Indexes on foreign keys
- `utf8mb4` charset (full Unicode support)
- Timestamps on most tables
- `password_reset_tokens` has proper indexes

### **⚠️ Check:**
- Are there indexes on all frequently queried columns?
- Consider composite indexes for common queries:
  ```sql
  -- For tickets: WHERE emp_id AND status
  CREATE INDEX idx_tickets_emp_status ON tickets(emp_id, status);
  -- For tickets: ORDER BY priority, created_at
  CREATE INDEX idx_tickets_priority_created ON tickets(priority, created_at DESC);
  ```

---

## 🔐 **SECURITY REVIEW**

| Issue | Severity | Status | Fix Priority |
|-------|----------|--------|--------------|
| Backdoor file `resetpass.php` | CRITICAL | ❌ Not fixed | **NOW** |
| Test files in production | CRITICAL | ❌ Not fixed | **NOW** |
| Rate limiting missing | HIGH | ❌ Not fixed | ** Soon** |
| Weak passwords | HIGH | ⚠️ Partial | **Soon** |
| Error leakage | HIGH | ❌ Not fixed | **Soon** |
| .htaccess in uploads | MEDIUM | ✅ Fixed | N/A |
| CSRF protection | HIGH | ✅ Implemented | N/A |
| SQL injection | CRITICAL | ✅ Prevented | N/A |
| XSS prevention | HIGH | ✅ Implemented | N/A |
| Session fixation | MEDIUM | ✅ Partial | Consider improvement |
| Password hashing | CRITICAL | ✅ bcrypt used | N/A |

---

## 🎯 **PRIORITY FIX LIST**

### **Phase 1: Deploy NOW (Critical)**
1. ✅ Delete `resetpass.php` (BACKDOOR)
2. ✅ Remove all test/debug files from production
3. ✅ Deploy to Railway **without** those files
4. ✅ Fix email: Switch from Gmail SMTP to SendGrid

### **Phase 2: Fix This Week (High)**
5. ✅ Implement rate limiting on login & password reset
6. ✅ Increase minimum password length to 10+ chars
7. ✅ Fix error handling - no raw DB errors to users
8. ✅ Add admin action audit logging
9. ✅ Fix ticket number race condition

### **Phase 3: Next Sprint (Medium)**
10. ✅ Add database indexes if queries are slow
11. ✅ Standardize validation rules across forms
12. ✅ Move hard-coded emails to config
13. ✅ Add email template management
14. ✅ Implement pagination on large lists
15. ✅ Add `getimagesize()` validation for images

### **Phase 4: Future Improvements (Low)**
16. ⭕ Add pagination to employee list
17. ⭕ Reduce modal code duplication
18. ⭕ Add email delivery tracking
19. ⭕ Implement full-text search
20. ⭕ Add cron job for token cleanup
21. ⭕ Consider moving uploads outside web root

---

## 📋 **IMMEDIATE ACTION CHECKLIST**

**Before next deployment:**
- [ ] Remove `resetpass.php` from repository
- [ ] Delete all `test_*.php` files
- [ ] Delete all `diagnose_*.php` files
- [ ] Delete `railway_*.php` files (keep only `railway_email_test.php` if needed)
- [ ] Delete `check_*.php` files
- [ ] Delete `migrate_*.php`, `create_*_table.php` (after running once)
- [ ] Delete `debug_*.php`, `setup_*.php`, `one_click_*.php`
- [ ] Delete `test_mobile.php`
- [ ] Update `.gitignore` to exclude test files from future commits
- [ ] Add `*.log` to .gitignore
- [ ] Verify only production code remains

---

## 🚀 **RECOMMENDED DEPLOYMENT PROCESS**

1. **Create a clean production branch:**
```bash
git branch production
# Remove all test files
git rm resetpass.php test_*.php diagnose_*.php railway_*.php \
    check_*.php create_*.php migrate_*.php debug_*.php \
    setup_*.php one_click_*.php test_mobile.php
git commit -m "Remove all test/debug files before production"
```

2. **Fix email configuration:**
   - Set up SendGrid (replace Gmail SMTP)
   - Add `SENDGRID_API_KEY` to Railway
   - Remove `SMTP_*` variables

3. **Apply security fixes:**
   - Increase password minimum length
   - Add rate limiting
   - Fix error messages

4. **Deploy to Railway:**
```bash
git push railway production:main
```

5. **Test:**
   - Can register/admin login
   - Can create tickets
   - Password reset works (SendGrid)
   - All pages load without errors

---

## 📞 **Questions to Stakeholder**

1. **Can we delete the test files?** (They shouldn't be in production repo)
2. **Do we need to support Gmail SMTP?** (Not possible on Railway - use SendGrid)
3. **What's our password policy?** (Should be 10+ chars with complexity)
4. **Do we need email templates editable in admin?** (Currently hard-coded)
5. **How many users?** (If >1000, need pagination, indexes)
6. **Compliance requirements?** (GDPR, HIPAA? Need audit logs)
7. **Expected ticket volume?** (>1000/day? Need performance tuning)

---

## 📚 **REFERENCES**

- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **PHP Security:** https://phpsecurity.readthedocs.io/
- **SendGrid Pricing:** https://sendgrid.com/pricing/ (Free: 100 emails/day)
- **Railway SMTP Block:** Common issue - they block outbound SMTP to prevent spam

---

**Report Generated:** 2026-04-07
**Next Review:** After Phase 1 fixes deployed

---

## 📝 **Summary for You (User)**

### **DO THESE NOW:**

1. **🔥 DELETE `resetpass.php`** - It's a backdoor!
2. **🔥 DELETE all test files** - They expose your database structure
3. **📧 Set up SendGrid** - Gmail won't work on Railway
4. **🔐 Increase password requirements** - Min 10 chars with complexity
5. **⏱️ Add rate limiting** - Stop brute force attacks
6. **🤫 Fix error messages** - Don't show DB errors to users

### **After You Fix These:**

Your TicketDesk will be:
- ✅ Secure (no backdoors, no info leaks)
- ✅ Working email (SendGrid instead of blocked Gmail)
- ✅ Protected against brute force
- ✅ Stronger passwords
- ✅ Production-ready

---

**What do you want to fix first?** I can help you implement any of these fixes step by step. Just ask!
