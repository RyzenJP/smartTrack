# Android Development Setup

## 🛠️ **Step-by-Step Android Setup**

### **1. Install Android Studio**
- Download from: https://developer.android.com/studio
- Install with default settings
- Open Android Studio

### **2. Install Android SDK**
1. In Android Studio, go to **Tools > SDK Manager**
2. Install these components:
   - ✅ **Android SDK Platform 33** (or latest)
   - ✅ **Android SDK Build-Tools**
   - ✅ **Android SDK Platform-Tools**
   - ✅ **Android SDK Tools**

### **3. Set Up Environment Variables**

#### **Windows:**
Add these to your System Environment Variables:
```
ANDROID_HOME=C:\Users\%USERNAME%\AppData\Local\Android\Sdk
JAVA_HOME=C:\Program Files\Java\jdk-11.0.x
```

#### **Mac/Linux:**
Add to your `~/.bashrc` or `~/.zshrc`:
```bash
export ANDROID_HOME=$HOME/Library/Android/sdk
export JAVA_HOME=/Library/Java/JavaVirtualMachines/jdk-11.0.x.jdk/Contents/Home
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/tools
export PATH=$PATH:$ANDROID_HOME/tools/bin
export PATH=$PATH:$ANDROID_HOME/platform-tools
```

### **4. Create Android Virtual Device (AVD)**
1. In Android Studio, go to **Tools > AVD Manager**
2. Click **Create Virtual Device**
3. Choose **Phone > Pixel 4** (or any phone)
4. Download **API 33** (or latest)
5. Click **Finish**

### **5. Test Your Setup**
```bash
# Check if Android SDK is working
adb devices

# Should show your emulator or connected device
```

## 🚀 **Ready to Run!**
Your Android development environment is now ready for the mobile app!
