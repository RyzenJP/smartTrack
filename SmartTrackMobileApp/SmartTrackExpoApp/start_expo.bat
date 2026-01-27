@echo off
echo Starting Smart Track Mobile App...
echo.

cd /d "%~dp0"
echo Current directory: %CD%
echo.

echo Checking if we're in the correct directory...
if not exist "package.json" (
    echo ERROR: package.json not found!
    echo Please make sure you're running this from the SmartTrackExpoApp directory
    echo.
    pause
    exit /b 1
)

echo Starting Expo development server with tunnel mode...
echo This will create a public URL for your mobile app
echo You should see a QR code below:
echo.

npx expo start --tunnel

echo.
echo If you see a QR code above, scan it with Expo Go app!
echo Your mobile app will connect to: https://smarttrack.bccbsis.com
echo.
pause
