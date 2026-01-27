# Smart Track Mobile App

A React Native mobile application for vehicle tracking that replaces ESP32 devices. This app sends GPS location data to your existing PHP backend.

## Features

- **GPS Tracking**: Real-time location tracking with configurable frequency
- **Settings Management**: Device configuration with unique keys and API settings
- **Background Tracking**: Continues tracking even when app is in background
- **Offline Support**: Stores data locally when network is unavailable
- **Simple Interface**: Easy-to-use settings and tracking controls

## Prerequisites

- Node.js (v16 or higher)
- React Native CLI
- Android Studio (for Android development)
- Xcode (for iOS development, macOS only)
- Your existing PHP backend running

## Installation

1. **Install dependencies:**
   ```bash
   cd SmartTrackMobileApp
   npm install
   ```

2. **For iOS (macOS only):**
   ```bash
   cd ios
   pod install
   cd ..
   ```

3. **Configure your backend:**
   - Copy `mobile_gps_api.php` and `test_connection.php` to your PHP backend's `api/` directory
   - Update the database connection path in these files
   - Ensure your database has the required tables (see Database Setup below)

## Database Setup

Perfect! You already have both `gps_logs` and `gps_devices` tables. The mobile app will work seamlessly with your existing database structure.

### Your Existing Database Structure:

**gps_logs table:**
- `id` (int, primary key, auto_increment)
- `device_id` (varchar(50))
- `imei` (varchar(20))
- `vehicle_id` (int, nullable)
- `status` (enum: 'active', 'inactive', 'maintenance')
- `last_update` (datetime)
- `battery_level` (int(3))
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `lat` (double) - latitude
- `lng` (double) - longitude  
- `speed` (decimal(5,2))

**gps_devices table:**
- `id` (int, primary key, auto_increment)
- `device_id` (varchar(50))
- `imei` (varchar(20))
- `vehicle_id` (int, nullable)
- `status` (enum: 'active', 'inactive', 'maintenance')
- `last_update` (datetime)
- `battery_level` (int(3))
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `lat` (double) - last known latitude
- `lng` (double) - last known longitude
- `speed` (decimal(5,2)) - last known speed

### Add Mobile Device (Optional)
To add a sample mobile device to your `gps_devices` table:

```bash
mysql -u your_username -p your_database < add_mobile_device.sql
```

This will add a mobile device entry that the mobile app can use for testing.

## Running the App

### Android
```bash
npm run android
```

### iOS
```bash
npm run ios
```

## Configuration

1. **First Launch**: The app will show the settings screen
2. **Configure Settings**:
   - **Device Name**: Unique name for this device (e.g., "Vehicle-001")
   - **API URL**: Your PHP backend URL (e.g., "http://your-server.com/trackingv2")
   - **API Key**: Authentication key for your backend
   - **Frequency**: How often to send location (10-300 seconds)
   - **Vehicle ID**: Optional vehicle assignment

3. **Start Tracking**: Once configured, you can start/stop tracking

## API Integration

The app sends GPS data to your PHP backend using these endpoints:

- **Test Connection**: `POST /api/test_connection.php`
- **GPS Data**: `POST /api/mobile_gps_api.php`

### Data Format
```json
{
  "device_id": "MOBILE-1234567890-abc123",
  "device_name": "Vehicle-001",
  "vehicle_id": "VEH-001",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "speed": 25.5,
  "accuracy": 5.0,
  "timestamp": "2024-01-01T12:00:00Z",
  "api_key": "your-api-key"
}
```

## Permissions

The app requires these permissions:

### Android
- `ACCESS_FINE_LOCATION`
- `ACCESS_COARSE_LOCATION`
- `ACCESS_BACKGROUND_LOCATION`
- `INTERNET`
- `WAKE_LOCK`

### iOS
- Location (Always and When In Use)

## Troubleshooting

### Common Issues

1. **Location not updating**: Check location permissions
2. **Connection failed**: Verify API URL and network connectivity
3. **App crashes**: Check device logs for error details

### Debug Mode
Enable debug logging by setting `__DEV__ = true` in your development build.

## Security Notes

- Store API keys securely
- Use HTTPS in production
- Implement proper authentication
- Validate all input data

## Support

For issues and questions:
1. Check the console logs
2. Verify network connectivity
3. Ensure backend is running
4. Check database connections

## License

This project is part of the Smart Track system.
