<?php
// database.php
// Database configuration and connection

// Database configuration for port 3307
define('DB_HOST', 'localhost:3306'); // or '127.0.0.1:3307'
define('DB_NAME', 'rotc_attendance1');
define('DB_USER', 'root'); // Change this to your database username
define('DB_PASS', ''); // Change this to your database password
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection using PDO
 * @return PDO
 * @throws PDOException
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            PDO::ATTR_TIMEOUT => 5 // Connection timeout in seconds
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error securely
        error_log("Database Connection Error: " . $e->getMessage());
        throw new PDOException("Database connection failed. Please check if MySQL is running on port 3307.");
    }
}

/**
 * Alternative connection method using mysqli (fallback)
 * @return mysqli
 * @throws Exception
 */
function getMysqliConnection() {
    try {
        // Parse host and port
        $hostParts = explode(':', DB_HOST);
        $host = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : 3307;
        
        $mysqli = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port);
        
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }
        
        $mysqli->set_charset(DB_CHARSET);
        return $mysqli;
        
    } catch (Exception $e) {
        error_log("MySQLi Connection Error: " . $e->getMessage());
        throw new Exception("Database connection failed. Please try again later.");
    }
}

/**
 * Test database connection
 * @return array
 */
function testDBConnection() {
    $result = [
        'success' => false,
        'message' => '',
        'details' => []
    ];
    
    try {
        // Test PDO connection
        $pdo = getDBConnection();
        $pdo->query("SELECT 1");
        
        // Get MySQL version
        $version = $pdo->query("SELECT VERSION() as version")->fetch();
        
        $result['success'] = true;
        $result['message'] = 'Database connection successful';
        $result['details'] = [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'mysql_version' => $version['version'],
            'pdo_drivers' => PDO::getAvailableDrivers(),
            'connection_type' => 'PDO'
        ];
        
    } catch (PDOException $e) {
        $result['message'] = 'PDO Connection failed: ' . $e->getMessage();
        $result['details']['pdo_error'] = $e->getMessage();
        
        // Try mysqli as fallback
        try {
            $mysqli = getMysqliConnection();
            $result['success'] = true;
            $result['message'] = 'Database connection successful (using MySQLi fallback)';
            $result['details']['connection_type'] = 'MySQLi';
            $mysqli->close();
        } catch (Exception $mysqliError) {
            $result['details']['mysqli_error'] = $mysqliError->getMessage();
        }
    }
    
    return $result;
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize_input($data) {
    if ($data === null || $data === '') {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a secure random token
 * @param int $length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if tables exist
 * @param PDO $pdo
 * @return array
 */
function checkTables($pdo) {
    $requiredTables = ['cadet_accounts', 'mp_accounts', 'admins'];
    $existingTables = [];
    $missingTables = [];
    
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($requiredTables as $table) {
            if (in_array($table, $tables)) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
        
        return [
            'existing' => $existingTables,
            'missing' => $missingTables
        ];
        
    } catch (PDOException $e) {
        error_log("Error checking tables: " . $e->getMessage());
        return [
            'existing' => [],
            'missing' => $requiredTables,
            'error' => $e->getMessage()
        ];
    }
}
?>