import Geolocation from 'react-native-geolocation-service';
import AsyncStorage from '@react-native-async-storage/async-storage';

class LocationService {
  constructor() {
    this.watchId = null;
    this.isTracking = false;
    this.settings = null;
    this.trackingInterval = null;
  }

  startTracking(settings) {
    this.settings = settings;
    this.isTracking = true;
    
    // Start watching position
    this.watchId = Geolocation.watchPosition(
      (position) => {
        this.handleLocationUpdate(position);
      },
      (error) => {
        console.log('Location error:', error);
        this.handleLocationError(error);
      },
      {
        enableHighAccuracy: true,
        distanceFilter: 10, // Update every 10 meters
        interval: settings.frequency * 1000, // Convert to milliseconds
        fastestInterval: settings.frequency * 1000,
        showLocationDialog: true,
        forceRequestLocation: true,
      }
    );

    console.log('Location tracking started');
  }

  stopTracking() {
    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    
    if (this.trackingInterval) {
      clearInterval(this.trackingInterval);
      this.trackingInterval = null;
    }
    
    this.isTracking = false;
    console.log('Location tracking stopped');
  }

  async handleLocationUpdate(position) {
    try {
      const locationData = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        speed: position.coords.speed ? (position.coords.speed * 3.6) : 0, // Convert m/s to km/h
        accuracy: position.coords.accuracy,
        battery_level: await this.getBatteryLevel(),
        status: 'active',
        timestamp: new Date().toISOString(),
        device_id: this.settings.deviceId,
        device_name: this.settings.deviceName,
        vehicle_id: this.settings.vehicleId || null,
      };

      console.log('Location update:', locationData);

      // Send to server
      await this.sendLocationToServer(locationData);

      // Update local stats
      await this.updateTrackingStats();

    } catch (error) {
      console.error('Error handling location update:', error);
      await this.incrementErrorCount();
    }
  }

  async sendLocationToServer(locationData) {
    try {
      const response = await fetch(`${this.settings.apiUrl}/api/mobile_gps_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...locationData,
          api_key: this.settings.apiKey,
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log('Location sent successfully:', result);
      
    } catch (error) {
      console.error('Error sending location to server:', error);
      throw error;
    }
  }

  async updateTrackingStats() {
    try {
      const stats = await AsyncStorage.getItem('trackingStats');
      const currentStats = stats ? JSON.parse(stats) : {
        totalUpdates: 0,
        lastUpdate: null,
        errors: 0,
      };

      currentStats.totalUpdates += 1;
      currentStats.lastUpdate = new Date().toISOString();

      await AsyncStorage.setItem('trackingStats', JSON.stringify(currentStats));
    } catch (error) {
      console.error('Error updating tracking stats:', error);
    }
  }

  async incrementErrorCount() {
    try {
      const stats = await AsyncStorage.getItem('trackingStats');
      const currentStats = stats ? JSON.parse(stats) : {
        totalUpdates: 0,
        lastUpdate: null,
        errors: 0,
      };

      currentStats.errors += 1;

      await AsyncStorage.setItem('trackingStats', JSON.stringify(currentStats));
    } catch (error) {
      console.error('Error updating error count:', error);
    }
  }

  handleLocationError(error) {
    console.error('Location error:', error);
    this.incrementErrorCount();
  }

  // Get current position once
  async getCurrentPosition() {
    return new Promise((resolve, reject) => {
      Geolocation.getCurrentPosition(
        (position) => {
          resolve(position);
        },
        (error) => {
          reject(error);
        },
        {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 10000,
        }
      );
    });
  }

  // Check if location services are enabled
  async checkLocationServices() {
    return new Promise((resolve) => {
      Geolocation.getCurrentPosition(
        () => resolve(true),
        () => resolve(false),
        {
          enableHighAccuracy: false,
          timeout: 5000,
          maximumAge: 10000,
        }
      );
    });
  }

  // Get battery level (simplified implementation)
  async getBatteryLevel() {
    try {
      // For React Native, we can't directly access battery level
      // This is a placeholder - in a real implementation, you might use
      // a native module or estimate based on device usage
      return Math.floor(Math.random() * 100); // Placeholder: random battery level
    } catch (error) {
      console.log('Error getting battery level:', error);
      return null;
    }
  }
}

export default new LocationService();
