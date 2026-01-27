# 📍 Fix Location Error in Mobile App

## ❌ Error You're Seeing

"Location Error: Unable to get location"

This means the app can't access your device's GPS location.

---

## ✅ Step-by-Step Fix

### Step 1: Grant Location Permissions

**On Android:**

1. **Go to:** Settings → Apps → Smart Track
2. **Tap:** Permissions
3. **Find:** Location
4. **Select:** "Allow all the time" (not just "While using app")
5. **Go back to app** and try again

**On iOS:**

1. **Go to:** Settings → Privacy → Location Services
2. **Find:** Smart Track
3. **Select:** "Always" (not just "While Using App")
4. **Go back to app** and try again

---

### Step 2: Enable Location Services

**On Android:**

1. **Settings** → **Location**
2. **Turn ON** Location
3. **Mode:** Select **"High accuracy"** (uses GPS + Wi-Fi + Mobile networks)
4. **Go back to app** and try again

**On iOS:**

1. **Settings** → **Privacy** → **Location Services**
2. **Turn ON** Location Services
3. **Go back to app** and try again

---

### Step 3: Go Outside (For First GPS Fix)

**GPS needs clear sky view:**

1. **Go outside** (not indoors)
2. **Wait 30-60 seconds** for GPS to lock
3. **Open Google Maps** first to verify GPS works
4. **Then open Smart Track app** and try again

**Why?** GPS signals are weak indoors. The first GPS fix (cold start) needs clear sky view.

---

### Step 4: Check Other Apps

**Test if GPS works on your device:**

1. **Open Google Maps**
2. **See if your location appears**
3. **If Maps works** → GPS is fine, check app permissions
4. **If Maps doesn't work** → Enable location services first

---

### Step 5: Restart App

After changing permissions:

1. **Close** Smart Track app completely
2. **Reopen** the app
3. **Try "Start Tracking"** again

---

### Step 6: Restart Device (If Still Not Working)

If nothing works:

1. **Restart** your device
2. **Open** Smart Track app
3. **Grant permissions** when asked
4. **Try again**

---

## 🔍 Common Issues

### Issue 1: Permission Denied

**Symptom:** App asks for permission but gets denied

**Fix:**
- Go to device Settings → Apps → Smart Track → Permissions
- Manually enable Location permission
- Select "Allow all the time"

### Issue 2: Background Location Not Allowed

**Symptom:** Works when app is open, but stops when minimized

**Fix:**
- Enable "Background location" permission
- Settings → Apps → Smart Track → Permissions → Location → Advanced → Background location → Allow

### Issue 3: GPS Signal Weak

**Symptom:** "Unable to get location" even with permissions

**Fix:**
- Go outside (GPS needs clear sky)
- Wait 30-60 seconds for GPS lock
- Check if Google Maps can get location
- If Maps works, GPS is fine - check app permissions

### Issue 4: Battery Saver Mode

**Symptom:** Location works sometimes but not always

**Fix:**
- Disable Battery Saver mode
- Settings → Battery → Turn off Battery Saver
- Or add Smart Track to "Unrestricted" apps

---

## ✅ Quick Checklist

- [ ] Location permission granted ("Allow all the time")
- [ ] Location services enabled on device
- [ ] Location mode set to "High accuracy" (Android)
- [ ] Background location enabled
- [ ] Went outside for GPS signal
- [ ] Google Maps can get location (test GPS)
- [ ] App restarted after changing permissions
- [ ] Device restarted (if still not working)

---

## 🧪 Test GPS

**Before using Smart Track:**

1. **Open Google Maps**
2. **See if blue dot appears** (your location)
3. **If Maps works** → GPS is fine, focus on app permissions
4. **If Maps doesn't work** → Fix device location settings first

---

## 💡 Tips

- **First time:** GPS takes 30-60 seconds to lock (cold start)
- **Indoors:** GPS is weak, go outside for better signal
- **Battery:** High accuracy GPS uses more battery (normal)
- **Background:** App needs "Allow all the time" permission for continuous tracking

---

## 🚀 After Fixing

Once location works:

1. **Tap "Start Tracking"**
2. **Allow permissions** when asked
3. **GPS tracking starts**
4. **Location data sent** to server every 30 seconds
5. **Check admin panel** to see live tracking!

---

**Most common fix:** Go to device Settings → Apps → Smart Track → Permissions → Location → "Allow all the time" ✅
