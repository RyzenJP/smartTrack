# 🔄 Change EAS Build Account

## 🎯 Why Change?

Your current account has a 1-hour build limit (free tier). Creating a new account gives you a fresh 1-hour build time!

---

## 📋 Step-by-Step Guide

### Step 1: Log Out of Current Account

```bash
cd SmartTrackMobileApp/SmartTrackExpoApp
eas logout
```

This will log you out of your current Expo account.

---

### Step 2: Create a New Expo Account

**Option A: Create Account via Website (Easiest)**
1. Go to: https://expo.dev/signup
2. Create a new account with:
   - Different email address (or use a temporary email)
   - Username
   - Password
3. Verify your email if required

**Option B: Create Account via CLI**
```bash
eas register
```
Follow the prompts to create a new account.

---

### Step 3: Log In with New Account

```bash
eas login
```

Enter your new account credentials:
- Email
- Password

---

### Step 4: Link Project to New Account

You have two options:

#### Option A: Keep Same Project (Recommended)
```bash
eas init
```

When asked:
- "Would you like to create a new project?" → **No**
- "Would you like to link to an existing project?" → **Yes**
- Enter your existing project ID: `8689863b-f457-49c6-8b01-f6c4c6508bb5`

This keeps your project but links it to the new account.

#### Option B: Create New Project
```bash
eas init
```

When asked:
- "Would you like to create a new project?" → **Yes**
- This will create a NEW project with a NEW project ID

**⚠️ Note:** If you create a new project, you'll need to update `app.json`:
```json
"extra": {
  "eas": {
    "projectId": "NEW_PROJECT_ID_HERE"
  }
}
```

---

### Step 5: Verify Account

Check which account you're logged in as:
```bash
eas whoami
```

This shows your current account email.

---

## 🚀 Build with New Account

Once logged in with the new account:

```bash
eas build --platform android
```

You'll get a fresh 1-hour build time!

---

## 💡 Tips

### Multiple Accounts Strategy

You can create multiple free accounts and switch between them:
- Account 1: `user1@email.com` (1 hour used)
- Account 2: `user2@email.com` (fresh 1 hour)
- Account 3: `user3@email.com` (fresh 1 hour)

Just log out and log in with different accounts!

### Temporary Email Services

If you don't want to use your real email:
- https://temp-mail.org/
- https://10minutemail.com/
- https://guerrillamail.com/

These provide temporary emails that work for account creation.

---

## ⚠️ Important Notes

1. **Project Ownership:**
   - If you link to existing project → Project stays the same, just new owner
   - If you create new project → You get a new project ID

2. **Build History:**
   - New account = Fresh build history
   - Old builds won't be visible in new account

3. **App Store/Play Store:**
   - If you already published, keep using the same account
   - New account = Need to publish again

4. **Free Tier Limits:**
   - Each free account gets 1 hour/month
   - Android builds: ~15-20 minutes each
   - iOS builds: ~20-30 minutes each
   - So you can do ~2-3 builds per account per month

---

## ✅ Quick Commands Summary

```bash
# 1. Log out
eas logout

# 2. Log in with new account
eas login

# 3. Link project (or create new)
eas init

# 4. Verify account
eas whoami

# 5. Build!
eas build --platform android
```

---

## 🎯 Alternative: Use Expo's Free Tier Efficiently

Instead of switching accounts, you can:
- Build only when needed (not for testing)
- Use local builds for development: `npx expo run:android`
- Use EAS Build only for production APKs

But if you need more builds, switching accounts works great! 😄

---

## 🆘 Troubleshooting

**"Project already exists"**
- The project ID is already linked to another account
- Solution: Create a new project instead (Option B above)

**"Authentication failed"**
- Check your email/password
- Try: `eas logout` then `eas login` again

**"Build queue full"**
- Free tier has limited concurrent builds
- Wait a few minutes and try again

---

Good luck with your builds! 🚀

