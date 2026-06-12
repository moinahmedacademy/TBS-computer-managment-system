# The Brighten Stars Academy – Setup Guide

## Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB
- Apache with mod_rewrite
- XAMPP / WAMP / LAMP

## Installation Steps

### 1. Place Files
Copy the `academy/` folder to your web server root:
- XAMPP: `C:/xampp/htdocs/academy/`
- WAMP: `C:/wamp64/www/academy/`

### 2. Create Database
1. Open phpMyAdmin → Create database: `brighten_stars`
2. Import `academy/database.sql`

### 3. Configure Database
Edit `academy/includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // Your MySQL password
define('DB_NAME', 'brighten_stars');
define('BASE_URL', 'http://localhost/academy');
```

### 4. Create Secure Files Directory
The `secure_files/` directory should be outside `htdocs/` for production:
- Move `secure_files/` to: `C:/xampp/secure_files/`
- Update `SECURE_FILES_PATH` in `config.php`

For development (localhost), keeping it inside is fine.

### 5. Login
URL: `http://localhost/academy/login.php`

**Admin:**
- Email: `admin@brightenstars.com`
- Password: `Admin@123`

**Student/Parent accounts** are created by admin.

## Default Password Policy
- Admin: `Admin@123`
- Student: `Student@123` (changeable)
- Parent: `Parent@123` (changeable)

## WhatsApp Setup (Optional)
1. Sign up at [ultramsg.com](https://ultramsg.com)
2. Create an instance and connect your WhatsApp
3. Go to Admin → Settings → WhatsApp API
4. Enter your Instance ID and Token

Without API config, WhatsApp buttons open `wa.me` links in browser.

## File Security (IP Restriction)
1. Go to Admin → Settings → Allowed IPs
2. Add your institute's IP address(es)
3. Students inside the network can view/download files
4. Students outside can only view PDFs inline (no download)

## Grading Scale
| Grade | Percentage |
|-------|-----------|
| A+    | 90%+      |
| A     | 80%+      |
| B     | 70%+      |
| C     | 60%+      |
| D     | 50%+      |
| F     | Below 50% |

## Features Summary
✅ Admin Portal (Full Management)
✅ Student Portal (Self-Service)
✅ Parent Portal (Child Monitoring)
✅ Course Management
✅ Attendance Tracking
✅ Test & Results Management
✅ Result Cards (Printable PDF)
✅ Monthly Reports
✅ WhatsApp Integration
✅ Announcements System
✅ Secure File Server (IP Restricted)
✅ Dark Theme, Bootstrap 5, Responsive
✅ Role-Based Access Control
✅ Password Hashing (bcrypt)
✅ Prepared Statements (SQL Injection Protection)
✅ Audit Logging
