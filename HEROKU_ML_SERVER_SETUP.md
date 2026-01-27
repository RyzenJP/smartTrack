# 🚀 Heroku ML Server Configuration

## ✅ Heroku Endpoint Configured

Your Python ML server is now configured to use the Heroku endpoint:

**Endpoint URL:** `https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com`

---

## 📋 Files Updated

### 1. **`api/python_ml_bridge.php`** ✅
- Now loads from `.env` file (if available)
- Falls back to Heroku endpoint if `.env` is not configured
- **Default:** `https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com`

### 2. **`config.prod.php`** ✅
- Updated fallback value to Heroku endpoint
- **Default:** `https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com`

### 3. **`motorpool_admin/predictive_maintenance.php`** ✅
- JavaScript `PYTHON_SERVER_URL` updated to Heroku endpoint
- Diagnostic tests updated to use Heroku endpoint

### 4. **`python_ml_interface.html`** ✅
- JavaScript `PYTHON_SERVER_URL` updated to Heroku endpoint

---

## 🔧 Configuration Options

### Option 1: Use `.env` File (Recommended)

Create or update `.env` file in `trackingv2/` directory:

```env
PYTHON_ML_SERVER_URL=https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com
```

**Benefits:**
- Easy to change without editing code
- Different URLs for local/production
- Secure (not in version control)

### Option 2: Use Default (Current Setup)

The files are already configured with the Heroku endpoint as the default, so it will work immediately without any `.env` file.

---

## 🧪 Testing the Connection

### Test 1: Direct URL Test
Visit in browser:
```
https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/status
```

Should return JSON with server status.

### Test 2: Via PHP Bridge
Visit:
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=status
```

Should return:
```json
{
    "success": true,
    "server_running": true,
    "data": { ... }
}
```

### Test 3: Via Predictive Maintenance Page
1. Go to: `motorpool_admin/predictive_maintenance.php`
2. Click "Test Connection" or "Check Server Status"
3. Should show ✅ connection successful

---

## 📍 Where the URL is Used

### Backend (PHP):
- `api/python_ml_bridge.php` - Main bridge to ML server
- `config.prod.php` - Production configuration

### Frontend (JavaScript):
- `motorpool_admin/predictive_maintenance.php` - Predictive maintenance dashboard
- `python_ml_interface.html` - ML server interface

---

## 🔄 Switching Between Local and Heroku

### For Local Development:
Update `.env` file:
```env
PYTHON_ML_SERVER_URL=http://localhost:8080
```

### For Production:
Update `.env` file:
```env
PYTHON_ML_SERVER_URL=https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com
```

Or use the default (already set to Heroku).

---

## ⚠️ Important Notes

1. **CORS Configuration:** Make sure your Heroku server allows CORS requests from your domain
2. **HTTPS:** Heroku endpoint uses HTTPS, which is required for production
3. **Environment Variables:** If using `.env`, make sure it's uploaded to production server
4. **No Trailing Slash:** The URL should NOT end with `/` (e.g., `https://...herokuapp.com` not `https://...herokuapp.com/`)

---

## 🐛 Troubleshooting

### Issue: "Connection failed" error
- **Check:** Is Heroku server running? Visit the endpoint directly
- **Check:** CORS headers on Heroku server
- **Check:** Browser console for CORS errors

### Issue: "Invalid response" error
- **Check:** Heroku server logs
- **Check:** Server is returning valid JSON
- **Check:** Endpoint path is correct (e.g., `/status`, `/predict`)

### Issue: Works locally but not in production
- **Check:** `.env` file exists on production server
- **Check:** `.env` has correct `PYTHON_ML_SERVER_URL`
- **Check:** File permissions on `.env` (should be 644 or 600)

---

## ✅ Verification Checklist

- [x] `api/python_ml_bridge.php` updated
- [x] `config.prod.php` updated
- [x] `motorpool_admin/predictive_maintenance.php` updated
- [x] `python_ml_interface.html` updated
- [ ] `.env` file created on production server (optional)
- [ ] Test connection via browser
- [ ] Test connection via PHP bridge
- [ ] Test predictive maintenance page

---

*Last Updated: 2025-01-27*

