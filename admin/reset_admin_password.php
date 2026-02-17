<?php
/**
 * Emergency Admin Password Reset Script
 *
 * INSTRUCTIONS:
 * 1. Upload this file to your production server (admin folder)
 * 2. Visit it in your browser: https://sabelonging.org/admin/reset_admin_password.php
 * 3. It will reset the admin password to "admin123"
 * 4. DELETE THIS FILE IMMEDIATELY after use!
 */

// Database configuration - UPDATE THESE FOR PRODUCTION
define('DB_HOST', 'localhost');
define('DB_NAME', 'usnsan5_belonging');
define('DB_USER', 'usnsan5_jon');
define('DB_PASS', 'l9,i`P9>:qE8');

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Generate new password hash
    $new_password = 'admin123';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Check which column name exists (password or password_hash)
    $checkStmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'password%'");
    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

    $columnName = in_array('password_hash', $columns) ? 'password_hash' : 'password';

    // Update admin password using the correct column name
    $stmt = $pdo->prepare("UPDATE admin_users SET $columnName = ? WHERE username = 'admin'");
    $stmt->execute([$password_hash]);

    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Password Reset Success</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px; }
            code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class='success'>
            <h2>✓ Password Reset Successful!</h2>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> admin123</p>
            <p><strong>Hash Generated:</strong><br><code>" . htmlspecialchars($password_hash) . "</code></p>
            <p><strong>Column Used:</strong> <code>" . htmlspecialchars($columnName) . "</code></p>
        </div>

        <div class='warning'>
            <h3>⚠️ IMPORTANT SECURITY STEPS:</h3>
            <ol>
                <li><strong>DELETE THIS FILE IMMEDIATELY!</strong> (reset_admin_password.php)</li>
                <li>Login with the credentials above</li>
                <li>Change your password to something secure</li>
            </ol>
        </div>

        <p><a href='login.php'>Go to Login Page →</a></p>
    </body>
    </html>";

} catch (PDOException $e) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Password Reset Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>✗ Error</h2>
            <p>Failed to reset password: " . htmlspecialchars($e->getMessage()) . "</p>
        </div>
    </body>
    </html>";
}
?>
