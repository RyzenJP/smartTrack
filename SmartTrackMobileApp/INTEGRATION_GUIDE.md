# Smart Track Mobile App - Integration Guide

## 🎯 **Perfect Database Compatibility**

Your existing database structure is **perfectly compatible** with the mobile app! No changes needed.

## 📊 **How Data Flows**

### 1. **Mobile App → gps_logs Table**
When the mobile app sends location data, it creates entries in your `gps_logs` table:

```sql
INSERT INTO gps_logs (
    device_id,      -- Mobile device identifier (e.g., "MOBILE-001")
    imei,           -- Same as device_id for mobile devices
    vehicle_id,     -- NULL initially, can be assigned later
    status,         -- 'active' when tracking
    last_update,    -- Current timestamp
    battery_level,  -- Mobile device battery %
    lat,            -- GPS latitude
    lng,            -- GPS longitude
    speed           -- Vehicle speed in km/h
)
```

### 2. **Mobile App → gps_devices Table**
The app also updates your `gps_devices` table with current device status:

```sql
UPDATE gps_devices SET 
    imei = 'MOBILE-001',
    status = 'active',
    last_update = NOW(),
    battery_level = 85,
    lat = 40.7128,
    lng = -74.0060,
    speed = 25.5
WHERE device_id = 'MOBILE-001'
```

## 🔧 **Setup Steps**

### Step 1: Copy API Files
```bash
# Copy these files to your PHP backend's api/ directory:
cp mobile_gps_api.php /path/to/your/backend/api/
cp test_connection.php /path/to/your/backend/api/
```

### Step 2: Update Database Connection
Edit `mobile_gps_api.php` and `test_connection.php`:
```php
// Update this line to match your database connection:
require_once '../config/db_connection.php';
```

### Step 3: Add Mobile Device (Optional)
```bash
# Add a sample mobile device to your gps_devices table:
mysql -u your_username -p your_database < add_mobile_device.sql
```

### Step 4: Test the API
```bash
# Test the connection endpoint:
curl -X POST http://your-server.com/api/test_connection.php \
  -H "Content-Type: application/json" \
  -d '{"device_name":"Test Device","api_key":"your-api-key"}'
```

## 📱 **Mobile App Configuration**

### Settings Screen Fields:
- **Device Name**: User-friendly name (e.g., "Driver John's Phone")
- **API URL**: Your server URL (e.g., "http://your-server.com/trackingv2")
- **API Key**: Authentication key for your backend
- **Frequency**: How often to send location (10-300 seconds)
- **Vehicle ID**: Optional vehicle assignment

### Data Sent by Mobile App:
```json
{
  "device_id": "MOBILE-1234567890-abc123",
  "device_name": "Driver John's Phone",
  "vehicle_id": null,
  "latitude": 40.7128,
  "longitude": -74.0060,
  "speed": 25.5,
  "battery_level": 85,
  "status": "active",
  "timestamp": "2024-01-01T12:00:00Z",
  "api_key": "your-api-key"
}
```

## 🔄 **Integration with Existing System**

### Your Current ESP32 Devices:
- Continue working as before
- Use same `gps_logs` and `gps_devices` tables
- No changes needed to existing code

### New Mobile Devices:
- Appear in same tables as ESP32 devices
- Can be assigned to vehicles like ESP32 devices
- Use same reporting and tracking interfaces

### Admin Panel Integration:
- Mobile devices appear in your existing GPS management interface
- Same status monitoring and device management
- Can assign mobile devices to vehicles
- Same reporting and analytics

## 🚀 **Benefits of Mobile App**

1. **No Hardware Costs**: Use existing phones instead of ESP32 devices
2. **Better Battery Life**: Phones have larger batteries than ESP32
3. **Easier Setup**: No hardware installation required
4. **Real-time Updates**: Instant location updates
5. **User-Friendly**: Simple settings and tracking controls

## 🔒 **Security Features**

- API key authentication
- Input validation and sanitization
- SQL injection protection
- CORS headers for mobile app access
- Error logging and monitoring

## 📈 **Monitoring and Analytics**

The mobile app data integrates seamlessly with your existing:
- Fleet tracking dashboard
- Vehicle status monitoring
- Route optimization
- Maintenance scheduling
- Reporting and analytics

## 🛠️ **Troubleshooting**

### Common Issues:
1. **Connection Failed**: Check API URL and network
2. **Invalid API Key**: Verify authentication key
3. **Location Not Updating**: Check GPS permissions
4. **Database Errors**: Verify table structure matches

### Debug Mode:
Enable debug logging in the mobile app to see detailed error messages.

## 📞 **Support**

The mobile app is designed to work with your existing Smart Track system without any modifications to your current setup. It's a drop-in replacement for ESP32 devices that provides the same functionality with better user experience.
