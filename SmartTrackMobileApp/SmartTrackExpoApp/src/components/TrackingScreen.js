import React, { useState, useEffect } from 'react';
import { View, StyleSheet, Alert } from 'react-native';
import { Button, Card, Title, Paragraph, Text } from 'react-native-paper';
import { useNavigation } from '@react-navigation/native';
import LocationService from '../services/LocationService';

export default function TrackingScreen() {
  const navigation = useNavigation();
  const [isTracking, setIsTracking] = useState(false);
  const [batteryLevel, setBatteryLevel] = useState(0);
  const [location, setLocation] = useState(null);

  useEffect(() => {
    // Get initial battery level
    updateBatteryLevel();
    
    // Update battery level every 30 seconds
    const batteryInterval = setInterval(updateBatteryLevel, 30000);
    
    return () => clearInterval(batteryInterval);
  }, []);

  const updateBatteryLevel = async () => {
    try {
      const level = await LocationService.getBatteryLevel();
      setBatteryLevel(level);
    } catch (error) {
      console.error('Error getting battery level:', error);
    }
  };

  const startTracking = async () => {
    try {
      await LocationService.startTracking();
      setIsTracking(true);
      Alert.alert('Success', 'GPS tracking started!');
    } catch (error) {
      Alert.alert('Error', 'Failed to start tracking: ' + error.message);
    }
  };

  const stopTracking = async () => {
    try {
      await LocationService.stopTracking();
      setIsTracking(false);
      Alert.alert('Success', 'GPS tracking stopped!');
    } catch (error) {
      Alert.alert('Error', 'Failed to stop tracking: ' + error.message);
    }
  };

  return (
    <View style={styles.container}>
      <Card style={styles.card}>
        <Card.Content>
          <Title>GPS Tracking</Title>
          <Paragraph>
            {isTracking ? 'Currently tracking your location' : 'GPS tracking is stopped'}
          </Paragraph>
          
          <View style={styles.statusContainer}>
            <Text style={styles.statusText}>
              Status: {isTracking ? '🟢 Active' : '🔴 Inactive'}
            </Text>
            <Text style={styles.batteryText}>
              Battery: {Math.round(batteryLevel * 100)}%
            </Text>
          </View>

          {location && (
            <View style={styles.locationContainer}>
              <Text style={styles.locationText}>
                Last Location: {location.latitude.toFixed(6)}, {location.longitude.toFixed(6)}
              </Text>
            </View>
          )}
        </Card.Content>
      </Card>

      <View style={styles.buttonContainer}>
        {!isTracking ? (
          <Button 
            mode="contained" 
            onPress={startTracking}
            style={styles.startButton}
            icon="play"
          >
            Start Tracking
          </Button>
        ) : (
          <Button 
            mode="contained" 
            onPress={stopTracking}
            style={styles.stopButton}
            icon="stop"
          >
            Stop Tracking
          </Button>
        )}
        
        <Button 
          mode="outlined" 
          onPress={() => navigation.navigate('Settings')}
          style={styles.settingsButton}
          icon="cog"
        >
          Settings
        </Button>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    backgroundColor: '#f5f5f5',
  },
  card: {
    marginBottom: 20,
    elevation: 4,
  },
  statusContainer: {
    marginTop: 15,
    padding: 10,
    backgroundColor: '#f0f0f0',
    borderRadius: 8,
  },
  statusText: {
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 5,
  },
  batteryText: {
    fontSize: 14,
    color: '#666',
  },
  locationContainer: {
    marginTop: 10,
    padding: 10,
    backgroundColor: '#e8f5e8',
    borderRadius: 8,
  },
  locationText: {
    fontSize: 12,
    color: '#2e7d32',
    fontFamily: 'monospace',
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
});
