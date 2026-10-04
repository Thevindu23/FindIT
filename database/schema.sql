-- setup.php creates/selects the database before running these statements.
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('Lost', 'Found', 'Claim Pending', 'Resolved') NOT NULL DEFAULT 'Lost',
    original_status ENUM('Lost', 'Found') NOT NULL DEFAULT 'Lost',
    location VARCHAR(255) NOT NULL,
    date_reported DATE NOT NULL,
    reward_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    reward_status ENUM('Unpaid', 'Paid (simulated)') NOT NULL DEFAULT 'Unpaid',
    description TEXT,
    image_url MEDIUMTEXT,
    reporter_name VARCHAR(255) NOT NULL,
    reporter_contact VARCHAR(255) NOT NULL,
    request_key VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_report_request (user_id, request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS claims (
    id VARCHAR(64) PRIMARY KEY,
    item_id VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NULL,
    finder_name VARCHAR(255) NOT NULL,
    finder_contact VARCHAR(255) NOT NULL,
    note TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A small support table limits repeated login attempts across browser sessions.
CREATE TABLE IF NOT EXISTS login_attempts (
    address_hash CHAR(64) PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
