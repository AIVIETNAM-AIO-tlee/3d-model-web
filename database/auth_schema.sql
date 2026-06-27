-- Run this once on ecommerce_db to support authentication security and Google Sign-In.

CREATE TABLE IF NOT EXISTS User_Auth (
    user_id INT PRIMARY KEY,
    provider ENUM('local', 'google') NOT NULL DEFAULT 'local',
    google_sub VARCHAR(64) NULL UNIQUE,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_auth_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
