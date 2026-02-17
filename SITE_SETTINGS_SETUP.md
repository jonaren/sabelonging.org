# Site Settings Setup Instructions

## Overview
The site settings feature allows you to edit header and footer elements (logo, navigation links, social media, contact info) from the admin panel without touching code.

## Installation Steps

### 1. Create the Database Table
Run this **ONE TIME** setup to create the site_settings table:

**Option A: Via Admin Panel (Recommended)**
1. Log in to the admin panel
2. Visit: `https://sabelonging.org/admin/setup_site_settings.php`
3. This will create the table and populate it with default values

**Option B: Via SQL**
Run the SQL file directly in your database:
```bash
mysql -u usnsan5_jon -p usnsan5_belonging < admin/create_site_settings_table.sql
```

### 2. Access Site Settings
1. Log in to the admin panel as an **admin** user (not editor)
2. Navigate to **Site Settings** in the left sidebar
3. You'll see all editable settings organized by category

## What You Can Edit

### General Settings
- **Site Name**: The text displayed next to the logo in the navbar
- **Site Logo URL**: The logo image URL

### Navigation Settings
- **Navigation Link 1-3**: Text, URL, Icon, and whether it's external
  - Link 1 is the "home" link (Resources)
  - Link 2 is the IHQ Resources link
  - Link 3 is the Belonging 2025 link

### Footer Settings
- **About Title**: Footer about section heading
- **About Text**: Footer about section description
- **Contact Email**: Email address displayed in footer
- **Contact Website**: Website URL for contact section
- **Copyright**: Copyright holder name

### Social Media Links
- **Facebook, Twitter, Instagram, YouTube**: Social media URLs
- Leave blank to hide a social icon

## How It Works

1. **Database Storage**: All settings are stored in the `site_settings` table
2. **Dynamic Loading**: The header and footer files load settings from the database on each page load
3. **Fallback Values**: If the database is unavailable, default values are used
4. **Security**: Only admin users can edit site settings

## File Structure

```
/includes/
  ├── settings.php       # Loads settings from database
  ├── header.php         # Uses dynamic settings for navbar
  └── footer.php         # Uses dynamic settings for footer

/admin/
  ├── site-settings.php  # Admin page to edit settings
  ├── setup_site_settings.php  # One-time setup script
  └── create_site_settings_table.sql  # Database schema
```

## Troubleshooting

**Settings not appearing?**
- Make sure you ran the setup script to create the database table
- Check that you're logged in as an admin (not editor)
- Clear your browser cache

**Changes not showing on the site?**
- Settings are loaded on each page load, so refresh the page
- Check that the database connection is working
- Verify the settings were saved (check the success message)

**Default values showing?**
- If the table doesn't exist, default hardcoded values are used
- Run the setup script to create the table

## Security Notes

- Settings are HTML-escaped to prevent XSS attacks
- Only admin users can access the Site Settings page
- Database queries use prepared statements
- URLs are validated on the client side

## Support

For questions or issues, contact the development team.
