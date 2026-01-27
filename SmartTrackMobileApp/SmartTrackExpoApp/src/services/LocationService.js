import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Location from 'expo-location';

class LocationService {
  static watchId = null;
  static isTracking = false;

  static async startTracking() {
    try {
      // Get settings
      const settings = await this.getSettings();
      if (!settings.deviceId || !settings.apiUrl) {
        throw new Error('Please configure device settings first');
      }

      // Request location permissions
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        throw new Error('Location permission denied');
      }

      // Start location tracking
      this.watchId = await Location.watchPositionAsync(
        {
          accuracy: Location.Accuracy.High,
          timeInterval: settings.trackingFrequency * 1000,
          distanceInterval: 10,
        },
        (location) => {
          this.sendLocationData(location, settings);
        }
      );

      this.isTracking = true;
      console.log('Location tracking started');
    } catch (error) {
      console.error('Error starting location tracking:', error);
      throw error;
    }
  }

  static async stopTracking() {
    if (this.watchId) {
      this.watchId.remove();
      this.watchId = null;
    }
    this.isTracking = false;
    console.log('Location tracking stopped');
  }

  static async sendLocationData(location, settings) {
    try {
      // Validate required settings
      if (!settings.deviceId || !settings.deviceName || !settings.apiKey) {
        console.error('Missing required settings: deviceId, deviceName, or apiKey');
        return;
      }

      const locationData = {
        device_id: settings.deviceId,
        device_name: settings.deviceName,
        imei: settings.deviceId,
        vehicle_id: null,
        status: 'active',
        battery_level: await this.getBatteryLevel(),
        latitude: location.coords.latitude,  // Fixed: API expects 'latitude' not 'lat'
        longitude: location.coords.longitude, // Fixed: API expects 'longitude' not 'lng'
        speed: location.coords.speed || 0,
        timestamp: new Date().toISOString(),
        api_key: settings.apiKey, // Fixed: Added missing api_key
      };

      console.log('📍 Sending location to:', `${settings.apiUrl}/api/mobile_gps_api.php`);

      const response = await fetch(`${settings.apiUrl}/api/mobile_gps_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(locationData),
      });

      // Fixed: Get text first, then parse JSON to handle HTML errors gracefully
      const responseText = await response.text();
      console.log('📡 Response status:', response.status);
      console.log('📡 Raw response:', responseText.substring(0, 200)); // Log first 200 chars

      try {
        const result = JSON.parse(responseText);
        
        if (result.success) {
          console.log('✅ Location data sent successfully');
        } else {
          console.error('❌ Failed to send location data:', result.error || result.message);
        }
      } catch (parseError) {
        console.error('❌ JSON parse error:', parseError.message);
        console.error('❌ Response was not JSON. First 500 chars:', responseText.substring(0, 500));
        // Show user-friendly error
        if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
          console.error('❌ Server returned HTML instead of JSON. Check API endpoint.');
        }
      }
    } catch (error) {
      console.error('❌ Network error sending location data:', error.message);
    }
  }

  static async getSettings() {
    try {
      const settings = await AsyncStorage.getItem('smartTrackSettings');
      return settings ? JSON.parse(settings) : {};
    } catch (error) {
      console.error('Error getting settings:', error);
      return {};
    }
  }

  static async getBatteryLevel() {
    try {
      // For now, return a random battery level
      // In a real app, you'd use a battery API
      return Math.random() * 0.4 + 0.6; // 60-100%
    } catch (error) {
      console.error('Error getting battery level:', error);
      return 0.8; // Default 80%
    }
  }

  static getTrackingStatus() {
    return this.isTracking;
  }
}

export default LocationService;
