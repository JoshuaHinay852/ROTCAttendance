<?php
// api/get_events.php
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
    
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
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
              FROM events";
    
    $params = [];
    
    if ($status && $status !== 'all') {
        $query .= " WHERE status = :status";
        $params[':status'] = $status;
    }
    
    $query .= " ORDER BY 
                CASE 
                    WHEN status = 'ongoing' THEN 1
                    WHEN status = 'scheduled' THEN 2
                    WHEN status = 'completed' THEN 3
                    WHEN status = 'cancelled' THEN 4
                END,
                event_date DESC,
                start_time DESC
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
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM events";
    if ($status && $status !== 'all') {
        $countQuery .= " WHERE status = :status";
    }
    $countStmt = $pdo->prepare($countQuery);
    if ($status && $status !== 'all') {
        $countStmt->bindValue(':status', $status);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
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
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset,
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
