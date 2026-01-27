# 🔨 Local Android Build Guide

## ✅ Step 1: Prebuild (Completed)
```bash
npx expo prebuild
```
✅ **Done!** Native Android code generated.

## ⚠️ Step 2: Build APK (Requires Setup)

### Command:
```bash
cd android
.\gradlew.bat assembleRelease
```

### Requirements:
1. **Java JDK 11+** installed
   - Download: https://adoptium.net/
   - Verify: `java -version`

2. **Android Studio** installed
   - Download: https://developer.android.com/studio
   - Install Android SDK (API 33+)

3. **Environment Variables** set:
   ```powershell
   # Set ANDROID_HOME
   [System.Environment]::SetEnvironmentVariable('ANDROID_HOME', 'C:\Users\YourName\AppData\Local\Android\Sdk', 'User')
   
   # Set JAVA_HOME
   [System.Environment]::SetEnvironmentVariable('JAVA_HOME', 'C:\Program Files\Java\jdk-11', 'User')
   
   # Add to PATH
   $env:Path += ";$env:ANDROID_HOME\platform-tools"
   ```

4. **Restart Terminal** after setting environment variables

### If Build Succeeds:
APK will be at:
```
android/app/build/outputs/apk/release/app-release.apk
```

## 📱 Step 3: Install APK

### Option A: Using ADB (Android Debug Bridge)
```bash
adb install android/app/build/outputs/apk/release/app-release.apk
```

**Requirements:**
- Android device connected via USB
- USB debugging enabled on device
- ADB installed (comes with Android SDK)

### Option B: Manual Install
1. Copy APK to Android device
2. Enable "Unknown Sources" in Settings
3. Tap APK file to install

## 🚀 Alternative: EAS Build (Easier!)

If local build is too complicated, use **EAS Build** (cloud):

```bash
# No Android Studio needed!
eas build --platform android --profile preview
```

**Advantages:**
- ✅ No local setup required
- ✅ No Java/SDK installation
- ✅ Works on any computer
- ✅ Takes 15-20 minutes

## 🐛 Troubleshooting

### "gradlew.bat not found"
- Run `npx expo prebuild` first
- Check you're in the project root

### "Java not found"
- Install Java JDK 11+
- Set JAVA_HOME environment variable
- Restart terminal

### "Android SDK not found"
- Install Android Studio
- Set ANDROID_HOME environment variable
- Install Android SDK (API 33+)

### "Build failed"
- Check build logs in `android/app/build/outputs/logs/`
- Verify all dependencies installed
- Try `.\gradlew.bat clean` then rebuild

## ✅ Quick Checklist

Before building locally:
- [ ] Java JDK 11+ installed
- [ ] Android Studio installed
- [ ] Android SDK installed
- [ ] ANDROID_HOME set
- [ ] JAVA_HOME set
- [ ] Terminal restarted
- [ ] Prebuild completed

## 💡 Recommendation

**For first-time users:** Use **EAS Build** instead:
```bash
eas build --platform android --profile preview
```

**For advanced users:** Set up Android Studio and build locally for faster iterations.

---

**Current Status:**
- ✅ Prebuild: Complete
- ⚠️ Gradle Build: Needs Android Studio setup
- 💡 Recommendation: Use EAS Build for easier setup







