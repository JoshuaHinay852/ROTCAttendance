<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'rotc_attendance1');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

require_once __DIR__ . '/../includes/event_status_helper.php';

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Establish database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Test connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    $error_message = "Database connection failed: " . $e->getMessage();
    $error_message .= "<br>Host: " . DB_HOST . ":" . DB_PORT;
    $error_message .= "<br>Database: " . DB_NAME;
    
    if (ini_get('display_errors')) {
        die($error_message);
    } else {
        error_log($error_message);
        die("Database connection failed. Please contact administrator.");
    }
}

// Helper Functions

// Dashboard helper functions
function getDashboardStats() {
    global $pdo;
    
    $stats = [
        'total_cadets' => 0,
        'total_mps' => 0,
        'total_admins' => 0,
        'approved_admins' => 0,
        'pending_admins' => 0,
        'active_cadets' => 0,
        'active_mps' => 0,
        'today_events' => 0,
        'pending_events' => 0
    ];
    
    try {
        // Total cadets
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_accounts");
        $result = $stmt->fetch();
        $stats['total_cadets'] = $result['count'];
        
        // Active cadets
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_accounts WHERE status = 'active'");
        $result = $stmt->fetch();
        $stats['active_cadets'] = $result['count'];
        
        // Total MPs
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mp_accounts");
        $result = $stmt->fetch();
        $stats['total_mps'] = $result['count'];
        
        // Active MPs
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mp_accounts WHERE status = 'active'");
        $result = $stmt->fetch();
        $stats['active_mps'] = $result['count'];
        
        // Total admins
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
        $result = $stmt->fetch();
        $stats['total_admins'] = $result['count'];
        
        // Approved admins
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins WHERE account_status = 'approved'");
        $result = $stmt->fetch();
        $stats['approved_admins'] = $result['count'];
        
        // Pending admins
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins WHERE account_status = 'pending'");
        $result = $stmt->fetch();
        $stats['pending_admins'] = $result['count'];
        
        // Today's events
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE event_date = CURDATE() AND status != 'cancelled'");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['today_events'] = $result['count'];
        
        // Pending events (upcoming)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM events WHERE event_date > CURDATE() AND status = 'scheduled'");
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['pending_events'] = $result['count'];
        
    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
    }
    
    return $stats;
}

function getRecentActivity($limit = 10) {
    global $pdo;
    
    $activities = [];
    
    try {
        $query = "
            (SELECT 
                'cadet_added' as type,
                CONCAT('Cadet ', first_name, ' ', last_name) as description,
                created_at as activity_date,
                'blue' as color
            FROM cadet_accounts 
            ORDER BY created_at DESC LIMIT 3)
            
            UNION ALL
            
            (SELECT 
                'mp_added' as type,
                CONCAT('MP ', first_name, ' ', last_name) as description,
                created_at as activity_date,
                'green' as color
            FROM mp_accounts 
            ORDER BY created_at DESC LIMIT 3)
            
            UNION ALL
            
            (SELECT 
                'event_created' as type,
                CONCAT('Event: ', event_name) as description,
                created_at as activity_date,
                'purple' as color
            FROM events 
            ORDER BY created_at DESC LIMIT 3)
            
            UNION ALL
            
            (SELECT 
                'admin_login' as type,
                CONCAT('Admin ', full_name, ' logged in') as description,
                last_login as activity_date,
                'yellow' as color
            FROM admins 
            WHERE last_login IS NOT NULL 
            ORDER BY last_login DESC LIMIT 1)
            
            UNION ALL
            
            (SELECT 
                'admin_approved' as type,
                CONCAT('Admin account approved: ', full_name) as description,
                updated_at as activity_date,
                'green' as color
            FROM admins 
            WHERE account_status = 'approved' AND updated_at IS NOT NULL
            ORDER BY updated_at DESC LIMIT 1)
            
            ORDER BY activity_date DESC 
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Recent activity error: " . $e->getMessage());
    }
    
    return $activities;
}

function sanitize_input($data) {
    if (empty($data)) return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

function redirect_if_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        // Check if account is approved
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            if ($admin && $admin['account_status'] === 'approved') {
                if (file_exists('admin/dashboard.php')) {
                    header('Location: admin/dashboard.php');
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Redirect check error: " . $e->getMessage());
        }
    }
}

function create_event_tables() {
    global $pdo;
    
    $tables_sql = [
        'events' => "CREATE TABLE IF NOT EXISTS events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_name VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            location VARCHAR(255),
            qr_code_data TEXT,
            qr_code_path VARCHAR(255),
            status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event_date (event_date),
            INDEX idx_status (status),
            INDEX idx_created_by (created_by),
            FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'event_attendance' => "CREATE TABLE IF NOT EXISTS event_attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            cadet_id INT NOT NULL,
            mp_id INT NOT NULL,
            check_in_time DATETIME NOT NULL,
            check_out_time DATETIME DEFAULT NULL,
            attendance_status ENUM('present', 'late', 'absent', 'excused') DEFAULT 'present',
            remarks TEXT,
            qr_code_verified BOOLEAN DEFAULT FALSE,
            verification_method ENUM('qr_scan', 'manual', 'api') DEFAULT 'qr_scan',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_cadet (event_id, cadet_id),
            INDEX idx_event_id (event_id),
            INDEX idx_cadet_id (cadet_id),
            INDEX idx_mp_id (mp_id),
            INDEX idx_check_in_time (check_in_time),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (cadet_id) REFERENCES cadet_accounts(id) ON DELETE CASCADE,
            FOREIGN KEY (mp_id) REFERENCES mp_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'event_qr_logs' => "CREATE TABLE IF NOT EXISTS event_qr_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            qr_code_data TEXT NOT NULL,
            generated_by INT NOT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            scan_count INT DEFAULT 0,
            last_scan_at DATETIME DEFAULT NULL,
            INDEX idx_event_id (event_id),
            INDEX idx_is_active (is_active),
            INDEX idx_expires_at (expires_at),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" ];

 
    
    foreach ($tables_sql as $table => $sql) {
        try {
            $pdo->exec($sql);
            error_log("Table $table created or already exists.");
        } catch (PDOException $e) {
            error_log("Table creation error for $table: " . $e->getMessage());
        }
    }
}

// Check and create tables (updated for new structure)
function check_and_create_tables() {
    global $pdo;
    
    $tables_sql = [
        'admins' => "CREATE TABLE IF NOT EXISTS admins (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin') DEFAULT 'admin',
            account_status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
            status_reason TEXT DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_account_status (account_status),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(50) NOT NULL,
            success BOOLEAN DEFAULT FALSE,
            notes TEXT DEFAULT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_attempt_time (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'admin_archives' => "CREATE TABLE IF NOT EXISTS admin_archives (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role VARCHAR(20) DEFAULT 'admin',
            account_status VARCHAR(20) DEFAULT 'pending',
            status_reason TEXT DEFAULT NULL,
            profile_image VARCHAR(255) DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            deleted_by INT NOT NULL,
            reason TEXT,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_deleted_at (deleted_at),
            INDEX idx_account_status (account_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'admin_activity_logs' => "CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_id (admin_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables_sql as $table => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Table creation error for $table: " . $e->getMessage());
        }
    }
     create_event_tables();
}

// Initialize tables
check_and_create_tables();

// Keep event statuses aligned with current date/time globally.
if (function_exists('ensureEventStatusesAreCurrent')) {
    ensureEventStatusesAreCurrent($pdo);
}
?>
