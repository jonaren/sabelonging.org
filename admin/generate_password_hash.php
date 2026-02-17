<?php
/**
 * Password Hash Generator
 * Use this to generate a secure password hash for the admin account
 *
 * INSTRUCTIONS:
 * 1. Run this script: php generate_password_hash.php
 * 2. Copy the UPDATE query it generates
 * 3. Run that query in your production database (phpMyAdmin, MySQL command line, etc.)
 * 4. DELETE this file after use for security
 */

// Change these values as needed
$username = 'admin';
$new_password = 'admin123';  // Change this to your desired password

// Generate the password hash using PHP's password_hash
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "=================================================\n";
echo "PASSWORD HASH GENERATOR\n";
echo "=================================================\n\n";

echo "Username: $username\n";
echo "Password: $new_password\n\n";

echo "Generated Hash:\n";
echo "$password_hash\n\n";

echo "=================================================\n";
echo "SQL UPDATE QUERY (Run this in your database):\n";
echo "=================================================\n\n";

echo "UPDATE admin_users SET password_hash = '$password_hash' WHERE username = '$username';\n\n";

echo "=================================================\n";
echo "IMPORTANT: Delete this file after use!\n";
echo "=================================================\n";
?>
