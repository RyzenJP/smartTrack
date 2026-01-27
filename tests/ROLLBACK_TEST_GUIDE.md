# Rollback Testing Guide

This guide outlines the procedures for testing database rollback functionality in the SmartTrack system.

## Overview

Rollback testing ensures that the system can safely revert to a previous state in case of:
- Failed deployments
- Database corruption
- Data integrity issues
- System errors

## Prerequisites

1. **Backup System**: Ensure the quick backup system is functional
2. **Test Environment**: Use a non-production environment
3. **Test Data**: Have known-good test data
4. **Access**: Super admin access required

## Test Scenarios

### 1. Database Backup Rollback

**Objective**: Test the ability to restore from a database backup

**Steps**:
1. Create a baseline backup
   - Navigate to Quick Backup page
   - Click "Create Backup Now"
   - Note the backup filename and size

2. Make controlled changes
   - Add test vehicle to fleet
   - Add test user
   - Create test reservation
   - Note all changes made

3. Create post-change backup
   - Create another backup
   - Verify new backup is larger

4. Restore to baseline
   - Select the baseline backup
   - Click "Restore"
   - Confirm restoration

5. Verify rollback
   - Check that test vehicle is removed
   - Check that test user is removed
   - Check that test reservation is removed
   - Verify data integrity

**Expected Result**: System should return to baseline state with no data corruption

**Pass Criteria**:
- ✅ Backup restoration completes without errors
- ✅ All test changes are reverted
- ✅ No data corruption or integrity issues
- ✅ System remains functional after rollback

---

### 2. Transaction Rollback

**Objective**: Test database transaction rollback on errors

**Steps**:
1. Simulate transaction failure
   - Attempt to insert invalid data
   - Force a constraint violation
   - Trigger a database error

2. Verify automatic rollback
   - Check that partial changes are not committed
   - Verify database consistency
   - Confirm error handling works correctly

**Expected Result**: Transaction should rollback cleanly, leaving database in consistent state

**Pass Criteria**:
- ✅ Failed transactions don't leave partial data
- ✅ Database constraints are enforced
- ✅ Error messages are clear and informative
- ✅ System recovers gracefully

---

### 3. File System Rollback

**Objective**: Test ability to restore uploaded files

**Steps**:
1. Note current file state
   - List files in uploads directory
   - Document file counts and sizes

2. Upload test files
   - Upload test reservation documents
   - Upload test images
   - Note all uploaded files

3. Simulate file corruption
   - Modify or delete uploaded files
   - Note what was changed

4. Restore from backup
   - Use file system backup if available
   - Or restore from application backup

5. Verify restoration
   - Check file integrity
   - Verify file accessibility
   - Test file downloads

**Expected Result**: Files should be restored to previous state

**Pass Criteria**:
- ✅ Files are restored correctly
- ✅ File permissions are maintained
- ✅ Downloads work after restoration
- ✅ No orphaned files remain

---

### 4. Application State Rollback

**Objective**: Test rolling back application code changes

**Steps**:
1. Document current version
   - Note git commit hash
   - Document application version
   - List any customizations

2. Deploy update
   - Deploy new code version
   - Run database migrations if any
   - Test new functionality

3. Identify issues
   - Simulate deployment failure
   - Or identify critical bug

4. Rollback deployment
   - Restore previous code version
   - Revert database migrations
   - Clear caches

5. Verify rollback
   - Test critical functionality
   - Check system stability
   - Verify user access

**Expected Result**: System should return to previous stable version

**Pass Criteria**:
- ✅ Code rollback completes successfully
- ✅ Database schema is compatible
- ✅ No functionality is broken
- ✅ Users can access system normally

---

### 5. Cache Rollback

**Objective**: Test cache invalidation after rollback

**Steps**:
1. Populate cache
   - Load several pages to populate cache
   - Verify cache files exist
   - Note cached data

2. Make data changes
   - Update vehicle information
   - Modify user data
   - Change system settings

3. Restore from backup
   - Restore database to previous state
   - Don't clear cache initially

4. Test for stale data
   - Check if old cache data is served
   - Look for inconsistencies

5. Clear cache
   - Manual cache clear
   - Or automatic invalidation

6. Verify data consistency
   - Check that fresh data is loaded
   - Verify no stale cache issues

**Expected Result**: Cache should be invalidated or cleared after rollback

**Pass Criteria**:
- ✅ Stale cache is detected
- ✅ Cache invalidation works
- ✅ Fresh data is loaded after clear
- ✅ No cache-related errors

---

## Automated Rollback Tests

### Running Automated Tests

```bash
# Run all rollback-related tests
php vendor/bin/phpunit tests/Integration/DatabaseTest.php --filter testTransaction

# Run specific rollback scenario
php vendor/bin/phpunit tests/Integration/DatabaseTest.php::testTransaction
```

### Continuous Integration

Add rollback tests to CI/CD pipeline:

```yaml
# Example GitHub Actions workflow
- name: Run Rollback Tests
  run: |
    php vendor/bin/phpunit tests/Integration/DatabaseTest.php --filter testTransaction
    php vendor/bin/phpunit tests/Integration/DatabaseTest.php --filter testPreparedStatementPreventsInjection
```

---

## Rollback Checklist

Before deployment, ensure:

- [ ] Current backup exists and is verified
- [ ] Rollback procedure is documented
- [ ] Rollback has been tested in staging
- [ ] Team knows how to execute rollback
- [ ] Rollback can complete within acceptable timeframe
- [ ] Data integrity checks are in place
- [ ] Users are notified of maintenance window
- [ ] Monitoring is in place to detect issues

## Post-Rollback Verification

After any rollback:

1. **Verify Data Integrity**
   - Run database consistency checks
   - Verify foreign key constraints
   - Check for orphaned records

2. **Test Critical Paths**
   - User login
   - Vehicle tracking
   - Report generation
   - Geofence monitoring

3. **Monitor System Health**
   - Check error logs
   - Monitor performance metrics
   - Verify all services are running

4. **Document Incident**
   - Record what caused rollback
   - Document rollback procedure used
   - Note any issues encountered
   - Plan corrective actions

---

## Emergency Rollback Procedure

### Quick Rollback Steps

1. **Stop accepting new requests** (if possible)
2. **Create emergency backup** of current state
3. **Identify last known good backup**
4. **Execute restore** from backup
5. **Verify critical functionality**
6. **Clear all caches**
7. **Resume normal operations**
8. **Monitor closely** for 24-48 hours

### Contact Information

- **System Administrator**: [Contact info]
- **Database Administrator**: [Contact info]
- **On-Call Developer**: [Contact info]

---

## Testing Schedule

- **Monthly**: Full rollback drill
- **Quarterly**: Emergency rollback simulation
- **Pre-deployment**: Rollback readiness check
- **Post-deployment**: Rollback capability verification

---

## Success Metrics

- **Recovery Time Objective (RTO)**: < 30 minutes
- **Recovery Point Objective (RPO)**: < 1 hour
- **Data Loss**: 0% on rollback
- **Rollback Success Rate**: 100%

---

## Notes

- Always test rollback in staging first
- Never rollback directly in production without backup
- Document every rollback for future reference
- Regular rollback drills keep team prepared

