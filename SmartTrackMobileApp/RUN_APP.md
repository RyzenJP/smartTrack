# 🚀 How to Run Your Smart Track Mobile App

## **Method 1: Using Android Emulator**

### **1. Start Android Emulator**
```bash
# Open Android Studio
# Go to Tools > AVD Manager
# Click the "Play" button next to your virtual device
```

### **2. Run the Mobile App**
```bash
cd SmartTrackMobileApp

# Start Metro bundler (in one terminal)
npm start

# Run Android app (in another terminal)
npm run android
```

## **Method 2: Using Physical Android Device**

### **1. Enable Developer Options**
1. Go to **Settings > About Phone**
2. Tap **Build Number** 7 times
3. Go back to **Settings > Developer Options**
4. Enable **USB Debugging**

### **2. Connect Device**
```bash
# Connect your Android phone via USB
# Allow USB debugging when prompted

# Check if device is detected
adb devices
```

### **3. Run the App**
```bash
cd SmartTrackMobileApp
npm run android
```

## **Method 3: Build APK for Distribution**

### **1. Generate Release APK**
```bash
cd SmartTrackMobileApp

# For debug APK
npx react-native build-android --mode=debug

# For release APK
npx react-native build-android --mode=release
```

### **2. Find Your APK**
The APK will be in:
```
SmartTrackMobileApp/android/app/build/outputs/apk/debug/app-debug.apk
```

## 🎯 **What Happens Next**

1. **App launches** on your device/emulator
2. **Shows tracking screen** with start/stop button
3. **Settings screen** for configuration
4. **GPS permissions** requested automatically
5. **Ready to track!**

## 🔧 **Troubleshooting**

### **Common Issues:**
- **Metro bundler not starting:** Run `npx react-native start --reset-cache`
- **Android build fails:** Check Android SDK installation
- **Device not detected:** Enable USB debugging
- **App crashes:** Check console logs for errors

### **Debug Mode:**
```bash
# Enable debug logging
npx react-native log-android
```
