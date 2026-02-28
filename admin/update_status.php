<?php
// admin/cadets/update_status.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$id = intval($input['id']);
$status = $input['status'];

// Validate status
$allowed_statuses = ['pending', 'approved', 'denied'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Update status
    $stmt = $pdo->prepare("UPDATE cadet_accounts SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $id]);
    
    if ($result) {
        // Get status text for display
        $status_texts = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'denied' => 'Denied'
        ];
        
        // Get status details for login ability
        $login_ability = [
            'pending' => 'Cannot login',
            'approved' => 'Can login',
            'denied' => 'Cannot login'
        ];
        
        $icons = [
            'pending' => 'fa-exclamation-circle',
            'approved' => 'fa-check-circle',
            'denied' => 'fa-ban'
        ];
        
        $colors = [
            'pending' => 'yellow',
            'approved' => 'green',
            'denied' => 'red'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status,
            'status_text' => $status_texts[$status],
            'login_ability' => $login_ability[$status],
            'icon' => $icons[$status],
            'color' => $colors[$status],
            'html' => '<span class="text-' . $colors[$status] . '-600 font-medium"><i class="fas ' . $icons[$status] . ' mr-1"></i>' . $login_ability[$status] . '</span>'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (PDOException $e) {
    error_log("Status update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>