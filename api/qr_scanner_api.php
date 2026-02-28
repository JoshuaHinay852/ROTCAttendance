<?php
// api/scan_qr.php
require_once '../config/database.php';
header('Content-Type: application/json');

function getEventTimeWindow($event_date, $start_time, $end_time) {
    $start_timestamp = strtotime($event_date . ' ' . $start_time);
    $end_timestamp = strtotime($event_date . ' ' . $end_time);
    
    // Overnight event support (end time is next day).
    if ($end_timestamp <= $start_timestamp) {
        $end_timestamp = strtotime('+1 day', $end_timestamp);
    }
    
    return [
        'start' => $start_timestamp,
        'end' => $end_timestamp
    ];
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['qr_data']) || !isset($input['cadet_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$qr_data = $input['qr_data'];
$cadet_id = intval($input['cadet_id']);

try {
    // Decode QR data
    $qr_info = json_decode($qr_data, true);
    
    if (!$qr_info || !isset($qr_info['event_id']) || !isset($qr_info['qr_type'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code format']);
        exit();
    }
    
    $event_id = $qr_info['event_id'];
    $qr_type = $qr_info['qr_type'];
    if (!in_array($qr_type, ['in', 'out'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR type']);
        exit();
    }
    
    $now_timestamp = time();

    // Verify QR code is still valid (not expired)
    if (isset($qr_info['expires']) && strtotime($qr_info['expires']) < $now_timestamp) {
        echo json_encode(['success' => false, 'message' => 'QR code has expired']);
        exit();
    }
    
    // Check if QR code exists and is active in logs
    $stmt = $pdo->prepare("SELECT * FROM event_qr_logs 
                           WHERE event_id = ? AND qr_type = ? AND is_active = 1 
                           ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$event_id, $qr_type]);
    $qr_log = $stmt->fetch();
    
    if (!$qr_log) {
        echo json_encode(['success' => false, 'message' => 'QR code is no longer active']);
        exit();
    }

    if (!empty($qr_log['expires_at']) && strtotime($qr_log['expires_at']) < $now_timestamp) {
        echo json_encode(['success' => false, 'message' => 'QR code has expired']);
        exit();
    }
    
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit();
    }
    
    if ($event['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Event is cancelled']);
        exit();
    }

    $event_window = getEventTimeWindow($event['event_date'], $event['start_time'], $event['end_time']);
    $event_has_ended = $now_timestamp >= $event_window['end'];
    
    // Handle check-in or check-out
    if ($qr_type === 'in') {
        // Rule: check-in QR becomes invalid once event is done/completed.
        if ($event['status'] === 'completed' || $event_has_ended) {
            echo json_encode(['success' => false, 'message' => 'Check-in QR has expired. Event is already completed.']);
            exit();
        }

        // Check if already checked in
        $stmt = $pdo->prepare("SELECT * FROM event_attendance 
                               WHERE event_id = ? AND cadet_id = ?");
        $stmt->execute([$event_id, $cadet_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($existing['check_in_time']) {
                echo json_encode(['success' => false, 'message' => 'Already checked in for this event']);
                exit();
            } else {
                // Update existing record with check-in
                $stmt = $pdo->prepare("UPDATE event_attendance SET 
                    check_in_time = NOW(),
                    mp_id = ?,
                    qr_code_verified = 1,
                    check_in_method = 'qr_scan',
                    check_in_qr_id = ?,
                    attendance_status = CASE 
                        WHEN TIME(NOW()) > ? THEN 'late'
                        ELSE 'present'
                    END
                    WHERE id = ?");
                
                $stmt->execute([
                    $_SESSION['mp_id'] ?? null,
                    $qr_log['id'],
                    $event['start_time'],
                    $existing['id']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Check-in recorded successfully']);
            }
        } else {
            // Create new attendance record
            $stmt = $pdo->prepare("INSERT INTO event_attendance 
                (event_id, cadet_id, mp_id, check_in_time, qr_code_verified, 
                 check_in_method, check_in_qr_id, attendance_status) 
                VALUES (?, ?, ?, NOW(), 1, 'qr_scan', ?, 
                    CASE WHEN TIME(NOW()) > ? THEN 'late' ELSE 'present' END)");
            
            $stmt->execute([
                $event_id,
                $cadet_id,
                $_SESSION['mp_id'] ?? null,
                $qr_log['id'],
                $event['start_time']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Check-in recorded successfully']);
        }
        
    } elseif ($qr_type === 'out') {
        // Allow check-out even when event is completed, as long as event is not cancelled.
        // Check if event has check-out enabled
        if (!$event['check_out_enabled']) {
            echo json_encode(['success' => false, 'message' => 'Check-out not enabled for this event']);
            exit();
        }
        
        // Find attendance record
        $stmt = $pdo->prepare("SELECT * FROM event_attendance 
                               WHERE event_id = ? AND cadet_id = ? 
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute([$event_id, $cadet_id]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            echo json_encode(['success' => false, 'message' => 'No check-in record found']);
            exit();
        }
        
        if ($attendance['check_out_time']) {
            echo json_encode(['success' => false, 'message' => 'Already checked out']);
            exit();
        }
        
        // Update with check-out
        $stmt = $pdo->prepare("UPDATE event_attendance SET 
            check_out_time = NOW(),
            check_out_method = 'qr_scan',
            check_out_qr_id = ?
            WHERE id = ?");
        
        $stmt->execute([$qr_log['id'], $attendance['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Check-out recorded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported QR type']);
        exit();
    }
    
} catch (PDOException $e) {
    error_log("QR Scan Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
