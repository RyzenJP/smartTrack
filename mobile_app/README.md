# Mobile App Download Directory

## 📱 How to Set Up

1. **Place your APK file here:**
   - Upload your `SmartTrack.apk` file to this directory
   - The file should be named: `SmartTrack.apk`

2. **File Location:**
   ```
   trackingv2/mobile_app/SmartTrack.apk
   ```

3. **Alternative Location:**
   If you want to use a different location or filename, edit `download_app.php` and update the `$apk_file` path.

## 🔒 Security Note

- This directory should be accessible via web server
- Make sure file permissions are set correctly
- The APK file will be downloaded directly when users click "Download App" in the navbar

## ✅ Testing

After uploading your APK file:
1. Visit: `https://smarttrack.bccbsis.com/trackingv2/trackingv2/download_app.php`
2. The APK should download automatically
3. Or click "Download App" in the navbar on the homepage



