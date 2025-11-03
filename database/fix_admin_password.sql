-- Fix Admin Password - Run this SQL in phpMyAdmin
-- This will update the password hash for the admin user

-- Step 1: Check if user exists
SELECT id, email, role, status, LENGTH(password_hash) as hash_length, LEFT(password_hash, 20) as hash_preview 
FROM users 
WHERE email = 'ramsitservices05@gmail.com';

-- Step 2: Update password hash (password: admin123)
-- Generate hash using: http://localhost/backend/database/get_password_hash.php?password=admin123
-- Then copy the hash and use it below

-- OPTION A: If user exists, UPDATE the password hash
UPDATE users 
SET 
    password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    status = 'active'
WHERE email = 'ramsitservices05@gmail.com';

-- OPTION B: If user doesn't exist, INSERT new user
-- INSERT INTO users (id, email, password_hash, role, status) 
-- VALUES (
--     UUID(),
--     'ramsitservices05@gmail.com',
--     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
--     'admin',
--     'active'
-- );

-- Step 3: Verify the update
SELECT id, email, role, status, created_at 
FROM users 
WHERE email = 'ramsitservices05@gmail.com';

-- Note: The hash above is for password 'admin123'
-- If you need a different password, generate a new hash first!

