<?php
// /api/api_get_events.php
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
require_once '../includes/event_status_helper.php';
header('Content-Type: application/json');

try {
    ensureEventStatusesAreCurrent($pdo);

    // Get parameters
    $cadet_id = isset($_GET['cadet_id']) ? intval($_GET['cadet_id']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : 'upcoming'; // upcoming, ongoing, all
    
    // Build query based on parameters
    $query = "
        SELECT 
            e.id,
            e.event_name,
            e.description,
            e.event_date,
            e.start_time,
            e.end_time,
            e.location,
            e.status,
            e.qr_code_path,
            CASE 
                WHEN e.event_date = CURDATE() THEN 'today'
                WHEN e.event_date > CURDATE() THEN 'upcoming'
                ELSE 'past'
            END as time_category,
            COUNT(DISTINCT ea.cadet_id) as attendees_count,
            EXISTS(
                SELECT 1 FROM event_attendance ea2 
                WHERE ea2.event_id = e.id AND ea2.cadet_id = :cadet_id
            ) as has_attended
        FROM events e
        LEFT JOIN event_attendance ea ON e.id = ea.event_id
        WHERE e.status != 'cancelled'
    ";
    
    $params = [':cadet_id' => $cadet_id];
    
    // Add status filter
    if ($status === 'upcoming') {
        $query .= " AND (e.event_date >= CURDATE() OR e.status = 'ongoing')";
    } elseif ($status === 'ongoing') {
        $query .= " AND e.status = 'ongoing'";
    } elseif ($status === 'past') {
        $query .= " AND e.event_date < CURDATE() AND e.status = 'completed'";
    }
    
    $query .= " GROUP BY e.id, e.event_name, e.description, e.event_date, e.start_time, e.end_time, e.location, e.status, e.qr_code_path";
    $query .= " ORDER BY 
        CASE 
            WHEN e.status = 'ongoing' THEN 1
            WHEN e.event_date = CURDATE() THEN 2
            ELSE 3
        END,
        e.event_date, 
        e.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    // Format for mobile app
    $formatted_events = [];
    foreach ($events as $event) {
        $formatted_events[] = [
            'id' => $event['id'],
            'name' => $event['event_name'],
            'description' => $event['description'],
            'date' => $event['event_date'],
            'start_time' => $event['start_time'],
            'end_time' => $event['end_time'],
            'datetime' => $event['event_date'] . ' ' . $event['start_time'],
            'location' => $event['location'],
            'status' => $event['status'],
            'time_category' => $event['time_category'],
            'has_qr' => !empty($event['qr_code_path']),
            'attendees_count' => $event['attendees_count'],
            'has_attended' => (bool)$event['has_attended'],
            'formatted_date' => date('D, M j', strtotime($event['event_date'])),
            'formatted_time' => date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time']))
        ];
    }
    
    // Get statistics
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_events,
            SUM(CASE WHEN event_date = CURDATE() THEN 1 ELSE 0 END) as today_events
        FROM events 
        WHERE status != 'cancelled'
    ")->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'events' => $formatted_events,
            'statistics' => [
                'total' => $stats['total_events'],
                'ongoing' => $stats['ongoing_events'],
                'today' => $stats['today_events']
            ],
            'meta' => [
                'count' => count($formatted_events),
                'generated_at' => date('Y-m-d H:i:s'),
                'status' => 'success'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'API_ERROR'
    ]);
}
