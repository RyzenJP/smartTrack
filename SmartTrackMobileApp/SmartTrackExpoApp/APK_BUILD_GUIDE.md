# 📱 Smart Track Mobile APK Build Guide

## 🚀 **Method 1: EAS Build (Recommended - Cloud Build)**

### Prerequisites:
- Expo account (free)
- EAS CLI installed

### Steps:

1. **Install EAS CLI:**
   ```bash
   npm install -g @expo/eas-cli
   ```

2. **Login to Expo:**
   ```bash
   eas login
   ```

3. **Initialize EAS:**
   ```bash
   eas build:configure
   ```

4. **Build APK:**
   ```bash
   eas build --platform android --profile preview
   ```

5. **Download APK:**
   - Go to https://expo.dev
   - Find your project
   - Download the APK file

---

## 🔧 **Method 2: Local Build (Advanced)**

### Prerequisites:
- Android Studio installed
- Android SDK configured
- Java Development Kit (JDK)

### Steps:

1. **Install Expo CLI:**
   ```bash
   npm install -g @expo/cli
   ```

2. **Prebuild for Android:**
   ```bash
   npx expo prebuild --platform android
   ```

3. **Build APK:**
   ```bash
   npx expo run:android --variant release
   ```

---

## 📦 **Method 3: Expo Development Build (Easiest)**

### Steps:

1. **Create Development Build:**
   ```bash
   npx expo install expo-dev-client
   ```

2. **Build Development APK:**
   ```bash
   npx expo run:android
   ```

3. **Install on Device:**
   - APK will be automatically installed on connected device
   - Or find APK in: `android/app/build/outputs/apk/`

---

## 🎯 **Quick APK Build (Recommended for You)**

### Step 1: Install EAS CLI
```bash
npm install -g @expo/eas-cli
```

### Step 2: Login to Expo
```bash
eas login
```
(You'll need to create a free Expo account)

### Step 3: Configure Build
```bash
eas build:configure
```

### Step 4: Build APK
```bash
eas build --platform android --profile preview
```

### Step 5: Download APK
- Go to https://expo.dev
- Find your project
- Download the APK file
- Install on Android devices

---

## 📋 **APK Features Included:**

✅ **GPS Tracking** - Real-time location tracking
✅ **Custom Frequency** - Set any interval (1 sec to hours)
✅ **Settings Screen** - Configure device ID, API URL, etc.
✅ **Background Tracking** - Works when app is minimized
✅ **Offline Support** - Stores data when offline
✅ **Production Ready** - Optimized for deployment

---

## 🔧 **Configuration for Production:**

### Update API URL for Production:
In your mobile app settings, change:
- **Development:** `http://192.168.1.2/trackingv2/trackingv2`
- **Production:** `https://yourdomain.com/trackingv2/trackingv2`

### Environment Variables:
You can set different API URLs for different environments:
- **Development:** Local server
- **Staging:** Test server
- **Production:** Live server

---

## 📱 **Installation Instructions:**

1. **Download APK** from Expo dashboard
2. **Enable Unknown Sources** on Android device:
   - Settings → Security → Unknown Sources (ON)
3. **Install APK** on device
4. **Configure Settings** in the app
5. **Start Tracking** - Ready to use!

---

## 🚀 **Deployment Checklist:**

- [ ] APK built successfully
- [ ] Tested on real device
- [ ] GPS permissions working
- [ ] API connection working
- [ ] Frequency settings working
- [ ] Background tracking working
- [ ] Ready for distribution

---

## 📞 **Support:**

If you encounter any issues:
1. Check Expo documentation: https://docs.expo.dev
2. Check EAS Build logs in Expo dashboard
3. Verify Android SDK installation
4. Check device permissions

**Your Smart Track Mobile APK is ready for deployment!** 🎉
