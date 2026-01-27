#!/bin/bash

echo "========================================"
echo "Smart Track Mobile App Setup"
echo "========================================"
echo

echo "Checking Node.js installation..."
if ! command -v node &> /dev/null; then
    echo "ERROR: Node.js not found. Please install Node.js from https://nodejs.org/"
    exit 1
fi
node --version

echo
echo "Checking npm installation..."
if ! command -v npm &> /dev/null; then
    echo "ERROR: npm not found. Please install Node.js from https://nodejs.org/"
    exit 1
fi
npm --version

echo
echo "Installing React Native CLI..."
npm install -g @react-native-community/cli

echo
echo "Installing project dependencies..."
npm install

echo
echo "========================================"
echo "Setup Complete!"
echo "========================================"
echo
echo "Next steps:"
echo "1. Install Android Studio from https://developer.android.com/studio"
echo "2. Set up Android SDK and environment variables"
echo "3. Run: npm run android"
echo
echo "For detailed instructions, see INSTALLATION_GUIDE.md"
echo
