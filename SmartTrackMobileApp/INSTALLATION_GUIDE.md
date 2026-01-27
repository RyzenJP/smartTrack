# Smart Track Mobile App - Installation Guide

## 🚀 **Prerequisites**

### **Required Software:**
1. **Node.js** (v16 or higher) - [Download here](https://nodejs.org/)
2. **Java Development Kit (JDK)** - [Download JDK 11](https://adoptium.net/)
3. **Android Studio** - [Download here](https://developer.android.com/studio)
4. **Git** - [Download here](https://git-scm.com/)

### **For iOS (macOS only):**
- **Xcode** (latest version from App Store)
- **CocoaPods** - `sudo gem install cocoapods`

## 📋 **Installation Steps**

### **Step 1: Install Node.js and npm**
```bash
# Download and install Node.js from https://nodejs.org/
# Verify installation:
node --version
npm --version
```

### **Step 2: Install React Native CLI**
```bash
npm install -g @react-native-community/cli
```

### **Step 3: Install Android Studio**
1. Download Android Studio from https://developer.android.com/studio
2. Install with default settings
3. Open Android Studio and install:
   - Android SDK
   - Android SDK Platform
   - Android Virtual Device
   - Android SDK Build-Tools

### **Step 4: Set up Environment Variables**
Add these to your system environment variables:

**Windows:**
```
ANDROID_HOME=C:\Users\%USERNAME%\AppData\Local\Android\Sdk
JAVA_HOME=C:\Program Files\Java\jdk-11.0.x
```

**macOS/Linux:**
```bash
export ANDROID_HOME=$HOME/Library/Android/sdk
export JAVA_HOME=/Library/Java/JavaVirtualMachines/jdk-11.0.x.jdk/Contents/Home
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/tools
export PATH=$PATH:$ANDROID_HOME/tools/bin
export PATH=$PATH:$ANDROID_HOME/platform-tools
```

### **Step 5: Install Project Dependencies**
```bash
cd SmartTrackMobileApp
npm install
```

### **Step 6: Configure Backend API**
1. Copy the mobile API files to your backend:
   ```bash
   cp mobile_gps_api.php ../api/
   cp test_connection.php ../api/
   ```

2. Update the API URL in the mobile app settings to match your server.

## 🚀 **Running the App**

### **Android:**
```bash
# Start Metro bundler
npm start

# In a new terminal, run Android app
npm run android
```

### **iOS (macOS only):**
```bash
# Install iOS dependencies
cd ios && pod install && cd ..

# Start Metro bundler
npm start

# In a new terminal, run iOS app
npm run ios
```

## 🔧 **Configuration**

### **Backend API Setup:**
1. Ensure your PHP backend is running
2. Update the API URL in the mobile app settings
3. Test the connection using the mobile app

### **GPS Permissions:**
The app will request location permissions when first launched.

## 📱 **Testing the App**

1. **Launch the app** on your device/emulator
2. **Configure settings:**
   - Device Name: Your device name
   - API URL: Your server URL
   - API Key: Your authentication key
   - Frequency: How often to send location (seconds)
3. **Start tracking** and verify data appears in your backend

## 🛠️ **Troubleshooting**

### **Common Issues:**
- **Metro bundler not starting:** Clear cache with `npx react-native start --reset-cache`
- **Android build fails:** Check Android SDK installation
- **iOS build fails:** Run `cd ios && pod install`
- **Location not working:** Check device permissions

### **Debug Mode:**
Enable debug logging in the mobile app for troubleshooting.

## 📞 **Support**

For issues and questions:
1. Check the console logs
2. Verify network connectivity
3. Ensure backend is running
4. Check database connections
