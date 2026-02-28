<?php
// api_event_stats.php - API for real-time event statistics
require_once '../config/database.php';
require_once '../includes/event_status_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    ensureEventStatusesAreCurrent($pdo);

    // Get real-time statistics
    $stats = [];
    
    // Today's events
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing
        FROM events 
        WHERE event_date = CURDATE()
    ");
    $stmt->execute();
    $today_stats = $stmt->fetch();
    
    // Upcoming events (next 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as upcoming 
        FROM events 
        WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND status = 'scheduled'
    ");
    $stmt->execute();
    $upcoming = $stmt->fetchColumn();
    
    // Recent attendance
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT cadet_id) as recent_cadets,
            COUNT(*) as recent_checkins
        FROM event_attendance 
        WHERE DATE(check_in_time) = CURDATE()
    ");
    $stmt->execute();
    $recent_attendance = $stmt->fetch();
    
    // Total statistics
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM events WHERE status = 'completed') as completed_events,
            (SELECT COUNT(DISTINCT cadet_id) FROM event_attendance) as total_cadets_attended,
            (SELECT COUNT(*) FROM event_attendance) as total_checkins
    ");
    $total_stats = $stmt->fetch();
    
    // Top event today
    $stmt = $pdo->prepare("
        SELECT 
            e.event_name,
            COUNT(DISTINCT ea.cadet_id) as cadets_attended
        FROM events e
        LEFT JOIN event_attendance ea ON e.id = ea.event_id
        WHERE e.event_date = CURDATE() 
        AND e.status IN ('ongoing', 'scheduled')
        GROUP BY e.id
        ORDER BY cadets_attended DESC
        LIMIT 1
    ");
    $stmt->execute();
    $top_event = $stmt->fetch();
    
    $response = [
        'success' => true,
        'data' => [
            'today' => [
                'total_events' => $today_stats['total'] ?? 0,
                'scheduled' => $today_stats['scheduled'] ?? 0,
                'ongoing' => $today_stats['ongoing'] ?? 0
            ],
            'upcoming' => $upcoming,
            'recent_attendance' => [
                'unique_cadets' => $recent_attendance['recent_cadets'] ?? 0,
                'checkins' => $recent_attendance['recent_checkins'] ?? 0
            ],
            'totals' => [
                'completed_events' => $total_stats['completed_events'] ?? 0,
                'cadets_attended' => $total_stats['total_cadets_attended'] ?? 0,
                'total_checkins' => $total_stats['total_checkins'] ?? 0
            ],
            'top_event_today' => $top_event ? [
                'name' => $top_event['event_name'],
                'cadets_attended' => $top_event['cadets_attended']
            ] : null,
            'server_time' => date('Y-m-d H:i:s'),
            'generated_at' => time()
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
