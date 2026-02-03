-- =========================================
-- PANTUBE DATABASE MIGRATIONS
-- =========================================

-- 000_create_base_tables
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    bio TEXT,
    role ENUM('user','admin','owner') DEFAULT 'user',
    twofa_secret VARCHAR(255) DEFAULT NULL,
    twofa_enabled BOOLEAN DEFAULT FALSE,
    twofa_backup_codes TEXT DEFAULT NULL,
    twofa_verified_at DATETIME DEFAULT NULL,
    login_count INT DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL,
    account_status ENUM('active','suspended','banned') DEFAULT 'active',
    status_changed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL UNIQUE,
    thumbnail VARCHAR(255),
    views INT DEFAULT 0,
    processing_status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    processing_data TEXT DEFAULT NULL,
    resolutions TEXT DEFAULT NULL,
    formats TEXT DEFAULT NULL,
    duration INT DEFAULT 0,
    width INT DEFAULT 0,
    height INT DEFAULT 0,
    file_size BIGINT DEFAULT 0,
    cdn_path VARCHAR(500) DEFAULT NULL,
    watermarked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_processing_status (processing_status),
    INDEX idx_duration (duration),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_video (video_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    type ENUM('like','dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_video (user_id, video_id),
    INDEX idx_video (video_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    source_user_id INT,
    video_id INT,
    comment_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 001_add_security_tables
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_identifier VARCHAR(255) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    reason VARCHAR(255),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_identifier (user_identifier),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(32) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_selector (selector),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 002_add_twofa_support
ALTER TABLE users 
ADD COLUMN twofa_secret VARCHAR(255) DEFAULT NULL,
ADD COLUMN twofa_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN twofa_backup_codes TEXT DEFAULT NULL,
ADD COLUMN twofa_verified_at DATETIME DEFAULT NULL,
ADD COLUMN login_count INT DEFAULT 0,
ADD COLUMN last_login_at DATETIME DEFAULT NULL,
ADD COLUMN last_login_ip VARCHAR(45) DEFAULT NULL,
ADD COLUMN account_status ENUM('active','suspended','banned') DEFAULT 'active',
ADD COLUMN status_changed_at DATETIME DEFAULT NULL,
ADD INDEX idx_account_status (account_status);

-- 003_add_video_processing_fields
ALTER TABLE videos
ADD COLUMN processing_status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
ADD COLUMN processing_data TEXT DEFAULT NULL,
ADD COLUMN resolutions TEXT DEFAULT NULL,
ADD COLUMN formats TEXT DEFAULT NULL,
ADD COLUMN duration INT DEFAULT 0,
ADD COLUMN width INT DEFAULT 0,
ADD COLUMN height INT DEFAULT 0,
ADD COLUMN file_size BIGINT DEFAULT 0,
ADD COLUMN cdn_path VARCHAR(500) DEFAULT NULL,
ADD COLUMN watermarked BOOLEAN DEFAULT FALSE,
ADD INDEX idx_processing_status (processing_status),
ADD INDEX idx_duration (duration);

CREATE TABLE IF NOT EXISTS video_formats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    format VARCHAR(10) NOT NULL,
    resolution VARCHAR(10) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    bitrate VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_video_format (video_id, format, resolution),
    INDEX idx_video_id (video_id),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 004_add_search_indexes
ALTER TABLE videos 
ADD FULLTEXT INDEX ft_videos_title_description (title, description),
ADD INDEX idx_created_at (created_at),
ADD INDEX idx_views (views),
ADD INDEX idx_user_created (user_id, created_at);

ALTER TABLE users 
ADD FULLTEXT INDEX ft_users_username_email (username, email),
ADD INDEX idx_created_at (created_at);

ALTER TABLE comments 
ADD FULLTEXT INDEX ft_comments_content (content),
ADD INDEX idx_video_created (video_id, created_at),
ADD INDEX idx_user_created (user_id, created_at);

-- 005_add_activity_logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_data TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activity (user_id, activity_type),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 006_add_backup_history
CREATE TABLE IF NOT EXISTS backup_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full','database','files') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    status ENUM('pending','completed','failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
