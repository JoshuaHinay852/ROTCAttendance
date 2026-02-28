<?php
// api/get_upcoming_events.php
require_once '../config/database.php';
require_once '../includes/event_status_helper.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection is not available');
    }

    ensureEventStatusesAreCurrent($pdo);
    
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    $query = "SELECT 
                id,
                event_name,
                description,
                event_date,
                start_time,
                end_time,
                location,
                status,
                created_at
              FROM events 
              WHERE status IN ('scheduled', 'ongoing')
                AND (event_date > :current_date 
                     OR (event_date = :current_date AND end_time > :current_time))
              ORDER BY event_date ASC, start_time ASC
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':current_date' => $currentDate,
        ':current_time' => $currentTime
    ]);
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for Flutter
    $formattedEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'event_name' => $event['event_name'],
            'description' => $event['description'],
            'event_date' => $event['event_date'],
            'start_time' => $event['start_time'],
            'end_time' => $event['end_time'],
            'location' => $event['location'],
            'status' => $event['status'],
            'created_at' => $event['created_at'],
            'time_formatted' => date('Hi', strtotime($event['start_time'])) . 'H-' . date('Hi', strtotime($event['end_time'])) . 'H',
            'date_formatted' => date('F j, Y', strtotime($event['event_date']))
        ];
    }, $events);
    
    echo json_encode([
        'success' => true,
        'events' => $formattedEvents,
        'count' => count($formattedEvents)
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
