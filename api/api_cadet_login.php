<?php
// api_cadet_login.php - Cadet login for mobile app
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Invalid JSON data');
        }
        
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
        
        // Find cadet by username or email
        $stmt = $pdo->prepare("
            SELECT * FROM cadet_accounts 
            WHERE (username = ? OR email = ?) 
            AND status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $cadet = $stmt->fetch();
        
        if (!$cadet) {
            throw new Exception('Account not found or not approved');
        }
        
        // Verify password
        if (!password_verify($password, $cadet['password'])) {
            throw new Exception('Invalid password');
        }
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE cadet_accounts SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$cadet['id']]);
        
        // Return cadet info (without sensitive data)
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'cadet' => [
                'id' => $cadet['id'],
                'username' => $cadet['username'],
                'email' => $cadet['email'],
                'first_name' => $cadet['first_name'],
                'last_name' => $cadet['last_name'],
                'course' => $cadet['course'],
                'platoon' => $cadet['platoon'],
                'company' => $cadet['company'],
                'profile_image' => $cadet['profile_image']
            ],
            'token' => md5($cadet['id'] . time() . uniqid()) // Simple token for demo
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}