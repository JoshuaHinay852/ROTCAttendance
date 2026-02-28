<?php
// /api/api_announcement_stats.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    // Get overall statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_announcements,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN announcement_date = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN announcement_date > CURDATE() THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN announcement_date < CURDATE() THEN 1 ELSE 0 END) as past
        FROM announcements
    ";
    
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch();
    
    // Get read statistics
    $read_stats_query = "
        SELECT 
            COUNT(DISTINCT arl.announcement_id) as announcements_with_reads,
            COUNT(arl.id) as total_reads,
            COUNT(DISTINCT CASE WHEN arl.user_type = 'cadet' THEN arl.user_id END) as unique_cadet_readers,
            COUNT(DISTINCT CASE WHEN arl.user_type = 'mp' THEN arl.user_id END) as unique_mp_readers
        FROM announcement_read_logs arl
        JOIN announcements a ON arl.announcement_id = a.id
        WHERE a.status = 'published'
    ";
    
    $stmt = $pdo->query($read_stats_query);
    $read_stats = $stmt->fetch();
    
    // Get recent announcements (last 7 days)
    $recent_query = "
        SELECT 
            a.id,
            a.title,
            a.announcement_date,
            a.priority,
            COUNT(arl.id) as read_count
        FROM announcements a
        LEFT JOIN announcement_read_logs arl ON a.id = arl.announcement_id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND a.status = 'published'
        GROUP BY a.id, a.title, a.announcement_date, a.priority
        ORDER BY a.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($recent_query);
    $recent = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'overall' => $stats,
            'read_stats' => $read_stats,
            'recent_announcements' => $recent,
            'server_time' => date('Y-m-d H:i:s'),
            'meta' => [
                'generated_at' => time(),
                'version' => '1.0'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'STATS_ERROR'
    ]);
}