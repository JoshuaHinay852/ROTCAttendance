<?php
// api/signup.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Set headers - MUST be first before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24 hours cache for preflight

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to send JSON response
function sendResponse($success, $message, $data = []) {
    // Clear any output that might have been buffered
    if (ob_get_length()) ob_clean();

    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed. Please use POST.');
}

// Include database configuration
require_once '../config/database1.php';

try {
    // Get JSON input
    $inputJSON = file_get_contents('php://input');

    if (!$inputJSON) {
        throw new Exception('No input data received');
    }

    $input = json_decode($inputJSON, true);

    if (!$input) {
        // Try to get from $_POST as fallback
        $input = $_POST;

        // If still no data, throw error
        if (empty($input)) {
            throw new Exception('Invalid JSON input. Please check your request format.');
        }
    }

    // Log for debugging
    error_log("Signup input: " . print_r($input, true));

    $userType = isset($input['user_type']) ? $input['user_type'] : '';

    if (empty($userType)) {
        throw new Exception('User type is required');
    }

    if (!in_array($userType, ['cadet', 'mp'])) {
        throw new Exception('Invalid user type. Must be "cadet" or "mp"');
    }

    // Validate required fields based on user type
    $requiredFields = [
        'username', 'email', 'password', 'first_name', 'last_name'
    ];

    // Both cadet and mp tables require these fields in your schema.
    $requiredFields = array_merge($requiredFields, [
        'course', 'full_address', 'platoon', 'company', 'dob',
        'mothers_name', 'fathers_name'
    ]);

    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
    }

    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $input['username'])) {
        throw new Exception('Username must be 3-20 characters and can only contain letters, numbers, and underscore');
    }

    // Validate password strength
    if (strlen($input['password']) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

    // Get database connection
    $pdo = getDBConnection();

    // Determine which table to use
    $table = $userType === 'cadet' ? 'cadet_accounts' : 'mp_accounts';

    // Check if table exists
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
    } catch (PDOException $e) {
        throw new Exception("Database table '$table' does not exist. Please run the database setup first.");
    }

    // Check if user exists
    $checkStmt = $pdo->prepare("SELECT id FROM $table WHERE username = ? OR email = ?");
    $checkStmt->execute([$input['username'], $input['email']]);

    if ($checkStmt->fetch()) {
        throw new Exception('Username or email already exists');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // All accounts start as 'pending' for admin approval
    $status = 'pending';

    // Prepare SQL based on user type
    if ($userType === 'cadet') {
        $sql = "INSERT INTO cadet_accounts (
            username, email, password, first_name, last_name, middle_name,
            course, full_address, platoon, company, dob, mothers_name,
            fathers_name, status, created_at
        ) VALUES (
            :username, :email, :password, :first_name, :last_name, :middle_name,
            :course, :full_address, :platoon, :company, :dob, :mothers_name,
            :fathers_name, :status, NOW()
        )";
    } else {
        $sql = "INSERT INTO mp_accounts (
            username, email, password, first_name, last_name, middle_name,
            course, full_address, platoon, company, dob, mothers_name,
            fathers_name, status, created_at
        ) VALUES (
            :username, :email, :password, :first_name, :last_name, :middle_name,
            :course, :full_address, :platoon, :company, :dob, :mothers_name,
            :fathers_name, :status, NOW()
        )";
    }

    $stmt = $pdo->prepare($sql);

    // Common parameters
    $params = [
        ':username' => $input['username'],
        ':email' => $input['email'],
        ':password' => $hashedPassword,
        ':first_name' => $input['first_name'],
        ':last_name' => $input['last_name'],
        ':middle_name' => isset($input['middle_name']) && !empty($input['middle_name']) ? $input['middle_name'] : null,
        ':course' => $input['course'],
        ':full_address' => $input['full_address'],
        ':platoon' => $input['platoon'],
        ':company' => $input['company'],
        ':dob' => $input['dob'],
        ':mothers_name' => $input['mothers_name'],
        ':fathers_name' => $input['fathers_name'],
        ':status' => $status,
    ];

    if (!$stmt->execute($params)) {
        throw new Exception('Failed to create account');
    }

    $userId = $pdo->lastInsertId();

    // Commit transaction
    $pdo->commit();

    // Success message
    $message = 'Registration successful! Your account is pending admin approval.';

    sendResponse(true, $message, [
        'user_id' => $userId,
        'user_type' => $userType,
        'status' => 'pending'
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Signup Database Error: " . $e->getMessage());

    // Check for duplicate entry
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
        sendResponse(false, 'Username or email already exists');
    } else {
        sendResponse(false, 'Database error occurred. Please try again.');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Signup Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage());
}

ob_end_flush();
?>
