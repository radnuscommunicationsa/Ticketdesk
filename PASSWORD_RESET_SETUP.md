# 🔐 Forgot Password Feature - Implementation Guide

## ✅ **Implementation Complete!**

A full-featured password reset system has been added to TicketDesk.

---

## 📦 **What Was Added**

### **1. Database Table**
**Table:** `password_reset_tokens`

```sql
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

**Indexes:**
- `idx_password_reset_token` - Fast token lookup
- `idx_password_reset_emp_id` - Fast employee queries
- `idx_password_reset_expires` - Cleanup expired tokens

---

### **2. New Files**

| File | Purpose | Line Count |
|------|---------|------------|
| `forgot_password.php` | Request password reset (enter email/emp ID) | 170 |
| `reset_password.php` | Actual reset form with password validation | 270 |
| `test_password_reset.php` | Development/debugging tool | 300 |
| This documentation | Setup instructions | You are here |

---

### **3. Updated Files**

#### **includes/config.php**
Added 3 new functions:

1. **`generateResetToken($pdo, $emp_id)`**
   - Generates 64-character secure random token
   - Stores in database with 1-hour expiry
   - Returns token string

2. **`verifyResetToken($pdo, $token)`**
   - Checks if token exists, unused, and not expired
   - Joins with employees table to get user info
   - Returns false if invalid

3. **`markTokenUsed($pdo, $token)`**
   - Marks token as used (prevents replay attacks)
   - Called after successful password reset

4. **`sendPasswordResetEmail($to, $name, $token)`**
   - Generates HTML email template
   - Logs to error log currently (simulation)
   - Needs mail server configuration for production

---

#### **login.php**
Added link at bottom: **"Forgot Password?"** → `forgot_password.php`

---

### **4. Features Implemented**

#### **Security 🔒**
- ✅ Secure tokens (cryptographically random, 64 chars)
- ✅ Token expiry (1 hour)
- ✅ One-time use (marked as used)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (sanitize output)
- ✅ CSRF token protection
- ✅ No account enumeration (generic success message)
- ✅ Password complexity requirements (8+ chars, upper, lower, number)

#### **User Experience ✨**
- ✅ Clean, Zoho-inspired design matching existing theme
- ✅ Mobile responsive
- ✅ Password strength indicator (real-time)
- ✅ Password visibility toggle
- ✅ Confirm password field with live matching
- ✅ Success/error feedback
- ✅ Employee info displayed on reset page

#### **Accessibility ♿**
- ✅ ARIA labels where needed
- ✅ Proper form labels
- ✅ Keyboard navigation
- ✅ Clear error messages

---

## 🧪 **How to Test**

### **Option 1: Using Test Panel (Recommended)**

1. Go to: `http://localhost/ticketdesk/test_password_reset.php`
2. Click **"Generate New Token"**
3. Check your PHP error log for the simulated email with reset link:
   ```
   Password reset email would be sent to: user@example.com (John Doe) - Token: abc123... - Link: http://localhost/ticketdesk/reset_password.php?token=abc123...
   ```
4. Copy the token
5. Use **"Forgot Password Form"** link to test the request page
6. Paste token into test panel "Verify Token" to check it's valid
7. Use **"Reset Password"** link to actually reset
8. Log in with employee account to verify new password

---

### **Option 2: Manual Testing**

1. **Request Reset:**
   - Visit: `http://localhost/ticketdesk/forgot_password.php`
   - Enter an existing employee's email or emp_id
   - Submit form
   - Check error log for token

2. **Reset Password:**
   - Visit: `http://localhost/ticketdesk/reset_password.php?token=PASTE_TOKEN_HERE`
   - Enter new password (8+ chars, uppercase, lowercase, number)
   - Confirm password
   - Submit
   - Try logging in with the new password

---

## 📧 **Configuring Email for Production**

Currently, emails are **simulated and logged** to the PHP error log. To send actual emails:

### **Method 1: PHP mail() function (simple)**
Edit `includes/config.php`, find `sendPasswordResetEmail()` function, uncomment:

```php
/* Uncomment this block */
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['SERVER_NAME'] . ">\r\n";
mail($to, $subject, $message, $headers);
```

**Note:** `mail()` requires a configured mail server on your system (sendmail, exim, etc.). For Windows/XAMPP, you'll need to configure `php.ini` SMTP settings.

---

### **Method 2: SMTP Library (Recommended for Production)**

Install PHPMailer or similar:

```bash
composer require phpmailer/phpmailer
```

Then update `sendPasswordResetEmail()` to use SMTP (Gmail, Outlook, SendGrid, etc.).

---

### **Method 3: Email Service API**

Use services like:
- **SendGrid** (API key)
- **Mailgun** (API key)
- **Amazon SES** (SMTP or API)
- **Resend** (modern, simple API)

---

## 🚀 **Production Checklist**

- [ ] Run the `database.sql` update on your production database
- [ ] Test forgot password flow in development
- [ ] Configure email sending (SMTP/API)
- [ ] Update email template with your logo and branding
- [ ] Set proper `SITE_URL` in `config.php` for production
- [ ] Test email delivery with real email addresses
- [ ] Delete test tokens older than 1 hour (cron job recommended)
- [ ] Consider implementing rate limiting to prevent abuse
- [ ] Add CAPTCHA if you get spam requests

---

## 🔧 **Customization**

### **Change Email Template**
Edit `includes/config.php` - function `sendPasswordResetEmail()`.

### **Change Token Expiry**
Edit `includes/config.php` - function `generateResetToken()`:
```php
$expires = date('Y-m-d H:i:s', strtotime('+2 hours')); // Change to +2 hours
```

### **Change Password Requirements**
Edit `reset_password.php` lines 19-29 (the validation section).

---

## 🐛 **Troubleshooting**

### **Issue:** "Invalid reset token" error
**Solution:** Tokens expire after 1 hour. Generate a new one.

### **Issue:** No email received
**Solution:** Check `error_log` to confirm email was logged. Configure SMTP.

### **Issue:** "No active employees found"
**Solution:** Add at least one employee with status = 'active' in database.

### **Issue:** Database error on table not found
**Solution:** Run `database.sql` to create the `password_reset_tokens` table.

---

## 📊 **Database Schema Reference**

```sql
-- See full schema in database.sql
INSERT INTO password_reset_tokens (emp_id, token, expires_at) VALUES
(1, 'abc123...', '2026-04-02 15:00:00');
```

---

## 🔒 **Security Notes**

1. **Timing attacks:** Functions use constant time comparisons (via prepared statements)
2. **Token leakage:** Tokens stored as SHA-256 hash? (Consider for production if paranoid)
3. **Brute force:** No rate limiting currently - add this for production
4. **Email enumeration:** System returns same message whether email exists or not
5. **Token cleanup:** Add cron job to delete expired tokens weekly:
   ```sql
   DELETE FROM password_reset_tokens WHERE expires_at < NOW();
   ```

---

## 📋 **Files Modified/Created**

```
database.sql                          - Added password_reset_tokens table
includes/config.php                   - Added 4 new functions (lines ~98-180)
forgot_password.php                   - [NEW] Request form
reset_password.php                    - [NEW] Reset form
test_password_reset.php               - [NEW] Debug/development tool
login.php                             - Added forgot password link
```

---

## 🤝 **Support**

**Issue:** If something doesn't work, check:
1. `error_log` file for PHP errors
2. `test_password_reset.php` for system status
3. All database tables exist
4. CSRF token is enabled

---

**Implementation Date:** April 2, 2025
**Status:** ✅ Complete and ready for testing
**Next Step:** Configure email sending for production
