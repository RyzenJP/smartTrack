# 🎨 How to Change the App Icon

## 📋 Current Icon Configuration

Your app currently uses these icon files (in `assets/` folder):
- `icon.png` - Main app icon (1024x1024px)
- `adaptive-icon.png` - Android adaptive icon (1024x1024px)
- `splash-icon.png` - Splash screen icon (can be any size, will be resized)
- `favicon.png` - Web favicon (48x48px or 96x96px)

---

## 🖼️ Icon Specifications

### 1. **Main App Icon** (`icon.png`)
- **Size:** 1024x1024 pixels
- **Format:** PNG with transparency
- **Shape:** Square (will be rounded automatically on iOS)
- **Background:** Transparent or solid color
- **Used for:** iOS app icon, Android fallback

### 2. **Android Adaptive Icon** (`adaptive-icon.png`)
- **Size:** 1024x1024 pixels
- **Format:** PNG with transparency
- **Safe Zone:** Keep important content within 768x768px center (25% padding on all sides)
- **Background:** Defined in `app.json` (currently white: `#ffffff`)
- **Used for:** Android adaptive icon (shows on home screen)

### 3. **Splash Screen Icon** (`splash-icon.png`)
- **Size:** Any size (recommended: 512x512px or larger)
- **Format:** PNG
- **Background:** Will use `backgroundColor` from `app.json` (currently white)
- **Used for:** App loading screen

### 4. **Web Favicon** (`favicon.png`)
- **Size:** 48x48px or 96x96px (or 192x192px for better quality)
- **Format:** PNG or ICO
- **Used for:** Web browser favicon (if you deploy as web app)

---

## 🔧 Steps to Change Icons

### Step 1: Prepare Your Icon Images

1. **Create or find your icon design:**
   - Use a design tool (Photoshop, Figma, Canva, etc.)
   - Or use an online icon generator
   - Make sure it represents "Smart Track" / GPS tracking theme

2. **Export in required sizes:**
   - Main icon: 1024x1024px PNG
   - Adaptive icon: 1024x1024px PNG (with safe zone)
   - Splash icon: 512x512px or larger PNG
   - Favicon: 96x96px PNG

### Step 2: Replace Icon Files

1. **Navigate to the assets folder:**
   ```
   SmartTrackMobileApp/SmartTrackExpoApp/assets/
   ```

2. **Replace the files:**
   - Replace `icon.png` with your 1024x1024px icon
   - Replace `adaptive-icon.png` with your 1024x1024px adaptive icon
   - Replace `splash-icon.png` with your splash icon
   - Replace `favicon.png` with your favicon (optional)

### Step 3: Update app.json (if needed)

If you want to change the adaptive icon background color, edit `app.json`:

```json
"android": {
  "adaptiveIcon": {
    "foregroundImage": "./assets/adaptive-icon.png",
    "backgroundColor": "#YOUR_COLOR_HERE"  // Change this
  }
}
```

**Color options:**
- `#1E3A8A` - Dark blue (matches Smart Track theme)
- `#3B82F6` - Blue
- `#10B981` - Green
- `#FFFFFF` - White (current)
- `#000000` - Black

### Step 4: Test Locally (Optional)

Before building with EAS, you can test the icons locally:

```bash
cd SmartTrackMobileApp/SmartTrackExpoApp
npx expo start
```

Then press `i` for iOS simulator or `a` for Android emulator to see the icons.

---

## 🎨 Icon Design Tips

### For Best Results:

1. **Keep it simple:**
   - Icons are small, so simple designs work best
   - Avoid too much detail or text

2. **Use high contrast:**
   - Make sure your icon stands out on any background
   - Test on both light and dark backgrounds

3. **Follow platform guidelines:**
   - **iOS:** Icons are automatically rounded
   - **Android:** Adaptive icons can be shaped (circle, square, rounded square)
   - Keep important content in the center safe zone

4. **Smart Track Theme Ideas:**
   - GPS/location pin icon
   - Vehicle/car icon
   - Map with location marker
   - Tracking/radar symbol
   - "ST" or "Smart Track" text logo

---

## 📱 Quick Icon Generator Tools

If you don't have a design tool, you can use:

1. **AppIcon.co** - https://www.appicon.co/
   - Upload one image, generates all sizes

2. **IconKitchen** - https://icon.kitchen/
   - Google's adaptive icon generator

3. **Canva** - https://www.canva.com/
   - Free design tool with app icon templates

4. **Figma** - https://www.figma.com/
   - Free design tool (more advanced)

---

## ✅ Checklist Before Building

- [ ] `icon.png` is 1024x1024px PNG
- [ ] `adaptive-icon.png` is 1024x1024px PNG
- [ ] `splash-icon.png` is at least 512x512px PNG
- [ ] `favicon.png` is 96x96px PNG (optional)
- [ ] All icons have transparent backgrounds (if needed)
- [ ] Adaptive icon background color updated in `app.json` (if desired)
- [ ] Icons tested locally (optional but recommended)

---

## 🚀 After Changing Icons

Once you've replaced the icon files:

1. **Test locally** (optional):
   ```bash
   npx expo start
   ```

2. **Build with EAS:**
   ```bash
   eas build --platform android
   # or
   eas build --platform ios
   # or
   eas build --platform all
   ```

The new icons will be included in your build!

---

## 📝 Example: Smart Track Icon Design

If you want a quick icon, here's a simple design idea:

**Concept:** Blue circle with white location pin in center

**Colors:**
- Background: `#1E3A8A` (dark blue)
- Icon: White location pin
- Accent: `#3B82F6` (lighter blue for highlights)

**Design:**
- Simple, recognizable location/GPS pin
- Clean, modern look
- Works well at small sizes

---

## ⚠️ Important Notes

1. **File names must match exactly:**
   - `icon.png` (not `Icon.png` or `ICON.PNG`)
   - `adaptive-icon.png`
   - `splash-icon.png`
   - `favicon.png`

2. **After changing icons, you may need to:**
   - Clear app cache: `npx expo start -c`
   - Rebuild the app (icons are baked into the build)

3. **EAS Build:**
   - Icons are included during the build process
   - You don't need to manually add them to Android/iOS folders
   - EAS handles all the resizing automatically

---

## 🎯 Quick Start

**Fastest way to change icons:**

1. Create or download a 1024x1024px PNG icon
2. Save it as `icon.png` in `assets/` folder
3. Copy it and save as `adaptive-icon.png` (same file is fine)
4. Optionally create a splash icon
5. Build with EAS - done!

The simplest approach: Use the same 1024x1024px icon for both `icon.png` and `adaptive-icon.png`. It will work perfectly!

