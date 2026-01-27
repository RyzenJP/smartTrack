# 🚀 SmartTrack System - Comprehensive Deployment Guide

**Version**: 2.0  
**Last Updated**: December 10, 2025  
**Status**: Production-Ready

---

## 📋 TABLE OF CONTENTS

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [System Requirements](#system-requirements)
3. [Database Setup](#database-setup)
4. [Application Configuration](#application-configuration)
5. [File Permissions](#file-permissions)
6. [Security Configuration](#security-configuration)
7. [Deployment Steps](#deployment-steps)
8. [Post-Deployment Verification](#post-deployment-verification)
9. [Monitoring & Maintenance](#monitoring--maintenance)
10. [Troubleshooting](#troubleshooting)
11. [Rollback Procedures](#rollback-procedures)

---

## ✅ PRE-DEPLOYMENT CHECKLIST

### Code Quality
- [x] All security vulnerabilities fixed
- [x] All SQL injection vulnerabilities patched
- [x] Input validation implemented (100%)
- [x] CSRF protection enabled
- [x] Security headers configured
- [x] Debug code removed
- [x] Error handling secure
- [x] Code follows PSR-12 standards

### Testing
- [x] Unit tests pass (29+ tests)
- [x] Integration tests pass
- [x] Feature tests pass
- [x] Security tests pass
- [x] Manual testing completed
- [x] Cross-browser testing done

### Documentation
- [x] Security documentation complete
- [x] Deployment guide complete
- [x] API documentation updated
- [x] User manual updated

### Dependencies
- [x] Composer dependencies installed
- [x] Dependency audit passed (`composer audit`)
- [x] PHP version compatible (7.4+)
- [x] MySQL version compatible (5.7+)

---

## 💻 SYSTEM REQUIREMENTS

### Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **PHP** | 7.4+ | 8.0+ |
| **MySQL** | 5.7+ | 8.0+ |
| **Apache** | 2.4+ | 2.4+ |
| **Memory** | 512MB | 2GB+ |
| **Disk Space** | 500MB | 2GB+ |
| **SSL Certificate** | Required | Required |

### PHP Extensions Required

```bash
php -m | grep -E "mysqli|pdo|json|mbstring|openssl|curl|zip|gd"
```

**Required Extensions:**
- `mysqli` - MySQL database access
- `pdo` - Database abstraction
- `json` - JSON processing
- `mbstring` - Multibyte string handling
- `openssl` - SSL/TLS support
- `curl` - HTTP requests
- `zip` - Archive handling
- `gd` - Image processing

### Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## 🗄️ DATABASE SETUP

### 1. Create Database

```sql
CREATE DATABASE smarttrack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Create Database User

```sql
CREATE USER 'smarttrack_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON smarttrack_db.* TO 'smarttrack_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Import Database Schema

```bash
mysql -u smarttrack_user -p smarttrack_db < database_schema.sql
```

### 4. Verify Database

```sql
USE smarttrack_db;
SHOW TABLES;
SELECT COUNT(*) FROM user_table;
```

**Expected Tables:**
- `user_table` - System users
- `reservation_users` - Reservation users
- `fleet_vehicles` - Vehicle fleet
- `vehicle_reservations` - Reservations
- `gps_logs` - GPS tracking data
- `maintenance_records` - Maintenance history
- And 20+ more tables

---

## ⚙️ APPLICATION CONFIGURATION

### 1. Environment Variables

Create `.env` file in project root:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=smarttrack_user
DB_PASS=strong_password_here
DB_NAME=smarttrack_db

# Application Environment
ENVIRONMENT=production

# Security
SESSION_LIFETIME=1800
CSRF_TOKEN_LIFETIME=3600

# Email Configuration (if using)
SMTP_HOST=smtp.example.com
SMTP_USER=your_email@example.com
SMTP_PASS=your_password
SMTP_PORT=587

# Application URLs
APP_URL=https://yourdomain.com
BASE_URL=https://yourdomain.com/trackingv2
```

### 2. Database Configuration

Update `db_connection.php`:

```php
<?php
// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'smarttrack_db');
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'production');
```

### 3. Security Configuration

Verify `config/security.php` is properly configured:

```php
// Security class should be available
require_once __DIR__ . '/config/security.php';
$security = Security::getInstance();
```

### 4. Apache Configuration

Create `.htaccess` in project root:

```apache
# Enable HTTPS redirect (production)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "DENY"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
php_value memory_limit 256M
```

---

## 📁 FILE PERMISSIONS

### Set Proper Permissions

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Set writable directories
chmod 775 cache/
chmod 775 uploads/
chmod 664 security.log

# Protect sensitive files
chmod 600 .env
chmod 600 db_connection.php
```

### Directory Structure

```
trackingv2/
├── cache/          (775 - writable)
├── uploads/        (775 - writable)
├── config/         (755 - readable)
├── includes/       (755 - readable)
├── api/            (755 - readable)
└── .env            (600 - protected)
```

---

## 🔒 SECURITY CONFIGURATION

### 1. Enable HTTPS

**Apache Virtual Host Configuration:**

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/trackingv2
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/chain.crt
    
    <Directory /var/www/trackingv2>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 2. Verify Security Headers

Check headers are set:

```bash
curl -I https://yourdomain.com/trackingv2/
```

**Expected Headers:**
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### 3. Disable Directory Listing

Add to `.htaccess`:

```apache
Options -Indexes
```

### 4. Protect Sensitive Files

Add to `.htaccess`:

```apache
<FilesMatch "\.(env|log|sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## 🚀 DEPLOYMENT STEPS

### Step 1: Backup Current System (if upgrading)

```bash
# Backup database
mysqldump -u smarttrack_user -p smarttrack_db > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /var/www/trackingv2/
```

### Step 2: Upload Files

```bash
# Upload via FTP/SFTP or Git
git clone https://github.com/yourrepo/smarttrack.git
# OR
rsync -avz trackingv2/ user@server:/var/www/trackingv2/
```

### Step 3: Install Dependencies

```bash
cd /var/www/trackingv2
composer install --no-dev --optimize-autoloader
```

### Step 4: Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit .env with production values
nano .env
```

### Step 5: Set Permissions

```bash
chmod -R 755 .
chmod -R 775 cache/ uploads/
chmod 600 .env
```

### Step 6: Database Migration (if needed)

```bash
# Run migrations if any
php migrate.php
```

### Step 7: Clear Cache

```bash
# Clear application cache
rm -rf cache/*
```

### Step 8: Test Application

```bash
# Test database connection
php test_connection.php

# Test security headers
curl -I https://yourdomain.com/trackingv2/
```

### Step 9: Enable Maintenance Mode (Optional)

Create `maintenance.php`:

```php
<?php
http_response_code(503);
die('System maintenance in progress. Please check back soon.');
```

### Step 10: Go Live

Remove maintenance mode and verify:

```bash
rm maintenance.php
# Test all critical functions
```

---

## ✅ POST-DEPLOYMENT VERIFICATION

### 1. Security Verification

```bash
# Check security headers
curl -I https://yourdomain.com/trackingv2/

# Test HTTPS redirect
curl -I http://yourdomain.com/trackingv2/

# Verify no debug output
curl https://yourdomain.com/trackingv2/login.php
```

### 2. Functionality Testing

- [ ] User registration works
- [ ] User login works
- [ ] Password reset works
- [ ] Vehicle tracking works
- [ ] Reservation system works
- [ ] Admin functions work
- [ ] API endpoints respond correctly

### 3. Performance Testing

```bash
# Test response times
time curl https://yourdomain.com/trackingv2/

# Check database queries
# Monitor slow query log
```

### 4. Error Log Review

```bash
# Check PHP error log
tail -f /var/log/php_errors.log

# Check Apache error log
tail -f /var/log/apache2/error.log

# Check application logs
tail -f security.log
```

---

## 📊 MONITORING & MAINTENANCE

### Daily Monitoring

1. **Error Logs**
   ```bash
   tail -100 /var/log/php_errors.log
   tail -100 security.log
   ```

2. **Database Performance**
   ```sql
   SHOW PROCESSLIST;
   SHOW STATUS LIKE 'Slow_queries';
   ```

3. **Disk Space**
   ```bash
   df -h
   du -sh cache/ uploads/
   ```

### Weekly Maintenance

1. **Backup Database**
   ```bash
   mysqldump -u smarttrack_user -p smarttrack_db > weekly_backup_$(date +%Y%m%d).sql
   ```

2. **Clear Old Logs**
   ```bash
   # Keep last 30 days
   find logs/ -name "*.log" -mtime +30 -delete
   ```

3. **Update Dependencies**
   ```bash
   composer update --no-dev
   composer audit
   ```

### Monthly Maintenance

1. **Security Audit**
   - Review security logs
   - Check for suspicious activity
   - Update security documentation

2. **Performance Review**
   - Analyze slow queries
   - Optimize database indexes
   - Review caching strategy

3. **Backup Verification**
   - Test backup restoration
   - Verify backup integrity
   - Update backup procedures

---

## 🔧 TROUBLESHOOTING

### Common Issues

#### 1. Database Connection Error

**Symptoms:**
```
Database connection error. Please contact the administrator.
```

**Solutions:**
- Verify database credentials in `.env`
- Check database server is running
- Verify user permissions
- Check firewall rules

#### 2. Permission Denied Errors

**Symptoms:**
```
Warning: file_put_contents(): failed to open stream: Permission denied
```

**Solutions:**
```bash
chmod -R 775 cache/ uploads/
chown -R www-data:www-data cache/ uploads/
```

#### 3. HTTPS Redirect Loop

**Symptoms:**
```
ERR_TOO_MANY_REDIRECTS
```

**Solutions:**
- Check proxy configuration
- Verify `X-Forwarded-Proto` header
- Disable HTTPS enforcement temporarily for testing

#### 4. CSRF Token Errors

**Symptoms:**
```
Invalid security token. Please try again.
```

**Solutions:**
- Verify session is working
- Check session cookie settings
- Clear browser cache

#### 5. Slow Performance

**Symptoms:**
- Slow page loads
- Timeout errors

**Solutions:**
- Enable OpCache
- Optimize database queries
- Implement caching
- Review server resources

---

## 🔄 ROLLBACK PROCEDURES

### Emergency Rollback

If critical issues are discovered:

#### 1. Enable Maintenance Mode

```bash
echo "<?php http_response_code(503); die('System maintenance'); ?>" > maintenance.php
```

#### 2. Restore Database

```bash
mysql -u smarttrack_user -p smarttrack_db < backup_YYYYMMDD.sql
```

#### 3. Restore Files

```bash
tar -xzf backup_files_YYYYMMDD.tar.gz -C /var/www/
```

#### 4. Verify Rollback

- Test critical functions
- Check error logs
- Verify database integrity

### Rollback Testing

See `tests/ROLLBACK_TEST_GUIDE.md` for comprehensive rollback testing procedures.

---

## 📞 SUPPORT

### Deployment Issues

- **Documentation**: Check this guide first
- **Logs**: Review error logs
- **Testing**: Run test suite
- **Support**: Contact system administrator

### Emergency Contacts

- **System Administrator**: [Contact Info]
- **Database Administrator**: [Contact Info]
- **Security Team**: [Contact Info]

---

## 📚 ADDITIONAL RESOURCES

- **Security Documentation**: `SECURITY_DOCUMENTATION.md`
- **Testing Guide**: `tests/README.md`
- **Rollback Guide**: `tests/ROLLBACK_TEST_GUIDE.md`
- **API Documentation**: `API_DOCUMENTATION.md`

---

**Deployment Guide Status**: ✅ Production-Ready  
**Last Updated**: December 10, 2025  
**Next Review**: January 10, 2026

