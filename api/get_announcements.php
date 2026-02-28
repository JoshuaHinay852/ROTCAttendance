// api/get_announcements.php
<?php
require_once '../config/database.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = getDBConnection();
    
    // Optional filters from query parameters
    $targetAudience = isset($_GET['target_audience']) ? $_GET['target_audience'] : null;
    $priority = isset($_GET['priority']) ? $_GET['priority'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $currentDateTime = date('Y-m-d H:i:s');
    
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
              WHERE a.status = 'published'";
    
    $params = [];
    
    // Add target audience filter
    if ($targetAudience && $targetAudience !== 'all') {
        if ($targetAudience === 'both') {
            $query .= " AND (a.target_audience = 'both' OR a.target_audience = 'all')";
        } else {
            $query .= " AND (a.target_audience = :target_audience OR a.target_audience = 'all')";
            $params[':target_audience'] = $targetAudience;
        }
    }
    
    // Add priority filter
    if ($priority && $priority !== 'all') {
        $query .= " AND a.priority = :priority";
        $params[':priority'] = $priority;
    }
    
    // Add expiration check
    $query .= " AND a.expires_at > :current_datetime";
    $params[':current_datetime'] = $currentDateTime;
    
    // Order by priority and date
    $query .= " ORDER BY 
                CASE a.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                a.announcement_date DESC,
                a.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    
    $stmt = $pdo->prepare($query);
    
    // Bind parameters with proper types
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM announcements WHERE status = 'published' AND expires_at > :current_datetime";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute([':current_datetime' => $currentDateTime]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
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
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset,
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