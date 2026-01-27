@echo off
echo Building Smart Track Mobile APK...
echo.

echo Step 1: Installing dependencies...
call npm install

echo.
echo Step 2: Building Android project...
call npx expo run:android --variant release

echo.
echo Step 3: Looking for APK...
if exist "android\app\build\outputs\apk\release\app-release.apk" (
    echo ✅ APK built successfully!
    echo Location: android\app\build\outputs\apk\release\app-release.apk
    echo.
    echo You can now install this APK on your Android device.
) else (
    echo ❌ APK not found. Build may have failed.
    echo.
    echo Alternative: Use Expo Go app for development
    echo 1. Install Expo Go from Google Play Store
    echo 2. Run: npx expo start
    echo 3. Scan QR code with Expo Go
)

echo.
echo Build process complete!
pause
