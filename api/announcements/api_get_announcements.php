<?php
// /api/api_get_announcements.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // Get parameters
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user_type = isset($_GET['user_type']) ? $_GET['user_type'] : ''; // cadet or mp
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : 'published'; // published, all, upcoming
    
    // Validate user type
    if (!in_array($user_type, ['cadet', 'mp'])) {
        throw new Exception('Invalid user type. Must be "cadet" or "mp"');
    }
    
    // Build query based on user type
    $query = "
        SELECT 
            a.id,
            a.title,
            a.content,
            a.announcement_date as date,
            a.announcement_time as start_time,
            a.end_time,
            a.location,
            a.dress_code,
            a.notes,
            a.priority,
            a.target_audience,
            a.status,
            a.created_at,
            a.published_at,
            a.expires_at,
            CASE 
                WHEN a.announcement_date = CURDATE() THEN 'today'
                WHEN a.announcement_date > CURDATE() THEN 'upcoming'
                ELSE 'past'
            END as time_category,
            CASE 
                WHEN arl.id IS NOT NULL THEN 1
                ELSE 0
            END as is_read,
            arl.read_at as read_time
        FROM announcements a
        LEFT JOIN announcement_read_logs arl ON a.id = arl.announcement_id 
            AND arl.user_id = :user_id 
            AND arl.user_type = :user_type
        WHERE a.status = 'published'
        AND a.expires_at > NOW()
    ";
    
    $params = [
        ':user_id' => $user_id,
        ':user_type' => $user_type
    ];
    
    // Filter by target audience
    if ($user_type === 'cadet') {
        $query .= " AND (a.target_audience IN ('all', 'cadets', 'both'))";
    } elseif ($user_type === 'mp') {
        $query .= " AND (a.target_audience IN ('all', 'mp', 'both'))";
    }
    
    // Filter by status/time
    if ($status === 'upcoming') {
        $query .= " AND a.announcement_date >= CURDATE()";
    } elseif ($status === 'today') {
        $query .= " AND a.announcement_date = CURDATE()";
    } elseif ($status === 'past') {
        $query .= " AND a.announcement_date < CURDATE()";
    }
    
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
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_type', $user_type, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    
    // Format for mobile app
    $formatted_announcements = [];
    foreach ($announcements as $ann) {
        $formatted_announcements[] = [
            'id' => $ann['id'],
            'title' => $ann['title'],
            'content' => $ann['content'],
            'date' => $ann['date'],
            'formatted_date' => date('j F Y', strtotime($ann['date'])),
            'start_time' => $ann['start_time'],
            'end_time' => $ann['end_time'],
            'formatted_time' => date('Hi', strtotime($ann['start_time'])) . 'H-' . date('Hi', strtotime($ann['end_time'])) . 'H',
            'location' => $ann['location'],
            'dress_code' => $ann['dress_code'],
            'notes' => $ann['notes'],
            'priority' => $ann['priority'],
            'target_audience' => $ann['target_audience'],
            'status' => $ann['status'],
            'time_category' => $ann['time_category'],
            'is_read' => (bool)$ann['is_read'],
            'read_time' => $ann['read_time'],
            'created_at' => $ann['created_at'],
            'published_at' => $ann['published_at'],
            'expires_at' => $ann['expires_at'],
            'days_remaining' => max(0, floor((strtotime($ann['expires_at']) - time()) / (60 * 60 * 24))),
            'is_expired' => strtotime($ann['expires_at']) < time()
        ];
    }
    
    // Get unread count
    $unread_query = "
        SELECT COUNT(*) as unread_count
        FROM announcements a
        WHERE a.status = 'published'
        AND a.expires_at > NOW()
        AND NOT EXISTS (
            SELECT 1 FROM announcement_read_logs arl 
            WHERE arl.announcement_id = a.id 
            AND arl.user_id = :user_id 
            AND arl.user_type = :user_type
        )
    ";
    
    if ($user_type === 'cadet') {
        $unread_query .= " AND (a.target_audience IN ('all', 'cadets', 'both'))";
    } elseif ($user_type === 'mp') {
        $unread_query .= " AND (a.target_audience IN ('all', 'mp', 'both'))";
    }
    
    $stmt = $pdo->prepare($unread_query);
    $stmt->execute([':user_id' => $user_id, ':user_type' => $user_type]);
    $unread_count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'announcements' => $formatted_announcements,
            'meta' => [
                'total' => count($formatted_announcements),
                'unread_count' => $unread_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => count($formatted_announcements) >= $limit,
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'ANNOUNCEMENTS_ERROR'
    ]);
}