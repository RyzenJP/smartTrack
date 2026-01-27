# 🧪 Production Testing Guide

## Testing on Production Server

Yes! You can test the verification script directly on your production server.

---

## 🚀 Quick Test

### Option 1: Direct Access (Simple)

1. **Upload `test_production_setup.php` to production** (via FileZilla)
2. **Access it via browser**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_production_setup.php
   ```
3. **Review the test results**
4. **Delete the file after testing** ⚠️

---

## 🔒 Secure Testing Options

### Option 2: IP Restriction (Recommended)

**Edit `test_production_setup.php`** and uncomment the IP restriction:

```php
// Add your IP address
$allowedIPs = ['YOUR_IP_ADDRESS_HERE', '123.456.789.0'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
    http_response_code(403);
    die('Access denied. This script is restricted to specific IP addresses.');
}
```

**How to find your IP**:
- Visit: https://whatismyipaddress.com/
- Copy your IP address
- Add it to the `$allowedIPs` array

**Then access**:
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_production_setup.php
```

---

### Option 3: Password Protection (Simple)

**Edit `test_production_setup.php`** and uncomment the password protection:

```php
$testPassword = 'your_secure_password_here';
if (!isset($_GET['key']) || $_GET['key'] !== $testPassword) {
    http_response_code(403);
    die('Access denied. Provide ?key=your_secure_password_here');
}
```

**Then access with password**:
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_production_setup.php?key=your_secure_password_here
```

---

## 📋 Testing Checklist

### Before Testing:
- [ ] Upload `test_production_setup.php` to production server
- [ ] (Optional) Add IP restriction or password protection
- [ ] Ensure `.env` file is uploaded to production
- [ ] Verify file permissions (644 or 600)

### During Testing:
- [ ] Access the test script URL
- [ ] Review all test results
- [ ] Check database connection
- [ ] Verify environment variables
- [ ] Test CORS configuration

### After Testing:
- [ ] **IMPORTANT**: Delete `test_production_setup.php` from production
- [ ] Or restrict access with IP/password protection
- [ ] Review test results
- [ ] Fix any issues found

---

## 🧪 What the Test Script Checks

1. ✅ **.env File Exists** - Verifies file is present and loaded
2. ✅ **Required Variables** - Checks all required env variables are set
3. ✅ **Database Connection** - Tests connection to production database
4. ✅ **CORS Configuration** - Verifies CORS settings
5. ✅ **Environment Setting** - Checks ENVIRONMENT variable
6. ✅ **Base URL** - Verifies BASE_URL is configured

---

## 🔍 Testing API Endpoints on Production

### Test Key Endpoints:

1. **Mobile GPS API**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php
   ```

2. **Reservation API**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/reservation_api.php
   ```

3. **Geofence Alert API**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/geofence_alert_api.php
   ```

4. **Python ML Bridge**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=status
   ```

### Check CORS Headers:

**Using Browser Developer Tools**:
1. Open browser console (F12)
2. Go to Network tab
3. Make a request to an API endpoint
4. Check Response Headers for:
   - `Access-Control-Allow-Origin` (should be your domain, not `*`)
   - `Access-Control-Allow-Methods`
   - `Access-Control-Allow-Headers`

**Using curl** (command line):
```bash
curl -I https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php
```

Look for CORS headers in the response.

---

## ⚠️ Security Warnings

1. **Delete After Testing**: Always delete `test_production_setup.php` after testing
2. **Never Commit**: Don't commit test files to version control
3. **Restrict Access**: Use IP restriction or password if keeping file
4. **Check Logs**: Review server logs for unauthorized access attempts
5. **File Permissions**: Set proper file permissions (644 or 600)

---

## 🎯 Quick Production Test Steps

1. **Upload test file**:
   ```
   FileZilla → Upload test_production_setup.php to /trackingv2/trackingv2/
   ```

2. **Access test script**:
   ```
   https://smarttrack.bccbsis.com/trackingv2/trackingv2/test_production_setup.php
   ```

3. **Review results**:
   - All tests should pass ✅
   - Database connection successful ✅
   - Environment variables correct ✅

4. **Delete test file**:
   ```
   FileZilla → Delete test_production_setup.php
   ```

---

## 📊 Expected Results

### ✅ All Tests Should Pass:
- `.env` file exists and loads
- All required variables are set
- Database connection successful
- CORS configuration present
- Environment set to production
- Base URL configured correctly

### ❌ If Tests Fail:
- Check `.env` file exists on production
- Verify file permissions (644 or 600)
- Check database credentials are correct
- Ensure database server is accessible
- Review error logs for details

---

## 🆘 Troubleshooting

### Test Script Shows 500 Error:
- Check `.env` file exists on production
- Verify file permissions
- Check PHP error logs

### Database Connection Fails:
- Verify credentials in `.env` file
- Check database host (might not be `localhost` on hosting)
- Ensure database server allows connections from web server
- Check firewall settings

### CORS Not Working:
- Verify `CORS_ALLOWED_ORIGINS` is set in `.env`
- Check `includes/cors_helper.php` is uploaded
- Verify API files use `setCORSHeaders()`
- Test with browser console to see actual headers

---

**Last Updated**: January 2025


