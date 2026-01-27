# Smart Track Mobile App - System Specification

## Table 1: Mobile Application System Specifications

| Components | Minimum Requirements |
|------------|----------------------|
| **Operating System** | Android 6.0 (API Level 23) or higher |
| **Device Type** | Smartphone or Tablet |
| **RAM** | 2 GB minimum, 4 GB recommended |
| **Storage** | 50 MB free space |
| **GPS** | Built-in GPS receiver required |
| **Network** | Wi-Fi or Mobile Data (3G/4G/5G) |
| **Location Services** | Must be enabled with High Accuracy mode |
| **Permissions** | Location (Foreground & Background), Internet, Network State |
| **Screen Resolution** | 320x480 dp minimum (supports all screen sizes) |
| **Orientation** | Portrait mode |

## Table 2: Backend Server Requirements

| Components | Minimum Requirements |
|------------|----------------------|
| **API Server** | PHP 7.4 or higher |
| **Database** | MySQL 5.7 or MariaDB 10.3+ |
| **API Endpoint** | `https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/mobile_gps_api.php` |
| **Network** | Internet connection (HTTPS recommended) |
| **SSL Certificate** | Required for production (HTTPS) |

## Table 3: Application Features

| Feature | Description |
|---------|-------------|
| **GPS Tracking** | Real-time location tracking with configurable frequency (1-300 seconds) |
| **Background Tracking** | Continues tracking when app is minimized |
| **Settings Management** | Device ID, API URL, API Key, and tracking frequency configuration |
| **Connection Testing** | Test API connectivity before starting tracking |
| **Offline Support** | Stores location data locally when network unavailable |
| **Battery Monitoring** | Displays device battery level |

## Table 4: Technical Specifications

| Component | Specification |
|-----------|---------------|
| **Framework** | React Native 0.81.5 |
| **Build System** | Expo SDK ~54.0.18 |
| **Package Name** | com.smarttrack.mobile |
| **Version** | 1.0.1 (Version Code: 2) |
| **Minimum SDK** | Android 23 (Android 6.0) |
| **Target SDK** | Android 34+ |
| **Location Library** | Expo Location 19.0.7 |
| **Storage** | AsyncStorage 2.2.0 |
| **UI Library** | React Native Paper 5.14.5 |

## Table 5: Required Permissions

| Permission | Purpose |
|-----------|---------|
| **ACCESS_FINE_LOCATION** | High accuracy GPS location tracking |
| **ACCESS_COARSE_LOCATION** | Network-based location (fallback) |
| **ACCESS_BACKGROUND_LOCATION** | Continuous tracking when app is in background |
| **INTERNET** | Send GPS data to backend server |
| **ACCESS_NETWORK_STATE** | Check network connectivity status |

## Table 6: Configuration Settings

| Setting | Default Value | Description |
|---------|---------------|-------------|
| **Device ID** | MOBILE-001 | Unique identifier for the device |
| **Device Name** | Driver Phone | Display name for the device |
| **API URL** | https://smarttrack.bccbsis.com/trackingv2/trackingv2 | Backend server URL |
| **API Key** | ST176121957633164140 | Authentication key for API access |
| **Tracking Frequency** | 30 seconds | Interval between GPS location updates |

---

**Note:** All settings can be configured within the app's Settings screen. The app requires location permissions to be granted for GPS tracking functionality.

