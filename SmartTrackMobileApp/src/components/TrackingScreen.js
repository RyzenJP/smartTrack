import React, { useState, useEffect } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
} from 'react-native';
import {
  Card,
  Title,
  Paragraph,
  Button,
  Chip,
  Divider,
  List,
  IconButton,
} from 'react-native-paper';
import AsyncStorage from '@react-native-async-storage/async-storage';

const TrackingScreen = ({
  settings,
  isTracking,
  onStartTracking,
  onStopTracking,
  onResetSettings,
}) => {
  const [lastLocation, setLastLocation] = useState(null);
  const [trackingStats, setTrackingStats] = useState({
    totalUpdates: 0,
    lastUpdate: null,
    errors: 0,
  });

  useEffect(() => {
    loadTrackingStats();
  }, []);

  const loadTrackingStats = async () => {
    try {
      const stats = await AsyncStorage.getItem('trackingStats');
      if (stats) {
        setTrackingStats(JSON.parse(stats));
      }
    } catch (error) {
      console.log('Error loading tracking stats:', error);
    }
  };

  const handleStartTracking = () => {
    Alert.alert(
      'Start Tracking',
      'Are you sure you want to start location tracking?',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Start', onPress: onStartTracking },
      ]
    );
  };

  const handleStopTracking = () => {
    Alert.alert(
      'Stop Tracking',
      'Are you sure you want to stop location tracking?',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Stop', onPress: onStopTracking },
      ]
    );
  };

  const handleResetSettings = () => {
    Alert.alert(
      'Reset Settings',
      'This will delete all settings and stop tracking. Are you sure?',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Reset', style: 'destructive', onPress: onResetSettings },
      ]
    );
  };

  const formatTime = (timestamp) => {
    if (!timestamp) return 'Never';
    return new Date(timestamp).toLocaleString();
  };

  return (
    <ScrollView style={styles.container}>
      <Card style={styles.card}>
        <Card.Content>
          <Title style={styles.title}>Smart Track</Title>
          <Paragraph style={styles.subtitle}>
            Vehicle Location Tracking
          </Paragraph>
          
          <Divider style={styles.divider} />
          
          {/* Status Section */}
          <View style={styles.statusSection}>
            <Title style={styles.sectionTitle}>Status</Title>
            <Chip
              icon={isTracking ? 'play' : 'pause'}
              style={[
                styles.statusChip,
                { backgroundColor: isTracking ? '#28a745' : '#dc3545' }
              ]}
            >
              {isTracking ? 'Tracking Active' : 'Tracking Stopped'}
            </Chip>
          </View>

          {/* Device Info */}
          <View style={styles.infoSection}>
            <Title style={styles.sectionTitle}>Device Information</Title>
            <List.Item
              title="Device Name"
              description={settings?.deviceName || 'Not set'}
              left={props => <List.Icon {...props} icon="cellphone" />}
            />
            <List.Item
              title="Device ID"
              description={settings?.deviceId || 'Not set'}
              left={props => <List.Icon {...props} icon="identifier" />}
            />
            <List.Item
              title="API Server"
              description={settings?.apiUrl || 'Not set'}
              left={props => <List.Icon {...props} icon="server" />}
            />
            <List.Item
              title="Update Frequency"
              description={`${settings?.frequency || 0} seconds`}
              left={props => <List.Icon {...props} icon="clock" />}
            />
          </View>

          {/* Tracking Stats */}
          <View style={styles.statsSection}>
            <Title style={styles.sectionTitle}>Tracking Statistics</Title>
            <List.Item
              title="Total Updates"
              description={trackingStats.totalUpdates.toString()}
              left={props => <List.Icon {...props} icon="chart-line" />}
            />
            <List.Item
              title="Last Update"
              description={formatTime(trackingStats.lastUpdate)}
              left={props => <List.Icon {...props} icon="update" />}
            />
            <List.Item
              title="Errors"
              description={trackingStats.errors.toString()}
              left={props => <List.Icon {...props} icon="alert-circle" />}
            />
          </View>

          {/* Last Location */}
          {lastLocation && (
            <View style={styles.locationSection}>
              <Title style={styles.sectionTitle}>Last Location</Title>
              <List.Item
                title="Latitude"
                description={lastLocation.latitude?.toFixed(6)}
                left={props => <List.Icon {...props} icon="crosshairs-gps" />}
              />
              <List.Item
                title="Longitude"
                description={lastLocation.longitude?.toFixed(6)}
                left={props => <List.Icon {...props} icon="crosshairs-gps" />}
              />
              <List.Item
                title="Speed"
                description={`${lastLocation.speed || 0} km/h`}
                left={props => <List.Icon {...props} icon="speedometer" />}
              />
            </View>
          )}

          <Divider style={styles.divider} />

          {/* Action Buttons */}
          <View style={styles.buttonSection}>
            {!isTracking ? (
              <Button
                mode="contained"
                onPress={handleStartTracking}
                style={[styles.actionButton, styles.startButton]}
                icon="play"
              >
                Start Tracking
              </Button>
            ) : (
              <Button
                mode="contained"
                onPress={handleStopTracking}
                style={[styles.actionButton, styles.stopButton]}
                icon="stop"
              >
                Stop Tracking
              </Button>
            )}
          </View>

          {/* Settings Button */}
          <View style={styles.settingsSection}>
            <Button
              mode="outlined"
              onPress={handleResetSettings}
              style={styles.resetButton}
              icon="settings"
            >
              Reset Settings
            </Button>
          </View>
        </Card.Content>
      </Card>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  card: {
    margin: 16,
    elevation: 4,
    borderRadius: 12,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: 8,
    color: '#003566',
  },
  subtitle: {
    textAlign: 'center',
    marginBottom: 16,
    color: '#6c757d',
  },
  divider: {
    marginVertical: 16,
  },
  statusSection: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 8,
    color: '#003566',
  },
  statusChip: {
    alignSelf: 'flex-start',
  },
  infoSection: {
    marginBottom: 16,
  },
  statsSection: {
    marginBottom: 16,
  },
  locationSection: {
    marginBottom: 16,
  },
  buttonSection: {
    marginBottom: 16,
  },
  actionButton: {
    paddingVertical: 8,
    marginBottom: 8,
  },
  startButton: {
    backgroundColor: '#28a745',
  },
  stopButton: {
    backgroundColor: '#dc3545',
  },
  settingsSection: {
    marginTop: 8,
  },
  resetButton: {
    borderColor: '#dc3545',
  },
});

export default TrackingScreen;
