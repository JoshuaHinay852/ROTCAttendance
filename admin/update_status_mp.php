<?php
// update_status_mp.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !isset($input['status'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    
    $id = $input['id'];
    $status = $input['status'];
    $type = isset($input['type']) ? $input['type'] : 'cadet'; // Default to cadet
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'denied'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Determine which table to update
    $table = ($type === 'mp') ? 'mp_accounts' : 'cadet_accounts';
    
    try {
        $stmt = $pdo->prepare("UPDATE $table SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully',
                'status_text' => ucfirst($status)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Account not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>