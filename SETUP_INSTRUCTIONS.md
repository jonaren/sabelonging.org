# Belonging Resources - MySQL Setup Instructions

## Overview
This guide will help you migrate from Google Sheets API to MySQL database for managing resources.

## Database Information
- **Database Name:** `usnsan5_belongingResources`
- **Username:** `usnsan5_jon`
- **Password:** `l9,i`P9>:qE8`
- **Host:** `localhost`

## Step 1: Create the Database Tables

1. Log into your MySQL database using phpMyAdmin or command line
2. Run the SQL script located at: `database_schema.sql`

```bash
mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources < database_schema.sql
```

OR copy and paste the contents of `database_schema.sql` into phpMyAdmin SQL tab.

This will create all necessary tables:
- `admin_users` - Admin user accounts
- `resource_types` - Article, Video, Book, etc.
- `categories` - Resource categories
- `languages` - Language options
- `ministries` - Ministry classifications
- `resources` - Main resources table
- `resource_files` - Uploaded files associated with resources

## Step 2: File Structure

### New Files Created:

**Admin Section:**
- `admin/resources-config.php` - Configuration for the resources database
- `admin/add-resource-new.php` - New add resource page with external URL feature
- `admin/edit-resource.php` - Edit existing resources
- `admin/resources-new.php` - Main resources management page
- `admin/users.php` - Admin user management (admin access only)

**API:**
- `api/get-resources.php` - REST API endpoint for fetching resources

**Frontend:**
- `resource-test.html` - Updated to use MySQL API instead of Google Sheets

### File Locations:
```
sabelonging.org/
├── admin/
│   ├── resources-config.php (NEW)
│   ├── add-resource-new.php (NEW)
│   ├── edit-resource.php (NEW)
│   ├── resources-new.php (NEW)
│   ├── users.php (NEW)
│   ├── login.php (existing)
│   └── config.php (existing)
├── api/
│   └── get-resources.php (NEW)
├── uploads/
│   └── resources/ (will be created automatically)
├── resource-test.html (UPDATED)
└── database_schema.sql (NEW)
```

## Step 3: Update File References

### Option A: Replace Old Files (Recommended)
Rename the new files to match your existing structure:
```bash
cd /Users/jon.aren/Desktop/websites/sabelonging.org/admin/
mv resources.php resources-old.php
mv resources-new.php resources.php
mv add-resource.php add-resource-old.php
mv add-resource-new.php add-resource.php
```

### Option B: Update Navigation Links
Update all admin navigation links to point to:
- `add-resource-new.php` instead of `add-resource.php`
- `resources-new.php` instead of `resources.php`

## Step 4: Create Uploads Directory

Create the uploads directory with proper permissions:
```bash
cd /Users/jon.aren/Desktop/websites/sabelonging.org/
mkdir -p uploads/resources
chmod 755 uploads
chmod 755 uploads/resources
```

## Step 5: Default Admin Login

After running the database schema, you can login with:
- **Username:** `admin`
- **Password:** `admin123`

**IMPORTANT:** Change this password immediately after first login!

## Step 6: Test the Setup

1. **Login to Admin Panel:**
   - Go to: `https://sabelonging.org/admin/login.php`
   - Login with default credentials

2. **Create Admin Users:**
   - Navigate to "Admin Users" (only visible to admins)
   - Create additional users as needed

3. **Add Sample Resource:**
   - Go to "Resources" > "Add New Resource"
   - Test both regular and external resource types
   - Upload thumbnail and additional files

4. **View Frontend:**
   - Go to: `https://sabelonging.org/resource-test.html`
   - Verify resources load from MySQL
   - Test filtering and search

## Features

### External Resources
When adding/editing a resource:
- Check "This is an external resource" checkbox
- Enter the external URL (e.g., YouTube video, external website)
- When clicked on frontend, users will be redirected to that URL
- File uploads are disabled for external resources

### Regular Resources
- Upload thumbnail images
- Upload multiple files (PDF, Word, Excel, PowerPoint)
- Files stored in `/uploads/resources/`
- Users click to view internal resource page

### Admin Features
- **Admins:** Full access including user management
- **Editors:** Can manage resources only
- Search and filter resources
- Bulk operations
- View statistics (view count)

## Troubleshooting

### Issue: Cannot upload files
**Solution:** Check folder permissions
```bash
chmod 755 uploads
chmod 755 uploads/resources
```

### Issue: Database connection error
**Solution:** Verify credentials in `admin/resources-config.php`

### Issue: Resources not showing on frontend
**Solution:**
1. Check API endpoint: `https://sabelonging.org/api/get-resources.php`
2. Ensure resources are marked as "Published"
3. Check browser console for errors

### Issue: Images not displaying
**Solution:**
1. Verify SITE_URL in `resources-config.php` matches your domain
2. Check that uploaded images are accessible

## Migration from Google Sheets

If you have existing data in Google Sheets:

1. Export your Google Sheet as CSV
2. Create a PHP script to import CSV into MySQL, or
3. Manually re-create resources using the admin panel

Sample import script structure:
```php
// Parse CSV and insert into resources table
$csv = file_get_contents('resources.csv');
$lines = explode("\n", $csv);
// ... parse and insert
```

## Database Maintenance

### Backup Database
```bash
mysqldump -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources > backup.sql
```

### Restore Database
```bash
mysql -u usnsan5_jon -p'l9,i`P9>:qE8' usnsan5_belongingResources < backup.sql
```

## Security Notes

1. **Change Default Password:** Change admin password immediately
2. **File Permissions:** Keep uploads directory at 755, not 777
3. **Database Credentials:** Never commit `resources-config.php` to public repositories
4. **SQL Injection:** All queries use prepared statements (already implemented)
5. **File Upload Security:** File type validation is in place

## API Documentation

### GET /api/get-resources.php

Returns all published resources in JSON format.

**Response:**
```json
{
  "success": true,
  "count": 10,
  "resources": [
    {
      "id": 1,
      "ResourceName": "Example Resource",
      "slug": "example-resource",
      "Description": "Short description",
      "ThumbnailImage": "https://...",
      "ButtonLink": "https://...",
      "ResourceType": "Article",
      "Category": "Faith",
      "Language": "English",
      "Ministry": "Youth",
      "is_external_resource": false,
      "view_count": 42
    }
  ]
}
```

## Support

For issues or questions:
1. Check this documentation
2. Review error logs in your hosting control panel
3. Check browser console for frontend errors
4. Verify database credentials

---

**Last Updated:** 2025-11-11
**Version:** 1.0
