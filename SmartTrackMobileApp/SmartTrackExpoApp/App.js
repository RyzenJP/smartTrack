import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert, TextInput, Linking } from 'react-native';
import { Provider as PaperProvider, Button, Card, Title, Paragraph, TextInput as PaperTextInput } from 'react-native-paper';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { PermissionsAndroid, Platform } from 'react-native';
import * as Location from 'expo-location';

export default function App() {
  const [isReady, setIsReady] = useState(false);
  const [isTracking, setIsTracking] = useState(false);
  const [currentScreen, setCurrentScreen] = useState('tracking');
  const [settings, setSettings] = useState({
    deviceId: 'MOBILE-001',
    deviceName: 'Driver Phone',
    apiUrl: 'https://smarttrack.bccbsis.com/trackingv2/trackingv2',
    apiKey: 'ST176121957633164140',
    trackingFrequency: '30'
  });
  const [currentLocation, setCurrentLocation] = useState(null);
  const [watchId, setWatchId] = useState(null);

  useEffect(() => {
    requestLocationPermission();
    loadSettings();
    setIsReady(true);
  }, []);

  const requestLocationPermission = async () => {
    if (Platform.OS === 'android') {
      try {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
          {
            title: 'Location Permission',
            message: 'Smart Track needs access to your location to track GPS data.',
            buttonNeutral: 'Ask Me Later',
            buttonNegative: 'Cancel',
            buttonPositive: 'OK',
          }
        );
        if (granted === PermissionsAndroid.RESULTS.GRANTED) {
          console.log('Location permission granted');
        } else {
          console.log('Location permission denied');
        }
      } catch (err) {
        console.warn(err);
      }
    }
  };

  const loadSettings = async () => {
    try {
      const savedSettings = await AsyncStorage.getItem('smartTrackSettings');
      if (savedSettings) {
        setSettings(JSON.parse(savedSettings));
      }
    } catch (error) {
      console.error('Error loading settings:', error);
    }
  };

  const saveSettings = async () => {
    try {
      await AsyncStorage.setItem('smartTrackSettings', JSON.stringify(settings));
      Alert.alert('Success', 'Settings saved successfully!');
    } catch (error) {
      Alert.alert('Error', 'Failed to save settings: ' + error.message);
    }
  };

  const startTracking = async () => {
    try {
      // Check if location services are enabled
      const locationEnabled = await Location.hasServicesEnabledAsync();
      if (!locationEnabled) {
        Alert.alert(
          'Location Services Disabled',
          'Please enable location services in your device settings:\n\n1. Settings → Location\n2. Turn ON Location\n3. Select "High accuracy" mode\n4. Return to this app and try again.',
          [{ text: 'OK' }]
        );
        return;
      }

      // Request location permissions (both foreground and background)
      const { status: foregroundStatus } = await Location.requestForegroundPermissionsAsync();
      if (foregroundStatus !== 'granted') {
        Alert.alert(
          'Location Permission Required',
          'Smart Track needs location permission to track GPS data.\n\nPlease:\n1. Go to Settings → Apps → Smart Track\n2. Tap Permissions → Location\n3. Select "Allow all the time"\n4. Return and try again.',
          [{ text: 'OK' }]
        );
        return;
      }

      // Also request background permission for continuous tracking
      const { status: backgroundStatus } = await Location.requestBackgroundPermissionsAsync();
      if (backgroundStatus !== 'granted') {
        console.log('Background location permission not granted, but continuing with foreground only');
      }

          // Get current location with high accuracy for real GPS
      console.log('📍 Getting current location with high accuracy GPS...');
      let currentLocation;
      try {
        // Use high accuracy for real GPS (not mock location)
        currentLocation = await Location.getCurrentPositionAsync({
          accuracy: Location.Accuracy.Highest, // Use highest accuracy for real GPS
          timeout: 30000, // 30 second timeout for GPS lock
          maximumAge: 0, // Always get fresh location (don't use cached)
          distanceInterval: 0, // Get every update
        });
        console.log('✅ GPS location obtained:', {
          latitude: currentLocation.coords.latitude,
          longitude: currentLocation.coords.longitude,
          accuracy: currentLocation.coords.accuracy
        });
      } catch (locationError) {
        console.error('❌ High accuracy failed, trying balanced...', locationError);
        // If high accuracy fails, try balanced
        try {
          currentLocation = await Location.getCurrentPositionAsync({
            accuracy: Location.Accuracy.Balanced,
            timeout: 30000,
            maximumAge: 60000, // Accept location up to 1 minute old
          });
          console.log('✅ Location obtained with balanced accuracy');
        } catch (balancedError) {
          console.error('❌ Balanced accuracy failed, trying low...', balancedError);
          // Last resort: low accuracy
          try {
            currentLocation = await Location.getCurrentPositionAsync({
              accuracy: Location.Accuracy.Low,
              timeout: 30000,
              maximumAge: 300000, // Accept location up to 5 minutes old
            });
            console.log('✅ Location obtained with low accuracy');
          } catch (lowAccuracyError) {
            console.error('❌ All location attempts failed:', lowAccuracyError);
            throw new Error('Unable to get GPS location. Please:\n1. Go outside for better GPS signal\n2. Wait 30-60 seconds for GPS lock\n3. Check if other apps (Maps) can get your location\n4. Enable location services in device settings');
          }
        }
      }
      
      console.log('Current location:', currentLocation);
      setCurrentLocation(currentLocation);
      sendLocationData(currentLocation);

      // Start location tracking with proper frequency control
      const frequencyMs = parseInt(settings.trackingFrequency) * 1000;
      console.log('Starting tracking with frequency:', settings.trackingFrequency, 'seconds (', frequencyMs, 'ms)');
      
      // Use setInterval for more reliable frequency control
      const intervalId = setInterval(async () => {
        try {
          const location = await Location.getCurrentPositionAsync({
            accuracy: Location.Accuracy.Balanced,
            timeout: 5000,
            maximumAge: 2000,
          });
          
          console.log('Location update (interval):', location);
          setCurrentLocation(location);
          sendLocationData(location);
        } catch (error) {
          console.error('Location update error:', error);
        }
      }, frequencyMs);
      
      const watchId = intervalId; // Store interval ID

      setWatchId(watchId);
      setIsTracking(true);
      Alert.alert('Success', `GPS tracking started! Sending location every ${settings.trackingFrequency} seconds.`);
    } catch (error) {
      console.error('Location error:', error);
      
      // If Google Play Services fails, offer to use mock location for testing
      if (error.message.includes('Google Play services') || error.message.includes('service disconnection')) {
        Alert.alert(
          'Location Service Unavailable', 
          'Google Play Services is not available. Would you like to use a test location for development?',
          [
            { text: 'Cancel', style: 'cancel' },
            { 
              text: 'Use Test Location', 
              onPress: () => {
                const mockLocation = {
                  coords: {
                    latitude: 14.5995, // Manila, Philippines
                    longitude: 120.9842,
                    speed: 0,
                    accuracy: 5
                  },
                  timestamp: Date.now()
                };
                setCurrentLocation(mockLocation);
                sendLocationData(mockLocation);
                
                // Set up interval for mock location too
                const frequencyMs = parseInt(settings.trackingFrequency) * 1000;
                const intervalId = setInterval(() => {
                  console.log('Sending mock location data...');
                  sendLocationData(mockLocation);
                }, frequencyMs);
                
                setWatchId(intervalId);
                setIsTracking(true);
                Alert.alert('Success', `Using test location! Sending every ${settings.trackingFrequency} seconds.`);
              }
            }
          ]
        );
      } else {
        // More helpful error message with specific troubleshooting
        let errorMessage = error.message;
        let troubleshooting = '';
        
        if (errorMessage.includes('location is unavailable') || errorMessage.includes('location services')) {
          troubleshooting = '\n\nTroubleshooting:\n1. Settings → Apps → Smart Track → Permissions → Location → Allow all the time\n2. Settings → Location → High accuracy mode\n3. Go outside for better GPS signal\n4. Wait 30 seconds for GPS lock\n5. Check if Google Maps can get your location';
        } else if (errorMessage.includes('timeout') || errorMessage.includes('TIMEOUT')) {
          troubleshooting = '\n\nTroubleshooting:\n1. Go outside (GPS needs clear sky view)\n2. Wait 30-60 seconds for GPS to lock\n3. Check if location services are on\n4. Try "Get Location Now" button first';
        } else if (errorMessage.includes('permission') || errorMessage.includes('denied')) {
          troubleshooting = '\n\nTroubleshooting:\n1. Settings → Apps → Smart Track\n2. Permissions → Location\n3. Select "Allow all the time"\n4. Restart the app';
        }
        
        Alert.alert(
          'Location Error',
          errorMessage + troubleshooting,
          [
            { text: 'OK' },
            { 
              text: 'Open Settings', 
              onPress: () => {
                // Try to open app settings (Android only)
                if (Platform.OS === 'android') {
                  Linking.openSettings();
                }
              }
            }
          ]
        );
      }
    }
  };

  const stopTracking = () => {
    if (watchId) {
      clearInterval(watchId); // Clear interval instead of removing watch
      setWatchId(null);
    }
    setIsTracking(false);
    setCurrentLocation(null);
    Alert.alert('Success', 'GPS tracking stopped!');
  };

  const sendLocationData = async (location) => {
    try {
      const locationData = {
        device_id: settings.deviceId,
        device_name: settings.deviceName,
        imei: settings.deviceId,
        vehicle_id: null,
        status: 'active',
        battery_level: 0.8, // Simulated battery level
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        speed: location.coords.speed || 0,
        timestamp: new Date().toISOString(),
        api_key: settings.apiKey,
      };

      console.log('📍 Sending location data to:', `${settings.apiUrl}/api/mobile_gps_api.php`);
      console.log('📍 Location data:', locationData);
      console.log('📍 Frequency setting:', settings.trackingFrequency, 'seconds');

      const response = await fetch(`${settings.apiUrl}/api/mobile_gps_api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(locationData),
      });

      console.log('Response status:', response.status);
      console.log('Response ok:', response.ok);

      const responseText = await response.text();
      console.log('Raw response (first 200 chars):', responseText.substring(0, 200));
      
      try {
        const result = JSON.parse(responseText);
        
        if (result.success) {
          console.log('✅ Location data sent successfully at', new Date().toLocaleTimeString());
        } else {
          console.error('❌ Failed to send location data:', result.error || result.message);
          // Show user-friendly error if possible
          if (result.error) {
            Alert.alert('GPS Send Failed', result.error);
          }
        }
      } catch (parseError) {
        console.error('❌ JSON parse error:', parseError.message);
        console.error('❌ Response was not JSON. Full response:', responseText);
        
        // Check if it's an HTML error page
        if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
          const errorMsg = 'Server returned HTML instead of JSON. The API endpoint may be incorrect or the server has an error.';
          console.error('❌', errorMsg);
          Alert.alert('Connection Error', errorMsg + '\n\nCheck your API URL: ' + settings.apiUrl);
        } else {
          Alert.alert('Parse Error', 'Could not parse server response. Check console for details.');
        }
      }
    } catch (error) {
      console.error('Network error details:', error);
      console.error('Error name:', error.name);
      console.error('Error message:', error.message);
      
      // Try alternative URL if first fails
      if (settings.apiUrl.includes('192.168.1.2')) {
        console.log('Trying alternative URL...');
        const altSettings = { ...settings, apiUrl: 'http://10.0.2.2/trackingv2/trackingv2' };
        // Retry with alternative URL
        try {
          const altResponse = await fetch(`${altSettings.apiUrl}/api/mobile_gps_api.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(locationData),
          });
          console.log('Alternative URL response:', altResponse.status);
        } catch (altError) {
          console.error('Alternative URL also failed:', altError);
        }
      }
    }
  };

  const testConnection = async () => {
    try {
      console.log('Testing connection to:', `${settings.apiUrl}/api/mobile_test.php`);
      
      const response = await fetch(`${settings.apiUrl}/api/mobile_test.php`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });
      
      console.log('Response status:', response.status);
      const result = await response.json();
      console.log('Response data:', result);
      
      if (result.success) {
        Alert.alert('Success', 'Connection to backend successful!');
      } else {
        Alert.alert('Error', 'Connection failed: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Connection error:', error);
      Alert.alert('Error', 'Connection failed: ' + error.message + '\n\nCheck your API URL: ' + settings.apiUrl);
    }
  };

  if (!isReady) {
    return (
      <View style={styles.container}>
        <Text>Loading Smart Track...</Text>
      </View>
    );
  }

  return (
    <PaperProvider>
    <View style={styles.container}>
        {currentScreen === 'tracking' ? (
          <View style={styles.screen}>
            <Card style={styles.card}>
              <Card.Content>
                <Title>GPS Tracking</Title>
                <Paragraph>
                  {isTracking ? 'Currently tracking your location' : 'GPS tracking is stopped'}
                </Paragraph>
                <Text style={styles.status}>
                  Status: {isTracking ? '🟢 Active' : '🔴 Inactive'}
                </Text>
                <Text style={styles.status}>
                  Frequency: Every {settings.trackingFrequency} seconds
                </Text>
                {currentLocation && (
                  <Text style={styles.locationText}>
                    Location: {currentLocation.coords.latitude.toFixed(6)}, {currentLocation.coords.longitude.toFixed(6)}
                  </Text>
                )}
              </Card.Content>
            </Card>

            <View style={styles.buttonContainer}>
              {!isTracking ? (
                <Button 
                  mode="contained" 
                  onPress={startTracking}
                  style={styles.startButton}
                >
                  Start Tracking
                </Button>
              ) : (
                <Button 
                  mode="contained" 
                  onPress={stopTracking}
                  style={styles.stopButton}
                >
                  Stop Tracking
                </Button>
              )}
              
              <Button 
                mode="outlined" 
                onPress={async () => {
                  try {
                    console.log('Getting manual location...');
                    const location = await Location.getCurrentPositionAsync({
                      accuracy: Location.Accuracy.Balanced,
                      timeout: 10000,
                      maximumAge: 60000,
                    });
                    console.log('Manual location:', location);
                    setCurrentLocation(location);
                    sendLocationData(location);
                    Alert.alert('Success', 'Location updated manually!');
                  } catch (error) {
                    console.error('Manual location error:', error);
                    Alert.alert('Error', 'Failed to get location: ' + error.message);
                  }
                }}
                style={styles.settingsButton}
              >
                Get Location Now
              </Button>
              
              <Button 
                mode="outlined" 
                onPress={() => setCurrentScreen('settings')}
                style={styles.settingsButton}
              >
                Settings
              </Button>
            </View>
          </View>
        ) : (
          <View style={styles.screen}>
            <Card style={styles.card}>
              <Card.Content>
                <Title>Settings</Title>
                
                <PaperTextInput
                  label="Device ID"
                  value={settings.deviceId}
                  onChangeText={(text) => setSettings({...settings, deviceId: text})}
                  style={styles.input}
                  placeholder="e.g., MOBILE-001"
                />
                
                <PaperTextInput
                  label="Device Name"
                  value={settings.deviceName}
                  onChangeText={(text) => setSettings({...settings, deviceName: text})}
                  style={styles.input}
                  placeholder="e.g., Driver John's Phone"
                />
                
                <PaperTextInput
                  label="API URL"
                  value={settings.apiUrl}
                  onChangeText={(text) => setSettings({...settings, apiUrl: text})}
                  style={styles.input}
                  placeholder="http://192.168.1.2/trackingv2/trackingv2"
                />
                
                <PaperTextInput
                  label="API Key"
                  value={settings.apiKey}
                  onChangeText={(text) => setSettings({...settings, apiKey: text})}
                  style={styles.input}
                  placeholder="Your unique API key"
                />
                
                <PaperTextInput
                  label="GPS Tracking Frequency (seconds)"
                  value={settings.trackingFrequency}
                  onChangeText={(text) => setSettings({...settings, trackingFrequency: text})}
                  style={styles.input}
                  placeholder="3"
                  keyboardType="numeric"
                  helper="Examples: 1=every second, 3=every 3 seconds, 10=every 10 seconds, 30=every 30 seconds"
                />
                
                <View style={{flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 10}}>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '1'})}
                    style={{borderColor: settings.trackingFrequency === '1' ? '#4caf50' : '#ccc'}}
                  >
                    1 sec
                  </Button>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '3'})}
                    style={{borderColor: settings.trackingFrequency === '3' ? '#4caf50' : '#ccc'}}
                  >
                    3 sec
                  </Button>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '5'})}
                    style={{borderColor: settings.trackingFrequency === '5' ? '#4caf50' : '#ccc'}}
                  >
                    5 sec
                  </Button>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '10'})}
                    style={{borderColor: settings.trackingFrequency === '10' ? '#4caf50' : '#ccc'}}
                  >
                    10 sec
                  </Button>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '30'})}
                    style={{borderColor: settings.trackingFrequency === '30' ? '#4caf50' : '#ccc'}}
                  >
                    30 sec
                  </Button>
                  <Button 
                    mode="outlined" 
                    compact
                    onPress={() => setSettings({...settings, trackingFrequency: '60'})}
                    style={{borderColor: settings.trackingFrequency === '60' ? '#4caf50' : '#ccc'}}
                  >
                    1 min
                  </Button>
                </View>
              </Card.Content>
            </Card>

            <View style={styles.buttonContainer}>
              <Button 
                mode="contained" 
                onPress={saveSettings}
                style={styles.saveButton}
              >
                Save Settings
              </Button>
              
              <Button 
                mode="outlined" 
                onPress={testConnection}
                style={styles.testButton}
              >
                Test Connection
              </Button>
              
              <Button 
                mode="outlined" 
                onPress={() => setCurrentScreen('tracking')}
                style={styles.backButton}
              >
                Back to Tracking
              </Button>
            </View>
          </View>
        )}
    </View>
    </PaperProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    padding: 20,
  },
  screen: {
    flex: 1,
    justifyContent: 'center',
  },
  card: {
    marginBottom: 20,
    elevation: 4,
  },
  status: {
    fontSize: 16,
    fontWeight: 'bold',
    marginTop: 10,
    textAlign: 'center',
  },
  buttonContainer: {
    gap: 15,
  },
  startButton: {
    backgroundColor: '#4caf50',
  },
  stopButton: {
    backgroundColor: '#f44336',
  },
  settingsButton: {
    borderColor: '#2196f3',
  },
  saveButton: {
    backgroundColor: '#4caf50',
  },
  testButton: {
    borderColor: '#2196f3',
  },
  backButton: {
    borderColor: '#666',
  },
  helpText: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  input: {
    marginBottom: 15,
    backgroundColor: '#fff',
  },
  locationText: {
    fontSize: 12,
    color: '#2e7d32',
    fontFamily: 'monospace',
    marginTop: 10,
    textAlign: 'center',
  },
});