# 📱 Build APK Instead of AAB

## ❌ Problem

EAS built an **AAB** (Android App Bundle) file, but you need an **APK** for direct installation.

**AAB vs APK:**
- **AAB** = For Google Play Store (optimized, smaller)
- **APK** = For direct installation on devices (what you need!)

---

## ✅ Solution: Build APK

I've updated `eas.json` to build APK files. Now rebuild:

### Option 1: Build Preview APK (Recommended)

```bash
eas build --platform android --profile preview
```

This will build an APK file that you can install directly.

### Option 2: Build Production APK

```bash
eas build --platform android --profile production
```

---

## 🔧 What I Changed

Updated `eas.json` to specify `"buildType": "apk"` for all build profiles.

Now all builds will produce APK files instead of AAB files.

---

## 📥 After Build

You'll get a download link like:
```
https://expo.dev/artifacts/eas/XXXXX.apk
```

Download and install on your Android device!

---

## 🚀 Quick Build Command

```bash
eas build --platform android --profile preview
```

This will give you an APK file! ✅

---

## 💡 Alternative: Convert AAB to APK

If you want to use the AAB you already have, you can convert it using:
- **bundletool** (Google's tool)
- Online converters

But it's easier to just rebuild with APK! 😄

---

**Rebuild now and you'll get an APK file!** 🎉

