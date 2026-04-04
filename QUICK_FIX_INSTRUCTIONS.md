# ⚡ QUICK FIX: Authentication Failed (535 error)

## Your Problem

```
❌ SMTP error: Authentication failed: 535-5.7.8 Username and Password not accepted
```

**Cause:** You're using your regular Gmail password, but **Gmail requires an App Password** (since May 2024).

---

## 🔑 SOLUTION: Get Gmail App Password (2 minutes)

### Step 1: Enable 2-Factor Authentication

1. Go to: https://myaccount.google.com/security
2. Sign in with your Google account
3. Find **"2-Step Verification"** → Turn it **ON**
4. Follow the prompts (you can use your phone number or Google Prompt)

---

### Step 2: Generate App Password

1. After enabling 2FA, stay on the Security page
2. Scroll to **"Signing in to Google"** section
3. Click **"App passwords"**
4. Select app: **"Other (Custom name)"**
5. Type: **"TicketDesk"**
6. Click **Generate**
7. Copy the **16-character password** (e.g., `abcd1234efgh5678`)
8. **Remove any spaces** - use as one continuous string

---

### Step 3: Configure Your Email (Choose ONE Option)

#### **Option A: One-Click Setup (Easiest - No Restart)**

1. Visit: `http://localhost/ticketdesk/one_click_email_setup.php`
2. Enter:
   - Email: `radnuscommunicationsa@gmail.com`
   - Password: **paste your 16-digit app password**
3. Click **"Configure Email Now"**
4. Done! (No restart needed)

#### **Option B: Manual Config (Edit config.php)**

1. Open: `C:\xampp\htdocs\ticketdesk\includes\config.php`
2. Find line around 45-60 where the `EMAIL_DEBUG` definition is
3. Look for this commented block:
   ```php
   // putenv('SMTP_HOST=smtp.gmail.com');
   // putenv('SMTP_PORT=587');
   // putenv('SMTP_USER=your-email@gmail.com');
   // putenv('SMTP_PASS=your-16-digit-app-password');
   // putenv('SMTP_SECURE=tls');
   ```
4. **Uncomment** (remove `//`) and **fill in**:
   ```php
   putenv('SMTP_HOST=smtp.gmail.com');
   putenv('SMTP_PORT=587');
   putenv('SMTP_USER=radnuscommunicationsa@gmail.com');
   putenv('SMTP_PASS=abcd1234efgh5678');  // Your actual 16-digit app password
   putenv('SMTP_SECURE=tls');
   ```
5. Save file
6. **No restart needed** - works immediately

#### **Option C: Apache Environment Variables (Permanent)**

1. Open: `C:\xampp\apache\conf\extra\httpd-xampp.conf`
2. Find `<IfModule env_module>` section
3. Add:
   ```apache
   SetEnv SMTP_HOST smtp.gmail.com
   SetEnv SMTP_PORT 587
   SetEnv SMTP_USER radnuscommunicationsa@gmail.com
   SetEnv SMTP_PASS your-16-digit-app-password
   SetEnv SMTP_SECURE tls
   ```
4. **Restart Apache** via XAMPP Control Panel
5. Done!

---

## ✅ Test After Configuration

After completing any option above:

1. Visit: `http://localhost/ticketdesk/test_email_config.php`
2. Should now show:
   ```
   ✅ SMTP is CONFIGURED
      Host: smtp.gmail.com
      Port: 587
      Username: radnuscommunicationsa@gmail.com
   ```
3. Click **"Send Test Email"**
4. Enter your email (same or different)
5. Should see: **✅ Email function returned SUCCESS**
6. Check your inbox (and spam folder) for the test email

---

## 🧪 Troubleshooting

### Still getting "Authentication failed"?

1. **Verify 2FA is actually enabled** on your Google account
   - Go to https://myaccount.google.com/security
   - Must see "2-Step Verification is ON"

2. **Generate a NEW app password** (old one may have expired)
   - Delete old app passwords
   - Create fresh one for "TicketDesk"
   - Copy exactly 16 characters (no spaces)

3. **Check you're using the app password, not your regular password**
   - Regular Gmail password = ❌ won't work
   - 16-digit app password = ✅ works

4. **Wait a minute** - Google sometimes takes 60 seconds to activate app passwords

5. **Try the One-Click Setup** (`one_click_email_setup.php`) - it's the most reliable

### "Could not connect to SMTP server"

- Check your internet connection
- Verify `smtp.gmail.com` is accessible (no firewall block on port 587)
- Try: `telnet smtp.gmail.com 587` from Command Prompt

### Email goes to spam

- Check spam folder
- Add "noreply@gmail.com" (or whichever from address) to contacts
- Use SendGrid for better deliverability (see EMAIL_SETUP_GUIDE.md)

---

## 📌 Summary

| What you need | Where to get it |
|---------------|-----------------|
| Gmail App Password | https://myaccount.google.com/security → App passwords |
| 2FA enabled | Same page - turn on 2-Step Verification |
| SMTP credentials | Already configured in code - just add app password |

---

## 📚 More Help

- **One-click setup:** `one_click_email_setup.php`
- **Test page:** `test_email_config.php`
- **Full guide:** `EMAIL_SETUP_GUIDE.md`
- **Railway deployment:** `RAILWAY_EMAIL_SETUP.md`

---

**You're 2 minutes away from getting this working! Just get the App Password and run the one-click setup.** 🚀
