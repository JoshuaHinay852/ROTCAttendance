<?php
// /api/api_mark_announcement_read.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }
        
        $required = ['announcement_id', 'user_id', 'user_type'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $announcement_id = intval($input['announcement_id']);
        $user_id = intval($input['user_id']);
        $user_type = $input['user_type'];
        $device_info = $input['device_info'] ?? 'Flutter Mobile App';
        
        // Validate user type
        if (!in_array($user_type, ['cadet', 'mp'])) {
            throw new Exception('Invalid user type');
        }
        
        // Check if announcement exists and is published
        $stmt = $pdo->prepare("SELECT id FROM announcements WHERE id = ? AND status = 'published'");
        $stmt->execute([$announcement_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Announcement not found or not published');
        }
        
        // Check if already marked as read
        $stmt = $pdo->prepare("SELECT id FROM announcement_read_logs 
                              WHERE announcement_id = ? AND user_id = ? AND user_type = ?");
        $stmt->execute([$announcement_id, $user_id, $user_type]);
        
        if ($stmt->fetch()) {
            // Already read, just update timestamp
            $stmt = $pdo->prepare("UPDATE announcement_read_logs SET read_at = NOW() 
                                  WHERE announcement_id = ? AND user_id = ? AND user_type = ?");
            $stmt->execute([$announcement_id, $user_id, $user_type]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Read timestamp updated',
                'already_read' => true
            ]);
        } else {
            // Mark as read
            $stmt = $pdo->prepare("INSERT INTO announcement_read_logs 
                                  (announcement_id, user_id, user_type, device_info) 
                                  VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$announcement_id, $user_id, $user_type, $device_info])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Announcement marked as read',
                    'read_id' => $pdo->lastInsertId(),
                    'read_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception('Failed to mark as read');
            }
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'MARK_READ_ERROR'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
}