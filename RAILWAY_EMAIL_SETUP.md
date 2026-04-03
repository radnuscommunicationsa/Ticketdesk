# 🚂 Railway Deployment - Email Configuration

## Quick Setup for Railway

### Option 1: Gmail SMTP (Simplest)

1. **Create Gmail App Password:**
   - Go to https://myaccount.google.com/security
   - Enable 2-Factor Authentication (required)
   - Click "App passwords"
   - Select "Other" → Name it "TicketDesk"
   - Copy the 16-digit password (e.g., `abcd efgh ijkl mnop`)

2. **Set Railway Variables:**
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-email@gmail.com
   SMTP_PASS=abcd-efgh-ijkl-mnop  (use the app password without spaces)
   SMTP_SECURE=tls
   ```

3. **Deploy:**
   ```bash
   git add -A
   git commit -m "Add email config"
   git push origin main
   ```
   Railway will auto-deploy and use SMTP.

---

### Option 2: SendGrid (Recommended - Better Deliverability)

1. **Sign up:** https://sendgrid.com/free
2. **Create API Key:**
   - Settings → API Keys → Create API Key
   - Name: "TicketDesk"
   - Full Access → Mail Send
   - Copy the API key
3. **Railway Variables:**
   ```
   SMTP_HOST=smtp.sendgrid.net
   SMTP_PORT=587
   SMTP_USER=apikey
   SMTP_PASS=your-sendgrid-api-key
   SMTP_SECURE=tls
   ```

---

### Option 3: Other SMTP Providers

| Provider | SMTP_HOST | SMTP_PORT | SMTP_SECURE |
|----------|-----------|-----------|-------------|
| Gmail | smtp.gmail.com | 587 | tls |
| SendGrid | smtp.sendgrid.net | 587 | tls |
| Mailgun | smtp.mailgun.org | 587 | tls |
| Amazon SES | email-smtp.region.amazonaws.com | 587 | tls |
| Outlook | smtp.office365.com | 587 | tls |
| Zoho | smtp.zoho.com | 587 | tls |

**Note:** Use your full email as SMTP_USER and your SMTP-specific password (may be different from login password).

---

## 🔧 After Deployment

### Verify Email is Working

1. **Check logs in Railway:**
   - Go to your project → Logs
   - Look for: `✅ Email sent via SMTP to:`
   - Or: `❌ SMTP error:`

2. **Test the feature:**
   ```
   https://your-app.up.railway.app/forgot_password.php
   ```
   - Enter your email
   - Check inbox (and spam folder)
   - Click reset link
   - Set new password

3. **Monitor:**
   - Railway logs show email sending attempts
   - SMTP provider dashboard shows delivery stats (SendGrid/Mailgun/AWS)

---

## 🐛 Troubleshooting Railway Email

### "SMTP connection failed"

- **Check variables are set correctly:** Railway dashboard → Variables
- **Restart deployment:** Sometimes new variables need a restart
- **Check SMTP provider allows your region** (Gmail may block if connecting from unusual location)
- **Test credentials** with a mail client first (Thunderbird, Outlook)

### Emails going to spam

- Use SendGrid or Amazon SES for better deliverability
- Add SPF/DKIM records to your domain (if using custom domain)
- Ask users to check spam folder and mark as "Not Spam"

### Railway logs show errors

Common SMTP errors:

```
Connection refused → Wrong SMTP_HOST or SMTP_PORT, or blocked
Authentication failed → Wrong SMTP_USER or SMTP_PASS
535 Authentication → For Gmail, verify it's an App Password, not regular password
550 Invalid sender → From address rejected, check SMTP_USER email
```

---

## 📊 What Gets Logged

All password reset attempts are logged to **Railway logs**:

**Success:**
```
✅ Email sent via SMTP to: user@company.com (John Doe)
   Reset link: https://your-app.up.railway.app/reset_password.php?token=abc123...
```

**Failure:**
```
❌ SMTP error: Could not connect to SMTP server: Connection refused
```

**Token created:**
```
INSERT INTO password_reset_tokens: emp_id=3, token=abc123..., expires=2025-04-03 14:30:00
```

Search Railway logs for "SMTP" or "Email" to filter.

---

## 🔐 Security Best Practices on Railway

1. **Never commit SMTP credentials** to git
   - ✅ Use Railway Variables (environment variables)
   - ❌ Don't hardcode passwords in code

2. **Use App Passwords** instead of main passwords (Gmail/Google Workspace)

3. **Rotate API keys** periodically (SendGrid, Mailgun, etc.)

4. **Monitor** SMTP provider dashboard for unusual activity

5. **Check Railway logs** regularly for failed attempts

---

## 🧪 Test Before Going Live

1. Deploy to Railway with SMTP configured
2. Visit: `https://your-app.up.railway.app/test_email_config.php`
3. Enter a test email address
4. Click "Send Test Email"
5. Check:
   - ✅ Email arrives in inbox
   - ✅ Railway logs show success
   - ✅ Link works (tries to reset password)
6. Then test full flow: forgot_password → email → reset_password → login

---

## 🆘 Need Help?

1. **Check Railway logs** → Most errors are visible there
2. **Verify SMTP credentials** → Test with desktop mail client
3. **Review error messages** → They're logged with `❌` prefix
4. **Contact IT Support:** radnuscommunicationsa@gmail.com

Include in your message:
- Railway app URL
- SMTP provider you're using
- Exact error from Railway logs
- Screenshot of Railway Variables (hide password)

---

## 📝 Variable Reference

All variables are **optional**, but for SMTP to work you need:

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `SMTP_HOST` | ✅ Yes | SMTP server address | `smtp.gmail.com` |
| `SMTP_PORT` | ⭕ No (default 587) | SMTP port | `587` or `465` |
| `SMTP_USER` | ✅ Yes | SMTP username (usually email) | `you@gmail.com` |
| `SMTP_PASS` | ✅ Yes | SMTP password or API key | `app-password-here` |
| `SMTP_SECURE` | ⭕ No (default 'tls') | `tls` or `ssl` | `tls` |
| `EMAIL_DEBUG` | ⭕ No (default false) | Set to 'true' for verbose logs | `true` |

**Without SMTP variables**, the system falls back to PHP `mail()` which **won't work on Railway** (no sendmail configured).

---

## ⚡ One-Line Setup (Gmail)

```bash
railway variables set SMTP_HOST=smtp.gmail.com SMTP_PORT=587 SMTP_USER=you@gmail.com SMTP_PASS=your-app-password SMTP_SECURE=tls
```

Then commit and push.
