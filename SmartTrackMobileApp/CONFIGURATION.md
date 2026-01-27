# ⚙️ Mobile App Configuration Guide

## 📱 **How to Configure Your Smart Track Mobile App**

### **Step 1: Launch the App**
1. **Open the Smart Track app** on your device
2. **You'll see two screens:**
   - **Tracking Screen** - Start/Stop GPS tracking
   - **Settings Screen** - Configure the app

### **Step 2: Configure Settings**

#### **Go to Settings Screen:**
1. **Tap the settings icon** in the app
2. **Fill in these details:**

#### **Required Settings:**
- **Device Name:** `Driver John's Phone` (or your device name)
- **API URL:** `http://localhost/trackingv2/trackingv2`
- **API Key:** `your-unique-key-here` (create a unique key)
- **Frequency:** `30` (seconds between GPS updates)

#### **Example Configuration:**
```
Device Name: John's iPhone
API URL: http://localhost/trackingv2/trackingv2
API Key: MOBILE-001
Frequency: 30
```

### **Step 3: Test Connection**

#### **Backend API Test:**
1. **Open your browser**
2. **Go to:** `http://localhost/trackingv2/trackingv2/api/test_connection.php`
3. **Should show:** "Database connection successful!"

#### **Mobile App Test:**
1. **In the mobile app settings**
2. **Tap "Test Connection"** button
3. **Should show:** "Connection successful!"

### **Step 4: Start Tracking**

#### **Begin GPS Tracking:**
1. **Go to Tracking Screen**
2. **Tap "Start Tracking"** button
3. **Allow location permissions** when prompted
4. **GPS tracking starts** automatically

#### **What Happens:**
- **GPS data sent** to your backend every 30 seconds
- **Location appears** in your Smart Track dashboard
- **Device shows up** in GPS Devices page
- **Can be assigned** to vehicles

### **Step 5: Monitor in Dashboard**

#### **Check Your Backend:**
1. **Go to:** `http://localhost/trackingv2/trackingv2/super_admin/gps.php`
2. **Look for your mobile device** in the GPS Devices table
3. **Assign to a vehicle** if needed
4. **View live tracking** on the map

## 🔧 **Advanced Configuration**

### **Custom API Settings:**
```javascript
// In the mobile app settings
API URL: http://your-server.com/trackingv2/trackingv2
API Key: MOBILE-001
Frequency: 60  // Send location every 60 seconds
```

### **Background Tracking:**
- **App continues tracking** when minimized
- **GPS works** in background
- **Battery optimized** for long-term use

### **Multiple Devices:**
- **Each device** needs unique API Key
- **Example keys:** MOBILE-001, MOBILE-002, MOBILE-003
- **All devices** appear in GPS Devices page

## 🎯 **Ready to Use!**

Your mobile app is now configured and ready to replace ESP32 devices for GPS tracking!
