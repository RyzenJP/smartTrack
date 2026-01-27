# 🔧 Fix: Failed to Upload Metadata to EAS Build

## ❌ Error
```
failed to upload metadata to eas build
```

## 🔍 Common Causes & Solutions

### 1. **Large node_modules Folder** (Most Common)

**Problem:** EAS tries to upload `node_modules` which can be huge (100+ MB)

**Solution:** Ensure `.gitignore` excludes `node_modules`

**Check:**
```bash
# Verify .gitignore exists and includes node_modules
cat .gitignore | grep node_modules
```

**Fix:**
Create/update `.gitignore`:
```
node_modules/
.expo/
dist/
```

### 2. **Network/Connection Issues**

**Problem:** Unstable internet connection

**Solutions:**
- Check your internet connection
- Try again: `eas build --platform android --profile preview`
- Use stable WiFi instead of mobile data
- Check firewall isn't blocking EAS

### 3. **File Size Limits**

**Problem:** Individual files too large

**Check large files:**
```bash
# Find files larger than 10MB
Get-ChildItem -Recurse -File | Where-Object {$_.Length -gt 10MB} | Select-Object FullName, @{Name="SizeMB";Expression={[math]::Round($_.Length/1MB,2)}}
```

**Solution:** Remove or exclude large unnecessary files

### 4. **Missing .easignore File**

**Problem:** EAS uploading unnecessary files

**Solution:** Create `.easignore` file:
```
node_modules/
.expo/
.git/
*.log
.DS_Store
android/
ios/
```

### 5. **EAS Service Issues**

**Problem:** Temporary EAS server issues

**Solution:**
- Wait a few minutes and try again
- Check EAS status: https://status.expo.dev
- Retry the build

## ✅ Quick Fixes

### Fix 1: Create .easignore
```bash
# Create .easignore file
echo "node_modules/" > .easignore
echo ".expo/" >> .easignore
echo ".git/" >> .easignore
echo "android/" >> .easignore
echo "ios/" >> .easignore
echo "*.log" >> .easignore
```

### Fix 2: Verify .gitignore
```bash
# Check if .gitignore exists
Test-Path .gitignore

# If not, create it
if (-not (Test-Path .gitignore)) {
    @"
node_modules/
.expo/
dist/
*.log
.DS_Store
"@ | Out-File -FilePath .gitignore -Encoding utf8
}
```

### Fix 3: Clean and Retry
```bash
# Clean build cache
eas build:cancel  # if build is stuck

# Remove node_modules (will reinstall)
Remove-Item -Recurse -Force node_modules

# Reinstall dependencies
npm install

# Try build again
eas build --platform android --profile preview
```

### Fix 4: Check Network
```bash
# Test connection to EAS
ping expo.io

# Check if you can reach EAS
curl https://expo.io
```

## 🎯 Step-by-Step Fix

1. **Create .easignore:**
   ```bash
   @"
   node_modules/
   .expo/
   .git/
   android/
   ios/
   *.log
   .DS_Store
   "@ | Out-File -FilePath .easignore -Encoding utf8
   ```

2. **Verify .gitignore:**
   ```bash
   # Should include node_modules
   Get-Content .gitignore | Select-String "node_modules"
   ```

3. **Retry Build:**
   ```bash
   eas build --platform android --profile preview
   ```

## 🔄 Alternative: Use EAS Build with Clean

```bash
# Cancel any stuck builds
eas build:cancel

# Clean and rebuild
eas build --platform android --profile preview --clear-cache
```

## 📊 Check What's Being Uploaded

EAS will show what it's uploading. Look for:
- Large file warnings
- Upload progress
- Error details

## 🆘 If Still Failing

1. **Check EAS Status:**
   - https://status.expo.dev

2. **Try Different Network:**
   - Switch WiFi
   - Use mobile hotspot
   - Try different location

3. **Contact Support:**
   - Check EAS build logs
   - Look for specific error messages
   - Share error details for help

## 💡 Prevention

Always have:
- ✅ `.gitignore` with `node_modules/`
- ✅ `.easignore` file
- ✅ Stable internet connection
- ✅ No large unnecessary files

---

**Try creating `.easignore` first - that usually fixes it!** 🔧







