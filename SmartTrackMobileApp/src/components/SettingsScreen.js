import React, { useState } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {
  TextInput,
  Button,
  Card,
  Title,
  Paragraph,
  Divider,
  HelperText,
} from 'react-native-paper';

const SettingsScreen = ({ onSettingsSave }) => {
  const [deviceName, setDeviceName] = useState('');
  const [apiUrl, setApiUrl] = useState('http://localhost/trackingv2/trackingv2');
  const [apiKey, setApiKey] = useState('');
  const [frequency, setFrequency] = useState('30');
  const [vehicleId, setVehicleId] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const validateSettings = () => {
    if (!deviceName.trim()) {
      Alert.alert('Error', 'Device name is required');
      return false;
    }
    if (!apiUrl.trim()) {
      Alert.alert('Error', 'API URL is required');
      return false;
    }
    if (!apiKey.trim()) {
      Alert.alert('Error', 'API Key is required');
      return false;
    }
    if (!frequency || isNaN(frequency) || parseInt(frequency) < 10) {
      Alert.alert('Error', 'Frequency must be at least 10 seconds');
      return false;
    }
    return true;
  };

  const handleSave = async () => {
    if (!validateSettings()) return;

    setIsLoading(true);
    
    const settings = {
      deviceName: deviceName.trim(),
      apiUrl: apiUrl.trim(),
      apiKey: apiKey.trim(),
      frequency: parseInt(frequency),
      vehicleId: vehicleId.trim(),
      deviceId: `MOBILE-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    };

    try {
      // Test API connection
      const testResponse = await fetch(`${apiUrl}/api/test_connection.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          device_name: settings.deviceName,
          api_key: settings.apiKey,
        }),
      });

      if (testResponse.ok) {
        onSettingsSave(settings);
        Alert.alert('Success', 'Settings saved successfully!');
      } else {
        Alert.alert('Error', 'Failed to connect to server. Please check your settings.');
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to connect to server. Please check your API URL and network connection.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView 
      style={styles.container} 
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <Card style={styles.card}>
          <Card.Content>
            <Title style={styles.title}>Smart Track Settings</Title>
            <Paragraph style={styles.subtitle}>
              Configure your device for vehicle tracking
            </Paragraph>
            
            <Divider style={styles.divider} />
            
            <TextInput
              label="Device Name *"
              value={deviceName}
              onChangeText={setDeviceName}
              style={styles.input}
              mode="outlined"
              placeholder="e.g., Vehicle-001, Driver-John"
            />
            <HelperText type="info">
              Unique name for this device
            </HelperText>

            <TextInput
              label="API URL *"
              value={apiUrl}
              onChangeText={setApiUrl}
              style={styles.input}
              mode="outlined"
              placeholder="http://your-server.com/trackingv2"
              keyboardType="url"
            />
            <HelperText type="info">
              Your Smart Track server URL
            </HelperText>

            <TextInput
              label="API Key *"
              value={apiKey}
              onChangeText={setApiKey}
              style={styles.input}
              mode="outlined"
              placeholder="Enter your API key"
              secureTextEntry
            />
            <HelperText type="info">
              Authentication key for server access
            </HelperText>

            <TextInput
              label="Tracking Frequency (seconds) *"
              value={frequency}
              onChangeText={setFrequency}
              style={styles.input}
              mode="outlined"
              placeholder="30"
              keyboardType="numeric"
            />
            <HelperText type="info">
              How often to send location (10-300 seconds)
            </HelperText>

            <TextInput
              label="Vehicle ID (Optional)"
              value={vehicleId}
              onChangeText={setVehicleId}
              style={styles.input}
              mode="outlined"
              placeholder="Vehicle ID if assigned"
            />
            <HelperText type="info">
              Optional: Specific vehicle this device is assigned to
            </HelperText>

            <Button
              mode="contained"
              onPress={handleSave}
              style={styles.saveButton}
              loading={isLoading}
              disabled={isLoading}
            >
              Save Settings & Start Tracking
            </Button>
          </Card.Content>
        </Card>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  scrollContent: {
    padding: 16,
    flexGrow: 1,
  },
  card: {
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
  input: {
    marginBottom: 8,
  },
  saveButton: {
    marginTop: 24,
    paddingVertical: 8,
    backgroundColor: '#00b4d8',
  },
});

export default SettingsScreen;
