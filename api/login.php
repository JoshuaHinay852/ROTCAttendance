<?php
// api/login.php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

function sendResponse($success, $message, $data = []) {
    ob_clean();
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit();
}

require_once '../config/database1.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input');
    }
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $userType = $input['user_type'] ?? '';
    
    if (empty($username) || empty($password) || empty($userType)) {
        throw new Exception('All fields are required');
    }
    
    if (!in_array($userType, ['cadet', 'mp'])) {
        throw new Exception('Invalid user type');
    }
    
    $pdo = getDBConnection();
    $table = $userType === 'cadet' ? 'cadet_accounts' : 'mp_accounts';
    
    // Get user by username or email
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Invalid username or password');
    }
    
    // Check account status
    if ($userType === 'cadet') {
        if ($user['status'] === 'pending') {
            throw new Exception('Your account is pending approval from admin');
        } elseif ($user['status'] === 'denied') {
            throw new Exception('Your account has been denied. Please contact admin');
        } elseif ($user['status'] !== 'approved') {
            throw new Exception('Your account is not approved yet');
        }
    } else { // MP accounts
        if ($user['status'] === 'inactive') {
            throw new Exception('Your account is inactive. Please contact admin');
        } elseif ($user['status'] === 'suspended') {
            throw new Exception('Your account has been suspended');
        } elseif ($user['status'] === 'discharged') {
            throw new Exception('Your account has been discharged');
        } elseif ($user['status'] !== 'active') {
            throw new Exception('Your account is not active');
        }
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Invalid username or password');
    }
    
    // Update last login
    $updateStmt = $pdo->prepare("UPDATE $table SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Remove sensitive data
    unset($user['password']);
    
    // Add user type to response
    $user['user_type'] = $userType;
    
    sendResponse(true, 'Login successful', ['user' => $user]);
    
} catch (Exception $e) {
    sendResponse(false, $e->getMessage());
}

ob_end_flush();
?>