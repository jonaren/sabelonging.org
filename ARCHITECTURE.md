# System Architecture

## Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Belonging Resources System                │
│                     (MySQL-Based Architecture)               │
└─────────────────────────────────────────────────────────────┘

┌─────────────────┐         ┌──────────────────┐         ┌────────────────┐
│   Public Users  │────────▶│  Frontend Layer  │────────▶│   API Layer    │
│                 │         │                  │         │                │
│  - View cards   │         │ resource-test.   │         │ get-resources. │
│  - Search       │         │ html             │         │ php            │
│  - Filter       │         │                  │         │                │
│  - Click links  │         │ JavaScript       │         │ JSON Response  │
└─────────────────┘         │ - AJAX calls     │         └────────┬───────┘
                            │ - Card rendering │                  │
                            │ - Filtering      │                  │
                            └──────────────────┘                  │
                                                                  │
┌─────────────────┐         ┌──────────────────┐                 │
│  Admin Users    │────────▶│   Admin Panel    │─────────────────┤
│                 │         │                  │                 │
│  - Admins       │         │ - login.php      │                 │
│  - Editors      │         │ - resources.php  │                 │
│                 │         │ - add-resource.  │                 │
│                 │         │   php            │                 │
│                 │         │ - edit-resource. │                 │
│                 │         │   php            │                 │
│                 │         │ - users.php      │                 │
└─────────────────┘         │                  │                 │
                            │ Session Auth     │                 │
                            └──────────┬───────┘                 │
                                       │                         │
                                       │                         │
                            ┌──────────▼─────────────────────────▼─────┐
                            │         Database Layer                   │
                            │    usnsan5_belongingResources           │
                            │                                          │
                            │  Tables:                                 │
                            │  - admin_users                           │
                            │  - resources ⭐                          │
                            │  - resource_files                        │
                            │  - resource_types                        │
                            │  - categories                            │
                            │  - languages                             │
                            │  - ministries                            │
                            └──────────────────────────────────────────┘
```

## Data Flow

### Frontend Resource Loading

```
User visits resource-test.html
         │
         ▼
JavaScript loads
         │
         ▼
AJAX call to /api/get-resources.php
         │
         ▼
API queries database (published resources only)
         │
         ▼
Returns JSON with all resource data
         │
         ▼
JavaScript renders cards
         │
         ▼
User can filter/search (client-side)
         │
         ▼
User clicks card
         │
         ├─── External Resource? ──▶ Redirect to external_url
         │
         └─── Internal Resource? ──▶ Open internal page (or button_link)
```

### Admin Resource Creation

```
Admin logs in (login.php)
         │
         ▼
Session created with role
         │
         ▼
Navigate to Add Resource
         │
         ▼
Fill form with:
  - Resource details
  - [✓] Is External Resource?
  │   └─▶ Show external_url field
  │       Hide file uploads
  │
  - OR upload files
         │
         ▼
Submit form
         │
         ▼
Validate inputs
         │
         ▼
Upload files to /uploads/resources/
         │
         ▼
Insert into resources table
         │
         ▼
Insert file records (if any)
         │
         ▼
Redirect to resources list
         │
         ▼
Success message displayed
```

## Database Schema

### Core Tables Relationship

```
admin_users (1) ──────────────┐
                              │
                              │ created_by
                              │
                              ▼
resources (M) ────────────────┬─────────────────┐
    │                        │                 │
    │ resource_type_id       │ category_id     │ ministry_id
    │                        │                 │
    ▼                        ▼                 ▼
resource_types (1)       categories (1)    ministries (1)

resources (1) ───────┐
                     │ language_id
                     │
                     ▼
                languages (1)

resources (1) ───────┐
                     │
                     │ has many
                     │
                     ▼
            resource_files (M)
```

### Resources Table Structure

```
resources
├── id (PK)
├── resource_name
├── slug (unique)
├── description
├── about_content
├── thumbnail_image
│
├── 🆕 is_external_resource (boolean)
├── 🆕 external_url
├── button_link (legacy)
│
├── meta_data (keywords)
├── resource_type_id (FK)
├── category_id (FK)
├── language_id (FK)
├── ministry_id (FK)
│
├── is_published
├── view_count
│
├── created_by (FK → admin_users)
├── updated_by (FK → admin_users)
├── created_at
└── updated_at
```

## Security Architecture

### Authentication Flow

```
┌──────────────────────────────────────────────────┐
│              Authentication System               │
└──────────────────────────────────────────────────┘

