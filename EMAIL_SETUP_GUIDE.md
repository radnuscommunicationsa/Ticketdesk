# 🚀 Password Reset Email Setup Guide

## Quick Overview

The password reset feature now supports **two email delivery methods**:

1. **SMTP** (Recommended for production/Railway) - Uses SendGrid, Gmail, Mailgun, etc.
2. **PHP mail()** (For localhost/XAMPP) - Uses local sendmail

---

## 📧 Method 1: SMTP Configuration (For Railway & Production)

### Using Gmail SMTP (Easiest)

1. **Get Gmail App Password** (not your regular password):
   - Go to https://myaccount.google.com/security
   - Enable 2-Factor Authentication
   - Generate "App Password" for "Other" → name it "TicketDesk"
   - Copy the 16-character password

2. **Set Railway Environment Variables**:

   In your Railway project → Variables tab, add:

   | Variable | Value |
   |----------|-------|
   | `SMTP_HOST` | `smtp.gmail.com` |
   | `SMTP_PORT` | `587` |
   | `SMTP_USER` | `your-email@gmail.com` |
   | `SMTP_PASS` | `your-app-password-here` |
   | `SMTP_SECURE` | `tls` |

3. **Deploy** - The system auto-detects SMTP and uses it.

---

### Using SendGrid (Recommended for Higher Volume)

1. **Sign up** at https://sendgrid.com (free tier: 100 emails/day)
2. **Create API Key**:
   - Settings → API Keys → Create API Key
   - Name: "TicketDesk"
   - Permissions: "Mail Send"
   - Copy the API key

3. **Railway Variables**:

   | Variable | Value |
   |----------|-------|
   | `SMTP_HOST` | `smtp.sendgrid.net` |
   | `SMTP_PORT` | `587` |
   | `SMTP_USER` | `apikey` |
   | `SMTP_PASS` | `your-sendgrid-api-key` |
   | `SMTP_SECURE` | `tls` |

---

### Using Other SMTP Providers

Common SMTP settings:

| Provider | Host | Port | Security |
|----------|------|------|----------|
| Outlook/Hotmail | smtp.office365.com | 587 | tls |
| Yahoo | smtp.mail.yahoo.com | 587 | tls |
| Amazon SES | email-smtp.region.amazonaws.com | 587 | tls |
| Mailgun | smtp.mailgun.org | 587 | tls |
| Zoho | smtp.zoho.com | 587 | tls |

Username is your full email, password is your SMTP password (may differ from login password).

---

## 💻 Method 2: PHP mail() on Localhost (XAMPP)

### Step 1: Configure XAMPP Sendmail

1. Open: `C:\xampp\sendmail\sendmail.ini`
2. Edit these lines:

   ```ini
   smtp_server=smtp.gmail.com
   smtp_port=587
   auth_username=your-email@gmail.com
   auth_password=your-gmail-app-password
   force_sender=your-email@gmail.com
   ```

   **Note**: Use Gmail App Password, not your regular password.

3. Restart XAMPP (Apache)

---

### Step 2: Test Email Locally

Visit: `http://localhost/ticketdesk/test_email.php` (we'll create this)

---

## 🧪 Testing Your Setup

### Check Database First

Run the diagnostic script:
```
http://localhost/ticketdesk/diagnose_password_reset.php
```

Or via browser: click the file directly.

---

### Test Email Sending

We'll create a comprehensive test file `test_email_config.php` that:
- Checks SMTP configuration
- Sends test email
- Verifies password reset token generation
- Tests the complete flow

---

## 🔍 Troubleshooting

### "mail() not configured" on Localhost

- Verify `sendmail_path` in `php.ini` points to `C:\xampp\sendmail\sendmail.exe`
- Check `C:\xampp\sendmail\sendmail.ini` is configured
- Restart Apache
- Check `C:\xampp\sendmail\sendmail.log` for errors

### Railway: "SMTP connection failed"

- Verify all 5 environment variables are set correctly
- Check Railway PostgreSQL logs for any errors
- Test with Gmail App Password if using Gmail
- Some SMTP providers block external connections - check provider settings

### Emails Going to Spam

- Use a reputable SMTP provider (SendGrid, Mailgun, AWS SES)
- Add proper SPF/DKIM/DMARC records (ask your domain admin)
- Avoid spammy subject lines

### No Emails Received

1. Check PHP error log: `C:\xampp\php\logs\php_error.log` (XAMPP)
2. Railway: View logs in Railway dashboard
3. The system logs **all email attempts** to error_log
4. Check spam folder

---

## 📊 What Gets Logged

All email attempts are logged to PHP error log with:

**Success:**
```
✅ Email sent via SMTP to: user@example.com (John Doe)
   Reset link: https://yoursite.com/reset_password.php?token=abc123...
```

**Failure:**
```
❌ SMTP send failed: Connection refused
❌ mail() failed: Could not execute mail delivery
```

Search for "Password reset email" or "SMTP" in your logs.

---

## 🔐 Security Notes

- ✅ Tokens are random 64-character hex strings
- ✅ Tokens expire in 1 hour
- ✅ One-time use only (marked as `used=1`)
- ✅ Password hashing uses bcrypt (PASSWORD_DEFAULT)
- ✅ CSRF protection on all forms
- ✅ No information leakage (generic success message even if email doesn't exist)

---

## 📞 Support

If emails still don't send after configuration:
1. Check the test file output
2. Review error logs
3. Verify SMTP credentials by testing with a simple mail client (Thunderbird, Outlook)
4. Contact: radnuscommunicationsa@gmail.com
