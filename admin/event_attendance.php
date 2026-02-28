<?php
// event_attendance.php
// Professional attendance view with sidebar integration - FIXED VERSION

require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if (!$event_id) {
    $_SESSION['error'] = "No event ID provided";
    header('Location: events.php');
    exit();
}

// Get event details
$stmt = $pdo->prepare("
    SELECT e.*, a.full_name as creator_name 
    FROM events e 
    LEFT JOIN admins a ON e.created_by = a.id 
    WHERE e.id = ? AND e.deleted_at IS NULL
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "Event not found";
    header('Location: events.php');
    exit();
}

function getEventAttendanceColumns(PDO $pdo): array {
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM event_attendance");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }
    } catch (Exception $e) {
        error_log("Failed to inspect event_attendance schema: " . $e->getMessage());
    }

    return $columns;
}

function getFallbackMpId(PDO $pdo): ?int {
    static $resolved = false;
    static $mpId = null;

    if ($resolved) {
        return $mpId;
    }

    $resolved = true;
    try {
        $stmt = $pdo->query("SELECT id FROM mp_accounts ORDER BY id ASC LIMIT 1");
        $value = $stmt->fetchColumn();
        if ($value !== false) {
            $mpId = (int) $value;
        }
    } catch (Exception $e) {
        error_log("Failed to resolve fallback MP id: " . $e->getMessage());
    }

    return $mpId;
}

function normalizeAttendanceStatusValue(?string $status): string {
    if ($status === 'excuse') {
        return 'excused';
    }

    $allowed = ['present', 'late', 'absent', 'excused'];
    return in_array($status, $allowed, true) ? $status : 'present';
}

function normalizeSummaryStatusValue(?string $status): ?string {
    if ($status === null) {
        return null;
    }

    $status = strtolower(trim($status));
    if ($status === '') {
        return null;
    }

    if ($status === 'excused') {
        $status = 'excuse';
    }

    $allowed = ['present', 'late', 'absent', 'excuse'];
    return in_array($status, $allowed, true) ? $status : null;
}

function getEventTimeBounds(array $event): array {
    $eventDate = $event['event_date'] ?? date('Y-m-d');
    $startTime = $event['start_time'] ?? '00:00:00';
    $endTime = $event['end_time'] ?? '23:59:59';

    $startTs = strtotime($eventDate . ' ' . $startTime);
    $endTs = strtotime($eventDate . ' ' . $endTime);

    if ($startTs === false) {
        $startTs = time();
    }
    if ($endTs === false) {
        $endTs = $startTs;
    }

    // Support overnight events where end time passes midnight.
    if ($endTs < $startTs) {
        $endTs = strtotime('+1 day', $endTs);
        if ($endTs === false) {
            $endTs = $startTs;
        }
    }

    return [$startTs, $endTs];
}

function buildEventAttendanceRedirectUrl(int $eventId): string {
    $params = $_GET;
    $params['event_id'] = $eventId;
    return 'event_attendance.php?' . http_build_query($params);
}

