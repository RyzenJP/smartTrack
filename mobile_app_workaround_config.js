// Mobile App Workaround Configuration
// Update your mobile app settings to use this local workaround

const WORKAROUND_CONFIG = {
    // Use your domain for the workaround
    apiUrl: 'https://smarttrack.bccbsis.com/trackingv2/trackingv2',
    
    // Alternative endpoints
    gpsEndpoint: 'mobile_app_workaround.php?action=save_gps',
    testEndpoint: 'mobile_app_workaround.php',
    locationEndpoint: 'mobile_app_workaround.php?action=get_latest',
    
    // Original production endpoints (when server is fixed)
    productionApiUrl: 'https://smarttrack.bccbsis.com/trackingv2/trackingv2',
    productionGpsEndpoint: 'api/mobile_gps_api.php',
    productionTestEndpoint: 'api/mobile_test.php',
    productionLocationEndpoint: 'get_latest_location.php'
};

// Instructions for updating your mobile app:
console.log(`
MOBILE APP WORKAROUND INSTRUCTIONS:
==================================

1. Update your mobile app settings:
   - Change API URL to: ${WORKAROUND_CONFIG.apiUrl}
   - Or modify the sendLocationData function to use workaround endpoints

2. Test the workaround:
   - Start your mobile app
   - Press "Start Tracking"
   - Check if GPS data is being saved locally

3. Monitor local storage:
   - Check the 'mobile_gps_storage' folder
   - Files are saved as: gps_MOBILE-001_YYYY-MM-DD.json

4. When production server is fixed:
   - Change API URL back to: ${WORKAROUND_CONFIG.productionApiUrl}
   - Resume normal operation

WORKAROUND ENDPOINTS:
====================
- Save GPS: POST ${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.gpsEndpoint}
- Get Latest: GET ${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.locationEndpoint}&device_id=MOBILE-001
- Test Connection: GET ${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.testEndpoint}
`);

// Test the workaround
async function testWorkaround() {
    try {
        console.log('Testing workaround API...');
        
        // Test connection
        const testResponse = await fetch(`${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.testEndpoint}`);
        const testData = await testResponse.json();
        console.log('Workaround API test:', testData);
        
        // Test GPS saving
        const gpsData = {
            device_id: 'MOBILE-001',
            device_name: 'Test Device',
            latitude: 10.5305239,
            longitude: 122.8426807,
            speed: 0,
            battery_level: 0.8
        };
        
        const gpsResponse = await fetch(`${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.gpsEndpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(gpsData)
        });
        const gpsResult = await gpsResponse.json();
        console.log('GPS save test:', gpsResult);
        
        // Test getting latest location
        const locationResponse = await fetch(`${WORKAROUND_CONFIG.apiUrl}/${WORKAROUND_CONFIG.locationEndpoint}&device_id=MOBILE-001`);
        const locationData = await locationResponse.json();
        console.log('Latest location:', locationData);
        
    } catch (error) {
        console.error('Workaround test failed:', error);
    }
}

// Export for use in mobile app
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { WORKAROUND_CONFIG, testWorkaround };
}
