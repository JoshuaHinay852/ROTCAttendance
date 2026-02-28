-- Drop existing tables if needed
DROP TABLE IF EXISTS mp_accounts;
DROP TABLE IF EXISTS cadet_accounts;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS admins;

-- Admins table (already exists but adding enhancements)
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin') DEFAULT 'admin',
    account_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    status_reson
    profile_image VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cadet accounts table
CREATE TABLE cadet_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    course VARCHAR(100) NOT NULL,
    full_address TEXT NOT NULL,
    platoon ENUM('1', '2', '3') NOT NULL,
    company ENUM('Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf') NOT NULL,
    dob DATE NOT NULL,
    mothers_name VARCHAR(100) NOT NULL,
    fathers_name VARCHAR(100) NOT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    last_login DATETIME DEFAULT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_status (status),
    INDEX idx_platoon (platoon),
    INDEX idx_company (company),
    INDEX idx_course (course)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MP (Military Police) accounts table
CREATE TABLE mp_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    course VARCHAR(100) NOT NULL,
    full_address TEXT NOT NULL,
    platoon ENUM('1', '2', '3') NOT NULL,
    company ENUM('Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf') NOT NULL,
    dob DATE NOT NULL,
    mothers_name VARCHAR(100) NOT NULL,
    fathers_name VARCHAR(100) NOT NULL,
    mp_rank ENUM('Private', 'Private First Class', 'Corporal', 'Sergeant', 'Staff Sergeant', 'Sergeant First Class') DEFAULT 'Private',
    profile_image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended', 'discharged') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_status (status),
    INDEX idx_mp_rank (mp_rank),
    INDEX idx_platoon (platoon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Events table
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(200) NOT NULL,
    event_description TEXT,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(200) NOT NULL,
    required_attendance BOOLEAN DEFAULT TRUE,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    cadet_id INT,
    mp_id INT,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'absent', 'late', 'excused', 'AWOL') DEFAULT 'absent',
    remarks TEXT,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (cadet_id) REFERENCES cadet_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (mp_id) REFERENCES mp_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES admins(id),
    UNIQUE KEY unique_attendance (event_id, cadet_id, mp_id),
    INDEX idx_status (status),
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts table (for security)
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_ip (ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin if none exists
INSERT INTO admins (username, email, password, full_name, role) 
VALUES ('admin', 'admin@rotc.system', '$2y$10$YourHashedPasswordHere', 'Super Admin', 'super_admin')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;