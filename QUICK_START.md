# Quick Start Checklist

## Pre-Deployment Checklist

- [ ] **Step 1:** Run database schema
  ```bash
  mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources < database_schema.sql
  ```

- [ ] **Step 2:** Create uploads directory
  ```bash
  mkdir -p uploads/resources
  chmod 755 uploads uploads/resources
  ```

- [ ] **Step 3:** Replace old admin files OR update navigation links
  ```bash
  # Option A - Replace files:
  cd admin/
  mv resources.php resources-old.php
  mv resources-new.php resources.php
  mv add-resource.php add-resource-old.php
  mv add-resource-new.php add-resource.php

  # Option B - Update links in navigation to point to:
  # - add-resource-new.php
  # - resources-new.php
  ```

- [ ] **Step 4:** Test admin login
  - Go to: `https://sabelonging.org/admin/login.php`
  - Username: `admin`
  - Password: `admin123`

- [ ] **Step 5:** Change admin password immediately!

- [ ] **Step 6:** Create your admin users
  - Go to Admin Panel → Admin Users
  - Add your team members

- [ ] **Step 7:** Test adding a resource
  - Try adding a regular resource with files
  - Try adding an external resource with URL

- [ ] **Step 8:** Test frontend
  - Go to: `https://sabelonging.org/resource-test.html`
  - Verify resources load
  - Test search and filters

## Post-Deployment Checklist

- [ ] **Security:** Change default admin password
- [ ] **Backup:** Create database backup
  ```bash
  mysqldump -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources > backup_$(date +%Y%m%d).sql
  ```
- [ ] **Test:** Add at least 5 sample resources
- [ ] **Verify:** Check API endpoint: `/api/get-resources.php`
- [ ] **Clean up:** Delete or move old files (optional)
  - `admin/resources-old.php`
  - `admin/add-resource-old.php`

## Common Issues & Solutions

### ❌ "Database connection failed"
**Solution:** Check credentials in `admin/resources-config.php`

### ❌ "Cannot upload files"
**Solution:** Check folder permissions
```bash
ls -la uploads/
chmod 755 uploads uploads/resources
```

### ❌ "Resources not showing on frontend"
**Solutions:**
1. Make sure resources are marked as "Published"
2. Check API directly: `https://sabelonging.org/api/get-resources.php`
3. Check browser console for errors (F12)

### ❌ "Images not displaying"
**Solution:** Verify SITE_URL in `admin/resources-config.php` matches your domain

## File Locations Reference

| File | Location | Purpose |
|------|----------|---------|
| Database Schema | `/database_schema.sql` | SQL to create all tables |
| API Endpoint | `/api/get-resources.php` | Returns JSON of resources |
| Admin Login | `/admin/login.php` | Login page |
| Manage Resources | `/admin/resources.php` | List all resources |
| Add Resource | `/admin/add-resource.php` | Create new resource |
| Edit Resource | `/admin/edit-resource.php` | Edit existing resource |
| Manage Users | `/admin/users.php` | Admin user management |
| Frontend | `/resource-test.html` | Public resources page |
| Config | `/admin/resources-config.php` | Database & settings |

## Quick Commands

### View database tables:
```bash
mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources -e "SHOW TABLES;"
```

### Check admin users:
```bash
mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources -e "SELECT id, username, email, role, is_active FROM admin_users;"
```

### Check resources count:
```bash
mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources -e "SELECT COUNT(*) as total, is_published FROM resources GROUP BY is_published;"
```

### Create database backup:
```bash
mysqldump -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources > backup_$(date +%Y%m%d).sql
```

## Need Help?

1. ✅ Check [SETUP_INSTRUCTIONS.md](SETUP_INSTRUCTIONS.md) for detailed guide
2. ✅ Check [FILES_SUMMARY.md](FILES_SUMMARY.md) for file overview
3. ✅ Review error logs in hosting control panel
4. ✅ Check browser console (F12) for frontend errors

---

**Status:** Ready for deployment
**Database:** usnsan5_belongingResources
**Default Login:** admin / admin123 (CHANGE THIS!)
