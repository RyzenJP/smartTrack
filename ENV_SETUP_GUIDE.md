# 🔐 Environment Variables Setup Guide

## Overview
This guide explains how to set up environment variables for the Smart Track System to securely store database credentials and configuration.

---

## ✅ What Was Fixed

1. **Created `.env.example` template** - Safe template file (can be committed)
2. **Created `.env` loader** - `includes/env_loader.php` to load environment variables
3. **Updated `config.prod.php`** - Now reads from `.env` file
4. **Fixed error handling** - All database connections now use secure error messages
5. **Updated `.gitignore`** - Excludes `.env` and `config.prod.php` from version control

---

## 📋 Setup Instructions

### Step 1: Create `.env` File

**⚠️ IMPORTANT:** The `.env` file is already created but may be blocked by `.gitignore`. If you don't see it, create it manually:

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Or create `.env` manually in the root directory (`trackingv2/.env`)

### Step 2: Configure Your `.env` File

Open `.env` and fill in your actual values:

```env
# Database Configuration (Production)
DB_HOST=localhost
DB_NAME=u520834156_dbSmartTrack
DB_USER=u520834156_uSmartTrck25
DB_PASS=xjOzav~2V

# Application Configuration
ENVIRONMENT=production
BASE_URL=https://smarttrack.bccbsis.com/trackingv2/trackingv2/
PYTHON_ML_SERVER_URL=https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com

# Debug Settings
DEBUG=false
SHOW_ERRORS=false
```

### Step 3: Verify Setup

1. Check that `.env` file exists in `trackingv2/` directory
2. Verify `.env` is in `.gitignore` (should not be committed)
3. Test that `config.prod.php` loads correctly

---

## 🔒 Security Improvements

### Before (❌ Insecure):
```php
// Hardcoded credentials in config.prod.php
define('DB_PASS', 'xjOzav~2V');  // Exposed in version control!
```

### After (✅ Secure):
```php
// Loads from .env file (not in version control)
loadEnv(__DIR__ . '/.env');
// Credentials stored in .env (excluded from git)
```

---

## 🛡️ Error Handling Improvements

### Before (❌ Exposes Info):
```php
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);  // Shows DB details!
}
```

### After (✅ Secure):
```php
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);  // Logs server-side
    http_response_code(500);
    die("Database connection error. Please contact the administrator.");  // Generic message
}
```

---

## 📁 Files Modified

1. **`config.prod.php`** - Now loads from `.env`
2. **`db_connection.php`** - Secure error handling
3. **`config/database.php`** - Secure error handling
4. **`includes/quick_secure_db.php`** - Secure error handling
5. **`config/secure_db.php`** - Secure error handling
6. **`config/security.php`** - Secure error handling
7. **`includes/db_connection.php`** - Secure error handling
8. **`_/db.php`** - Secure error handling
9. **`.gitignore`** - Added `config.prod.php` and `.env`

---

## 🚀 Deployment Checklist

- [x] `.env.example` created (template)
- [x] `.env` loader created (`includes/env_loader.php`)
- [x] `config.prod.php` updated to use `.env`
- [x] All database connections use secure error handling
- [x] `.gitignore` updated to exclude sensitive files
- [ ] **YOU NEED TO:** Create `.env` file manually (if not already created)
- [ ] **YOU NEED TO:** Verify `.env` is not committed to git
- [ ] **YOU NEED TO:** Test database connection after setup

---

## ⚠️ Important Notes

1. **Never commit `.env` to version control** - It contains sensitive credentials
2. **Never commit `config.prod.php`** - It may contain fallback credentials
3. **Use `.env.example` as a template** - This file is safe to commit
4. **Each environment needs its own `.env`** - Development, staging, production
5. **Backup your `.env` file securely** - Store it in a password manager or secure vault

---

## 🔍 Verification

To verify everything is working:

1. Check that `.env` file exists:
   ```bash
   ls -la .env
   ```

2. Check that `.env` is in `.gitignore`:
   ```bash
   grep "\.env" .gitignore
   ```

3. Test database connection by loading any page that uses `config.prod.php`

4. Check error logs (should see detailed errors there, not in user-facing messages)

---

## 📞 Troubleshooting

### Issue: "Database connection error" appears
- **Solution:** Check that `.env` file exists and has correct credentials
- **Check:** Server error logs for detailed error message

### Issue: `.env` file not found
- **Solution:** Create `.env` file manually from `.env.example`
- **Location:** Should be in `trackingv2/.env` (root directory)

### Issue: Credentials still hardcoded
- **Solution:** Make sure `config.prod.php` is using `loadEnv()` function
- **Check:** Verify `includes/env_loader.php` exists

---

*Last Updated: 2025-01-27*

