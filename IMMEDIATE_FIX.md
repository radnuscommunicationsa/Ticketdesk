# ⚡ IMMEDIATE FIX: Password Reset Emails on Localhost

## Your Current Problem

```
Warning: mail(): Failed to connect to mailserver at "localhost" port 25
```

This means XAMPP's `mail()` function is trying to use localhost:25 (no mail server) instead of using the configured sendmail.

---

## 🎯 QUICKEST FIX (2 minutes)

### Option 1: Use SMTP Directly (Recommended)

Instead of relying on sendmail.ini, we'll make the code use SMTP directly by setting environment variables.

**Edit: `C:\xampp\apache\conf\extra\httpd-xampp.conf`**

Find the `<IfModule env_module>` section and add these lines:

```apache
SetEnv SMTP_HOST smtp.gmail.com
SetEnv SMTP_PORT 587
SetEnv SMTP_USER your-email@gmail.com
SetEnv SMTP_PASS your-gmail-app-password
SetEnv SMTP_SECURE tls
```

Then **restart Apache** via XAMPP Control Panel.

This makes the system use `sendEmailSMTP()` directly, bypassing sendmail entirely.

---

### Option 2: Configure sendmail.ini (Already Done)

I've already updated `C:\xampp\sendmail\sendmail.ini` with Gmail settings.

**You need to:**
1. Open `C:\xampp\sendmail\sendmail.ini` in Notepad (as Administrator)
2. Replace these lines:
   ```
   auth_username=YOUR_EMAIL@gmail.com
   auth_password=YOUR_GMAIL_APP_PASSWORD
   force_sender=YOUR_EMAIL@gmail.com
   ```
   With your actual Gmail and App Password
3. Save file
4. **Restart Apache** via XAMPP Control Panel

---

## 🔑 Getting Gmail App Password (Required for both options)

1. Go to: https://myaccount.google.com/security
2. Turn ON **2-Step Verification** (if not already)
3. Scroll to **"Signing in to Google"** → **App passwords**
4. Click "Select app" → "Other (Custom name)" → type **"TicketDesk"**
5. Copy the 16-character password (e.g., `abcd efgh ijkl mnop`)
6. Remove spaces → use this as your password

---

## 🧪 Test After Setup

Visit: `http://localhost/ticketdesk/test_email_config.php`

Enter your email and click "Send Test Email". You should see:
```
✅ Email sent via SMTP to: your-email@example.com
```

Check your inbox (and spam folder).

---

## 📝 Summary of What Was Fixed

1. ✅ **Added full SMTP implementation** to `includes/config.php`
2. ✅ **Updated sendmail.ini** with proper Gmail configuration
3. ✅ **Created test pages** to verify email works
4. ✅ **Added Railway support** (works on both localhost & production)

---

## 🔧 Troubleshooting

**Still see "Failed to connect to mailserver"?**

- Did you restart Apache? (Required after config changes)
- Is your Gmail App Password correct? (Must have 2FA enabled)
- Check `C:\xampp\sendmail\debug.log` for detailed SMTP conversation
- Check `C:\xampp\php\logs\php_error.log` for PHP errors

**Emails going to spam?**

- Check spam folder
- Use a different SMTP provider like SendGrid (see EMAIL_SETUP_GUIDE.md)

**Want to use SendGrid instead of Gmail?**

Update `sendmail.ini`:
```
smtp_server=smtp.sendgrid.net
smtp_port=587
auth_username=apikey
auth_password=your-sendgrid-api-key
force_sender=your-verified-sendgrid-email
```

---

**After you complete the setup above, the password reset feature will work perfectly! 🎉**

Let me know if you need help with any step.
