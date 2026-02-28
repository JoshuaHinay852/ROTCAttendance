<?php
// cron/auto_mark_absent.php
// Run this script daily to auto-mark absent cadets

require_once '../config/database.php';

// Get settings
$stmt = $pdo->query("SELECT setting_value FROM attendance_settings WHERE setting_key = 'auto_mark_absent_hours'");
$hours = $stmt->fetchColumn() ?: 24;

// Find events that ended more than X hours ago
$stmt = $pdo->prepare("
    SELECT e.id, e.event_date, e.end_time
    FROM events e
    WHERE e.deleted_at IS NULL 
      AND e.status != 'cancelled'
      AND TIMESTAMP(e.event_date, e.end_time) < DATE_SUB(NOW(), INTERVAL ? HOUR)
");
$stmt->execute([$hours]);
$events = $stmt->fetchAll();

foreach ($events as $event) {
    // Mark absent cadets
    $pdo->prepare("
        INSERT INTO event_attendance_summary (event_id, cadet_id, check_in_status)
        SELECT ?, c.id, 'absent'
        FROM cadet_accounts c
        WHERE c.is_archived = FALSE 
          AND c.status = 'approved'
          AND NOT EXISTS (
              SELECT 1 FROM event_attendance_summary eas 
              WHERE eas.event_id = ? AND eas.cadet_id = c.id
          )
    ")->execute([$event['id'], $event['id']]);
}

echo "Auto-marked absent for " . count($events) . " events\n";