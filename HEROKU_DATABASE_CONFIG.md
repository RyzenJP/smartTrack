# 🔧 Heroku Database Configuration

## ❌ Problem

The Heroku ML server is trying to connect to `localhost`, but your database is on Hostinger. This causes the error:

```
Can't connect to MySQL server on 'localhost:3306' (111)
```

---

## ✅ Solution: Configure Heroku Environment Variables

You need to set environment variables on Heroku with your production database credentials.

---

## 📋 Step-by-Step Instructions

### Option 1: Via Heroku Dashboard (Easiest)

1. **Go to Heroku Dashboard:**
   - Visit: https://dashboard.heroku.com
   - Login to your account
   - Select your app: `endpoint-smarttrack-ec777ab9bb50`

2. **Open Settings:**
   - Click on "Settings" tab
   - Scroll down to "Config Vars"
   - Click "Reveal Config Vars"

3. **Add Environment Variables:**
   Click "Add" and add each of these:

   | Key | Value |
   |-----|-------|
   | `DB_HOST` | `localhost` (or your Hostinger DB host if different) |
   | `DB_NAME` | `u520834156_dbSmartTrack` |
   | `DB_USER` | `u520834156_uSmartTrck25` |
   | `DB_PASS` | `xjOzav~2V` |

4. **Save and Restart:**
   - After adding all variables, restart your Heroku app:
   - Go to "More" → "Restart all dynos"

---

### Option 2: Via Heroku CLI

If you have Heroku CLI installed:

```bash
# Set database host (check Hostinger panel for actual host)
heroku config:set DB_HOST=localhost -a endpoint-smarttrack-ec777ab9bb50

# Set database name
heroku config:set DB_NAME=u520834156_dbSmartTrack -a endpoint-smarttrack-ec777ab9bb50

# Set database user
heroku config:set DB_USER=u520834156_uSmartTrck25 -a endpoint-smarttrack-ec777ab9bb50

# Set database password
heroku config:set DB_PASS=xjOzav~2V -a endpoint-smarttrack-ec777ab9bb50

# Restart the app
heroku restart -a endpoint-smarttrack-ec777ab9bb50
```

---

## ⚠️ Important: Database Host

**Most likely issue:** Hostinger databases might not use `localhost` as the host.

### Check Your Hostinger Database Host:

1. **Login to Hostinger Control Panel:**
   - Go to: https://hpanel.hostinger.com
   - Navigate to "Databases" → "MySQL Databases"

2. **Find Your Database Host:**
   - Look for "Database Host" or "MySQL Host"
   - Common values:
     - `localhost` (if same server)
     - `mysql.hostinger.com` (shared hosting)
     - `127.0.0.1`
     - A specific hostname like `mysql123.hostinger.com`

3. **Update Heroku Config:**
   - Use the actual hostname from Hostinger (not `localhost`)
   - Example: `heroku config:set DB_HOST=mysql.hostinger.com -a endpoint-smarttrack-ec777ab9bb50`

---

## 🔍 Verify Configuration

After setting the environment variables, verify they're set:

### Via Dashboard:
- Go to Settings → Config Vars
- You should see all 4 variables listed

### Via CLI:
```bash
heroku config -a endpoint-smarttrack-ec777ab9bb50
```

Should show:
```
DB_HOST: localhost (or your actual host)
DB_NAME: u520834156_dbSmartTrack
DB_USER: u520834156_uSmartTrck25
DB_PASS: xjOzav~2V
```

---

## 🧪 Test the Connection

After configuring, test the training again:

1. **Via API:**
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=train
   ```

2. **Via Training Page:**
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/train_ml_model.php
   ```

3. **Check Heroku Logs:**
   ```bash
   heroku logs --tail -a endpoint-smarttrack-ec777ab9bb50
   ```
   
   Or via Dashboard: "More" → "View logs"

---

## 🚨 Common Issues

### Issue 1: "Can't connect to MySQL server"
**Solution:** 
- Check that `DB_HOST` is correct (not `localhost` if database is on different server)
- Verify Hostinger allows external connections (some hosts require whitelisting IPs)

### Issue 2: "Access denied for user"
**Solution:**
- Double-check `DB_USER` and `DB_PASS` are correct
- Verify user has permissions to access the database

### Issue 3: "Unknown database"
**Solution:**
- Verify `DB_NAME` is exactly: `u520834156_dbSmartTrack`
- Check database exists in Hostinger panel

### Issue 4: Hostinger Blocks External Connections
**Solution:**
- Some Hostinger plans don't allow external MySQL connections
- **Alternative:** Use a database proxy or VPN
- **Or:** Deploy ML server on same server as database (not Heroku)

---

## 🔒 Security Note

- Environment variables on Heroku are encrypted
- Never commit database credentials to git
- Use Heroku config vars for sensitive data

---

## ✅ After Configuration

Once configured correctly, the training should work:

```json
{
    "success": true,
    "message": "Model trained successfully using Python ML server",
    "training_stats": {
        "accuracy": 95,
        "algorithm": "XGBoost + Schedule-Based",
        ...
    }
}
```

---

*Last Updated: 2025-01-27*

