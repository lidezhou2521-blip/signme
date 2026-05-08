CREATE DATABASE IF NOT EXISTS signme;
USE signme;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert admin user (password is 'admin' hashed with PASSWORD_DEFAULT)
-- For simplicity in this demo, we can use a direct comparison or hash. 
-- Let's use password_hash for security.
INSERT INTO users (username, email, password) 
VALUES ('admin', 'admin@example.com', '$2y$10$IOkxODA1/YjmSrsfzag3GuXJT73/hFDwT7fXuwnpnBd5EjbiMkboe')
ON DUPLICATE KEY UPDATE username=username;
