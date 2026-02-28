<?php
// cron/expire_announcements.php - Run daily via cron
require_once '../config/database.php';

try {
    // Auto-expire announcements that are past their expiry date
    $stmt = $pdo->prepare("
        UPDATE announcements 
        SET status = 'expired', 
            updated_at = NOW()
        WHERE status = 'published'
        AND expires_at <= NOW()
        AND status != 'expired'
    ");
    
    $affected = $stmt->execute();
    
    error_log("Auto-expired announcements: " . $affected . " rows affected");
    
    // Also update expired status for past events
    $stmt = $pdo->prepare("
        UPDATE announcements 
        SET status = 'expired'
        WHERE status = 'published'
        AND announcement_date < CURDATE()
        AND expires_at IS NULL
    ");
    
    $stmt->execute();
    
    echo "Announcement expiration completed at " . date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    error_log("Announcement expiration error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}