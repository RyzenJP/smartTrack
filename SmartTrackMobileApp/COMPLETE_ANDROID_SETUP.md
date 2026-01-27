# 🚀 Complete Android Setup for Smart Track Mobile App

## **Current Status:**
✅ Node.js - Working  
✅ npm - Working  
✅ JDK - Working  
✅ Android Studio - Installed  
❌ Android SDK - Need to install  
❌ Android Emulator - Need to create  

## **Step 1: Install Android SDK**

### **Open Android Studio:**
1. **Launch Android Studio**
2. **Go to:** `Tools > SDK Manager`
3. **Install these components:**
   - ✅ **Android SDK Platform 33** (or latest)
   - ✅ **Android SDK Build-Tools 33.0.0**
   - ✅ **Android SDK Platform-Tools**
   - ✅ **Android SDK Tools**
   - ✅ **Android Emulator**

### **Set SDK Location:**
- **Default location:** `C:\Users\cjsar\AppData\Local\Android\Sdk`
- **Note this path** - you'll need it for environment variables

## **Step 2: Create Android Virtual Device (AVD)**

### **In Android Studio:**
1. **Go to:** `Tools > AVD Manager`
2. **Click:** `Create Virtual Device`
3. **Choose:** `Phone > Pixel 4` (or any phone)
4. **Download:** `API 33` (or latest)
5. **Click:** `Finish`
6. **Click:** `Play` button to start emulator

## **Step 3: Set Environment Variables**

### **Windows Environment Variables:**
1. **Press:** `Win + R`, type `sysdm.cpl`, press Enter
2. **Click:** `Environment Variables`
3. **Add these variables:**

```
ANDROID_HOME=C:\Users\cjsar\AppData\Local\Android\Sdk
JAVA_HOME=C:\Program Files\Java\jdk-11.0.x
```

4. **Add to PATH:**
```
%ANDROID_HOME%\emulator
%ANDROID_HOME%\tools
%ANDROID_HOME%\tools\bin
%ANDROID_HOME%\platform-tools
```

## **Step 4: Test Your Setup**

### **Open Command Prompt as Administrator:**
```bash
# Check if Android SDK is working
adb devices

# Should show your emulator
```

### **Start Android Emulator:**
```bash
# Start emulator manually
C:\Users\cjsar\AppData\Local\Android\Sdk\emulator\emulator -avd Pixel_4_API_33
```

## **Step 5: Run the Mobile App**

### **In SmartTrackMobileApp folder:**
```bash
# Start Metro bundler
npm start

# In another terminal, run the app
npm run android
```

## **Alternative: Use Physical Device**

### **Enable Developer Options:**
1. **Go to:** `Settings > About Phone`
2. **Tap:** `Build Number` 7 times
3. **Go to:** `Settings > Developer Options`
4. **Enable:** `USB Debugging`

### **Connect Device:**
```bash
# Connect Android phone via USB
# Allow USB debugging when prompted

# Check if device is detected
adb devices
```

## **Step 6: Configure the App**

### **When App Launches:**
1. **Go to Settings screen**
2. **Configure:**
   - **Device Name:** `Driver John's Phone`
   - **API URL:** `http://localhost/trackingv2/trackingv2`
   - **API Key:** `MOBILE-001`
   - **Frequency:** `30`

3. **Test Connection**
4. **Start GPS Tracking**

## **🎯 Ready to Use!**

Your mobile app will now:
- ✅ **Send GPS data** to your backend
- ✅ **Appear in GPS Devices** page
- ✅ **Show on live map** in dashboard
- ✅ **Replace ESP32 devices** for tracking

## **🔧 Troubleshooting**

### **If emulator won't start:**
```bash
# Check if emulator is running
adb devices

# Start emulator manually
C:\Users\cjsar\AppData\Local\Android\Sdk\emulator\emulator -avd YOUR_AVD_NAME
```

### **If app won't install:**
```bash
# Clear cache and try again
npx react-native start --reset-cache
npm run android
```

### **If build fails:**
```bash
# Check Android SDK installation
npx react-native doctor
```
