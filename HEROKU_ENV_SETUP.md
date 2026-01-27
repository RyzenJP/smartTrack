# 🔧 Heroku Environment Variables Setup

## ✅ Your ML Server is Updated!

Your `ml_server.py` has been updated to use **Flask** (perfect for Heroku!). The database connection is already configured to use environment variables.

---

## 📋 Required Heroku Environment Variables

Your Heroku app needs these 4 environment variables to connect to your Hostinger database:

### **Via Heroku Dashboard:**

1. **Go to:** https://dashboard.heroku.com/apps/endpoint-smarttrack-ec777ab9bb50/settings

2. **Click:** "Reveal Config Vars"

3. **Add these 4 variables:**

| Key | Value |
|-----|-------|
| `DB_HOST` | `localhost` (or your Hostinger DB host) |
| `DB_NAME` | `u520834156_dbSmartTrack` |
| `DB_USER` | `u520834156_uSmartTrck25` |
| `DB_PASS` | `xjOzav~2V` |

4. **Restart:** "More" → "Restart all dynos"

---

### **Via Heroku CLI:**

```bash
heroku config:set DB_HOST=localhost -a endpoint-smarttrack-ec777ab9bb50
heroku config:set DB_NAME=u520834156_dbSmartTrack -a endpoint-smarttrack-ec777ab9bb50
heroku config:set DB_USER=u520834156_uSmartTrck25 -a endpoint-smarttrack-ec777ab9bb50
heroku config:set DB_PASS=xjOzav~2V -a endpoint-smarttrack-ec777ab9bb50
heroku restart -a endpoint-smarttrack-ec777ab9bb50
```

---

## 🔍 Verify Configuration

### Check if variables are set:

**Via Dashboard:**
- Settings → Config Vars → Should see all 4 variables

**Via CLI:**
```bash
heroku config -a endpoint-smarttrack-ec777ab9bb50
```

Should show:
```
DB_HOST: localhost
DB_NAME: u520834156_dbSmartTrack
DB_USER: u520834156_uSmartTrck25
DB_PASS: xjOzav~2V
```

---

## ⚠️ Important: Database Host

**Most Hostinger databases use `localhost`**, but if your database is on a different server, you need to:

1. **Check Hostinger Panel:**
   - Login: https://hpanel.hostinger.com
   - Go to: Databases → MySQL Databases
   - Find: "Database Host" or "MySQL Host"

2. **Common Hostinger Hosts:**
   - `localhost` (if same server)
   - `mysql.hostinger.com` (shared hosting)
   - `127.0.0.1`
   - Specific hostname like `mysql123.hostinger.com`

3. **Update Heroku:**
   ```bash
   heroku config:set DB_HOST=your_actual_host -a endpoint-smarttrack-ec777ab9bb50
   ```

---

## 🧪 Test After Configuration

### 1. Test Status:
```
https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/status
```

### 2. Test Training:
```
POST https://endpoint-smarttrack-ec777ab9bb50.herokuapp.com/train
```

Or via your PHP bridge:
```
https://smarttrack.bccbsis.com/trackingv2/trackingv2/api/python_ml_bridge.php?action=train
```

### 3. Check Heroku Logs:
```bash
heroku logs --tail -a endpoint-smarttrack-ec777ab9bb50
```

Or via Dashboard: "More" → "View logs"

---

## 🚨 Common Issues

### Issue: "Can't connect to MySQL server on 'localhost:3306'"

**Possible Causes:**
1. **Wrong DB_HOST** - Check Hostinger for actual hostname
2. **Hostinger blocks external connections** - Some plans don't allow external MySQL access
3. **Environment variables not set** - Verify all 4 variables are set

**Solutions:**
- Check Hostinger panel for correct database host
- Verify environment variables are set correctly
- Check if Hostinger allows remote MySQL connections

### Issue: "Access denied for user"

**Solution:**
- Double-check `DB_USER` and `DB_PASS` are correct
- Verify user has permissions in Hostinger

### Issue: "Unknown database"

**Solution:**
- Verify `DB_NAME` is exactly: `u520834156_dbSmartTrack`
- Check database exists in Hostinger panel

---

## ✅ Your Updated ML Server

Your `ml_server.py` is now:
- ✅ Using Flask (better for Heroku)
- ✅ CORS enabled (works with your PHP app)
- ✅ Using environment variables for database
- ✅ Ready for Heroku deployment

**Just need to set the environment variables!**

---

## 📝 Quick Checklist

- [ ] Set `DB_HOST` in Heroku
- [ ] Set `DB_NAME` in Heroku
- [ ] Set `DB_USER` in Heroku
- [ ] Set `DB_PASS` in Heroku
- [ ] Restart Heroku app
- [ ] Test status endpoint
- [ ] Test training endpoint
- [ ] Check Heroku logs for errors

---

*Last Updated: 2025-01-27*

