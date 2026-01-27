# 🔍 Debug Test Connection Issue

## ❌ Problem

Test connection works in browser but fails in mobile app with "JSON Parse error: Unexpected character: <"

---

## 🔍 Debug Steps

### Step 1: Check Console Logs

When you tap "Test Connection", check the console/logs for:

1. **What URL is being called:**
   ```
   🔍 Testing connection to: https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/test_connection.php
   ```

2. **Response status:**
   ```
   📡 Response status: 200
   ```

3. **Response content:**
   ```
   📡 Response text (first 500 chars): {...}
   ```

### Step 2: Check if Using Expo Go

**If using Expo Go:**
- Code changes should hot-reload automatically
- Shake device → "Reload" to force reload
- Or close and reopen Expo Go

**If using built APK:**
- You need to rebuild the APK with updated code
- Development changes won't apply to built APK

### Step 3: Check Network in Emulator

**Test if emulator can access internet:**

1. **Open browser in emulator**
2. **Visit:** `https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/test_connection.php`
3. **If it works** → Emulator network is fine
4. **If it doesn't** → Emulator network issue

### Step 4: Check Actual Response

The console should show what the app is receiving. Look for:

- **If HTML:** `❌ Server returned HTML instead of JSON!`
- **If JSON:** Should parse successfully

---

## 🔧 Quick Fixes

### Fix 1: Reload App

**If using Expo Go:**
- Shake device
- Tap "Reload"
- Try test connection again

**If using development build:**
```bash
# Stop the app
# Restart Metro bundler
npx expo start --clear
# Rebuild app
npx expo run:android
```

### Fix 2: Rebuild APK

If you built an APK with EAS:

```bash
cd SmartTrackMobileApp/SmartTrackExpoApp
eas build --platform android --profile preview
```

Install the new APK and test again.

### Fix 3: Check API URL

Make sure API URL in app is exactly:
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2
```

**NOT:**
- `https://smarttrack.bccbsis.com/trackingv2/trackingv2/` (trailing slash)
- `http://smarttrack.bccbsis.com/...` (http instead of https)

---

## 🧪 Test in Browser First

Before testing in app:

1. **Open browser**
2. **Visit:** `https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/test_connection.php`
3. **Should see JSON:**
   ```json
   {"success":true,"message":"Connection successful","database":"u520834156_dbSmartTrack"}
   ```

If browser works but app doesn't → App code or network issue

---

## 📱 Check Console Output

When you tap "Test Connection", the console should show:

```
🔍 Testing connection to: https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/test_connection.php
📡 Response status: 200
📡 Response text: {"success":true,"message":"Connection successful",...}
```

**If you see HTML instead:**
- Check what the actual response is
- Might be a redirect or error page
- Check server logs

---

## 🚀 Most Likely Fix

**If using Expo Go or development build:**
1. **Reload the app** (shake device → Reload)
2. **Try test connection again**

**If using built APK:**
1. **Rebuild APK** with updated code
2. **Install new APK**
3. **Test again**

---

## 💡 Check These

- [ ] API works in browser ✅
- [ ] App console shows the correct URL
- [ ] App console shows response status
- [ ] App has been reloaded/rebuilt with latest code
- [ ] API URL has no trailing slash
- [ ] Using HTTPS (not HTTP)

---

**Check the console logs when you tap "Test Connection" - they'll show exactly what's happening!** 🔍

