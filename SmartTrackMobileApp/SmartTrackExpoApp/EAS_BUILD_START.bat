@echo off
echo ========================================
echo Smart Track - EAS Build (Cloud)
echo ========================================
echo.

echo Starting EAS Build for Android APK...
echo This will build your app in the cloud (no local setup needed!)
echo.
echo Expected time: 15-20 minutes
echo.

eas build --platform android --profile preview

echo.
echo ========================================
echo Build Started!
echo ========================================
echo.
echo Check build status at: https://expo.dev
echo Or run: eas build:list
echo.
pause







