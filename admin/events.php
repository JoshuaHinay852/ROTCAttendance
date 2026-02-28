<?php
// events.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/event_status_helper.php';
requireAdminLogin();

// Run automatic status updates at the beginning of the script
$auto_update_result = ensureEventStatusesAreCurrent($pdo);

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);
$show_conflict_error_modal = false;
$conflict_error_message = '';

// List filters (GET)
$filter_search = isset($_GET['search']) ? trim(sanitize_input($_GET['search'])) : '';
$filter_date = isset($_GET['date']) ? trim(sanitize_input($_GET['date'])) : '';
$filter_status = isset($_GET['status']) ? trim(sanitize_input($_GET['status'])) : '';
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';

if ($filter_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $filter_date = '';
}

$allowed_status_filters = ['scheduled', 'ongoing', 'completed', 'cancelled'];
if ($filter_status !== '' && !in_array($filter_status, $allowed_status_filters, true)) {
    $filter_status = '';
}

// Include QR code library
require_once '../includes/phpqrcode/qrlib.php';

// Function to generate QR code for check-in or check-out
function generateEventQRCode($event_id, $qr_type = 'in', $regenerate = false) {
    global $pdo;
    
    try {
        // Get event details
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if (!$event) {
            throw new Exception("Event not found");
        }
        
        // Create QR expiration based on event schedule.
        $start_timestamp = strtotime($event['event_date'] . ' ' . $event['start_time']);
        $end_timestamp = strtotime($event['event_date'] . ' ' . $event['end_time']);
        if ($end_timestamp <= $start_timestamp) {
            $end_timestamp = strtotime('+1 day', $end_timestamp);
        }
        
        // Check-in QR expires when the event ends; check-out stays valid for a grace window.
        $check_in_expires_at = date('Y-m-d H:i:s', $end_timestamp);
        $check_out_expires_at = date('Y-m-d H:i:s', strtotime('+7 days', $end_timestamp));
        $expires_at = ($qr_type === 'in') ? $check_in_expires_at : $check_out_expires_at;

        $unique_hash = md5($event_id . $event['event_name'] . time() . uniqid() . $qr_type);
        
        $qr_data = json_encode([
            'event_id' => $event_id,
            'event_name' => $event['event_name'],
            'date' => $event['event_date'],
            'time' => $event['start_time'],
            'qr_type' => $qr_type,
            'hash' => $unique_hash,
            'expires' => $expires_at
        ]);
        
        // Create directories if they don't exist
        $qr_dir = '../assets/qrcodes/events/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0777, true);
        }
        
        // Generate QR code filename
        $filename = 'event_' . $event_id . '_' . $qr_type . '_' . time() . '.png';
        $filepath = $qr_dir . $filename;
        
        // Generate QR code with error correction level M (15% error correction)
        QRcode::png($qr_data, $filepath, QR_ECLEVEL_M, 10, 2);
        
        // Update event with QR code data based on type
        if ($qr_type === 'in') {
            $stmt = $pdo->prepare("UPDATE events SET 
                qr_in_data = ?, 
                qr_in_path = ?,
                updated_at = NOW()
                WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE events SET 
                qr_out_data = ?, 
                qr_out_path = ?,
                updated_at = NOW()
                WHERE id = ?");
        }
        
        $stmt->execute([$qr_data, $filename, $event_id]);
        
        // Log QR code generation
        if ($regenerate) {
            // Deactivate old QR codes of this type
            $stmt = $pdo->prepare("UPDATE event_qr_logs SET is_active = FALSE 
                                   WHERE event_id = ? AND qr_type = ?");
            $stmt->execute([$event_id, $qr_type]);
        }
        
        // Create new QR log entry
        $stmt = $pdo->prepare("INSERT INTO event_qr_logs 
            (event_id, qr_type, qr_code_data, qr_code_path, generated_by, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $event_id,
            $qr_type,
            $qr_data,
            $filename,
            $_SESSION['admin_id'],
            $expires_at
        ]);
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => $filename,
            'qr_type' => $qr_type
        ];
        
    } catch (Exception $e) {
        error_log("QR Generation Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function findScheduleConflict(PDO $pdo, $event_date, $start_time, $end_time, $exclude_event_id = null) {
    $new_start_timestamp = strtotime($event_date . ' ' . $start_time);
    $new_end_timestamp = strtotime($event_date . ' ' . $end_time);

    if ($new_start_timestamp === false || $new_end_timestamp === false) {
        return false;
    }

    // Overnight event support (end time next day).
    if ($new_end_timestamp <= $new_start_timestamp) {
        $new_end_timestamp = strtotime('+1 day', $new_end_timestamp);
    }

    $new_start_datetime = date('Y-m-d H:i:s', $new_start_timestamp);
    $new_end_datetime = date('Y-m-d H:i:s', $new_end_timestamp);

    $sql = "
        SELECT id, event_name, event_date, start_time, end_time
        FROM events
        WHERE deleted_at IS NULL
          AND status != 'cancelled'
          AND event_date BETWEEN DATE_SUB(?, INTERVAL 1 DAY) AND DATE_ADD(?, INTERVAL 1 DAY)
          AND TIMESTAMP(event_date, start_time) < ?
          AND (
                CASE
                    WHEN end_time >= start_time THEN TIMESTAMP(event_date, end_time)
                    ELSE DATE_ADD(TIMESTAMP(event_date, end_time), INTERVAL 1 DAY)
                END
              ) > ?
    ";

    $params = [$event_date, $event_date, $new_end_datetime, $new_start_datetime];

    if ($exclude_event_id !== null) {
        $sql .= " AND id != ?";
        $params[] = $exclude_event_id;
    }

    $sql .= " ORDER BY event_date ASC, start_time ASC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function buildScheduleConflictMessage($conflict_event) {
    $start_timestamp = strtotime($conflict_event['event_date'] . ' ' . $conflict_event['start_time']);
    $end_timestamp = strtotime($conflict_event['event_date'] . ' ' . $conflict_event['end_time']);

    if ($end_timestamp <= $start_timestamp) {
        $end_timestamp = strtotime('+1 day', $end_timestamp);
    }

    $start_text = date('F j, Y g:i A', $start_timestamp);
    $end_text = date('F j, Y g:i A', $end_timestamp);

    return "Schedule conflict detected. \"" . $conflict_event['event_name'] . "\" is already scheduled from {$start_text} to {$end_text}.";
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_event'])) {
        // Add new event
        $event_name = sanitize_input($_POST['event_name']);
        $description = sanitize_input($_POST['description']);
        $event_date = sanitize_input($_POST['event_date']);
        $start_time = sanitize_input($_POST['start_time']);
        $end_time = sanitize_input($_POST['end_time']);
        $location = sanitize_input($_POST['location']);
        $check_out_enabled = isset($_POST['check_out_enabled']) ? 1 : 0;

        // Block events with overlapping date/time schedule.
        $conflict_event = findScheduleConflict($pdo, $event_date, $start_time, $end_time);
        if ($conflict_event) {
            $error = buildScheduleConflictMessage($conflict_event);
            $show_conflict_error_modal = true;
            $conflict_error_message = $error;
        } else {
        
            // Set initial status using the same automatic rules used by auto-update.
            $status = getAutomaticEventStatus($event_date, $start_time, $end_time);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO events 
                    (event_name, description, event_date, start_time, end_time, location, 
                     check_out_enabled, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([
                    $event_name, $description, $event_date, $start_time, $end_time, 
                    $location, $check_out_enabled, $status, $_SESSION['admin_id']
                ])) {
                    $event_id = $pdo->lastInsertId();
                    
                    // Generate check-in QR code
                    $result_in = generateEventQRCode($event_id, 'in');
                    
                    // Generate check-out QR code if enabled
                    $result_out = null;
                    if ($check_out_enabled) {
                        $result_out = generateEventQRCode($event_id, 'out');
                    }
                    
                    $message = "Event created successfully! ";
                    if ($result_in['success']) {
                        $message .= "Check-in QR generated. ";
                    }
                    if ($result_out && $result_out['success']) {
                        $message .= "Check-out QR generated.";
                    } elseif ($check_out_enabled && !$result_out['success']) {
                        $message .= "Check-out QR generation failed: " . $result_out['error'];
                    }
                    
                    $_SESSION['message'] = $message;
                    header('Location: events.php');
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to create event: " . $e->getMessage();
            }
        }
        
    } elseif (isset($_POST['edit_event'])) {
        // Edit event
        $id = $_POST['id'];
        $event_name = sanitize_input($_POST['event_name']);
        $description = sanitize_input($_POST['description']);
        $event_date = sanitize_input($_POST['event_date']);
        $start_time = sanitize_input($_POST['start_time']);
        $end_time = sanitize_input($_POST['end_time']);
        $location = sanitize_input($_POST['location']);
        $status = sanitize_input($_POST['status']);
        $check_out_enabled = isset($_POST['check_out_enabled']) ? 1 : 0;

        // Block updates that overlap another event's date/time schedule.
        $conflict_event = findScheduleConflict($pdo, $event_date, $start_time, $end_time, $id);
        if ($conflict_event) {
            $error = buildScheduleConflictMessage($conflict_event);
            $show_conflict_error_modal = true;
            $conflict_error_message = $error;
        } else {
        
            // Recalculate status based on new date/time if not manually set
            if ($status === 'auto') {
                $status = getAutomaticEventStatus($event_date, $start_time, $end_time);
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE events SET 
                    event_name = ?, description = ?, event_date = ?, start_time = ?, 
                    end_time = ?, location = ?, status = ?, check_out_enabled = ?, updated_at = NOW() 
                    WHERE id = ? AND deleted_at IS NULL");
                
                if ($stmt->execute([
                    $event_name, $description, $event_date, $start_time, 
                    $end_time, $location, $status, $check_out_enabled, $id
                ])) {
                    $_SESSION['message'] = "Event updated successfully!";
                    header('Location: events.php');
                    exit();
                } else {
                    $error = "Failed to update event.";
                }
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
        
    } elseif (isset($_POST['soft_delete_event'])) {
        // Soft delete - move to draft
        $id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE events SET 
                deleted_at = NOW(), 
                deleted_by = ? 
                WHERE id = ? AND deleted_at IS NULL");
            
            if ($stmt->execute([$_SESSION['admin_id'], $id])) {
                $_SESSION['message'] = "Event moved to Draft successfully!";
            } else {
                $_SESSION['error'] = "Failed to move event to Draft.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: events.php' . ($show_deleted ? '?show_deleted=1' : ''));
        exit();
        
    } elseif (isset($_POST['restore_event'])) {
        // Restore from draft
        $id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE events SET 
                deleted_at = NULL, 
                deleted_by = NULL,
                updated_at = NOW()
                WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                $_SESSION['message'] = "Event restored successfully!";
            } else {
                $_SESSION['error'] = "Failed to restore event.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: events.php' . ($show_deleted ? '?show_deleted=1' : ''));
        exit();
        
    } elseif (isset($_POST['permanent_delete_event'])) {
        // Permanent delete
        $id = $_POST['id'];
        
        try {
            // Delete associated QR files
            $stmt = $pdo->prepare("SELECT qr_in_path, qr_out_path FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch();
            
            if ($event) {
                if ($event['qr_in_path']) {
                    $qr_file = '../assets/qrcodes/events/' . $event['qr_in_path'];
                    if (file_exists($qr_file)) unlink($qr_file);
                }
                if ($event['qr_out_path']) {
                    $qr_file = '../assets/qrcodes/events/' . $event['qr_out_path'];
                    if (file_exists($qr_file)) unlink($qr_file);
                }
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['message'] = "Event permanently deleted!";
            } else {
                $_SESSION['error'] = "Failed to delete event permanently.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: events.php?show_deleted=1');
        exit();
        
    } elseif (isset($_POST['generate_qr_in'])) {
        // Regenerate check-in QR code
        $event_id = $_POST['event_id'];
        $result = generateEventQRCode($event_id, 'in', true);
        
        if ($result['success']) {
            $_SESSION['message'] = "Check-in QR code regenerated successfully!";
        } else {
            $_SESSION['error'] = "Failed to regenerate check-in QR: " . $result['error'];
        }
        
        header('Location: events.php?action=edit&id=' . $event_id);
        exit();
        
    } elseif (isset($_POST['generate_qr_out'])) {
        // Regenerate check-out QR code
        $event_id = $_POST['event_id'];
        $result = generateEventQRCode($event_id, 'out', true);
        
        if ($result['success']) {
            $_SESSION['message'] = "Check-out QR code regenerated successfully!";
        } else {
            $_SESSION['error'] = "Failed to regenerate check-out QR: " . $result['error'];
        }
        
        header('Location: events.php?action=edit&id=' . $event_id);
        exit();
        
    } elseif (isset($_POST['update_status'])) {
        // Update event status
        $id = $_POST['id'];
        $status = sanitize_input($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
            if ($stmt->execute([$status, $id])) {
                $_SESSION['message'] = "Event status updated to " . ucfirst($status) . "!";
            } else {
                $_SESSION['error'] = "Failed to update status.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: events.php');
        exit();
    }
    
    elseif (isset($_POST['auto_update_all'])) {
        // Manual trigger for auto-update
        $result = updateEventStatusesAdvanced($pdo);
        if ($result['success']) {
            $_SESSION['message'] = "Auto-update completed! " . 
                                 ($result['ongoing'] ?? 0) . " events set to ongoing, " . 
                                 ($result['completed'] ?? 0) . " events set to completed, " .
                                 ($result['scheduled'] ?? 0) . " events set to scheduled.";
        } else {
            $_SESSION['error'] = "Failed to auto-update event statuses: " . $result['error'];
        }
        header('Location: events.php');
        exit();
    }
}

// Get event data for edit
$event = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$_GET['id']]);
    $event = $stmt->fetch();
    if (!$event) {
        $action = 'list';
        $error = "Event not found or has been moved to Draft.";
    }
}

// Get event for QR view
if ($action === 'view_qr' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$_GET['id']]);
    $qr_event = $stmt->fetch();
    
    if (!$qr_event) {
        header('Location: events.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management | ROTC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-scheduled { background: #dbeafe; color: #1e40af; }
        .status-ongoing { background: #fef3c7; color: #92400e; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-draft { background: #e5e7eb; color: #374151; }
        
        .table-row-hover:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .qr-preview {
            border: 2px dashed #d1d5db;
            padding: 20px;
            border-radius: 12px;
            background: white;
            display: inline-block;
        }
        
        .qr-in-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .qr-out-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .input-field {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            width: 100%;
            transition: all 0.2s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .select-field {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            width: 100%;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .tab-button {
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        
        .tab-button.active {
            background: white;
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
        }
        
        .auto-update-badge {
            background: #818cf8;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .auto-update-badge i {
            font-size: 8px;
        }

        .status-switch-tab {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            background: #ffffff;
            color: #4b5563;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .status-switch-tab:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #1f2937;
        }

        .status-switch-tab.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.2);
        }

        .status-count-chip {
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            background: #f1f5f9;
            color: #1f2937;
            line-height: 1.4;
        }

        .status-switch-tab.active .status-count-chip {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        
        .completed-highlight {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .draft-row {
            background-color: #f9fafb;
            opacity: 0.8;
        }
        
        .draft-row:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Event Management</h2>
                    <p class="text-gray-600">Create and manage ROTC events with IN/OUT QR codes</p>
                </div>
                <div class="flex gap-3">
                    <?php if ($action === 'list'): ?>
                        <!-- Auto-Update Button -->
                        <form method="POST" action="" class="inline">
                            <input type="hidden" name="auto_update_all" value="1">
                            <button type="submit" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors"
                                    title="Manually trigger auto-update of event statuses">
                                <i class="fas fa-sync-alt"></i>
                                <span>Auto-Update Status</span>
                            </button>
                        </form>
                        
                        <a href="?action=add" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Create New Event</span>
                        </a>
                    <?php else: ?>
                        <a href="events.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Events</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Auto-Update Info Badge -->
            <div class="mt-3 flex items-center gap-2 text-xs text-gray-600">
                <span class="auto-update-badge">
                    <i class="fas fa-clock"></i>
                    AUTO-UPDATE ACTIVE
                </span>
                <span>Events automatically move to ONGOING at start time and COMPLETED at end time</span>
            </div>
            
            <!-- Auto-Update Result Message -->
            <?php if ($action === 'list' && isset($auto_update_result) && $auto_update_result['success'] && (($auto_update_result['ongoing'] + $auto_update_result['completed'] + ($auto_update_result['scheduled'] ?? 0)) > 0)): ?>
                <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-2 text-xs text-green-700 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span>
                        <?php echo $auto_update_result['ongoing']; ?> to ONGOING,
                        <?php echo $auto_update_result['completed']; ?> to COMPLETED,
                        <?php echo ($auto_update_result['scheduled'] ?? 0); ?> to SCHEDULED
                    </span>
                </div>
            <?php endif; ?>
        </header>
        
        <main class="px-8 pb-8">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Add/Edit Form -->
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus-circle' : 'edit'; ?> text-blue-600"></i>
                        <?php echo $action === 'add' ? 'Create New Event' : 'Edit Event'; ?>
                    </h3>
                    
                    <form method="POST" action="" class="space-y-6">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                            <input type="hidden" name="edit_event" value="1">
                        <?php else: ?>
                            <input type="hidden" name="add_event" value="1">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Event Name -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Event Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="event_name" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($event['event_name']) : ''; ?>"
                                       class="input-field"
                                       placeholder="Enter event name">
                            </div>
                            
                            <!-- Date and Time -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Event Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="event_date" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($event['event_date']) : date('Y-m-d'); ?>"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       class="input-field">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Start Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="start_time" required
                                           value="<?php echo $action === 'edit' ? htmlspecialchars($event['start_time']) : '08:00'; ?>"
                                           class="input-field">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        End Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="end_time" required
                                           value="<?php echo $action === 'edit' ? htmlspecialchars($event['end_time']) : '17:00'; ?>"
                                           class="input-field">
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle"></i>
                                        Event will be auto-marked as COMPLETED when this time is reached
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Location -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Location
                                </label>
                                <input type="text" name="location"
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($event['location']) : ''; ?>"
                                       class="input-field"
                                       placeholder="Enter event location">
                            </div>
                            
                            <!-- Check-out enabled -->
                            <div class="flex items-center">
                                <input type="checkbox" name="check_out_enabled" id="check_out_enabled" 
                                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                       <?php echo ($action === 'edit' && $event['check_out_enabled']) ? 'checked' : ''; ?>>
                                <label for="check_out_enabled" class="ml-2 text-sm text-gray-700">
                                    Enable check-out QR code
                                </label>
                            </div>
                            
                            <!-- Status (for edit only) -->
                            <?php if ($action === 'edit'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select name="status" required class="select-field">
                                        <option value="auto">Auto (Based on date/time)</option>
                                        <option value="scheduled" <?php echo $event['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="ongoing" <?php echo $event['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="completed" <?php echo $event['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $event['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle"></i>
                                        Select "Auto" to let system determine status based on date/time
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Description
                                </label>
                                <textarea name="description" rows="4"
                                          class="input-field"
                                          placeholder="Enter event description"><?php echo $action === 'edit' ? htmlspecialchars($event['description']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                            <a href="events.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                                <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                <?php echo $action === 'add' ? 'Create Event' : 'Update Event'; ?>
                            </button>
                        </div>
                    </form>
                    
                    <!-- QR Code Section (for edit only) -->
                    <?php if ($action === 'edit' && ($event['qr_in_path'] || $event['qr_out_path'])): ?>
                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-qrcode text-green-600"></i>
                                Event QR Codes
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Check-in QR Code -->
                                <div class="border rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="font-medium text-gray-800 flex items-center gap-2">
                                            <span class="qr-in-badge">
                                                <i class="fas fa-sign-in-alt"></i> CHECK-IN QR
                                            </span>
                                        </h5>
                                        <?php if (!$event['qr_in_path']): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <input type="hidden" name="generate_qr_in" value="1">
                                                <button type="submit" class="text-xs px-3 py-1 bg-blue-600 text-white rounded-lg">
                                                    Generate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($event['qr_in_path']): ?>
                                        <div class="flex flex-col items-center">
                                            <div class="qr-preview mb-3">
                                                <img src="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_in_path']); ?>" 
                                                     alt="Check-in QR Code" 
                                                     class="w-32 h-32 mx-auto">
                                            </div>
                                            
                                            <div class="flex gap-2 mt-2">
                                                <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_in_path']); ?>" 
                                                   download="event_<?php echo $event['id']; ?>_checkin.png"
                                                   class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm flex items-center gap-1">
                                                    <i class="fas fa-download"></i>
                                                    Download
                                                </a>
                                                
                                                <form method="POST" action="" class="inline" 
                                                      onsubmit="return confirm('Generate new check-in QR? Old QR will be deactivated.')">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                    <input type="hidden" name="generate_qr_in" value="1">
                                                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded-lg text-sm flex items-center gap-1">
                                                        <i class="fas fa-sync"></i>
                                                        Regenerate
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500 text-center py-4">No check-in QR generated yet</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Check-out QR Code -->
                                <div class="border rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="font-medium text-gray-800 flex items-center gap-2">
                                            <span class="qr-out-badge">
                                                <i class="fas fa-sign-out-alt"></i> CHECK-OUT QR
                                            </span>
                                        </h5>
                                        <?php if ($event['check_out_enabled'] && !$event['qr_out_path']): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <input type="hidden" name="generate_qr_out" value="1">
                                                <button type="submit" class="text-xs px-3 py-1 bg-blue-600 text-white rounded-lg">
                                                    Generate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($event['qr_out_path']): ?>
                                        <div class="flex flex-col items-center">
                                            <div class="qr-preview mb-3">
                                                <img src="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_out_path']); ?>" 
                                                     alt="Check-out QR Code" 
                                                     class="w-32 h-32 mx-auto">
                                            </div>
                                            
                                            <div class="flex gap-2 mt-2">
                                                <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_out_path']); ?>" 
                                                   download="event_<?php echo $event['id']; ?>_checkout.png"
                                                   class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm flex items-center gap-1">
                                                    <i class="fas fa-download"></i>
                                                    Download
                                                </a>
                                                
                                                <form method="POST" action="" class="inline" 
                                                      onsubmit="return confirm('Generate new check-out QR? Old QR will be deactivated.')">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                    <input type="hidden" name="generate_qr_out" value="1">
                                                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded-lg text-sm flex items-center gap-1">
                                                        <i class="fas fa-sync"></i>
                                                        Regenerate
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php elseif ($event['check_out_enabled']): ?>
                                        <p class="text-sm text-gray-500 text-center py-4">No check-out QR generated yet</p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 text-center py-4">Check-out not enabled for this event</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-500 bg-gray-50 p-3 rounded-lg">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                <span class="font-medium">QR Code Information:</span> 
                                Check-in QR for attendance entry, Check-out QR for attendance exit. 
                                Check-in QR expires at event end time; check-out QR remains valid after event completion.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            
            <!-- QR View Page -->
            <?php elseif ($action === 'view_qr' && isset($qr_event)): ?>
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2 text-center">
                        QR Codes for <?php echo htmlspecialchars($qr_event['event_name']); ?>
                    </h3>
                    <p class="text-gray-600 mb-6 text-center">Scan these QR codes for attendance</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Check-in QR -->
                        <?php if ($qr_event['qr_in_path']): ?>
                        <div class="text-center border-r border-gray-200 pr-6">
                            <div class="qr-in-badge inline-block mb-3">
                                <i class="fas fa-sign-in-alt"></i> CHECK-IN QR
                            </div>
                            <div class="qr-preview inline-block mb-4">
                                <img src="../assets/qrcodes/events/<?php echo htmlspecialchars($qr_event['qr_in_path']); ?>" 
                                     alt="Check-in QR Code" 
                                     class="w-48 h-48 mx-auto">
                            </div>
                            <div class="space-y-2 mb-4">
                                <p class="text-gray-700"><strong>Event Name:</strong> <?php echo htmlspecialchars($qr_event['event_name']); ?></p>
                                <p class="text-gray-700"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($qr_event['event_date'])); ?></p>
                                <p class="text-gray-700"><strong>Start Time:</strong> <?php echo date('g:i A', strtotime($qr_event['start_time'])); ?></p>
                            </div>
                            <div class="flex justify-center gap-3">
                                <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($qr_event['qr_in_path']); ?>" 
                                   download="event_<?php echo $qr_event['id']; ?>_checkin.png"
                                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                    <i class="fas fa-download"></i>
                                    Download Check-in
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Check-out QR -->
                        <?php if ($qr_event['qr_out_path']): ?>
                        <div class="text-center <?php echo $qr_event['qr_in_path'] ? 'pl-6' : ''; ?>">
                            <div class="qr-out-badge inline-block mb-3">
                                <i class="fas fa-sign-out-alt"></i> CHECK-OUT QR
                            </div>
                            <div class="qr-preview inline-block mb-4">
                                <img src="../assets/qrcodes/events/<?php echo htmlspecialchars($qr_event['qr_out_path']); ?>" 
                                     alt="Check-out QR Code" 
                                     class="w-48 h-48 mx-auto">
                            </div>
                            <div class="space-y-2 mb-4">
                                <p class="text-gray-700"><strong>Event Name:</strong> <?php echo htmlspecialchars($qr_event['event_name']); ?></p>
                                <p class="text-gray-700"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($qr_event['event_date'])); ?></p>
                                <p class="text-gray-700"><strong>End Time:</strong> <?php echo date('g:i A', strtotime($qr_event['end_time'])); ?></p>
                            </div>
                            <div class="flex justify-center gap-3">
                                <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($qr_event['qr_out_path']); ?>" 
                                   download="event_<?php echo $qr_event['id']; ?>_checkout.png"
                                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                    <i class="fas fa-download"></i>
                                    Download Check-out
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$qr_event['qr_in_path'] && !$qr_event['qr_out_path']): ?>
                        <div class="col-span-2 text-center py-8">
                            <p class="text-gray-500">No QR codes generated for this event yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            
            <!-- Events List -->
            <?php else: ?>
                <?php
                try {
                    $tab_counts = $pdo->query("
                        SELECT
                            COALESCE(SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END), 0) AS total,
                            COALESCE(SUM(CASE WHEN deleted_at IS NULL AND status = 'scheduled' THEN 1 ELSE 0 END), 0) AS scheduled,
                            COALESCE(SUM(CASE WHEN deleted_at IS NULL AND status = 'ongoing' THEN 1 ELSE 0 END), 0) AS ongoing,
                            COALESCE(SUM(CASE WHEN deleted_at IS NULL AND status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                            COALESCE(SUM(CASE WHEN deleted_at IS NULL AND status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
                            COALESCE(SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS draft
                        FROM events
                    ")->fetch();
                } catch (PDOException $e) {
                    $tab_counts = [
                        'total' => 0,
                        'scheduled' => 0,
                        'ongoing' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                        'draft' => 0
                    ];
                }

                $status_tabs = [
                    ['label' => 'All', 'value' => '', 'icon' => 'layer-group', 'count' => (int)$tab_counts['total']],
                    ['label' => 'Scheduled', 'value' => 'scheduled', 'icon' => 'clock', 'count' => (int)$tab_counts['scheduled']],
                    ['label' => 'Ongoing', 'value' => 'ongoing', 'icon' => 'play', 'count' => (int)$tab_counts['ongoing']],
                    ['label' => 'Completed', 'value' => 'completed', 'icon' => 'check-circle', 'count' => (int)$tab_counts['completed']],
                    ['label' => 'Cancelled', 'value' => 'cancelled', 'icon' => 'times-circle', 'count' => (int)$tab_counts['cancelled']],
                    ['label' => 'Draft', 'value' => 'draft', 'icon' => 'archive', 'count' => (int)$tab_counts['draft'], 'show_deleted' => true]
                ];
                ?>

                <div class="mb-6 bg-white rounded-xl shadow p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <?php foreach ($status_tabs as $tab): ?>
                            <?php
                            $tab_params = ['action' => 'list'];
                            if ($filter_search !== '') {
                                $tab_params['search'] = $filter_search;
                            }
                            if ($filter_date !== '') {
                                $tab_params['date'] = $filter_date;
                            }
                            
                            if (isset($tab['show_deleted']) && $tab['show_deleted']) {
                                $tab_params['show_deleted'] = 1;
                                $tab_params['status'] = '';
                            } elseif ($tab['value'] !== '') {
                                $tab_params['status'] = $tab['value'];
                                $tab_params['show_deleted'] = 0;
                            } else {
                                $tab_params['show_deleted'] = 0;
                            }
                            
                            $tab_url = 'events.php?' . http_build_query($tab_params);
                            $is_active_tab = ($tab['show_deleted'] ?? false) ? $show_deleted : (!$show_deleted && $tab['value'] === $filter_status);
                            ?>
                            <a href="<?php echo htmlspecialchars($tab_url); ?>" class="status-switch-tab <?php echo $is_active_tab ? 'active' : ''; ?>">
                                <i class="fas fa-<?php echo htmlspecialchars($tab['icon']); ?>"></i>
                                <span><?php echo htmlspecialchars($tab['label']); ?></span>
                                <span class="status-count-chip"><?php echo (int)$tab['count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Auto-Update Timestamp -->
                <div class="mb-4 text-sm text-gray-500 flex items-center gap-2">
                    <i class="fas fa-clock text-purple-500"></i>
                    <span>Last auto-update: <?php echo date('F j, Y g:i:s A'); ?></span>
                    <span class="text-xs text-gray-400">(Automatic transition: Scheduled -> Ongoing -> Completed)</span>
                </div>
                
                <!-- Events Table -->
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <?php echo $show_deleted ? 'Draft Events' : 'All Events'; ?>
                        </h3>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fas fa-filter"></i>
                            <span><?php echo $show_deleted ? 'Events in draft can be restored or permanently deleted' : 'Filter events by date, status, or keyword'; ?></span>
                        </div>
                    </div>

                    <?php if (!$show_deleted): ?>
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <form method="GET" action="events.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end w-full">
                            <input type="hidden" name="action" value="list">
                            <input type="hidden" name="show_deleted" value="0">
                            <div class="sm:col-span-2 lg:col-span-5 w-full">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                                <input type="text" name="search" class="input-field" placeholder="Event name, description, location, creator"
                                       value="<?php echo htmlspecialchars($filter_search); ?>">
                            </div>
                            <div class="lg:col-span-3 w-full">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                                <input type="date" name="date" class="input-field" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            <div class="lg:col-span-2 w-full">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                <select name="status" class="select-field">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2 lg:col-span-2 w-full">
                                <div class="grid grid-cols-2 gap-2 w-full">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    Apply
                                </button>
                                <a href="events.php?action=list" class="w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                                    Reset
                                </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    
                                    <?php if (!$show_deleted): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Codes</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                try {
                                    $where_clauses = $show_deleted ? ["e.deleted_at IS NOT NULL"] : ["e.deleted_at IS NULL"];
                                    $query_params = [];
                                    
                                    if (!$show_deleted) {
                                        if ($filter_date !== '') {
                                            $where_clauses[] = "e.event_date = ?";
                                            $query_params[] = $filter_date;
                                        }
                                        
                                        if ($filter_status !== '') {
                                            $where_clauses[] = "e.status = ?";
                                            $query_params[] = $filter_status;
                                        }
                                        
                                        if ($filter_search !== '') {
                                            $where_clauses[] = "(e.event_name LIKE ? OR e.description LIKE ? OR e.location LIKE ? OR a.full_name LIKE ?)";
                                            $search_term = '%' . $filter_search . '%';
                                            $query_params[] = $search_term;
                                            $query_params[] = $search_term;
                                            $query_params[] = $search_term;
                                            $query_params[] = $search_term;
                                        }
                                    }
                                    
                                    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
                                    
                                    if ($show_deleted) {
                                        $order_sql = "ORDER BY e.deleted_at DESC";
                                    } else {
                                        $order_sql = "ORDER BY 
                                            CASE e.status 
                                                WHEN 'ongoing' THEN 1
                                                WHEN 'scheduled' THEN 2
                                                WHEN 'completed' THEN 3
                                                WHEN 'cancelled' THEN 4
                                            END,
                                            e.event_date DESC, 
                                            e.start_time DESC";
                                    }
                                    
                                    $stmt = $pdo->prepare("
                                        SELECT e.*, a.full_name as creator, d.full_name as deleted_by_name
                                        FROM events e 
                                        LEFT JOIN admins a ON e.created_by = a.id
                                        LEFT JOIN admins d ON e.deleted_by = d.id
                                        $where_sql
                                        $order_sql
                                    ");
                                    $stmt->execute($query_params);
                                    $events = $stmt->fetchAll();
                                    $filtered_count = count($events);
                                } catch (PDOException $e) {
                                    $events = [];
                                    $filtered_count = 0;
                                    $error = "Error loading events: " . $e->getMessage();
                                }
                                
                                if (empty($events)): ?>
                                    <tr>
                                        <td colspan="<?php echo $show_deleted ? '5' : '6'; ?>" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-<?php echo $show_deleted ? 'archive' : 'calendar-times'; ?> text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">
                                                <?php echo $show_deleted ? 'No Draft Events' : 'No Events Found'; ?>
                                            </h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                <?php echo $show_deleted 
                                                    ? 'Your draft is empty. Events you delete will appear here.' 
                                                    : 'No events match your current filters. Try adjusting date, status, or search.'; ?>
                                            </p>
                                            <?php if (!$show_deleted): ?>
                                            <a href="?action=add" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                                                <i class="fas fa-calendar-plus"></i>
                                                Create Your First Event
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <tr class="<?php echo $show_deleted ? 'draft-row' : 'table-row-hover'; ?> <?php echo (!$show_deleted && $event['status'] == 'completed') ? 'bg-green-50' : ''; ?>">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg <?php 
                                                        echo $show_deleted ? 'bg-purple-100' : 
                                                            ($event['status'] == 'completed' ? 'bg-green-100' : 'bg-blue-100'); 
                                                    ?> flex items-center justify-center">
                                                        <i class="fas fa-<?php 
                                                            echo $show_deleted ? 'archive' : 
                                                                ($event['status'] == 'completed' ? 'check-circle' : 'calendar-alt'); 
                                                        ?> <?php 
                                                            echo $show_deleted ? 'text-purple-600' : 
                                                                ($event['status'] == 'completed' ? 'text-green-600' : 'text-blue-600'); 
                                                        ?>"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($event['event_name']); ?>
                                                        </div>
                                                        <?php if ($event['description']): ?>
                                                            <div class="text-sm text-gray-600 truncate max-w-xs">
                                                                <?php echo htmlspecialchars($event['description']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Created by: <?php echo htmlspecialchars($event['creator']); ?>
                                                            <?php if ($show_deleted && $event['deleted_by_name']): ?>
                                                                <span class="text-purple-600"> • Deleted by: <?php echo htmlspecialchars($event['deleted_by_name']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($show_deleted): ?>
                                                            <div class="text-xs text-purple-600 mt-1">
                                                                <i class="fas fa-clock"></i> Deleted: <?php echo date('M j, Y g:i A', strtotime($event['deleted_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                </div>
                                                <?php if (!$show_deleted && $event['status'] == 'completed'): ?>
                                                    <div class="text-xs text-green-600 mt-1">
                                                        <i class="fas fa-check-circle"></i>
                                                        Completed at end time
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($event['location']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <?php if ($show_deleted): ?>
                                                        <span class="status-badge status-draft">
                                                            <i class="fas fa-archive"></i>
                                                            Draft
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-<?php echo $event['status']; ?>">
                                                            <i class="fas fa-<?php 
                                                                echo $event['status'] == 'scheduled' ? 'clock' : 
                                                                       ($event['status'] == 'ongoing' ? 'play' : 
                                                                       ($event['status'] == 'completed' ? 'check-circle' : 'times-circle')); 
                                                            ?>"></i>
                                                            <?php echo ucfirst($event['status']); ?>
                                                        </span>
                                                        
                                                        <!-- Auto-update indicator -->
                                                        <?php if ($event['status'] == 'ongoing'): ?>
                                                            <div class="text-xs text-yellow-600">
                                                                <i class="fas fa-hourglass-half"></i> In progress - Ends at <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                            </div>
                                                        <?php elseif ($event['status'] == 'completed'): ?>
                                                            <div class="text-xs text-green-600">
                                                                <i class="fas fa-check"></i> Finished
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Quick Status Update -->
                                                        <div class="mt-1">
                                                            <button onclick="showStatusModal(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>', '<?php echo $event['status']; ?>')"
                                                                    class="text-xs px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                                                                <i class="fas fa-edit mr-1"></i>
                                                                Change
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <?php if (!$show_deleted): ?>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php if ($event['qr_in_path']): ?>
                                                        <div class="flex items-center" title="Check-in QR generated">
                                                            <span class="qr-in-badge text-xs px-2 py-1">
                                                                <i class="fas fa-sign-in-alt"></i> IN
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($event['qr_out_path']): ?>
                                                        <div class="flex items-center" title="Check-out QR generated">
                                                            <span class="qr-out-badge text-xs px-2 py-1">
                                                                <i class="fas fa-sign-out-alt"></i> OUT
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$event['qr_in_path'] && !$event['qr_out_path']): ?>
                                                        <span class="text-sm text-gray-400">No QR</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <a href="events.php?action=view_qr&id=<?php echo $event['id']; ?>" 
                                                       class="text-xs text-blue-600 hover:text-blue-800">
                                                        View QR Codes
                                                    </a>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <?php if ($show_deleted): ?>
                                                        <!-- Draft Actions -->
                                                        <button onclick="showRestoreModal(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')"
                                                                class="w-8 h-8 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition-colors"
                                                                title="Restore Event">
                                                            <i class="fas fa-undo-alt text-sm"></i>
                                                        </button>
                                                        
                                                        <button onclick="showPermanentDeleteModal(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')"
                                                                class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-colors"
                                                                title="Delete Permanently">
                                                            <i class="fas fa-trash-alt text-sm"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Active Event Actions -->
                                                        <a href="?action=edit&id=<?php echo $event['id']; ?>" 
                                                           class="w-8 h-8 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-600 flex items-center justify-center transition-colors" 
                                                           title="Edit">
                                                            <i class="fas fa-edit text-sm"></i>
                                                        </a>
                                                        
                                                        <?php if ($event['qr_in_path']): ?>
                                                            <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_in_path']); ?>" 
                                                               download="event_<?php echo $event['id']; ?>_checkin.png"
                                                               class="w-8 h-8 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition-colors"
                                                               title="Download Check-in QR">
                                                                <i class="fas fa-download text-sm"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($event['qr_out_path']): ?>
                                                            <a href="../assets/qrcodes/events/<?php echo htmlspecialchars($event['qr_out_path']); ?>" 
                                                               download="event_<?php echo $event['id']; ?>_checkout.png"
                                                               class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-colors"
                                                               title="Download Check-out QR">
                                                                <i class="fas fa-download text-sm"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- In the actions column of events.php, add: -->
                                                        <a href="event_attendance.php?event_id=<?php echo $event['id']; ?>" 
                                                        class="w-8 h-8 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition-colors"
                                                        title="View Attendance">
                                                            <i class="fas fa-users text-sm"></i>
                                                        </a>


                                                        
                                                        <button onclick="confirmSoftDelete(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')"
                                                                class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-colors"
                                                                title="Move to Draft">
                                                            <i class="fas fa-trash text-sm"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                            <?php
                                            // Get attendance count for this event
                                            $attendance_stmt = $pdo->prepare("
                                                SELECT COUNT(*) as count 
                                                FROM event_attendance_summary 
                                                WHERE event_id = ? AND check_in_time IS NOT NULL
                                            ");
                                            $attendance_stmt->execute([$event['id']]);
                                            $attended = $attendance_stmt->fetchColumn();
                                            
                                            // Get total approved cadets
                                            $total_stmt = $pdo->query("SELECT COUNT(*) FROM cadet_accounts WHERE status = 'approved' AND is_archived = FALSE");
                                            $total = $total_stmt->fetchColumn();
                                            ?>
                                            <div class="text-sm">
                                                <span class="font-semibold <?php echo $attended > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
                                                    <?php echo $attended; ?>/<?php echo $total; ?>
                                                </span>
                                                <div class="w-24 h-1.5 bg-gray-200 rounded-full mt-1">
                                                    <div class="h-1.5 rounded-full <?php echo $attended > 0 ? 'bg-green-500' : 'bg-gray-300'; ?>" 
                                                        style="width: <?php echo $total > 0 ? ($attended / $total) * 100 : 0; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Soft Delete Modal (Move to Draft) -->
    <div id="softDeleteModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-purple-100 mb-4">
                            <i class="fas fa-archive text-purple-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Move to Draft</h3>
                        <p class="text-sm text-gray-600 mb-6" id="softDeleteMessage">
                            Are you sure you want to move this event to Draft?
                        </p>
                        <p class="text-xs text-gray-500 mb-6">
                            The event will be hidden from main lists and can be restored later.
                        </p>
                    </div>
                    <form id="softDeleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="softDeleteId">
                        <input type="hidden" name="soft_delete_event" value="1">
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeSoftDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg hover:from-purple-700 hover:to-purple-800 transition-colors font-medium">
                                Move to Draft
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Restore Modal -->
    <div id="restoreModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                            <i class="fas fa-undo-alt text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Restore Event</h3>
                        <p class="text-sm text-gray-600 mb-6" id="restoreMessage">
                            Are you sure you want to restore this event?
                        </p>
                    </div>
                    <form id="restoreForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="restoreId">
                        <input type="hidden" name="restore_event" value="1">
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeRestoreModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-colors font-medium">
                                Restore Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Permanent Delete Modal -->
    <div id="permanentDeleteModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Permanently Delete Event</h3>
                        <p class="text-sm text-gray-600 mb-6" id="permanentDeleteMessage">
                            Are you sure you want to permanently delete this event?
                        </p>
                        <p class="text-xs text-red-600 mb-6">
                            <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                        </p>
                    </div>
                    <form id="permanentDeleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="permanentDeleteId">
                        <input type="hidden" name="permanent_delete_event" value="1">
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closePermanentDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                            <i class="fas fa-toggle-on text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Update Event Status</h3>
                        <p class="text-sm text-gray-600" id="statusMessage">
                            Update status for: <span id="statusEventName" class="font-semibold"></span>
                        </p>
                    </div>
                    <form id="statusForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="statusId">
                        <input type="hidden" name="update_status" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Status
                            </label>
                            <select name="status" required class="select-field" id="statusSelect">
                                <option value="scheduled">Scheduled</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-lg text-xs text-blue-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Note: Status will automatically update based on date/time on page load
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeStatusModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-colors font-medium">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Conflict Error Modal -->
    <div id="conflictErrorModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-lg">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-calendar-times text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Schedule Conflict</h3>
                        <p class="text-sm text-gray-600" id="conflictErrorMessage">
                            <?php echo htmlspecialchars($conflict_error_message); ?>
                        </p>
                    </div>
                    <div class="bg-red-50 border border-red-200 p-3 rounded-lg text-xs text-red-700 mb-4">
                        <i class="fas fa-info-circle mr-1"></i>
                        Choose a different date/time to avoid overlapping events.
                    </div>
                    <div class="flex justify-center">
                        <button type="button" onclick="closeConflictErrorModal()"
                                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-medium">
                            OK, I Understand
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Soft Delete Modal Functions
        function confirmSoftDelete(id, name) {
            document.getElementById('softDeleteId').value = id;
            document.getElementById('softDeleteMessage').innerHTML = 
                `Are you sure you want to move the event <strong>"${name}"</strong> to Draft?`;
            document.getElementById('softDeleteModal').classList.remove('hidden');
        }
        
        function closeSoftDeleteModal() {
            document.getElementById('softDeleteModal').classList.add('hidden');
        }
        
        // Restore Modal Functions
        function showRestoreModal(id, name) {
            document.getElementById('restoreId').value = id;
            document.getElementById('restoreMessage').innerHTML = 
                `Are you sure you want to restore the event <strong>"${name}"</strong>?`;
            document.getElementById('restoreModal').classList.remove('hidden');
        }
        
        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.add('hidden');
        }
        
        // Permanent Delete Modal Functions
        function showPermanentDeleteModal(id, name) {
            document.getElementById('permanentDeleteId').value = id;
            document.getElementById('permanentDeleteMessage').innerHTML = 
                `Are you sure you want to permanently delete <strong>"${name}"</strong>?`;
            document.getElementById('permanentDeleteModal').classList.remove('hidden');
        }
        
        function closePermanentDeleteModal() {
            document.getElementById('permanentDeleteModal').classList.add('hidden');
        }
        
        // Status Modal Functions
        function showStatusModal(id, name, currentStatus) {
            document.getElementById('statusId').value = id;
            document.getElementById('statusEventName').textContent = name;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        // Conflict Error Modal Functions
        function showConflictErrorModal() {
            const modal = document.getElementById('conflictErrorModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closeConflictErrorModal() {
            const modal = document.getElementById('conflictErrorModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const softDeleteModal = document.getElementById('softDeleteModal');
            const restoreModal = document.getElementById('restoreModal');
            const permanentDeleteModal = document.getElementById('permanentDeleteModal');
            const statusModal = document.getElementById('statusModal');
            const conflictErrorModal = document.getElementById('conflictErrorModal');
            
            if (event.target == softDeleteModal) {
                closeSoftDeleteModal();
            }
            if (event.target == restoreModal) {
                closeRestoreModal();
            }
            if (event.target == permanentDeleteModal) {
                closePermanentDeleteModal();
            }
            if (event.target == statusModal) {
                closeStatusModal();
            }
            if (event.target == conflictErrorModal) {
                closeConflictErrorModal();
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('animate-fade-in');
            });
            
            // Check for events that should be completed every minute
            setInterval(function() {
                // Reload the page to trigger auto-update
                // This ensures events are marked as completed exactly when end time is reached
                window.location.reload();
            }, 60000); // Check every minute

            <?php if ($show_conflict_error_modal): ?>
            showConflictErrorModal();
            <?php endif; ?>
        });

        
    </script>
</body>
</html>
