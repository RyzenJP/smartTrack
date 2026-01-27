# 🗄️ Local Database Setup Guide

## Problem: "Unknown database 'trackingv2'"

This error occurs because the database `trackingv2` doesn't exist in your local XAMPP MySQL.

---

## ✅ Solution: Create the Database

### Option 1: Using phpMyAdmin (Easiest)

1. **Open phpMyAdmin:**
   - Go to: `http://localhost/phpmyadmin`
   - Or click "phpMyAdmin" in XAMPP Control Panel

2. **Create Database:**
   - Click "New" in the left sidebar
   - Database name: `trackingv2`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

3. **Import Database Structure:**
   - Select the `trackingv2` database
   - Click "Import" tab
   - Choose file: `trackingv2.sql` (if you have it)
   - Click "Go"

---

### Option 2: Using MySQL Command Line

1. **Open MySQL Command Line:**
   - Open XAMPP Control Panel
   - Click "Shell" button
   - Or open Command Prompt and navigate to: `C:\xampp\mysql\bin`

2. **Login to MySQL:**
   ```bash
   mysql -u root -p
   ```
   (Press Enter when asked for password - XAMPP root has no password by default)

3. **Create Database:**
   ```sql
   CREATE DATABASE trackingv2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

4. **Use the Database:**
   ```sql
   USE trackingv2;
   ```

5. **Import SQL File (if you have it):**
   ```sql
   SOURCE C:/xampp/htdocs/trackingv2/trackingv2/trackingv2.sql;
   ```

6. **Exit MySQL:**
   ```sql
   EXIT;
   ```

---

### Option 3: Quick SQL Script

Create a file `create_database.sql` in your project root:

```sql
CREATE DATABASE IF NOT EXISTS trackingv2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE trackingv2;
```

Then run it:
```bash
mysql -u root < create_database.sql
```

---

## 🔍 Verify Database Exists

1. **Check in phpMyAdmin:**
   - Open phpMyAdmin
   - Look for `trackingv2` in the left sidebar
   - If it exists, you're good!

2. **Check via Command Line:**
   ```sql
   SHOW DATABASES LIKE 'trackingv2';
   ```

---

## 📋 Next Steps

After creating the database:

1. **If you have a SQL dump file:**
   - Import `trackingv2.sql` using phpMyAdmin or command line

2. **If you don't have a SQL dump:**
   - The application will create tables as needed
   - Or you can run the setup scripts

3. **Test the Connection:**
   - Try accessing `http://localhost/trackingv2/trackingv2/login.php`
   - The error should be gone!

---

## ⚠️ Troubleshooting

### Error: "Access denied for user 'root'@'localhost'"

**Solution:**
- Check your `.env` file or `db_connection.php`
- Make sure `DB_USER` is `root` and `DB_PASS` is empty (for XAMPP default)

### Error: "Can't connect to MySQL server"

**Solution:**
- Make sure MySQL is running in XAMPP Control Panel
- Check that MySQL service is started (green "Running" status)

### Error: "Database exists but still getting error"

**Solution:**
- Check database name spelling (case-sensitive on some systems)
- Verify the database name in `db_connection.php` matches exactly

---

## 🔧 Alternative: Use Different Database Name

If you want to use a different database name:

1. **Update `.env` file:**
   ```env
   DB_NAME=your_database_name
   ```

2. **Or update `db_connection.php`:**
   ```php
   if (!defined('DB_NAME')) define('DB_NAME', 'your_database_name');
   ```

3. **Create the database with your chosen name**

---

*Last Updated: 2025-01-27*

