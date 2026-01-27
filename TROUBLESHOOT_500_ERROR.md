# 🔧 Troubleshooting HTTP 500 Error on Production

## Problem: HTTP ERROR 500 on `smarttrack.bccbsis.com/trackingv2/trackingv2/login.php`

This error usually means there's a **PHP fatal error** or **database connection failure**.

---

## 🔍 Most Common Causes

### 1. **Database Connection Failure** (Most Likely) ⚠️

**Symptoms:**
- HTTP 500 error
- Page won't load
- No specific error message shown

**Possible Causes:**
- `.env` file doesn't exist on production server
- Wrong database credentials in `.env`
- Database doesn't exist on production
- Database server is down
- Wrong database host (might not be `localhost` on hosting)

---

## ✅ Quick Fixes

### Fix 1: Check if `.env` File Exists on Production

**Via FileZilla:**
1. Connect to your server
2. Navigate to: `/trackingv2/trackingv2/` (or wherever your files are)
3. Check if `.env` file exists
4. If missing, create it with production credentials

**Via Hosting File Manager:**
1. Login to your hosting control panel
2. Open File Manager
3. Navigate to your site directory
4. Check for `.env` file
5. If missing, create it

---

### Fix 2: Create `.env` File on Production Server

Create a file named `.env` in the same directory as `db_connection.php`:

```env
# Database Configuration (Production)
DB_HOST=localhost
DB_NAME=u520834156_dbSmartTrack
DB_USER=u520834156_uSmartTrck25
DB_PASS=xjOzav~2V

# Application Configuration
ENVIRONMENT=production
BASE_URL=https://smarttrack.bccbsis.com/trackingv2/trackingv2/
PYTHON_ML_SERVER_URL=http://localhost:8080

# Debug Settings
DEBUG=false
SHOW_ERRORS=false
```

**⚠️ Important:**
- Make sure file is named exactly `.env` (with the dot)
- Set file permissions to `644` or `600`
- File should be in same directory as `db_connection.php`

---

### Fix 3: Check Database Host

On some hosting providers, the database host might NOT be `localhost`.

**Common alternatives:**
- `127.0.0.1`
- `localhost:3306`
- A specific hostname like `mysql.yourhost.com`
- Check your hosting panel for the correct database host

**Update `.env` file:**
```env
DB_HOST=your_actual_database_host
```

---

### Fix 4: Verify Database Credentials

**Check your hosting control panel:**
1. Login to your hosting account
2. Go to Database section (phpMyAdmin or Database Manager)
3. Verify:
   - Database name: `u520834156_dbSmartTrack`
   - Username: `u520834156_uSmartTrck25`
   - Password: `xjOzav~2V`
4. Make sure database exists
5. Make sure user has permissions

---

### Fix 5: Check PHP Error Logs

**Via Hosting Control Panel:**
1. Login to hosting control panel
2. Find "Error Logs" or "PHP Error Logs"
3. Look for recent errors
4. Common errors you might see:
   - `Unknown database 'u520834156_dbSmartTrack'`
   - `Access denied for user 'u520834156_uSmartTrck25'@'localhost'`
   - `Connection refused`
   - `Call to undefined function loadEnv()`

**Via FileZilla:**
1. Check for `error_log` file in your site directory
2. Or check `logs/` folder
3. Look for recent PHP errors

---

### Fix 6: Temporary Debug Mode (To See Actual Error)

**Option A: Enable Error Display Temporarily**

Edit `db_connection.php` temporarily to see the actual error:

```php
// TEMPORARY - Remove after fixing!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ... rest of your code
```

**⚠️ WARNING:** Remove this after fixing! Don't leave error display enabled in production.

**Option B: Check Error Logs Instead**

Better approach - check server error logs instead of displaying errors.

---

### Fix 7: Test Database Connection Directly

Create a test file `test_db.php` on your server:

```php
<?php
// Test database connection
$host = 'localhost';
$user = 'u520834156_uSmartTrck25';
$pass = 'xjOzav~2V';
$db = 'u520834156_dbSmartTrack';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connection successful!";
}

$conn->close();
?>
```

**Steps:**
1. Upload `test_db.php` to your server
2. Visit: `https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_db.php`
3. If it shows error, you'll see the exact problem
4. **DELETE this file after testing!** (Security risk)

---

## 🔍 Step-by-Step Troubleshooting

### Step 1: Check File Structure
```
/trackingv2/trackingv2/
├── .env                    ← Does this exist?
├── db_connection.php       ← Main connection file
├── includes/
│   └── env_loader.php      ← Environment loader
└── login.php               ← The page with error
```

### Step 2: Verify `.env` File
- [ ] File exists on production server
- [ ] File has correct name (`.env` with dot)
- [ ] File has correct permissions (644 or 600)
- [ ] File contains correct credentials

### Step 3: Verify Database
- [ ] Database exists on production server
- [ ] Database name matches `.env` file
- [ ] Database user has correct permissions
- [ ] Database password is correct

### Step 4: Check Error Logs
- [ ] Check hosting error logs
- [ ] Check PHP error logs
- [ ] Look for specific error messages

---

## 🆘 Common Error Messages & Solutions

### Error: "Unknown database 'u520834156_dbSmartTrack'"
**Solution:** Database doesn't exist. Create it via phpMyAdmin or hosting panel.

### Error: "Access denied for user 'u520834156_uSmartTrck25'@'localhost'"
**Solution:** Wrong username/password. Check credentials in hosting panel.

### Error: "Call to undefined function loadEnv()"
**Solution:** `includes/env_loader.php` file is missing. Upload it.

### Error: "Connection refused"
**Solution:** Database host is wrong. Check hosting panel for correct host.

### Error: "No such file or directory: .env"
**Solution:** `.env` file doesn't exist. Create it with correct credentials.

---

## ✅ Quick Checklist

- [ ] `.env` file exists on production server
- [ ] `.env` file has correct database credentials
- [ ] Database exists on production server
- [ ] Database user has correct permissions
- [ ] `includes/env_loader.php` file is uploaded
- [ ] File permissions are correct (644 for files, 755 for folders)
- [ ] Checked error logs for specific error message
- [ ] Tested database connection directly

---

## 🎯 Most Likely Solution

**90% of the time, it's one of these:**

1. **`.env` file is missing** → Create it with production credentials
2. **Wrong database host** → Check hosting panel for correct host
3. **Database doesn't exist** → Create database via phpMyAdmin
4. **Wrong credentials** → Verify in hosting control panel

---

## 📞 Still Not Working?

If none of these work:

1. **Check hosting error logs** - They will show the exact error
2. **Contact hosting support** - They can check server-side issues
3. **Enable temporary error display** - To see the actual PHP error (remove after fixing!)

---

*Last Updated: 2025-01-27*



