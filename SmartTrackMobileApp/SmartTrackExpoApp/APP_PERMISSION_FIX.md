# 🔧 Fix App Location Permission (Google Maps Works)

## ✅ Good News

**Google Maps works** = Your GPS hardware is fine! ✅

The issue is **app-specific permissions** for Smart Track.

---

## 🔍 The Problem

The Smart Track app doesn't have location permission, or it's set incorrectly.

---

## ✅ Step-by-Step Fix

### Step 1: Check App Permissions

**On Android:**

1. **Settings** → **Apps** → **Smart Track**
2. **Tap:** **Permissions**
3. **Find:** **Location**
4. **Check current setting:**
   - If "Denied" → Change to **"Allow all the time"**
   - If "Allow only while using app" → Change to **"Allow all the time"**
   - If already "Allow all the time" → Try toggling OFF then ON

### Step 2: Enable Background Location

**On Android:**

1. **Settings** → **Apps** → **Smart Track**
2. **Permissions** → **Location**
3. **Tap:** **Advanced** or **Additional permissions**
4. **Background location** → **Allow**

### Step 3: Restart App

After changing permissions:

1. **Close** Smart Track app completely (swipe away from recent apps)
2. **Reopen** the app
3. **Try "Start Tracking"** again

---

## 🧪 Test Steps

1. **Open Smart Track app**
2. **Go to Tracking screen**
3. **Tap "Start Tracking"**
4. **If permission dialog appears:**
   - Tap **"Allow"** or **"Allow all the time"**
   - NOT "Allow only while using app"
5. **Wait 10-20 seconds** for GPS to get location
6. **Should work now!** ✅

---

## ⚠️ Common Mistakes

### ❌ Wrong: "Allow only while using app"
- App can only get location when it's open
- Stops tracking when you minimize the app
- **Fix:** Change to "Allow all the time"

### ❌ Wrong: "Denied" or "Ask every time"
- App can't get location at all
- **Fix:** Change to "Allow all the time"

### ✅ Correct: "Allow all the time"
- App can get location even when minimized
- Continuous GPS tracking works
- **This is what you need!**

---

## 🔄 If Still Not Working

### Option 1: Uninstall and Reinstall

1. **Uninstall** Smart Track app
2. **Reinstall** from APK
3. **When opening for first time:**
   - Grant location permission
   - Select **"Allow all the time"**
4. **Try tracking**

### Option 2: Clear App Data

1. **Settings** → **Apps** → **Smart Track**
2. **Storage** → **Clear Data**
3. **Reopen app**
4. **Grant permissions** when asked
5. **Try tracking**

---

## ✅ Quick Checklist

- [ ] Google Maps works (GPS is fine) ✅
- [ ] Smart Track app has location permission
- [ ] Permission is set to "Allow all the time" (not "While using app")
- [ ] Background location is enabled
- [ ] App restarted after changing permissions
- [ ] Try "Start Tracking" again

---

## 💡 Why This Happens

**Google Maps works because:**
- It's a system app with full permissions
- It has been granted location access before

**Smart Track app doesn't work because:**
- It's a new app that needs permission
- Permission might be denied or set incorrectly
- Needs "Allow all the time" for continuous tracking

---

## 🚀 After Fixing

Once permissions are correct:

1. **Tap "Start Tracking"**
2. **Allow permission** if asked
3. **GPS tracking starts**
4. **Location sent to server** every 30 seconds
5. **Check admin panel** to see live tracking!

---

**Most likely fix:** Settings → Apps → Smart Track → Permissions → Location → "Allow all the time" ✅

