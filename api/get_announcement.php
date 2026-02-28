// api/get_announcement.php
<?php
require_once '../config/database.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get announcement ID from query parameter
    $announcementId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($announcementId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid announcement ID'
        ]);
        exit;
    }
    
    $pdo = getDBConnection();
    
    $query = "SELECT 
                a.id,
                a.title,
                a.content,
                a.announcement_date,
                a.announcement_time,
                a.end_time,
                a.location,
                a.dress_code,
                a.notes,
                a.target_audience,
                a.priority,
                a.status,
                a.created_at,
                a.published_at,
                a.expires_at,
                ad.full_name as created_by_name
              FROM announcements a
              LEFT JOIN admins ad ON a.created_by = ad.id
              WHERE a.id = :id AND a.status = 'published'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $announcementId]);
    
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$announcement) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Announcement not found'
        ]);
        exit;
    }
    
    // Format the data for Flutter
    $formattedAnnouncement = [
        'id' => $announcement['id'],
        'title' => $announcement['title'],
        'content' => $announcement['content'],
        'announcement_date' => $announcement['announcement_date'],
        'announcement_time' => $announcement['announcement_time'],
        'end_time' => $announcement['end_time'],
        'location' => $announcement['location'],
        'dress_code' => $announcement['dress_code'],
        'notes' => $announcement['notes'],
        'target_audience' => $announcement['target_audience'],
        'priority' => $announcement['priority'],
        'status' => $announcement['status'],
        'created_at' => $announcement['created_at'],
        'published_at' => $announcement['published_at'],
        'expires_at' => $announcement['expires_at'],
        'created_by_name' => $announcement['created_by_name'] ?? 'Admin',
        'time_formatted' => date('Hi', strtotime($announcement['announcement_time'])) . 'H-' . date('Hi', strtotime($announcement['end_time'])) . 'H',
        'date_formatted' => date('F j, Y', strtotime($announcement['announcement_date']))
    ];
    
    // Log that this announcement was read (for statistics)
    try {
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $userType = isset($_GET['user_type']) ? $_GET['user_type'] : 'cadet';
        
        if ($userId > 0) {
            $logStmt = $pdo->prepare("
                INSERT INTO announcement_read_logs (announcement_id, user_id, user_type, read_at) 
                VALUES (:announcement_id, :user_id, :user_type, NOW())
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            $logStmt->execute([
                ':announcement_id' => $announcementId,
                ':user_id' => $userId,
                ':user_type' => $userType
            ]);
        }
    } catch (Exception $e) {
        // Silently fail - don't block the response for logging errors
    }
    
    echo json_encode([
        'success' => true,
        'announcement' => $formattedAnnouncement
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>