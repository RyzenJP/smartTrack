# ✅ Test Heroku ML Server Deployment

## 🎯 Status: Code Uploaded to Heroku

---

## 📋 Next Steps

### **Step 1: Restart Heroku Dyno** ⚠️ **REQUIRED**

After uploading code, you **MUST** restart the dyno for changes to take effect:

#### **Option A: Via Heroku Dashboard**
1. Go to: https://dashboard.heroku.com/apps/endpoint-smarttrack-ec777ab9bb50
2. Click **"More"** → **"Restart all dynos"**

#### **Option B: Via Heroku CLI**
```bash
heroku restart -a endpoint-smarttrack-ec777ab9bb50
```

---

### **Step 2: Test Database Connection**

Test if the ML server can connect to your Hostinger database:

#### **Test URL:**
```
https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/health
```

**Expected Response:**
```json
{
  "status": "ok",
  "database": "connected"
}
```

#### **If Connection Fails:**
You'll see an error like:
```
Can't connect to MySQL server on 'localhost:3306' (111)
```

**This means:** The `DB_HOST` default (`localhost`) is incorrect for Hostinger.

---

### **Step 3: Fix DB_HOST (If Needed)**

If Step 2 fails, you need to update the `DB_HOST` default in `ml_server.py`:

1. **Find your Hostinger database host:**
   - Log into Hostinger control panel
   - Go to **"Databases"** → **"MySQL Databases"**
   - Look for **"Database Host"** or **"MySQL Host"**
   - Common values:
     - `localhost` (only works from same server)
     - `mysql.hostinger.com` (shared hosting)
     - `mysql123.hostinger.com` (specific server)

2. **Update `ml_server.py` line 54:**
   ```python
   'host': os.getenv('DB_HOST', 'your_actual_hostname_here'),
   ```
   Replace `your_actual_hostname_here` with the actual Hostinger database host.

3. **Re-upload to Heroku** and **restart dyno**

---

### **Step 4: Test ML Model Training**

Once database connection works, test training:

#### **Via PHP Bridge:**
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=train
```

#### **Direct to Heroku:**
```
https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/train
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Model trained successfully",
  "accuracy": 0.95,
  "training_time": "2.5s"
}
```

---

### **Step 5: Test Prediction**

Test if predictions work:

```
https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/predict?vehicle_id=1
```

**Expected Response:**
```json
{
  "success": true,
  "vehicle_id": 1,
  "prediction": "maintenance_needed",
  "confidence": 0.85,
  "maintenance_date": "2024-01-15"
}
```

---

## 🔍 Troubleshooting

### **Error: "Can't connect to MySQL server"**

**Cause:** Wrong `DB_HOST` or Hostinger blocks external connections.

**Solutions:**
1. ✅ Update `DB_HOST` in `ml_server.py` to actual Hostinger hostname
2. ✅ Check Hostinger allows remote MySQL connections
3. ✅ Verify database credentials are correct

### **Error: "Access denied for user"**

**Cause:** Wrong username/password.

**Solutions:**
1. ✅ Double-check `DB_USER` and `DB_PASS` in `ml_server.py`
2. ✅ Verify credentials in Hostinger control panel

### **Error: "Unknown database"**

**Cause:** Wrong database name.

**Solutions:**
1. ✅ Verify `DB_NAME` is `u520834156_dbSmartTrack`
2. ✅ Check database exists in Hostinger

---

## ✅ Success Checklist

- [ ] Heroku dyno restarted
- [ ] `/health` endpoint returns `"database": "connected"`
- [ ] `/train` endpoint works without errors
- [ ] `/predict` endpoint returns predictions
- [ ] PHP bridge can communicate with Heroku ML server

---

## 📞 Quick Test Commands

```bash
# Test health
curl https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/health

# Test training
curl https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/train

# Test prediction
curl "https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/predict?vehicle_id=1"
```

---

## 🎉 Once All Tests Pass

Your ML server is fully deployed and ready to use! 🚀



