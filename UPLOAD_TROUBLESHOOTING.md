# Profile Image Upload Troubleshooting Guide

## Issues Fixed

1. **JavaScript Event Listeners**: Changed from inline `onclick` to proper event listeners
2. **PHP Syntax Error**: Removed duplicate code in settings.php
3. **Better Error Handling**: Added comprehensive error messages
4. **Default Avatar**: Created SVG default avatar

## Testing Steps

### 1. Check Upload Directory
Run the test script to verify everything is set up correctly:
```
http://localhost/chat/test_upload.php
```

This will show you:
- If the upload directory exists
- Directory permissions
- PHP upload settings
- A simple upload form to test

### 2. Create Upload Directory Manually (if needed)
If the directory doesn't exist or isn't writable:

**Windows (XAMPP):**
```bash
mkdir uploads\profiles
```

**Linux/Mac:**
```bash
mkdir -p uploads/profiles
chmod 777 uploads/profiles
```

### 3. Check PHP Settings
Edit your `php.ini` file (usually in `C:\xampp\php\php.ini` for XAMPP):

```ini
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20
```

Restart Apache after changing php.ini.

### 4. Test the Upload

1. Login to your chat application
2. Click the settings icon (⚙️)
3. Select an image file (JPG, PNG, or GIF)
4. Click "Upload"
5. You should see a success message

### 5. Check Browser Console

If upload still doesn't work:
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Try uploading again
4. Look for any JavaScript errors
5. Go to Network tab
6. Look for the request to `settings.php`
7. Check the response

### Common Issues & Solutions

**Issue: "No file uploaded"**
- Make sure you selected a file before clicking Upload
- Check that the file input has `accept="image/*"` attribute

**Issue: "Failed to create upload directory"**
- Create the directory manually: `mkdir uploads/profiles`
- Set proper permissions (777 on development)

**Issue: "Failed to move uploaded file"**
- Directory permissions issue
- Run: `chmod 777 uploads/profiles` (Linux/Mac)
- Or right-click folder → Properties → Security → Give full control (Windows)

**Issue: "File too large"**
- Check your image file size (must be under 5MB)
- Or increase the limit in php.ini

**Issue: "Invalid file type"**
- Only JPG, JPEG, PNG, and GIF are allowed
- Make sure your file has the correct extension

**Issue: Button doesn't respond**
- Clear browser cache
- Check browser console for JavaScript errors
- Make sure you're logged in

### Verify Database Column

Make sure the `profile_image` column exists in your users table:

```sql
SHOW COLUMNS FROM users LIKE 'profile_image';
```

If it doesn't exist, add it:

```sql
ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL;
```

### Debug Mode

To see detailed error messages, temporarily add this to the top of `api/settings.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

Remove this in production!

## Still Not Working?

1. Check Apache error logs:
   - XAMPP: `C:\xampp\apache\logs\error.log`
   - Linux: `/var/log/apache2/error.log`

2. Check PHP error logs:
   - XAMPP: `C:\xampp\php\logs\php_error_log`

3. Use the test_upload.php script to isolate the issue

4. Try uploading a very small image (under 100KB) to rule out size issues