function writeAttendanceAudit(
    PDO $pdo,
    int $eventId,
    int $cadetId,
    ?string $status,
    string $remarks,
    string $action = 'check_in'
): void {
    $columns = getEventAttendanceColumns($pdo);

    if (empty($columns)) {
        return;
    }

    try {
        // Legacy schema compatibility
        if (isset($columns['scan_type'])) {
            $scanType = ($action === 'check_out') ? 'out' : 'in';
            $dummyHash = md5($eventId . $cadetId . time() . $scanType);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $stmt = $pdo->prepare("
                INSERT INTO event_attendance
                (event_id, cadet_id, scan_type, scan_time, status, remarks, scanned_by_ip, qr_code_hash)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $cadetId, $scanType, $status, $remarks, $ip, $dummyHash]);
            return;
        }

        // Current schema compatibility
        if (isset($columns['mp_id']) && isset($columns['check_in_time']) && isset($columns['attendance_status'])) {
            $mpId = getFallbackMpId($pdo);
            if (!$mpId) {
                error_log("Attendance audit skipped: no MP account available for event {$eventId}, cadet {$cadetId}");
                return;
            }

            $normalizedStatus = normalizeAttendanceStatusValue($status);
            $verificationMethod = isset($columns['verification_method']) ? 'manual' : null;
            $qrVerified = isset($columns['qr_code_verified']) ? 1 : null;
            $setCheckoutNow = ($action === 'check_out') ? "NOW()" : "NULL";

            $sql = "
                INSERT INTO event_attendance
                (event_id, cadet_id, mp_id, check_in_time, check_out_time, attendance_status, remarks" .
                    (isset($columns['qr_code_verified']) ? ", qr_code_verified" : "") .
                    (isset($columns['verification_method']) ? ", verification_method" : "") . ")
                VALUES (?, ?, ?, NOW(), {$setCheckoutNow}, ?, ?" .
                    (isset($columns['qr_code_verified']) ? ", ?" : "") .
                    (isset($columns['verification_method']) ? ", ?" : "") . ")
                ON DUPLICATE KEY UPDATE
                    attendance_status = VALUES(attendance_status),
                    remarks = CONCAT(IFNULL(event_attendance.remarks, ''), '; ', VALUES(remarks)),
                    check_in_time = COALESCE(event_attendance.check_in_time, NOW())" .
                    ($action === 'check_out' ? ",
                    check_out_time = NOW()" : "");

            $params = [$eventId, $cadetId, $mpId, $normalizedStatus, $remarks];
            if (isset($columns['qr_code_verified'])) {
                $params[] = $qrVerified;
            }
            if (isset($columns['verification_method'])) {
                $params[] = $verificationMethod;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    } catch (Exception $e) {
        // Audit writes should not block summary updates.
        error_log("Attendance audit write failed: " . $e->getMessage());
    }
}

// Handle status update for individual cadet - FIXED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $cadet_id = intval($_POST['cadet_id'] ?? 0);
    $raw_check_in_status = isset($_POST['check_in_status']) ? trim((string)$_POST['check_in_status']) : '';
    $raw_check_out_status = isset($_POST['check_out_status']) ? trim((string)$_POST['check_out_status']) : '';
    $check_in_status = normalizeSummaryStatusValue($raw_check_in_status);
    $check_out_status = normalizeSummaryStatusValue($raw_check_out_status);
    $remarks = isset($_POST['remarks']) ? trim((string)$_POST['remarks']) : '';

    if ($cadet_id <= 0) {
        $_SESSION['error'] = "Invalid cadet selected";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    if (($raw_check_in_status !== '' && $check_in_status === null) || ($raw_check_out_status !== '' && $check_out_status === null)) {
        $_SESSION['error'] = "Invalid status selected";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    if ($check_in_status === 'absent' && $check_out_status !== null && $check_out_status !== 'absent') {
        $_SESSION['error'] = "Check-out cannot be marked as present/late/excuse when check-in is marked absent";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    if ($check_in_status === null && $check_out_status === null && $remarks === '') {
        $_SESSION['error'] = "No changes were provided";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    try {
        $pdo->beginTransaction();

        $cadet_check = $pdo->prepare("SELECT id FROM cadet_accounts WHERE id = ? AND is_archived = FALSE AND status = 'approved'");
        $cadet_check->execute([$cadet_id]);
        if (!$cadet_check->fetch()) {
            throw new RuntimeException("Selected cadet is invalid or not approved");
        }

        $check_stmt = $pdo->prepare("
            SELECT * 
            FROM event_attendance_summary 
            WHERE event_id = ? AND cadet_id = ?
            FOR UPDATE
        ");
        $check_stmt->execute([$event_id, $cadet_id]);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO event_attendance_summary (event_id, cadet_id, remarks, updated_at)
                VALUES (?, ?, NULL, NOW())
            ");
            $insert_stmt->execute([$event_id, $cadet_id]);

            $check_stmt->execute([$event_id, $cadet_id]);
            $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
        }

        [$event_start, $event_end] = getEventTimeBounds($event);
        $now = date('Y-m-d H:i:s');
        $current_check_in_time = $exists['check_in_time'] ?? null;
        $current_check_out_time = $exists['check_out_time'] ?? null;
        $remarks_log = [];

        if ($check_in_status !== null) {
            if ($check_in_status === 'absent') {
                $current_check_in_time = null;
                if ($check_out_status === null) {
                    $current_check_out_time = null;
                }
            } elseif (!$current_check_in_time) {
                $current_check_in_time = $now;
            }

            $check_in_timestamp = $current_check_in_time ? strtotime($current_check_in_time) : null;
            if ($check_in_status === 'late') {
                $is_late = 1;
            } elseif ($check_in_timestamp) {
                $is_late = ($check_in_timestamp > $event_start) ? 1 : 0;
            } else {
                $is_late = 0;
            }

            $update_stmt = $pdo->prepare("
                UPDATE event_attendance_summary 
                SET check_in_time = :check_in_time,
                    check_in_status = :check_in_status,
                    is_late_check_in = :is_late,
                    updated_at = NOW()
                WHERE event_id = :event_id AND cadet_id = :cadet_id
            ");
            $update_stmt->bindValue(':check_in_time', $current_check_in_time, $current_check_in_time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $update_stmt->bindValue(':check_in_status', $check_in_status, PDO::PARAM_STR);
            $update_stmt->bindValue(':is_late', $is_late, PDO::PARAM_INT);
            $update_stmt->bindValue(':event_id', $event_id, PDO::PARAM_INT);
            $update_stmt->bindValue(':cadet_id', $cadet_id, PDO::PARAM_INT);
            $update_stmt->execute();

            $remarks_log[] = "Admin updated check-in to " . ucfirst($check_in_status) . " on " . $now;
            writeAttendanceAudit($pdo, $event_id, $cadet_id, $check_in_status, "Admin updated check-in via edit", 'check_in');
        }

        if ($check_out_status !== null) {
            if ($check_out_status === 'absent') {
                $current_check_out_time = null;
                $total_duration = 0;
                $is_early = 0;
            } else {
                if (!$current_check_in_time) {
                    $current_check_in_time = $now;
                    $auto_check_in_timestamp = strtotime($current_check_in_time);
                    $auto_is_late = ($auto_check_in_timestamp > $event_start) ? 1 : 0;
                    $auto_check_in_status = $auto_is_late ? 'late' : 'present';

                    $auto_check_in_stmt = $pdo->prepare("
                        UPDATE event_attendance_summary
                        SET check_in_time = :check_in_time,
                            check_in_status = :check_in_status,
                            is_late_check_in = :is_late,
                            updated_at = NOW()
                        WHERE event_id = :event_id AND cadet_id = :cadet_id
                    ");
                    $auto_check_in_stmt->execute([
                        ':check_in_time' => $current_check_in_time,
                        ':check_in_status' => $auto_check_in_status,
                        ':is_late' => $auto_is_late,
                        ':event_id' => $event_id,
                        ':cadet_id' => $cadet_id
                    ]);

                    $remarks_log[] = "Auto-set check-in to " . ucfirst($auto_check_in_status) . " on " . $now;
                }

                if (!$current_check_out_time) {
                    $current_check_out_time = $now;
                }

                $check_in_timestamp = strtotime($current_check_in_time);
                $check_out_timestamp = strtotime($current_check_out_time);
                if ($check_out_timestamp < $check_in_timestamp) {
                    $check_out_timestamp = $check_in_timestamp;
                    $current_check_out_time = date('Y-m-d H:i:s', $check_out_timestamp);
                }

                $total_duration = (int)round(($check_out_timestamp - $check_in_timestamp) / 60);
                $is_early = ($check_out_timestamp < $event_end) ? 1 : 0;
            }

            $update_stmt = $pdo->prepare("
                UPDATE event_attendance_summary 
                SET check_out_time = :check_out_time,
                    check_out_status = :check_out_status,
                    is_early_check_out = :is_early,
                    total_duration_minutes = :duration,
                    updated_at = NOW()
                WHERE event_id = :event_id AND cadet_id = :cadet_id
            ");
            $update_stmt->bindValue(':check_out_time', $current_check_out_time, $current_check_out_time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $update_stmt->bindValue(':check_out_status', $check_out_status, PDO::PARAM_STR);
            $update_stmt->bindValue(':is_early', $is_early, PDO::PARAM_INT);
            $update_stmt->bindValue(':duration', $total_duration, PDO::PARAM_INT);
            $update_stmt->bindValue(':event_id', $event_id, PDO::PARAM_INT);
            $update_stmt->bindValue(':cadet_id', $cadet_id, PDO::PARAM_INT);
            $update_stmt->execute();

            $remarks_log[] = "Admin updated check-out to " . ucfirst($check_out_status) . " on " . $now;
            writeAttendanceAudit($pdo, $event_id, $cadet_id, $check_out_status, "Admin updated check-out via edit", 'check_out');
        }

        if ($remarks !== '') {
            $remarks_log[] = "Admin note: " . $remarks . " on " . $now;
        }

        if (!empty($remarks_log)) {
            $remarks_entry = implode('; ', $remarks_log);
            $remarks_stmt = $pdo->prepare("
                UPDATE event_attendance_summary
                SET remarks = CASE
                        WHEN remarks IS NULL OR remarks = '' THEN :remarks_entry_new
                        ELSE CONCAT(remarks, '; ', :remarks_entry_append)
                    END,
                    updated_at = NOW()
                WHERE event_id = :event_id AND cadet_id = :cadet_id
            ");
            $remarks_stmt->execute([
                ':remarks_entry_new' => $remarks_entry,
                ':remarks_entry_append' => $remarks_entry,
                ':event_id' => $event_id,
                ':cadet_id' => $cadet_id
            ]);
        }

        $pdo->commit();
        $_SESSION['message'] = "Attendance updated successfully";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Failed to update attendance: " . $e->getMessage();
        error_log("Attendance Update Error: " . $e->getMessage());
    }

    header("Location: " . buildEventAttendanceRedirectUrl($event_id));
    exit();
}

// Handle bulk status update for check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $bulk_scope = isset($_POST['bulk_scope']) ? trim((string)$_POST['bulk_scope']) : '';
    $raw_status = isset($_POST['bulk_status']) ? trim((string)$_POST['bulk_status']) : '';
    $status = normalizeSummaryStatusValue($raw_status);
    $selected_cadets = isset($_POST['selected_cadets']) && is_array($_POST['selected_cadets']) ? $_POST['selected_cadets'] : [];

    $allowed_scopes = ['check_in', 'check_out'];
    if (!in_array($bulk_scope, $allowed_scopes, true)) {
        $_SESSION['error'] = "Invalid bulk update target selected";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    if ($status === null) {
        $_SESSION['error'] = "Invalid status selected";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    if (empty($selected_cadets)) {
        $_SESSION['error'] = "Please select at least one cadet";
        header("Location: " . buildEventAttendanceRedirectUrl($event_id));
        exit();
    }

    try {
        $pdo->beginTransaction();

        $success_count = 0;
        $failed_cadets = [];

        [$event_start, $event_end] = getEventTimeBounds($event);
        $current_time = date('Y-m-d H:i:s');
        $scope_label = $bulk_scope === 'check_out' ? 'check-out' : 'check-in';

        foreach ($selected_cadets as $cadet_id_value) {
            $cadet_id = intval($cadet_id_value);
            if ($cadet_id <= 0) {
                $failed_cadets[] = $cadet_id_value;
                continue;
            }

            $cadet_check = $pdo->prepare("SELECT id FROM cadet_accounts WHERE id = ? AND is_archived = FALSE AND status = 'approved'");
            $cadet_check->execute([$cadet_id]);
            if (!$cadet_check->fetch()) {
                $failed_cadets[] = $cadet_id;
                continue;
            }

            $check_summary = $pdo->prepare("
                SELECT *
                FROM event_attendance_summary
                WHERE event_id = ? AND cadet_id = ?
                FOR UPDATE
            ");
            $check_summary->execute([$event_id, $cadet_id]);
            $summary = $check_summary->fetch(PDO::FETCH_ASSOC);

            if (!$summary) {
                $insert_summary = $pdo->prepare("
                    INSERT INTO event_attendance_summary (event_id, cadet_id, remarks, updated_at)
                    VALUES (?, ?, NULL, NOW())
                ");
                $insert_summary->execute([$event_id, $cadet_id]);

                $check_summary->execute([$event_id, $cadet_id]);
                $summary = $check_summary->fetch(PDO::FETCH_ASSOC);
            }

            $check_in_time = $summary['check_in_time'] ?? null;
            $check_out_time = $summary['check_out_time'] ?? null;

            if ($bulk_scope === 'check_in') {
                if ($status === 'absent') {
                    $bulk_stmt = $pdo->prepare("
                        UPDATE event_attendance_summary
                        SET check_in_time = NULL,
                            check_in_status = 'absent',
                            is_late_check_in = 0,
                            check_out_time = NULL,
                            check_out_status = 'absent',
                            is_early_check_out = 0,
                            total_duration_minutes = 0,
                            updated_at = NOW()
                        WHERE event_id = ? AND cadet_id = ?
                    ");
                    $bulk_stmt->execute([$event_id, $cadet_id]);
                } else {
                    if (!$check_in_time) {
                        $check_in_time = $current_time;
                    }

                    $check_in_timestamp = strtotime($check_in_time);
                    if ($status === 'late') {
                        $is_late = 1;
                    } else {
                        $is_late = ($check_in_timestamp > $event_start) ? 1 : 0;
                    }

                    $duration = 0;
                    $is_early = 0;
                    if ($check_out_time) {
                        $check_out_timestamp = strtotime($check_out_time);
                        if ($check_out_timestamp < $check_in_timestamp) {
                            $check_out_timestamp = $check_in_timestamp;
                            $check_out_time = date('Y-m-d H:i:s', $check_out_timestamp);
                        }
                        $duration = (int)round(($check_out_timestamp - $check_in_timestamp) / 60);
                        $is_early = ($check_out_timestamp < $event_end) ? 1 : 0;
                    }

                    $bulk_stmt = $pdo->prepare("
                        UPDATE event_attendance_summary
                        SET check_in_time = ?,
                            check_in_status = ?,
                            is_late_check_in = ?,
                            check_out_time = ?,
                            is_early_check_out = ?,
                            total_duration_minutes = ?,
                            updated_at = NOW()
                        WHERE event_id = ? AND cadet_id = ?
                    ");
                    $bulk_stmt->execute([$check_in_time, $status, $is_late, $check_out_time, $is_early, $duration, $event_id, $cadet_id]);
                }

                writeAttendanceAudit($pdo, $event_id, $cadet_id, $status, "Bulk check-in updated to {$status} by admin", 'check_in');
            } else {
                if ($status === 'absent') {
                    $bulk_stmt = $pdo->prepare("
                        UPDATE event_attendance_summary
                        SET check_out_time = NULL,
                            check_out_status = 'absent',
                            is_early_check_out = 0,
                            total_duration_minutes = 0,
                            updated_at = NOW()
                        WHERE event_id = ? AND cadet_id = ?
                    ");
                    $bulk_stmt->execute([$event_id, $cadet_id]);
                } else {
                    if (!$check_in_time) {
                        $check_in_time = $current_time;
                        $auto_check_in_timestamp = strtotime($check_in_time);
                        $auto_is_late = ($auto_check_in_timestamp > $event_start) ? 1 : 0;
                        $auto_check_in_status = $auto_is_late ? 'late' : 'present';

                        $auto_checkin_stmt = $pdo->prepare("
                            UPDATE event_attendance_summary
                            SET check_in_time = ?,
                                check_in_status = ?,
                                is_late_check_in = ?,
                                updated_at = NOW()
                            WHERE event_id = ? AND cadet_id = ?
                        ");
                        $auto_checkin_stmt->execute([$check_in_time, $auto_check_in_status, $auto_is_late, $event_id, $cadet_id]);
                    }

                    if (!$check_out_time) {
                        $check_out_time = $current_time;
                    }

                    $check_in_timestamp = strtotime($check_in_time);
                    $check_out_timestamp = strtotime($check_out_time);
                    if ($check_out_timestamp < $check_in_timestamp) {
                        $check_out_timestamp = $check_in_timestamp;
                        $check_out_time = date('Y-m-d H:i:s', $check_out_timestamp);
                    }

                    $duration = (int)round(($check_out_timestamp - $check_in_timestamp) / 60);
                    $is_early = ($check_out_timestamp < $event_end) ? 1 : 0;

                    $bulk_stmt = $pdo->prepare("
                        UPDATE event_attendance_summary
                        SET check_out_time = ?,
                            check_out_status = ?,
                            is_early_check_out = ?,
                            total_duration_minutes = ?,
                            updated_at = NOW()
                        WHERE event_id = ? AND cadet_id = ?
                    ");
                    $bulk_stmt->execute([$check_out_time, $status, $is_early, $duration, $event_id, $cadet_id]);
                }

                writeAttendanceAudit($pdo, $event_id, $cadet_id, $status, "Bulk check-out updated to {$status} by admin", 'check_out');
            }

            $remark_entry = "Bulk {$scope_label} updated to " . ucfirst($status) . " by admin on {$current_time}";
            $remark_stmt = $pdo->prepare("
                UPDATE event_attendance_summary
                SET remarks = CASE
                        WHEN remarks IS NULL OR remarks = '' THEN ?
                        ELSE CONCAT(remarks, '; ', ?)
                    END,
                    updated_at = NOW()
                WHERE event_id = ? AND cadet_id = ?
            ");
            $remark_stmt->execute([$remark_entry, $remark_entry, $event_id, $cadet_id]);

            $success_count++;
        }

        $pdo->commit();

        if ($success_count > 0) {
            $_SESSION['message'] = "Bulk {$scope_label} update completed: {$success_count} cadet(s) set to " . ucfirst($status);
            if (!empty($failed_cadets)) {
                $_SESSION['message'] .= " (" . count($failed_cadets) . " failed)";
            }
        } else {
            $_SESSION['error'] = "Bulk update failed. No records were updated.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Failed to bulk update: " . $e->getMessage();
        error_log("Bulk Update Error: " . $e->getMessage());
    }

    header("Location: " . buildEventAttendanceRedirectUrl($event_id));
    exit();
}

// Handle manual check-in - FIXED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_checkin'])) {
    $cadet_id = intval($_POST['cadet_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Check if already checked in
        $check_stmt = $pdo->prepare("SELECT check_in_time FROM event_attendance_summary WHERE event_id = ? AND cadet_id = ?");
        $check_stmt->execute([$event_id, $cadet_id]);
        $existing = $check_stmt->fetch();
        
        if ($existing && $existing['check_in_time']) {
            $_SESSION['error'] = "Cadet already checked in";
            $pdo->rollBack();
        } else {
            // Get event start time to determine if late
            [$event_start, $event_end] = getEventTimeBounds($event);
            $current_timestamp = time();
            $is_late = ($current_timestamp > $event_start) ? 1 : 0;
            
            // Insert/update check-in record in main attendance table
            writeAttendanceAudit($pdo, $event_id, $cadet_id, 'present', 'Manually checked in by admin', 'check_in');
            
            // Update or insert summary
            if ($existing) {
                // Update existing
                $summary_stmt = $pdo->prepare("
                    UPDATE event_attendance_summary 
                    SET check_in_time = NOW(), 
                        check_in_status = 'present',
                        is_late_check_in = ?,
                        remarks = CONCAT(IFNULL(remarks, ''), '; Manually checked in by admin'),
                        updated_at = NOW()
                    WHERE event_id = ? AND cadet_id = ?
                ");
                $summary_stmt->execute([$is_late, $event_id, $cadet_id]);
            } else {
                // Insert new
                $summary_stmt = $pdo->prepare("
                    INSERT INTO event_attendance_summary 
                    (event_id, cadet_id, check_in_time, check_in_status, is_late_check_in, remarks, updated_at) 
                    VALUES (?, ?, NOW(), 'present', ?, 'Manually checked in by admin', NOW())
                ");
                $summary_stmt->execute([$event_id, $cadet_id, $is_late]);
            }
            
            $pdo->commit();
            $_SESSION['message'] = "Cadet manually checked in successfully";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to check in: " . $e->getMessage();
        error_log("Manual Check-in Error: " . $e->getMessage());
    }
    
    header("Location: " . buildEventAttendanceRedirectUrl($event_id));
    exit();
}

// Handle manual check-out - FIXED
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_checkout'])) {
    $cadet_id = intval($_POST['cadet_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Check if checked in
        $check_stmt = $pdo->prepare("SELECT check_in_time, check_out_time FROM event_attendance_summary WHERE event_id = ? AND cadet_id = ?");
        $check_stmt->execute([$event_id, $cadet_id]);
        $existing = $check_stmt->fetch();
        
        if (!$existing || !$existing['check_in_time']) {
            $_SESSION['error'] = "Cadet must check in first";
            $pdo->rollBack();
        } elseif ($existing['check_out_time']) {
            $_SESSION['error'] = "Cadet already checked out";
            $pdo->rollBack();
        } else {
            // Calculate duration
            $check_in = strtotime($existing['check_in_time']);
            $check_out = time();
            $duration = round(($check_out - $check_in) / 60);
            
            // Get event end time to determine if early check-out
            [$event_start, $event_end] = getEventTimeBounds($event);
            $is_early = ($check_out < $event_end) ? 1 : 0;
            
            $remarks = "Manually checked out by admin. Duration: {$duration} minutes";
            writeAttendanceAudit($pdo, $event_id, $cadet_id, 'present', $remarks, 'check_out');
            
            // Update summary
            $update_stmt = $pdo->prepare("
                UPDATE event_attendance_summary 
                SET check_out_time = NOW(), 
                    check_out_status = 'present', 
                    total_duration_minutes = ?,
                    is_early_check_out = ?,
                    remarks = CONCAT(IFNULL(remarks, ''), '; Manually checked out by admin. Duration: {$duration} minutes')
                WHERE event_id = ? AND cadet_id = ?
            ");
            $update_stmt->execute([$duration, $is_early, $event_id, $cadet_id]);
            
            $pdo->commit();
            $_SESSION['message'] = "Cadet manually checked out successfully";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to check out: " . $e->getMessage();
        error_log("Manual Check-out Error: " . $e->getMessage());
    }
    
    header("Location: " . buildEventAttendanceRedirectUrl($event_id));
    exit();
}

// Get filter parameters
$filter_platoon = isset($_GET['platoon']) ? trim((string)$_GET['platoon']) : '';
$filter_company = isset($_GET['company']) ? trim((string)$_GET['company']) : '';
$filter_check_in = isset($_GET['check_in']) ? trim((string)$_GET['check_in']) : '';
$filter_check_out = isset($_GET['check_out']) ? trim((string)$_GET['check_out']) : '';
$filter_search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 25;

$base_from_where_sql = "
    FROM cadet_accounts c
    LEFT JOIN event_attendance_summary eas
        ON c.id = eas.cadet_id AND eas.event_id = ?
    WHERE c.is_archived = FALSE AND c.status = 'approved'
";

$params = [$event_id];

if ($filter_platoon !== '') {
    $base_from_where_sql .= " AND c.platoon = ?";
    $params[] = $filter_platoon;
}

if ($filter_company !== '') {
    $base_from_where_sql .= " AND c.company = ?";
    $params[] = $filter_company;
}

if ($filter_check_in !== '') {
    if ($filter_check_in === 'not_checked_in') {
        $base_from_where_sql .= " AND eas.check_in_time IS NULL";
    } else {
        $base_from_where_sql .= " AND eas.check_in_status = ?";
        $params[] = $filter_check_in;
    }
}

if ($filter_check_out !== '') {
    if ($filter_check_out === 'not_checked_out') {
        $base_from_where_sql .= " AND eas.check_out_time IS NULL AND eas.check_in_time IS NOT NULL";
    } else {
        $base_from_where_sql .= " AND eas.check_out_status = ?";
        $params[] = $filter_check_out;
    }
}

if ($filter_search !== '') {
    $base_from_where_sql .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.username LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_term = "%$filter_search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$order_sql = " ORDER BY
    CASE
        WHEN eas.check_in_time IS NULL THEN 1
        WHEN eas.check_in_status = 'late' THEN 2
        WHEN eas.check_in_status = 'present' THEN 3
        ELSE 4
    END,
    c.platoon,
    c.company,
    c.last_name,
    c.first_name";

// Total filtered rows for pagination and counters
$count_sql = "SELECT COUNT(*) " . $base_from_where_sql;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_records / $records_per_page));
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $records_per_page;

// Paginated attendance rows
$attendance_sql = "
    SELECT
        c.id as cadet_id,
        c.first_name,
        c.last_name,
        c.middle_name,
        c.platoon,
        c.company,
        c.course,
        c.username,
        eas.check_in_time,
        eas.check_out_time,
        COALESCE(eas.check_in_status, 'absent') as check_in_status,
        COALESCE(eas.check_out_status, 'absent') as check_out_status,
        eas.remarks,
        eas.total_duration_minutes,
        eas.is_late_check_in,
        eas.is_early_check_out,
        eas.updated_at as last_updated
    " . $base_from_where_sql .
    $order_sql .
    " LIMIT ? OFFSET ?";

$attendance_stmt = $pdo->prepare($attendance_sql);
$bind_index = 1;
foreach ($params as $param) {
    $attendance_stmt->bindValue($bind_index++, $param);
}
$attendance_stmt->bindValue($bind_index++, $records_per_page, PDO::PARAM_INT);
$attendance_stmt->bindValue($bind_index++, $offset, PDO::PARAM_INT);
$attendance_stmt->execute();
$attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics based on the full filtered dataset (not only current page)
$stats_sql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NOT NULL THEN 1 ELSE 0 END), 0) AS present,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NULL THEN 1 ELSE 0 END), 0) AS absent,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NOT NULL AND COALESCE(eas.check_in_status, 'absent') = 'late' THEN 1 ELSE 0 END), 0) AS late,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NOT NULL AND eas.check_out_time IS NOT NULL THEN 1 ELSE 0 END), 0) AS checked_out,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NOT NULL AND eas.check_out_time IS NULL THEN 1 ELSE 0 END), 0) AS not_checked_out,
        COALESCE(SUM(CASE WHEN eas.check_in_time IS NULL THEN 1 ELSE 0 END), 0) AS not_checked_in
    " . $base_from_where_sql;

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats_row = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stats = [
    'total' => (int) ($stats_row['total'] ?? 0),
    'present' => (int) ($stats_row['present'] ?? 0),
    'absent' => (int) ($stats_row['absent'] ?? 0),
    'late' => (int) ($stats_row['late'] ?? 0),
    'checked_out' => (int) ($stats_row['checked_out'] ?? 0),
    'not_checked_in' => (int) ($stats_row['not_checked_in'] ?? 0),
    'not_checked_out' => (int) ($stats_row['not_checked_out'] ?? 0)
];

$active_filter_params = [];
if ($filter_search !== '') {
    $active_filter_params['search'] = $filter_search;
}
if ($filter_platoon !== '') {
    $active_filter_params['platoon'] = $filter_platoon;
}
if ($filter_company !== '') {
    $active_filter_params['company'] = $filter_company;
}
if ($filter_check_in !== '') {
    $active_filter_params['check_in'] = $filter_check_in;
}
if ($filter_check_out !== '') {
    $active_filter_params['check_out'] = $filter_check_out;
}

$build_page_url = function (int $target_page) use ($event_id, $active_filter_params): string {
    return 'event_attendance.php?' . http_build_query(array_merge(
        ['event_id' => $event_id],
        $active_filter_params,
        ['page' => $target_page]
    ));
};

$page_start_record = $total_records > 0 ? ($offset + 1) : 0;
$page_end_record = $total_records > 0 ? min($offset + $records_per_page, $total_records) : 0;
$max_visible_pages = 5;
$half_visible_pages = intdiv($max_visible_pages, 2);
$pagination_start = max(1, $current_page - $half_visible_pages);
$pagination_end = min($total_pages, $pagination_start + $max_visible_pages - 1);
$pagination_start = max(1, $pagination_end - $max_visible_pages + 1);

// Get unique platoons and companies for filters
$platoons = $pdo->query("SELECT DISTINCT platoon FROM cadet_accounts WHERE platoon IS NOT NULL AND platoon != '' AND is_archived = FALSE ORDER BY platoon")->fetchAll(PDO::FETCH_COLUMN);
$companies = $pdo->query("SELECT DISTINCT company FROM cadet_accounts WHERE company IS NOT NULL AND company != '' AND is_archived = FALSE ORDER BY company")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance - <?php echo htmlspecialchars($event['event_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f3f4f6;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-present {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .status-late {
            background: #fed7aa;
            color: #92400e;
            border: 1px solid #fdba74;
        }
        
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .status-excuse {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .status-not-checked-in {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            color: white;
            padding: 20px;
        }
        
        .table-row:hover {
            background: #f9fafb;
        }
        
        .filter-select, .filter-input {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
        }
        
        .filter-select:focus, .filter-input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: white;
            border: 2px solid #e5e7eb;
            color: #4b5563;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }

        .back-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px 8px 10px;
            border-radius: 12px;
            border: 1px solid #dbe3f0;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            color: #334155;
            font-weight: 600;
            letter-spacing: 0.01em;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: all 0.2s ease;
        }

        .back-nav-btn:hover {
            color: #1e3a8a;
            border-color: #bfdbfe;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -16px rgba(37, 99, 235, 0.85), 0 4px 10px rgba(15, 23, 42, 0.08);
        }

        .back-nav-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2), 0 10px 20px -16px rgba(37, 99, 235, 0.85);
        }

        .back-nav-icon {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        .back-nav-btn:hover .back-nav-icon {
            transform: translateX(-2px);
        }
        
        .avatar-initial {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .main-content {
            margin-left: 16rem;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: 5rem;
        }
        
        @media print {
            .sidebar, .filter-card, .btn-primary, .btn-secondary, .actions, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .table-container {
                box-shadow: none;
            }
            .table-header {
                background: #f3f4f6 !important;
                color: black !important;
            }
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }

        .dialog-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .dialog-icon.info {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .dialog-icon.warning {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .dialog-icon.danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .dialog-icon.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .dialog-confirm-btn {
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            min-width: 116px;
        }

        .dialog-confirm-btn:hover {
            transform: translateY(-1px);
        }

        .dialog-confirm-btn.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .dialog-confirm-btn.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .dialog-confirm-btn.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .dialog-confirm-btn.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 11px;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content min-h-screen transition-all duration-300" id="mainContent">
        <!-- Header with Back Button -->
        <div class="bg-white border-b border-gray-200 px-8 py-6 no-print">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="events.php" class="back-nav-btn" aria-label="Back to Events">
                        <span class="back-nav-icon">
                            <i class="fas fa-arrow-left text-xs"></i>
                        </span>
                        <span>Back to Events</span>
                    </a>
                    <div class="h-6 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Event Attendance</h1>
                        <p class="text-gray-600 mt-1">
                            <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($event['event_name']); ?></span> â€¢ 
                            <?php echo date('F j, Y', strtotime($event['event_date'])); ?> â€¢ 
                            <?php echo date('g:i A', strtotime($event['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button onclick="exportToCSV()" class="btn-secondary flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        Export CSV
                    </button>
                    <button onclick="window.print()" class="btn-secondary flex items-center gap-2">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="px-8 py-6">
            <!-- Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3 animate-fade-in no-print">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <span class="text-green-800"><?php echo htmlspecialchars($_SESSION['message']); ?></span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3 animate-fade-in no-print">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                    <span class="text-red-800"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Total</p>
                        <i class="fas fa-users text-blue-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Present</p>
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['present']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php echo $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100) : 0; ?>%
                    </p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Late</p>
                        <i class="fas fa-clock text-yellow-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['late']; ?></p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Checked Out</p>
                        <i class="fas fa-sign-out-alt text-purple-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-purple-600"><?php echo $stats['checked_out']; ?></p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Not Checked Out</p>
                        <i class="fas fa-hourglass-half text-orange-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['not_checked_out']; ?></p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Not Checked In</p>
                        <i class="fas fa-user-clock text-gray-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-gray-600"><?php echo $stats['not_checked_in']; ?></p>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Absent</p>
                        <i class="fas fa-times-circle text-red-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['absent']; ?></p>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card no-print">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Filter Attendance</h2>
                    <span class="text-sm text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
                        <?php echo number_format($total_records); ?> records found
                    </span>
                </div>
                
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Name or username..."
                               value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Platoon</label>
                        <select name="platoon" class="filter-select">
                            <option value="">All Platoons</option>
                            <?php foreach ($platoons as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" 
                                    <?php echo $filter_platoon == $p ? 'selected' : ''; ?>>
                                    Platoon <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                        <select name="company" class="filter-select">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" 
                                    <?php echo $filter_company == $c ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?> Company
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Check-in</label>
                        <select name="check_in" class="filter-select">
                            <option value="">All</option>
                            <option value="present" <?php echo $filter_check_in == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="late" <?php echo $filter_check_in == 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="excuse" <?php echo $filter_check_in == 'excuse' ? 'selected' : ''; ?>>Excuse</option>
                            <option value="not_checked_in" <?php echo $filter_check_in == 'not_checked_in' ? 'selected' : ''; ?>>Not Checked In</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Check-out</label>
                        <select name="check_out" class="filter-select">
                            <option value="">All</option>
                            <option value="present" <?php echo $filter_check_out == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="late" <?php echo $filter_check_out == 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="excuse" <?php echo $filter_check_out == 'excuse' ? 'selected' : ''; ?>>Excuse</option>
                            <option value="not_checked_out" <?php echo $filter_check_out == 'not_checked_out' ? 'selected' : ''; ?>>Not Checked Out</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-2 items-end">
                        <button type="submit" class="btn-primary flex-1 flex items-center justify-center gap-2">
                            <i class="fas fa-filter"></i>
                            Apply
                        </button>
                        <a href="?event_id=<?php echo $event_id; ?>" class="btn-secondary flex items-center justify-center px-4" title="Reset Filters">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Update Form - FIXED -->
            <form method="POST" action="" id="bulkUpdateForm" class="mb-4 no-print" onsubmit="return validateBulkUpdate(event)">
                <input type="hidden" name="bulk_update" value="1">
                <div class="flex flex-wrap items-center gap-4 p-4 bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="flex items-center gap-2 bg-blue-50 px-3 py-2 rounded-lg">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" 
                               class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                        <label for="selectAll" class="text-sm font-medium text-gray-700">Select All</label>
                    </div>
                    
                    <div class="flex-1 flex flex-wrap items-center gap-4">
                        <select name="bulk_scope" id="bulkScope" required class="filter-select w-48 border-2 focus:ring-2 focus:ring-blue-200">
                            <option value="">Apply To</option>
                            <option value="check_in">Check-in Status</option>
                            <option value="check_out">Check-out Status</option>
                        </select>

                        <select name="bulk_status" id="bulkStatus" required class="filter-select w-48 border-2 focus:ring-2 focus:ring-blue-200">
                            <option value="">Select Status</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excuse">Excuse</option>
                        </select>
                        
                        <button type="submit" id="bulkUpdateBtn" class="btn-primary flex items-center gap-2 px-6">
                            <i class="fas fa-check-double"></i>
                            Apply Bulk Update
                        </button>
                        
                        <span class="text-sm font-medium" id="selectedCount">
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                                0 selected
                            </span>
                        </span>
                    </div>
                </div>
            </form>
            
            <!-- Attendance Table -->
            <div class="table-container">
                <div class="table-header flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Cadet Attendance Records</h3>
                    <div class="flex gap-4 text-sm">
                        <span><i class="fas fa-circle text-green-500 mr-1"></i> Present</span>
                        <span><i class="fas fa-circle text-yellow-500 mr-1"></i> Late</span>
                        <span><i class="fas fa-circle text-red-500 mr-1"></i> Absent</span>
                        <span><i class="fas fa-circle text-blue-500 mr-1"></i> Excuse</span>
                        <span><i class="fas fa-circle text-gray-500 mr-1"></i> Not Checked</span>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left no-print">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Select</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Cadet Information</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Platoon</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Company</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Check-in</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Status</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Check-out</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Status</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Duration</span>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Remarks</span>
                                </th>
                                <th class="px-6 py-3 text-left actions no-print">
                                    <span class="text-xs font-semibold text-gray-600 uppercase">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="11" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-users text-5xl mb-4 text-gray-300"></i>
                                        <p class="text-lg font-medium">No attendance records found</p>
                                        <p class="text-sm mt-2">Try adjusting your filters</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance as $index => $record): ?>
                                    <tr class="table-row animate-fade-in hover:bg-blue-50 transition-colors" style="animation-delay: <?php echo $index * 0.05; ?>s" id="row-<?php echo $record['cadet_id']; ?>">
                                        <td class="px-6 py-4 no-print">
                                            <input type="checkbox" name="selected_cadets[]" value="<?php echo $record['cadet_id']; ?>" 
                                                   form="bulkUpdateForm" class="row-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                                                   onchange="updateSelectedCount()"
                                                   data-name="<?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>">
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="avatar-initial">
                                                    <?php echo strtoupper(substr($record['first_name'], 0, 1) . substr($record['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                        <?php if ($record['middle_name']): ?>
                                                            <?php echo ' ' . htmlspecialchars(substr($record['middle_name'], 0, 1) . '.'); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        @<?php echo htmlspecialchars($record['username']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo htmlspecialchars($record['course']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                                                <?php echo htmlspecialchars($record['platoon'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                                                <?php echo htmlspecialchars($record['company'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($record['check_in_time']): ?>
                                                <div class="font-mono text-sm font-medium text-gray-900">
                                                    <?php echo date('g:i A', strtotime($record['check_in_time'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M j', strtotime($record['check_in_time'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm font-medium">--:-- --</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $status_class = 'status-' . $record['check_in_status'];
                                            $status_icon = [
                                                'present' => 'fa-check-circle',
                                                'late' => 'fa-clock',
                                                'absent' => 'fa-times-circle',
                                                'excuse' => 'fa-file-excel'
                                            ][$record['check_in_status']] ?? 'fa-question-circle';
                                            
                                            if (!$record['check_in_time']) {
                                                $status_class = 'status-not-checked-in';
                                                $status_icon = 'fa-user-clock';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?>"></i>
                                                <?php echo $record['check_in_time'] ? ucfirst($record['check_in_status']) : 'Not Checked'; ?>
                                            </span>
                                            <?php if ($record['is_late_check_in'] && $record['check_in_time']): ?>
                                                <div class="text-xs text-orange-600 mt-1">Late</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($record['check_out_time']): ?>
                                                <div class="font-mono text-sm font-medium text-gray-900">
                                                    <?php echo date('g:i A', strtotime($record['check_out_time'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M j', strtotime($record['check_out_time'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm font-medium">--:-- --</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($record['check_out_time']): ?>
                                                <?php
                                                $out_status_class = 'status-' . $record['check_out_status'];
                                                $out_status_icon = [
                                                    'present' => 'fa-check-circle',
                                                    'late' => 'fa-clock',
                                                    'absent' => 'fa-times-circle',
                                                    'excuse' => 'fa-file-excel'
                                                ][$record['check_out_status']] ?? 'fa-question-circle';
                                                ?>
                                                <span class="status-badge <?php echo $out_status_class; ?>">
                                                    <i class="fas <?php echo $out_status_icon; ?>"></i>
                                                    <?php echo ucfirst($record['check_out_status']); ?>
                                                </span>
                                                <?php if ($record['is_early_check_out']): ?>
                                                    <div class="text-xs text-purple-600 mt-1">Early</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($record['check_in_time']): ?>
                                                    <span class="status-badge status-not-checked-in">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">--</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($record['total_duration_minutes'] > 0): ?>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php 
                                                    $hours = floor($record['total_duration_minutes'] / 60);
                                                    $minutes = $record['total_duration_minutes'] % 60;
                                                    echo $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 max-w-xs">
                                            <?php if ($record['remarks']): ?>
                                                <div class="text-xs text-gray-600 tooltip">
                                                    <?php echo htmlspecialchars(substr($record['remarks'], 0, 30)) . '...'; ?>
                                                    <span class="tooltiptext"><?php echo htmlspecialchars($record['remarks']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">No remarks</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 actions no-print">
                                            <div class="flex items-center gap-2">
                                                <button
                                                        type="button"
                                                        data-record="<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="openEditModal(this)"
                                                        class="w-8 h-8 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-600 flex items-center justify-center transition-colors tooltip"
                                                        title="Edit Attendance">
                                                    <i class="fas fa-edit"></i>
                                                    <span class="tooltiptext">Edit</span>
                                                </button>
                                                
                                                <?php if (!$record['check_in_time']): ?>
                                                    <form method="POST" action="" class="inline" 
                                                          onsubmit="return handleManualAttendanceSubmit(event, 'checkin', <?php echo htmlspecialchars(json_encode($record['first_name'] . ' ' . $record['last_name']), ENT_QUOTES, 'UTF-8'); ?>)">
                                                        <input type="hidden" name="manual_checkin" value="1">
                                                        <input type="hidden" name="cadet_id" value="<?php echo $record['cadet_id']; ?>">
                                                        <button type="submit" class="w-8 h-8 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition-colors tooltip"
                                                                title="Manual Check-in">
                                                            <i class="fas fa-sign-in-alt"></i>
                                                            <span class="tooltiptext">Check In</span>
                                                        </button>
                                                    </form>
                                                <?php elseif (!$record['check_out_time']): ?>
                                                    <form method="POST" action="" class="inline"
                                                          onsubmit="return handleManualAttendanceSubmit(event, 'checkout', <?php echo htmlspecialchars(json_encode($record['first_name'] . ' ' . $record['last_name']), ENT_QUOTES, 'UTF-8'); ?>)">
                                                        <input type="hidden" name="manual_checkout" value="1">
                                                        <input type="hidden" name="cadet_id" value="<?php echo $record['cadet_id']; ?>">
                                                        <button type="submit" class="w-8 h-8 rounded-lg bg-purple-100 hover:bg-purple-200 text-purple-600 flex items-center justify-center transition-colors tooltip"
                                                                title="Manual Check-out">
                                                            <i class="fas fa-sign-out-alt"></i>
                                                            <span class="tooltiptext">Check Out</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Footer with Summary -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between text-sm text-gray-600 gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">Summary:</span>
                            <span class="text-green-600">Present: <?php echo $stats['present']; ?></span>
                            <span class="text-yellow-600">Late: <?php echo $stats['late']; ?></span>
                            <span class="text-purple-600">Checked Out: <?php echo $stats['checked_out']; ?></span>
                            <span class="text-orange-600">Not Checked Out: <?php echo $stats['not_checked_out']; ?></span>
                            <span class="text-red-600">Absent: <?php echo $stats['absent']; ?></span>
                        </div>
                        <div>
                            <span class="font-medium">Last Updated:</span>
                            <span class="ml-2"><?php echo date('F j, Y g:i A'); ?></span>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 no-print">
                        <div class="text-sm text-gray-600">
                            <?php if ($total_records > 0): ?>
                                Showing <span class="font-semibold"><?php echo $page_start_record; ?>-<?php echo $page_end_record; ?></span>
                                of <span class="font-semibold"><?php echo $total_records; ?></span> records
                            <?php else: ?>
                                Showing <span class="font-semibold">0</span> records
                            <?php endif; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <nav class="flex flex-wrap items-center gap-1" aria-label="Cadet Attendance Pagination">
                                <?php if ($current_page > 1): ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url(1)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors">First</a>
                                    <a href="<?php echo htmlspecialchars($build_page_url($current_page - 1)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors">Previous</a>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">First</span>
                                    <span class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">Previous</span>
                                <?php endif; ?>

                                <?php if ($pagination_start > 1): ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url(1)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors">1</a>
                                    <?php if ($pagination_start > 2): ?>
                                        <span class="px-2 text-gray-400">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($page = $pagination_start; $page <= $pagination_end; $page++): ?>
                                    <?php if ($page === $current_page): ?>
                                        <span class="px-3 py-1.5 text-sm border border-blue-600 rounded-lg bg-blue-600 text-white font-semibold" aria-current="page"><?php echo $page; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($build_page_url($page)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors"><?php echo $page; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($pagination_end < $total_pages): ?>
                                    <?php if ($pagination_end < $total_pages - 1): ?>
                                        <span class="px-2 text-gray-400">...</span>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors"><?php echo $total_pages; ?></a>
                                <?php endif; ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?php echo htmlspecialchars($build_page_url($current_page + 1)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors">Next</a>
                                    <a href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white hover:bg-gray-100 transition-colors">Last</a>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">Next</span>
                                    <span class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-100 text-gray-400 cursor-not-allowed">Last</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Attendance Modal - FIXED -->
    <div id="editModal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 no-print">
        <div class="bg-white rounded-xl max-w-md w-full p-6 modal-content shadow-2xl">
            <div class="flex items-center justify-between mb-4 border-b pb-3">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-edit text-blue-600 mr-2"></i>
                    Update Attendance
                </h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" action="" id="editForm" onsubmit="return validateEditAttendance(event)">
                <input type="hidden" name="update_attendance" value="1">
                <input type="hidden" name="cadet_id" id="editCadetId">
                
                <div class="space-y-4">
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                        <label class="block text-sm font-medium text-gray-700">
                            Cadet: <span id="editCadetName" class="font-semibold text-blue-800"></span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                            <p class="text-gray-500 uppercase tracking-wide">Current Check-in</p>
                            <p id="editCurrentCheckIn" class="mt-1 font-semibold text-gray-800">Not Checked</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                            <p class="text-gray-500 uppercase tracking-wide">Current Check-out</p>
                            <p id="editCurrentCheckOut" class="mt-1 font-semibold text-gray-800">Not Checked</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sign-in-alt text-green-600 mr-1"></i>
                            Check-in Status
                        </label>
                        <select name="check_in_status" id="editCheckInStatus" class="filter-select">
                            <option value="">No change</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excuse">Excuse</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sign-out-alt text-red-600 mr-1"></i>
                            Check-out Status
                        </label>
                        <select name="check_out_status" id="editCheckOutStatus" class="filter-select">
                            <option value="">No change</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excuse">Excuse</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-comment text-gray-600 mr-1"></i>
                            Remarks
                        </label>
                        <textarea name="remarks" id="editRemarks" rows="3" 
                                  class="filter-input" placeholder="Add remarks..."></textarea>
                    </div>
                    
                    <div class="bg-blue-50 p-3 rounded-lg text-xs text-blue-700 border border-blue-100">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> Status changes will create audit records and update timestamps.
                        Leave fields empty if you don't want to change them.
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeEditModal()" 
                            class="flex-1 btn-secondary py-3">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 btn-primary py-3">
                        <i class="fas fa-save mr-2"></i>
                        Update Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Dialog Modal -->
    <div id="dialogModal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 no-print" role="dialog" aria-modal="true" aria-labelledby="dialogTitle">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 modal-content shadow-2xl border border-gray-100 mx-4">
            <div class="flex items-start gap-4">
                <div id="dialogIconWrap" class="dialog-icon info">
                    <i id="dialogIconSymbol" class="fas fa-circle-info"></i>
                </div>
                <div class="flex-1">
                    <h3 id="dialogTitle" class="text-lg font-semibold text-gray-900">Notice</h3>
                    <p id="dialogMessage" class="mt-2 text-sm text-gray-600 leading-relaxed whitespace-pre-line"></p>
                </div>
                <button type="button" onclick="closeDialog(false)" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" id="dialogCancelBtn" onclick="closeDialog(false)" class="btn-secondary">Cancel</button>
                <button type="button" id="dialogConfirmBtn" onclick="closeDialog(true)" class="dialog-confirm-btn info">Confirm</button>
            </div>
        </div>
    </div>
    
    <script>
        let dialogResolver = null;

        function isModalVisible(modalElement) {
            return modalElement && !modalElement.classList.contains('hidden');
        }

        function syncBodyScrollLock() {
            const editModal = document.getElementById('editModal');
            const dialogModal = document.getElementById('dialogModal');
            const shouldLock = isModalVisible(editModal) || isModalVisible(dialogModal);
            document.body.style.overflow = shouldLock ? 'hidden' : 'auto';
        }

        function showDialog(options) {
            const modal = document.getElementById('dialogModal');
            const iconWrap = document.getElementById('dialogIconWrap');
            const iconSymbol = document.getElementById('dialogIconSymbol');
            const titleElement = document.getElementById('dialogTitle');
            const messageElement = document.getElementById('dialogMessage');
            const cancelBtn = document.getElementById('dialogCancelBtn');
            const confirmBtn = document.getElementById('dialogConfirmBtn');

            const mode = options.mode || 'alert';
            const tone = options.tone || 'info';
            const toneIcons = {
                info: 'fa-circle-info',
                warning: 'fa-triangle-exclamation',
                danger: 'fa-circle-xmark',
                success: 'fa-circle-check'
            };

            if (dialogResolver) {
                const previousResolver = dialogResolver;
                dialogResolver = null;
                previousResolver(false);
            }

            titleElement.textContent = options.title || (mode === 'confirm' ? 'Please Confirm' : 'Notice');
            messageElement.textContent = options.message || '';
            confirmBtn.textContent = options.confirmText || (mode === 'confirm' ? 'Confirm' : 'OK');
            cancelBtn.textContent = options.cancelText || 'Cancel';

            iconWrap.className = 'dialog-icon ' + tone;
            iconSymbol.className = 'fas ' + (toneIcons[tone] || toneIcons.info);
            confirmBtn.className = 'dialog-confirm-btn ' + tone;
            cancelBtn.classList.toggle('hidden', mode !== 'confirm');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            syncBodyScrollLock();

            setTimeout(() => confirmBtn.focus(), 0);

            return new Promise((resolve) => {
                dialogResolver = resolve;
            });
        }

        function closeDialog(result) {
            const modal = document.getElementById('dialogModal');
            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            syncBodyScrollLock();

            if (dialogResolver) {
                const currentResolver = dialogResolver;
                dialogResolver = null;
                currentResolver(Boolean(result));
            }
        }

        function showAlertModal(title, message, tone = 'info') {
            return showDialog({
                mode: 'alert',
                title,
                message,
                tone,
                confirmText: 'OK'
            });
        }

        function showConfirmModal(title, message, tone, confirmText, cancelText = 'Cancel') {
            return showDialog({
                mode: 'confirm',
                title,
                message,
                tone,
                confirmText,
                cancelText
            });
        }

        // Sidebar state management
        function updateMainContentMargin() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar && mainContent) {
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.add('sidebar-collapsed');
                } else {
                    mainContent.classList.remove('sidebar-collapsed');
                }
            }
        }
        
        // Toggle select all checkboxes
        function toggleSelectAll(source) {
            const checkboxes = document.getElementsByClassName('row-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            updateSelectedCount();
        }
        
        // Update selected count
        function updateSelectedCount() {
            const checkboxes = document.getElementsByClassName('row-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            const bulkScope = document.getElementById('bulkScope');
            const bulkStatus = document.getElementById('bulkStatus');
            const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
            let count = 0;
            
            for (let i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) count++;
            }
            
            // Update visible count with badge
            selectedCount.innerHTML = `<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">${count} selected</span>`;
            
            // Update select all checkbox
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = count === checkboxes.length && checkboxes.length > 0;
                selectAll.indeterminate = count > 0 && count < checkboxes.length;
            }
            
            // Enable/disable bulk update button based on selection and status
            if (bulkUpdateBtn && bulkStatus && bulkScope) {
                if (count > 0 && bulkScope.value && bulkStatus.value) {
                    bulkUpdateBtn.disabled = false;
                    bulkUpdateBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    bulkUpdateBtn.disabled = true;
                    bulkUpdateBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        }
        
        // Validate bulk update before submission
        function validateBulkUpdate(event) {
            if (event) {
                event.preventDefault();
            }

            const checkboxes = document.getElementsByClassName('row-checkbox');
            const bulkScope = document.getElementById('bulkScope');
            const bulkStatus = document.getElementById('bulkStatus');
            let count = 0;
            
            for (let i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) count++;
            }
            
            if (count === 0) {
                showAlertModal(
                    'No Cadets Selected',
                    'Please select at least one cadet to update.',
                    'warning'
                );
                return false;
            }
            
            if (!bulkScope.value) {
                showAlertModal(
                    'Target Required',
                    'Please choose whether to update check-in or check-out status.',
                    'warning'
                );
                bulkScope.focus();
                return false;
            }

            if (!bulkStatus.value) {
                showAlertModal(
                    'Status Required',
                    'Please select a status to apply.',
                    'warning'
                );
                bulkStatus.focus();
                return false;
            }
            
            const scopeText = bulkScope.options[bulkScope.selectedIndex].text;
            const statusText = bulkStatus.options[bulkStatus.selectedIndex].text;
            showConfirmModal(
                'Confirm Bulk Update',
                `Are you sure you want to set ${scopeText.toLowerCase()} to "${statusText}" for ${count} selected cadet(s)?\n\nThis will update attendance records and create audit logs.`,
                'warning',
                'Update Attendance'
            ).then((confirmed) => {
                if (confirmed) {
                    document.getElementById('bulkUpdateForm').submit();
                }
            });

            return false;
        }

        function handleManualAttendanceSubmit(event, actionType, cadetName) {
            if (event) {
                event.preventDefault();
            }

            const form = event.currentTarget || event.target;
            const isCheckIn = actionType === 'checkin';
            const actionText = isCheckIn ? 'check in' : 'check out';

            showConfirmModal(
                isCheckIn ? 'Confirm Manual Check-in' : 'Confirm Manual Check-out',
                `Are you sure you want to manually ${actionText} ${cadetName}?`,
                isCheckIn ? 'success' : 'warning',
                isCheckIn ? 'Check In' : 'Check Out'
            ).then((confirmed) => {
                if (confirmed && form) {
                    form.submit();
                }
            });

            return false;
        }
        
        // Edit modal functions - FIXED
        function openEditModal(trigger) {
            let record = null;

            if (trigger && trigger.dataset && trigger.dataset.record) {
                try {
                    record = JSON.parse(trigger.dataset.record);
                } catch (error) {
                    console.error('Invalid edit payload:', error);
                }
            } else if (trigger && typeof trigger === 'object') {
                // Backward compatibility with old inline object usage.
                record = trigger;
            }

            if (!record || !record.cadet_id) {
                showAlertModal(
                    'Unable to Open Edit Form',
                    'The attendance record payload is invalid. Please refresh and try again.',
                    'danger'
                );
                return;
            }

            const checkInText = record.check_in_time
                ? `${record.check_in_status ? record.check_in_status.charAt(0).toUpperCase() + record.check_in_status.slice(1) : 'Present'} (${record.check_in_time})`
                : 'Not Checked';
            const checkOutText = record.check_out_time
                ? `${record.check_out_status ? record.check_out_status.charAt(0).toUpperCase() + record.check_out_status.slice(1) : 'Present'} (${record.check_out_time})`
                : 'Not Checked';

            document.getElementById('editCadetId').value = record.cadet_id;
            document.getElementById('editCadetName').textContent = record.first_name + ' ' + record.last_name;
            document.getElementById('editCurrentCheckIn').textContent = checkInText;
            document.getElementById('editCurrentCheckOut').textContent = checkOutText;

            // Reset selects to default "No change" option
            document.getElementById('editCheckInStatus').value = '';
            document.getElementById('editCheckOutStatus').value = '';

            // Set current remarks for quick editing.
            document.getElementById('editRemarks').value = record.remarks || '';

            // Show modal
            const editModal = document.getElementById('editModal');
            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
            syncBodyScrollLock();
        }

        function validateEditAttendance(event) {
            if (event) {
                event.preventDefault();
            }

            const form = document.getElementById('editForm');
            const checkInStatus = document.getElementById('editCheckInStatus').value;
            const checkOutStatus = document.getElementById('editCheckOutStatus').value;
            const remarks = document.getElementById('editRemarks').value.trim();

            if (!checkInStatus && !checkOutStatus && !remarks) {
                showAlertModal(
                    'No Changes Detected',
                    'Select a check-in/check-out status or enter remarks before updating.',
                    'warning'
                );
                return false;
            }

            const changes = [];
            if (checkInStatus) {
                changes.push(`Check-in -> ${checkInStatus}`);
            }
            if (checkOutStatus) {
                changes.push(`Check-out -> ${checkOutStatus}`);
            }
            if (remarks) {
                changes.push('Remarks update');
            }

            showConfirmModal(
                'Confirm Attendance Update',
                `Apply the following update?\n\n${changes.join('\n')}`,
                'warning',
                'Update Attendance'
            ).then((confirmed) => {
                if (confirmed && form) {
                    form.submit();
                }
            });

            return false;
        }
        
        function closeEditModal() {
            const editModal = document.getElementById('editModal');
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
            syncBodyScrollLock();
        }
        
        // Export to CSV
        function exportToCSV() {
            const rows = [];
            
            // Add headers
            rows.push([
                'Cadet Name',
                'Username',
                'Platoon',
                'Company',
                'Course',
                'Check-in Time',
                'Check-in Status',
                'Is Late',
                'Check-out Time',
                'Check-out Status',
                'Is Early',
                'Duration (minutes)',
                'Remarks'
            ]);
            
            // Add data
            <?php foreach ($attendance as $record): ?>
            rows.push([
                '<?php echo addslashes($record['first_name'] . ' ' . $record['last_name']); ?>',
                '<?php echo addslashes($record['username']); ?>',
                '<?php echo addslashes($record['platoon']); ?>',
                '<?php echo addslashes($record['company']); ?>',
                '<?php echo addslashes($record['course']); ?>',
                '<?php echo $record['check_in_time'] ?: ''; ?>',
                '<?php echo $record['check_in_status']; ?>',
                '<?php echo $record['is_late_check_in'] ? 'Yes' : 'No'; ?>',
                '<?php echo $record['check_out_time'] ?: ''; ?>',
                '<?php echo $record['check_out_status']; ?>',
                '<?php echo $record['is_early_check_out'] ? 'Yes' : 'No'; ?>',
                '<?php echo $record['total_duration_minutes'] ?: 0; ?>',
                '<?php echo addslashes(str_replace(["\r", "\n"], ' ', $record['remarks'] ?: '')); ?>'
            ]);
            <?php endforeach; ?>
            
            // Convert to CSV
            const csvContent = rows.map(row => row.map(cell => 
                typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n')) 
                    ? `"${cell.replace(/"/g, '""')}"` 
                    : cell
            ).join(',')).join('\n');
            
            // Download
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_<?php echo $event_id; ?>_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const dialogModal = document.getElementById('dialogModal');

            if (event.target === dialogModal) {
                closeDialog(false);
                return;
            }

            if (event.target === editModal) {
                closeEditModal();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const dialogModal = document.getElementById('dialogModal');
                const editModal = document.getElementById('editModal');

                if (isModalVisible(dialogModal)) {
                    closeDialog(false);
                } else if (isModalVisible(editModal)) {
                    closeEditModal();
                }
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            updateMainContentMargin();
            
            // Observe sidebar class changes
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            updateMainContentMargin();
                        }
                    });
                });
                observer.observe(sidebar, { attributes: true });
            }
            
            // Add animation to table rows
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                setTimeout(() => {
                    row.style.opacity = '1';
                }, index * 50);
            });
            
            // Initialize bulk update button state
            const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
            const bulkScope = document.getElementById('bulkScope');
            const bulkStatus = document.getElementById('bulkStatus');
            if (bulkUpdateBtn && bulkStatus && bulkScope) {
                bulkUpdateBtn.disabled = true;
                bulkUpdateBtn.classList.add('opacity-50', 'cursor-not-allowed');
                
                bulkScope.addEventListener('change', function() {
                    updateSelectedCount();
                });

                bulkStatus.addEventListener('change', function() {
                    updateSelectedCount();
                });
            }
            
            // Add event listeners to row checkboxes
            const checkboxes = document.getElementsByClassName('row-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].addEventListener('change', updateSelectedCount);
            }
        });
    </script>
</body>
</html>