User enters credentials
         │
         ▼
login.php validates
         │
         ▼
Check admin_users table
  - username matches?
  - is_active = 1?
  - password_verify()?
         │
         ├─── ✓ Success ──▶ Create session
         │                  - admin_id
         │                  - admin_username
         │                  - admin_role
         │                  │
         │                  ▼
         │              Update last_login
         │                  │
         │                  ▼
         │              Redirect to dashboard
         │
         └─── ✗ Fail ────▶ Show error message
```

### Authorization Levels

```
┌───────────────────────────────────────┐
│          Role Permissions             │
├───────────────────────────────────────┤
│                                       │
│  EDITOR:                              │
│   ✓ View resources                    │
│   ✓ Add resources                     │
│   ✓ Edit resources                    │
│   ✓ Delete resources                  │
│   ✓ Upload files                      │
│   ✓ Manage categories, types, etc.    │
│   ✗ Manage users                      │
│                                       │
├───────────────────────────────────────┤
│                                       │
│  ADMIN:                               │
│   ✓ Everything Editor can do          │
│   ✓ Manage admin users                │
│   ✓ Create new admins                 │
│   ✓ Activate/deactivate users         │
│   ✓ Delete users                      │
│   ✓ Full system access                │
│                                       │
└───────────────────────────────────────┘
```

### File Upload Security

```
File Upload Process:
         │
         ▼
1. Check file size (< 10MB)
         │
         ▼
2. Validate file extension
   Allowed: jpg, jpeg, png, gif, webp,
           pdf, doc, docx, xls, xlsx,
           ppt, pptx
         │
         ▼
3. Generate unique filename
   Format: {unique_id}_{timestamp}.{ext}
         │
         ▼
4. Move to /uploads/resources/
         │
         ▼
5. Store path in database
         │
         ▼
6. Return URL for access
```

## External vs Internal Resources

### External Resource Flow

```
Admin creates resource:
  ☑ Is External Resource
  URL: https://youtube.com/watch?v=...
         │
         ▼
Stored in database:
  is_external_resource = 1
  external_url = "https://..."
         │
         ▼
Frontend displays card
         │
         ▼
User clicks "View Resource"
         │
         ▼
Opens in new tab (target="_blank")
         │
         ▼
Redirects to external URL
```

### Internal Resource Flow

```
Admin creates resource:
  ☐ Is External Resource
  Uploads: document.pdf, image.jpg
         │
         ▼
Files stored in /uploads/resources/
         │
         ▼
Stored in database:
  is_external_resource = 0
  external_url = NULL
  Files in resource_files table
         │
         ▼
Frontend displays card
         │
         ▼
User clicks "View Resource"
         │
         ▼
Opens internal page (same tab)
         │
         ▼
Shows resource details + file downloads
```

## Performance Considerations

### Database Indexing

```
Indexes created on:
  - resources.slug (UNIQUE)
  - resources.is_published
  - resources.resource_type_id
  - resources.category_id
  - resources.created_at
  - resources.meta_data (FULLTEXT)

Benefits:
  ✓ Fast lookups by slug
  ✓ Efficient published filter
  ✓ Quick type/category filtering
  ✓ Optimized date sorting
  ✓ Full-text search on keywords
```

### Caching Strategy

```
Frontend:
  - Browser caches static assets
  - No server-side caching (yet)

API:
  - Returns fresh data on each request
  - Future: Add Redis/Memcached

Files:
  - Served directly by web server
  - CDN ready (can add CloudFlare)
```

## Scalability Path

### Current Architecture
- Single MySQL database
- File storage on web server
- No caching layer

### Future Enhancements
```
1. Add Redis caching
   └─▶ Cache API responses (5 min TTL)

2. Move files to CDN
   └─▶ S3/CloudFlare/DigitalOcean Spaces

3. Add search service
   └─▶ Elasticsearch for advanced search

4. Database replication
   └─▶ Master-slave for read scaling

5. Add queue system
   └─▶ Background jobs for image processing
```

---

**Architecture Version:** 1.0
**Last Updated:** 2025-11-11
