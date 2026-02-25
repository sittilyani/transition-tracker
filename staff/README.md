# County Staff Management System

A comprehensive staff management system for county health facilities with photo management, facility auto-population, and Excel export capabilities.

## Features

- ✅ Add new staff members with photo upload
- ✅ Edit existing staff records
- ✅ View detailed staff information
- ✅ Disable staff members (soft delete)
- ✅ Search and filter staff list in real-time
- ✅ Pagination (20 records per page)
- ✅ Export to Excel with current filters
- ✅ Auto-populate facility details (county, subcounty, level of care)
- ✅ Photo upload and display with LONGBLOB storage
- ✅ User tracking (created_by from logged-in user)

## Installation

### Step 1: Database Update
Run the SQL script to add the photo and status columns to your county_staff table:

```sql
-- Run update_county_staff_table.sql
source update_county_staff_table.sql;
```

Or manually execute:
```sql
ALTER TABLE `county_staff` ADD COLUMN `photo` LONGBLOB AFTER `cadre_name`;
ALTER TABLE `county_staff` ADD COLUMN `status` VARCHAR(20) DEFAULT 'active' AFTER `photo`;
UPDATE `county_staff` SET `status` = 'active' WHERE `status` IS NULL;
```

### Step 2: File Structure
Upload all files to your server with the following structure:

```
your-project/
├── includes/
│   ├── config.php (your database connection)
│   └── header.php (your header file)
├── staff/ (or your preferred directory)
│   ├── add_staff.php
│   ├── staffslist.php
│   ├── update_staff.php
│   ├── view_staff.php
│   ├── disable_staff.php
│   ├── display_photo.php
│   ├── get_facility_details.php
│   └── export_staff.php
```

### Step 3: Database Connection
Ensure your `../includes/config.php` has the mysqli connection:

```php
<?php
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "your_database";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
```

### Step 4: Session Management
Ensure your session has these variables set after login:
- `$_SESSION['user_id']` - User ID from tblusers
- `$_SESSION['full_name']` - Full name from tblusers

## Files Description

### 1. add_staff.php
Main form for adding new staff members with:
- Photo upload with preview
- Facility auto-population
- Department and cadre autocomplete
- Form validation

### 2. staffslist.php
Main staff list view with:
- Real-time search filtering
- Pagination (20 records per page)
- Photo thumbnails
- Action buttons (View, Edit, Disable)
- Export to Excel button
- Statistics display

### 3. update_staff.php
Edit existing staff records with:
- Pre-filled form data
- Current photo display
- Option to update photo
- All fields editable

### 4. view_staff.php
Detailed staff information view with:
- Large photo display
- Formatted details in grid
- Edit button
- Back to list navigation

### 5. disable_staff.php
Soft delete functionality:
- Sets status to 'disabled'
- Preserves data for records
- Redirects with success message

### 6. display_photo.php
Photo retrieval handler:
- Fetches LONGBLOB from database
- Serves as image/jpeg
- Can be extended for default avatar

### 7. get_facility_details.php
AJAX endpoint for facility auto-population:
- Returns JSON with facility details
- Populates county, subcounty, level_of_care

### 8. export_staff.php
Excel export functionality:
- Exports filtered results
- Tab-delimited format
- Timestamped filename
- All staff fields included

## Usage

### Adding Staff
1. Navigate to `add_staff.php` or click "Add New Staff" button
2. Fill in required fields (marked with *)
3. Select facility (auto-populates county, subcounty, level of care)
4. Upload photo (optional)
5. Click "Add Staff"

### Viewing Staff List
1. Navigate to `staffslist.php`
2. Use search box to filter results
3. Click page numbers for pagination
4. Click "View" to see details
5. Click "Edit" to modify record
6. Click "Disable" to deactivate staff

### Editing Staff
1. From staff list, click "Edit" button
2. Update any fields as needed
3. Upload new photo if desired (old photo retained if not updated)
4. Click "Update Staff"

### Exporting to Excel
1. From staff list, apply any search filters
2. Click "Export to Excel" button
3. File downloads with current filter applied

## Color Scheme
Primary color: #011f88 (Navy Blue)
- Used for headers, buttons, accents
- Maintains consistent branding throughout

## Browser Compatibility
- Chrome/Edge (Latest)
- Firefox (Latest)
- Safari (Latest)
- Mobile responsive

## Security Notes
- Always sanitize user inputs with `mysqli_real_escape_string()`
- Check user authentication before displaying pages
- Use prepared statements for production (recommended upgrade)
- Validate file uploads (type, size)
- Implement CSRF tokens for forms (recommended)

## Customization

### Change Records Per Page
In `staffslist.php`, modify:
```php
$limit = 20; // Change to desired number
```

### Add More Search Fields
In `staffslist.php`, update the WHERE clause:
```php
$where_clause .= " AND (field_name LIKE '%$search%' OR ...)";
```

### Modify Export Format
In `export_staff.php`, adjust:
- Headers
- Data columns
- File format (change .xls to .csv if needed)

## Troubleshooting

### Photos not displaying
- Check file upload permissions
- Verify LONGBLOB column exists
- Check file size limits in php.ini (upload_max_filesize, post_max_size)

### Facility auto-population not working
- Verify facilities table has correct columns
- Check JavaScript console for errors
- Ensure get_facility_details.php is accessible

### Export not working
- Check file permissions
- Verify headers are sent before any output
- Check for errors in query

## Future Enhancements
- Add fingerprint capture functionality
- Implement advanced filtering (by county, department, etc.)
- Add bulk import from Excel
- Generate staff ID cards
- Add audit trail for changes
- Implement soft delete with restore option
- Add role-based permissions

## Support
For issues or questions, contact your system administrator.

## Version
1.0.0 - Initial Release
