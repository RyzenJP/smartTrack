# 📱 Quick APK Build Guide

## 🚀 **Method 1: Development APK (Easiest)**

### Step 1: Run Development Build
```bash
npx expo run:android
```

### Step 2: Find APK
The APK will be created at:
```
android/app/build/outputs/apk/debug/app-debug.apk
```

### Step 3: Install APK
1. Copy APK to your Android device
2. Enable "Unknown Sources" in Android settings
3. Install the APK

---

## 🔧 **Method 2: Using Expo Go (No APK needed)**

### Step 1: Install Expo Go
- Download from Google Play Store
- Install on your Android device

### Step 2: Start Development Server
```bash
npx expo start
```

### Step 3: Connect
- Scan QR code with Expo Go
- App loads directly on your device

---

## 📦 **Method 3: Production APK (Advanced)**

### Prerequisites:
- Android Studio installed
- Android SDK configured
- Java Development Kit

### Steps:
1. **Install Android Studio**
2. **Configure Android SDK**
3. **Run build script:**
   ```bash
   build_apk.bat
   ```

---

## 🎯 **Recommended Approach:**

### For Testing:
- **Use Expo Go** - No APK needed, instant testing
- **Development APK** - For offline testing

### For Production:
- **Use EAS Build** - Cloud build service
- **Local build** - Requires Android Studio

---

## 📱 **APK Features:**

✅ **GPS Tracking** - Real-time location tracking
✅ **Custom Frequency** - Set any interval (1 sec to hours)
✅ **Settings Screen** - Configure device ID, API URL
✅ **Background Tracking** - Works when minimized
✅ **Production Ready** - Optimized for deployment

---

## 🚀 **Quick Start:**

1. **Install Expo Go** on your phone
2. **Run:** `npx expo start`
3. **Scan QR code** with Expo Go
4. **Test your app** immediately!

**No APK building required for development!** 🎉
