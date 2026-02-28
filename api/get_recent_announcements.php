// api/get_recent_announcements.php
<?php
require_once '../config/database.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = getDBConnection();
    
    // Get current date and time
    $currentDateTime = date('Y-m-d H:i:s');
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Get recent announcements (last 7 days) that are published
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
              WHERE a.status = 'published' 
                AND a.announcement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND a.expires_at > :current_datetime
                AND (a.announcement_date > :current_date 
                     OR (a.announcement_date = :current_date AND a.end_time > :current_time))
              ORDER BY 
                CASE a.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                a.announcement_date DESC,
                a.created_at DESC
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':current_datetime' => $currentDateTime,
        ':current_date' => $currentDate,
        ':current_time' => $currentTime
    ]);
    
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for Flutter
    $formattedAnnouncements = array_map(function($ann) {
        return [
            'id' => $ann['id'],
            'title' => $ann['title'],
            'content' => $ann['content'],
            'announcement_date' => $ann['announcement_date'],
            'announcement_time' => $ann['announcement_time'],
            'end_time' => $ann['end_time'],
            'location' => $ann['location'],
            'dress_code' => $ann['dress_code'],
            'notes' => $ann['notes'],
            'target_audience' => $ann['target_audience'],
            'priority' => $ann['priority'],
            'status' => $ann['status'],
            'created_at' => $ann['created_at'],
            'published_at' => $ann['published_at'],
            'expires_at' => $ann['expires_at'],
            'created_by_name' => $ann['created_by_name'] ?? 'Admin',
            'time_formatted' => date('Hi', strtotime($ann['announcement_time'])) . 'H-' . date('Hi', strtotime($ann['end_time'])) . 'H',
            'date_formatted' => date('F j, Y', strtotime($ann['announcement_date']))
        ];
    }, $announcements);
    
    echo json_encode([
        'success' => true,
        'announcements' => $formattedAnnouncements,
        'count' => count($formattedAnnouncements)
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