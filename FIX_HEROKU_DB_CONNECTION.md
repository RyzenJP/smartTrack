# 🔧 Fix Heroku Database Connection Error

## ❌ Current Error

```
ML Server Error: Error: 2003 (HY000): Can't connect to MySQL server on 'localhost:3306' (111)
```

**Problem:** Heroku ML server is trying to connect to `localhost`, but your Hostinger database is on a different server.

---

## ✅ Solution: Set DB_HOST Environment Variable in Heroku

### **Step 1: Find Your Hostinger Database Hostname**

1. **Login to Hostinger Control Panel:**
   - Go to: https://hpanel.hostinger.com
   - Navigate to **"Databases"** → **"MySQL Databases"**

2. **Find Your Database Host:**
   - Look for **"Database Host"** or **"MySQL Host"**
   - Common values:
     - `localhost` (only works from same server - NOT for Heroku)
     - `mysql.hostinger.com` (shared hosting - most common)
     - `127.0.0.1`
     - A specific hostname like `mysql123.hostinger.com`

3. **Copy the hostname** (e.g., `mysql.hostinger.com`)

---

### **Step 2: Set Environment Variable in Heroku**

#### **Via Heroku Dashboard (Easiest):**

1. **Go to Heroku Dashboard:**
   - Visit: https://dashboard.heroku.com
   - Login to your account
   - Select your app: **`endpoint-smarttrack-ec777ab9bb50`**

2. **Open Settings:**
   - Click on **"Settings"** tab
   - Scroll down to **"Config Vars"**
   - Click **"Reveal Config Vars"**

3. **Add/Update DB_HOST:**
   - Click **"Edit"** next to `DB_HOST` (if it exists) or **"Add"** to create new
   - **Key:** `DB_HOST`
   - **Value:** Your Hostinger database hostname (e.g., `mysql.hostinger.com`)
   - Click **"Save"**

4. **Restart Dyno:**
   - Click **"More"** → **"Restart all dynos"**

#### **Via Heroku CLI:**

```bash
# Set database host (replace with your actual Hostinger hostname)
heroku config:set DB_HOST=mysql.hostinger.com -a endpoint-smarttrack-ec777ab9bb50

# Restart the app
heroku restart -a endpoint-smarttrack-ec777ab9bb50
```

**Replace `mysql.hostinger.com` with your actual Hostinger database hostname!**

---

### **Step 3: Verify Configuration**

Check that the environment variable is set:

**Via Dashboard:**
- Go to Settings → Config Vars
- You should see `DB_HOST` with your Hostinger hostname

**Via CLI:**
```bash
heroku config -a endpoint-smarttrack-ec777ab9bb50
```

Should show:
```
DB_HOST: mysql.hostinger.com (or your actual host)
```

---

### **Step 4: Test the Connection**

1. **Test Health Endpoint:**
   ```
   https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/health
   ```
   Should return: `{"status":"healthy","database":"connected"}`

2. **Test Predictions:**
   - Go to: `https://smarttrack.bccbsis.com/trackingv2/trackingv2/motorpool_admin/predictive_maintenance.php`
   - Click **"Refresh All Predictions"**
   - Should load vehicle predictions without errors

---

## 🔍 Alternative: Update Default in Code (Not Recommended)

If you prefer to hardcode the hostname in the code (Option 2):

1. **Find your Hostinger database hostname** (see Step 1 above)

2. **Update `ml_server.py` line 54:**
   ```python
   'host': os.getenv('DB_HOST', 'mysql.hostinger.com'),  # Replace with your actual host
   ```

3. **Re-upload to Heroku and restart**

**⚠️ Note:** This is less secure and less flexible than using environment variables.

---

## 🚨 Common Issues

### **Issue 1: Still Getting "Can't connect to localhost"**

**Solution:**
- Make sure you set `DB_HOST` in Heroku Config Vars (not just in code)
- Restart the Heroku dyno after setting the variable
- Verify the variable is set: `heroku config -a endpoint-smarttrack-ec777ab9bb50`

### **Issue 2: "Access denied for user"**

**Solution:**
- Verify `DB_USER` and `DB_PASS` are correct in Heroku Config Vars
- Check that the database user has permissions to access from external IPs

### **Issue 3: "Unknown database"**

**Solution:**
- Verify `DB_NAME` is set to `u520834156_dbSmartTrack` in Heroku Config Vars

### **Issue 4: Hostinger Blocks External Connections**

**Solution:**
- Some Hostinger plans don't allow external MySQL connections
- Check Hostinger documentation or contact support
- You may need to whitelist Heroku's IP addresses

---

## ✅ Success Checklist

- [ ] Found Hostinger database hostname
- [ ] Set `DB_HOST` in Heroku Config Vars
- [ ] Restarted Heroku dyno
- [ ] Tested `/health` endpoint - returns "database": "connected"
- [ ] Tested predictive maintenance page - loads vehicles successfully

---

## 📞 Need Help?

If you can't find your database hostname:
1. Check Hostinger control panel → Databases → MySQL Databases
2. Look for "Database Host" or "MySQL Host" field
3. Contact Hostinger support if you can't find it



