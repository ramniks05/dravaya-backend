-- Create Admin User
-- Run this SQL script in phpMyAdmin or MySQL command line
-- Make sure to update the email and password_hash before running

-- Option 1: Insert with pre-hashed password
-- Default password: admin123 (change this!)
-- To generate a new password hash, use: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"

-- Generate password hash first using: php database/get_password_hash.php admin123
-- Then update the password_hash value below

INSERT INTO users (id, email, password_hash, role, status) 
VALUES (
    UUID(),
    'ramsitservices05@gmail.com',  -- Admin email
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Hash for 'admin123'
    'admin',
    'active'
);

-- If user already exists, update the password:
-- UPDATE users SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE email = 'ramsitservices05@gmail.com';

-- Option 2: If you want to specify a custom UUID
-- INSERT INTO users (id, email, password_hash, role, status) 
-- VALUES (
--     'admin-user-uuid-here',
--     'admin@example.com',
--     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
--     'admin',
--     'active'
-- );

-- Verify the admin user was created
SELECT id, email, role, status, created_at FROM users WHERE role = 'admin';

