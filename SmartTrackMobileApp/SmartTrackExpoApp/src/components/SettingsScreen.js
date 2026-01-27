import React, { useState, useEffect } from 'react';
import { View, StyleSheet, Alert } from 'react-native';
import { Button, Card, Title, TextInput, Text } from 'react-native-paper';
import AsyncStorage from '@react-native-async-storage/async-storage';

export default function SettingsScreen() {
  const [deviceId, setDeviceId] = useState('');
  const [deviceName, setDeviceName] = useState('');
  const [apiUrl, setApiUrl] = useState('');
  const [apiKey, setApiKey] = useState('');
  const [trackingFrequency, setTrackingFrequency] = useState('30');

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const settings = await AsyncStorage.getItem('smartTrackSettings');
      if (settings) {
        const parsedSettings = JSON.parse(settings);
        setDeviceId(parsedSettings.deviceId || '');
        setDeviceName(parsedSettings.deviceName || '');
        setApiUrl(parsedSettings.apiUrl || 'http://localhost/trackingv2/trackingv2');
        setApiKey(parsedSettings.apiKey || '');
        setTrackingFrequency(parsedSettings.trackingFrequency || '30');
      }
    } catch (error) {
      console.error('Error loading settings:', error);
    }
  };

  const saveSettings = async () => {
    try {
      const settings = {
        deviceId,
        deviceName,
        apiUrl,
        apiKey,
        trackingFrequency: parseInt(trackingFrequency),
      };
      
      await AsyncStorage.setItem('smartTrackSettings', JSON.stringify(settings));
      Alert.alert('Success', 'Settings saved successfully!');
    } catch (error) {
      Alert.alert('Error', 'Failed to save settings: ' + error.message);
    }
  };

  const testConnection = async () => {
    try {
      if (!apiUrl) {
        Alert.alert('Error', 'Please enter an API URL first');
        return;
      }

      // Remove trailing slash from API URL if present
      const cleanApiUrl = apiUrl.replace(/\/$/, '');
      const testUrl = `${cleanApiUrl}/api/test_connection.php`;
      
      console.log('🔍 Testing connection to:', testUrl);
      console.log('🔍 Original API URL:', apiUrl);
      console.log('🔍 Cleaned API URL:', cleanApiUrl);
      
      let response;
      let responseText;
      
      try {
        response = await fetch(testUrl, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        responseText = await response.text();
        console.log('📡 Response status:', response.status);
        console.log('📡 Response headers:', JSON.stringify([...response.headers.entries()]));
        console.log('📡 Response text (first 500 chars):', responseText.substring(0, 500));
        console.log('📡 Response length:', responseText.length);
        
        // Check if response is HTML
        if (responseText.trim().startsWith('<') || responseText.includes('<!DOCTYPE')) {
          console.error('❌ Server returned HTML instead of JSON!');
          console.error('❌ Full response:', responseText);
        }
      } catch (fetchError) {
        console.error('❌ Fetch error:', fetchError);
        Alert.alert('Network Error', `Failed to connect: ${fetchError.message}\n\nURL: ${testUrl}`);
        return;
      }

      try {
        const result = JSON.parse(responseText);
        
        if (result.success) {
          Alert.alert('Success', `Connection successful!\nDatabase: ${result.database || 'Connected'}`);
        } else {
          Alert.alert('Error', result.error || 'Connection failed. Check your API URL.');
        }
      } catch (parseError) {
        console.error('JSON parse error:', parseError);
        console.error('Response was:', responseText.substring(0, 500));
        
        // If not JSON, check if it's HTML error
        if (responseText.includes('successful') || responseText.includes('Connection successful')) {
          Alert.alert('Success', 'Connection to backend successful!');
        } else if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
          Alert.alert('Error', `Server returned HTML instead of JSON.\n\nCheck console for details.\n\nResponse preview: ${responseText.substring(0, 150)}`);
        } else {
          Alert.alert('Error', `Connection failed: ${responseText.substring(0, 100)}\n\nCheck console for full response.`);
        }
      }
    } catch (error) {
      Alert.alert('Error', `Connection failed: ${error.message}\n\nCheck:\n1. API URL is correct\n2. Server is online\n3. Internet connection`);
    }
  };

  return (
    <View style={styles.container}>
      <Card style={styles.card}>
        <Card.Content>
          <Title>Device Settings</Title>
          
          <TextInput
            label="Device ID"
            value={deviceId}
            onChangeText={setDeviceId}
            style={styles.input}
            placeholder="e.g., MOBILE-001"
          />
          
          <TextInput
            label="Device Name"
            value={deviceName}
            onChangeText={setDeviceName}
            style={styles.input}
            placeholder="e.g., Driver John's Phone"
          />
          
          <TextInput
            label="API URL"
            value={apiUrl}
            onChangeText={setApiUrl}
            style={styles.input}
            placeholder="http://localhost/trackingv2/trackingv2"
          />
          
          <TextInput
            label="API Key"
            value={apiKey}
            onChangeText={setApiKey}
            style={styles.input}
            placeholder="Your unique API key"
          />
          
          <TextInput
            label="Tracking Frequency (seconds)"
            value={trackingFrequency}
            onChangeText={setTrackingFrequency}
            style={styles.input}
            keyboardType="numeric"
            placeholder="30"
          />
        </Card.Content>
      </Card>

      <View style={styles.buttonContainer}>
        <Button 
          mode="contained" 
          onPress={saveSettings}
          style={styles.saveButton}
          icon="content-save"
        >
          Save Settings
        </Button>
        
        <Button 
          mode="outlined" 
          onPress={testConnection}
          style={styles.testButton}
          icon="wifi"
        >
          Test Connection
        </Button>
      </View>

      <Card style={styles.infoCard}>
        <Card.Content>
          <Title>Configuration Help</Title>
          <Text style={styles.helpText}>
            • Device ID: Unique identifier for this device{'\n'}
            • Device Name: Friendly name for this device{'\n'}
            • API URL: Your Smart Track backend URL{'\n'}
            • API Key: Authentication key for your backend{'\n'}
            • Frequency: How often to send GPS data (seconds)
          </Text>
        </Card.Content>
      </Card>
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
  input: {
    marginBottom: 15,
  },
  buttonContainer: {
    gap: 15,
    marginBottom: 20,
  },
  saveButton: {
    backgroundColor: '#4caf50',
  },
  testButton: {
    borderColor: '#2196f3',
  },
  infoCard: {
    elevation: 2,
  },
  helpText: {
    fontSize: 14,
    lineHeight: 20,
    color: '#666',
  },
});
