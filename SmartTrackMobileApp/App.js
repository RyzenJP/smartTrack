import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  StyleSheet,
  StatusBar,
  Alert,
  PermissionsAndroid,
  Platform,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Provider as PaperProvider } from 'react-native-paper';

import SettingsScreen from './src/components/SettingsScreen';
import TrackingScreen from './src/components/TrackingScreen';
import LocationService from './src/services/LocationService';

const App = () => {
  const [isConfigured, setIsConfigured] = useState(false);
  const [settings, setSettings] = useState(null);
  const [isTracking, setIsTracking] = useState(false);

  useEffect(() => {
    checkConfiguration();
    requestLocationPermission();
  }, []);

  const checkConfiguration = async () => {
    try {
      const savedSettings = await AsyncStorage.getItem('trackingSettings');
      if (savedSettings) {
        setSettings(JSON.parse(savedSettings));
        setIsConfigured(true);
      }
    } catch (error) {
      console.log('Error loading settings:', error);
    }
  };

  const requestLocationPermission = async () => {
    if (Platform.OS === 'android') {
      try {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
          {
            title: 'Location Permission',
            message: 'Smart Track needs access to your location for vehicle tracking.',
            buttonNeutral: 'Ask Me Later',
            buttonNegative: 'Cancel',
            buttonPositive: 'OK',
          }
        );
        if (granted === PermissionsAndroid.RESULTS.GRANTED) {
          console.log('Location permission granted');
        } else {
          Alert.alert('Permission Denied', 'Location permission is required for tracking.');
        }
      } catch (err) {
        console.warn(err);
      }
    }
  };

  const handleSettingsSave = async (newSettings) => {
    try {
      await AsyncStorage.setItem('trackingSettings', JSON.stringify(newSettings));
      setSettings(newSettings);
      setIsConfigured(true);
    } catch (error) {
      Alert.alert('Error', 'Failed to save settings');
    }
  };

  const handleStartTracking = () => {
    if (settings) {
      setIsTracking(true);
      LocationService.startTracking(settings);
    }
  };

  const handleStopTracking = () => {
    setIsTracking(false);
    LocationService.stopTracking();
  };

  const handleResetSettings = async () => {
    try {
      await AsyncStorage.removeItem('trackingSettings');
      setSettings(null);
      setIsConfigured(false);
      setIsTracking(false);
      LocationService.stopTracking();
    } catch (error) {
      Alert.alert('Error', 'Failed to reset settings');
    }
  };

  return (
    <PaperProvider>
      <SafeAreaView style={styles.container}>
        <StatusBar barStyle="dark-content" backgroundColor="#ffffff" />
        {!isConfigured ? (
          <SettingsScreen onSettingsSave={handleSettingsSave} />
        ) : (
          <TrackingScreen
            settings={settings}
            isTracking={isTracking}
            onStartTracking={handleStartTracking}
            onStopTracking={handleStopTracking}
            onResetSettings={handleResetSettings}
          />
        )}
      </SafeAreaView>
    </PaperProvider>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
});

export default App;
