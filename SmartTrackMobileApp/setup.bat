@echo off
echo ========================================
echo Smart Track Mobile App Setup
echo ========================================
echo.

echo Checking Node.js installation...
node --version
if %errorlevel% neq 0 (
    echo ERROR: Node.js not found. Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo.
echo Checking npm installation...
npm --version
if %errorlevel% neq 0 (
    echo ERROR: npm not found. Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo.
echo Installing React Native CLI...
npm install -g @react-native-community/cli

echo.
echo Installing project dependencies...
npm install

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Install Android Studio from https://developer.android.com/studio
echo 2. Set up Android SDK and environment variables
echo 3. Run: npm run android
echo.
echo For detailed instructions, see INSTALLATION_GUIDE.md
echo.
pause
