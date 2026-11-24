CREATE DATABASE IF NOT EXISTS school_event_db
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_event_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    year_level VARCHAR(30) NULL,
    block VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    venue VARCHAR(150) NOT NULL,
    capacity INT NOT NULL,
    status ENUM('upcoming','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attendance ENUM('registered','present','absent') NOT NULL DEFAULT 'registered',
    qr_token CHAR(64) NULL UNIQUE,
    UNIQUE KEY unique_event_user (event_id, user_id),
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reg_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
