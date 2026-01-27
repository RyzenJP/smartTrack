# 📝 Update Your .env File

## ✅ Copy and Paste This Content

Open your `.env` file (located at `trackingv2/.env`) and replace all content with:

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

---

## 📍 File Location

**Local:** `C:\xampp\htdocs\trackingv2\trackingv2\.env`

**Production:** `/trackingv2/trackingv2/.env` (via FileZilla)

---

## ✅ What's Updated

1. ✅ **Database credentials** - Production database settings
2. ✅ **Base URL** - Production website URL
3. ✅ **ML Server URL** - Heroku endpoint (`https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com`)
4. ✅ **Environment** - Set to `production`
5. ✅ **Debug** - Disabled for production

---

## 🔒 Security Note

- The `.env` file is protected by `.gitignore` (won't be committed to git)
- Make sure file permissions are set to `644` or `600` on production server
- Never share this file publicly

---

## 🧪 After Updating

1. **Test database connection:**
   - Visit: `https://smarttrack.bccbsis.com/trackingv2/trackingv2/login.php`
   - Should load without errors

2. **Test ML server connection:**
   - Visit: `https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=status`
   - Should return JSON with server status

---

*Last Updated: 2025-01-27*

