# 📍 Set Up Location in Android Emulator

## ❌ Problem

**Android emulators don't have real GPS!** That's why you're getting "Current location is unavailable."

---

## ✅ Solution: Set Mock Location in Emulator

### Method 1: Using Emulator Controls (Easiest)

1. **Open Android Studio**
2. **With emulator running**, click the **"..." (three dots)** button on the emulator toolbar
3. **Go to:** **"Location"** tab
4. **Set coordinates:**
   - **Latitude:** `10.3157` (Manila, Philippines)
   - **Longitude:** `123.8854`
5. **Click:** **"Send"**
6. **Go back to Smart Track app**
7. **Try "Start Tracking"** again

### Method 2: Using Extended Controls

1. **Click the "..." button** on emulator toolbar
2. **Go to:** **"Extended controls"** (or press `Ctrl + Shift + A`)
3. **Select:** **"Location"** from left menu
4. **Enter coordinates:**
   - **Latitude:** `10.3157`
   - **Longitude:** `123.8854`
5. **Click:** **"Set Location"**
6. **Go back to app** and try again

### Method 3: Using ADB Command

**In terminal/command prompt:**

```bash
adb emu geo fix 123.8854 10.3157
```

This sets:
- Longitude: 123.8854
- Latitude: 10.3157
- (Manila, Philippines coordinates)

---

## 🗺️ Sample Coordinates

### Manila, Philippines
- **Latitude:** `10.3157`
- **Longitude:** `123.8854`

### Bago City, Negros Occidental
- **Latitude:** `10.5305`
- **Longitude:** `122.8427`

### Any Location
- Use Google Maps to find coordinates
- Right-click on map → "What's here?"
- Copy latitude and longitude

---

## 🔄 Simulate Movement

To test tracking with movement:

### Method 1: Multiple Locations

1. **Set first location:**
   - Latitude: `10.3157`
   - Longitude: `123.8854`

2. **Wait 30 seconds** (your tracking interval)

3. **Set second location:**
   - Latitude: `10.3160`
   - Longitude: `123.8860`

4. **Repeat** to simulate movement

### Method 2: Using GPX/KML Route

1. **Create a GPX file** with route points
2. **In emulator controls:**
   - Go to **"Location"** tab
   - Click **"Load GPX/KML"**
   - Select your route file
   - Click **"Play"** to simulate movement

---

## ⚙️ Enable Mock Location in App

**Important:** Make sure mock location is enabled:

1. **Settings** → **Developer options**
2. **Find:** **"Select mock location app"**
3. **Select:** **"Smart Track"** (or your app)

**If Developer options not visible:**
1. **Settings** → **About phone**
2. **Tap "Build number" 7 times**
3. **Developer options** will appear

---

## 🧪 Quick Test

1. **Set location in emulator:**
   - Latitude: `10.3157`
   - Longitude: `123.8854`

2. **Open Smart Track app**

3. **Tap "Start Tracking"**

4. **Should work now!** ✅

---

## 💡 Tips

- **Emulator location is instant** (no GPS lock needed)
- **You can set any coordinates** (even fake ones for testing)
- **Movement simulation** helps test tracking features
- **Use real coordinates** to test with actual map locations

---

## 🚀 After Setting Location

Once location is set in emulator:

1. **Open Smart Track app**
2. **Tap "Start Tracking"**
3. **Location should be detected immediately**
4. **GPS data will be sent to your server**
5. **Check admin panel** to see the location!

---

## 📱 Alternative: Use Real Device

If you want to test with real GPS:

1. **Build APK** with EAS
2. **Install on real Android device**
3. **Real GPS will work** (no mock location needed)

But for development/testing, emulator with mock location is perfect! ✅

---

**Quick fix:** Open emulator controls → Location → Set coordinates → Send → Try app again! 🎯

