<?php
// qr_scanner.php - API endpoint for scanning QR codes
require_once '../config/database.php';
require_once '../includes/event_status_helper.php';

header('Content-Type: application/json');

function getEventTimeWindow($event_date, $start_time, $end_time) {
    $start_timestamp = strtotime($event_date . ' ' . $start_time);
    $end_timestamp = strtotime($event_date . ' ' . $end_time);
    
    if ($end_timestamp <= $start_timestamp) {
        $end_timestamp = strtotime('+1 day', $end_timestamp);
    }
    
    return [
        'start' => $start_timestamp,
        'end' => $end_timestamp
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['qr_data']) || !isset($data['cadet_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    try {
        $qr_data = json_decode($data['qr_data'], true);
        $cadet_id = $data['cadet_id'];
        $mp_id = $data['mp_id'] ?? null;

        // Ensure event statuses reflect current server time.
        ensureEventStatusesAreCurrent($pdo);
        
        // Validate QR data
        if (!isset($qr_data['event_id']) || !isset($qr_data['hash'])) {
            throw new Exception('Invalid QR code data');
        }
        
        $event_id = $qr_data['event_id'];
        $qr_type = $qr_data['qr_type'] ?? 'in';
        $now_timestamp = time();

        if ($qr_type !== 'in') {
            throw new Exception('Unsupported QR type for this endpoint');
        }

        if (isset($qr_data['expires']) && strtotime($qr_data['expires']) < $now_timestamp) {
            throw new Exception('QR code has expired');
        }
        
        // Check if event exists and is not deleted/cancelled
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if (!$event) {
            throw new Exception('Event not found');
        }

        if ($event['status'] === 'cancelled') {
            throw new Exception('Event is cancelled');
        }

        $event_window = getEventTimeWindow($event['event_date'], $event['start_time'], $event['end_time']);
        if ($event['status'] === 'completed' || $now_timestamp >= $event_window['end']) {
            throw new Exception('Check-in QR has expired. Event is already completed.');
        }
        
        // Check if cadet exists
        $stmt = $pdo->prepare("SELECT * FROM cadet_accounts WHERE id = ? AND status = 'approved'");
        $stmt->execute([$cadet_id]);
        $cadet = $stmt->fetch();
        
        if (!$cadet) {
            throw new Exception('Cadet not found or not approved');
        }
        
        // Check if already attended
        $stmt = $pdo->prepare("SELECT * FROM event_attendance WHERE event_id = ? AND cadet_id = ?");
        $stmt->execute([$event_id, $cadet_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Already checked in for this event']);
            exit();
        }
        
        // Record attendance
        $stmt = $pdo->prepare("INSERT INTO event_attendance 
            (event_id, cadet_id, mp_id, check_in_time, qr_code_verified, verification_method) 
            VALUES (?, ?, ?, NOW(), TRUE, 'qr_scan')");
        
        if ($stmt->execute([$event_id, $cadet_id, $mp_id])) {
            // Update QR log
            $stmt = $pdo->prepare("UPDATE event_qr_logs SET 
                scan_count = scan_count + 1, 
                last_scan_at = NOW() 
                WHERE event_id = ? AND is_active = TRUE");
            $stmt->execute([$event_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => [
                    'event_name' => $event['event_name'],
                    'event_date' => $event['event_date'],
                    'check_in_time' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            throw new Exception('Failed to record attendance');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
