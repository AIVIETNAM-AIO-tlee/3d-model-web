-- Run this once in ecommerce_db to create a default admin account.
-- Login page: index.php?p=admin_login
-- Default credentials:
-- Email: admin@assetforge3d.com
-- Password: Admin@12345

INSERT INTO Users (full_name, email, password_hash, role)
VALUES (
    'Site Administrator',
    'admin@assetforge3d.com',
    '$2y$10$BmojTHB5y4EHUThbeIGx4OzcJrS/taDI5rfOduk/s7T4xhNMFRrtu',
    'admin'
)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    role = 'admin';

INSERT INTO User_Auth (user_id, provider, email_verified, failed_attempts, locked_until, last_login_at)
SELECT id, 'local', 1, 0, NULL, NULL
FROM Users
WHERE email = 'admin@assetforge3d.com'
ON DUPLICATE KEY UPDATE
    provider = 'local',
    email_verified = 1,
    failed_attempts = 0,
    locked_until = NULL;
