<?php
// reports.php
// Professional Attendance Reports with Comprehensive Data Display

require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get filter parameters
$filter_platoon = isset($_GET['platoon']) ? $_GET['platoon'] : '';
$filter_company = isset($_GET['company']) ? $_GET['company'] : '';
$filter_cadet = isset($_GET['cadet_id']) ? intval($_GET['cadet_id']) : 0;
$filter_event = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$is_csv_export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Pagination controls (server-side for large datasets)
$per_page_options = [25, 50, 100, 200];
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
if (!in_array($per_page, $per_page_options, true)) {
    $per_page = 50;
}
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get unique platoons for filter
$platoons = $pdo->query("
    SELECT DISTINCT platoon 
    FROM cadet_accounts 
    WHERE platoon IS NOT NULL AND platoon != '' AND is_archived = FALSE 
    ORDER BY platoon
")->fetchAll(PDO::FETCH_COLUMN);

// Get unique companies for filter
$companies = $pdo->query("
    SELECT DISTINCT company 
    FROM cadet_accounts 
    WHERE company IS NOT NULL AND company != '' AND is_archived = FALSE 
    ORDER BY company
")->fetchAll(PDO::FETCH_COLUMN);

// Get all cadets for dropdown
$cadets = $pdo->query("
    SELECT id, first_name, last_name, username, platoon, company
    FROM cadet_accounts
    WHERE is_archived = FALSE AND status = 'approved'
    ORDER BY last_name, first_name
")->fetchAll();

// Get all events for dropdown
$events = $pdo->prepare("
    SELECT id, event_name, event_date, start_time, end_time 
    FROM events 
    WHERE deleted_at IS NULL 
    ORDER BY event_date DESC
");
$events->execute();
$events_list = $events->fetchAll();

$selected_cadet_label = '';
if ($filter_cadet > 0) {
    foreach ($cadets as $cadet_item) {
        if ((int)$cadet_item['id'] === $filter_cadet) {
            $selected_cadet_label = $cadet_item['last_name'] . ', ' . $cadet_item['first_name'] .
                ' (' . $cadet_item['platoon'] . ' - ' . $cadet_item['company'] . ')';
            break;
        }
    }
    if ($selected_cadet_label === '') {
        $selected_cadet_label = 'Cadet #' . $filter_cadet;
    }
}

$selected_event_label = '';
if ($filter_event > 0) {
    foreach ($events_list as $event_item) {
        if ((int)$event_item['id'] === $filter_event) {
            $selected_event_label = $event_item['event_name'] . ' (' . date('M j, Y', strtotime($event_item['event_date'])) . ')';
            break;
        }
    }
    if ($selected_event_label === '') {
        $selected_event_label = 'Event #' . $filter_event;
    }
}

// Build base query parts used by detail, summary, and export queries
$select_clause = "
    SELECT 
        c.id as cadet_id,
        c.first_name,
        c.last_name,
        c.username,
        c.platoon,
        c.company,
        c.course,
        e.id as event_id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.location,
        eas.check_in_time,
        eas.check_out_time,
        COALESCE(eas.check_in_status, 'absent') as status,
        COALESCE(eas.check_out_status, 'absent') as check_out_status,
        eas.is_late_check_in,
        eas.is_early_check_out,
        eas.total_duration_minutes,
        eas.remarks,
        CASE 
            WHEN eas.check_in_status = 'present' THEN 'Present'
            WHEN eas.check_in_status = 'late' THEN 'Late'
            WHEN eas.check_in_status = 'absent' THEN 'Absent'
            WHEN eas.check_in_status IN ('excuse', 'excused') THEN 'Excuse'
            ELSE 'Absent'
        END as status_display,
        TIMEDIFF(eas.check_out_time, eas.check_in_time) as duration_formatted
";

$from_clause = "
    FROM cadet_accounts c
    CROSS JOIN events e
    LEFT JOIN event_attendance_summary eas ON e.id = eas.event_id AND c.id = eas.cadet_id
";

$where_clause = "
    WHERE c.is_archived = FALSE
    AND c.status = 'approved'
    AND e.deleted_at IS NULL
    AND (e.event_date BETWEEN :date_from AND :date_to OR :event_filter > 0)
";

$params = [
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'event_filter' => $filter_event
];

// Apply independent platoon filter (always applied if selected)
if (!empty($filter_platoon)) {
    $where_clause .= " AND c.platoon = :platoon";
    $params['platoon'] = $filter_platoon;
}

// Apply independent company filter (always applied if selected)
if (!empty($filter_company)) {
    $where_clause .= " AND c.company = :company";
    $params['company'] = $filter_company;
}

// Apply cadet filter
if ($filter_cadet > 0) {
    $where_clause .= " AND c.id = :cadet_id";
    $params['cadet_id'] = $filter_cadet;
}

// Apply event filter
if ($filter_event > 0) {
    $where_clause .= " AND e.id = :event_id";
    $params['event_id'] = $filter_event;
}

// Apply status filter
if (!empty($filter_status)) {
    if ($filter_status === 'excuse') {
        $where_clause .= " AND COALESCE(eas.check_in_status, 'absent') IN ('excuse', 'excused')";
    } else {
        $where_clause .= " AND COALESCE(eas.check_in_status, 'absent') = :status";
        $params['status'] = $filter_status;
    }
}

// Apply search filter
if (!empty($filter_search)) {
    $where_clause .= " AND (
        c.first_name LIKE :search_first
        OR c.last_name LIKE :search_last
        OR c.username LIKE :search_username
        OR e.event_name LIKE :search_event
        OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search_fullname
    )";
    $search_value = '%' . $filter_search . '%';
    $params['search_first'] = $search_value;
    $params['search_last'] = $search_value;
    $params['search_username'] = $search_value;
    $params['search_event'] = $search_value;
    $params['search_fullname'] = $search_value;
}

// Shared bind helper for named parameters
$bindNamedParams = static function(PDOStatement $stmt, array $bindParams): void {
    foreach ($bindParams as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
};

// Get total records for pagination info
$count_query = "SELECT COUNT(*) as total " . $from_clause . $where_clause;
$count_stmt = $pdo->prepare($count_query);
$bindNamedParams($count_stmt, $params);
$count_stmt->execute();
$total_records = (int)$count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_records / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// Execute main query (paginated for large dataset handling)
$query = $select_clause . $from_clause . $where_clause . "
    ORDER BY e.event_date DESC, c.last_name ASC, c.first_name ASC
    LIMIT :limit OFFSET :offset
";
$query_params = $params;
$query_params['limit'] = $per_page;
$query_params['offset'] = $offset;

$stmt = $pdo->prepare($query);
$bindNamedParams($stmt, $query_params);
$stmt->execute();
$report_data = $stmt->fetchAll();

// Calculate summary statistics
$summary_query = "
    SELECT
        COUNT(*) as total_records,
        COUNT(DISTINCT c.id) as total_cadets,
        COUNT(DISTINCT e.id) as total_events,
        SUM(CASE WHEN COALESCE(eas.check_in_status, 'absent') = 'present' THEN 1 ELSE 0 END) as check_in_present_count,
        SUM(CASE WHEN COALESCE(eas.check_in_status, 'absent') = 'late' THEN 1 ELSE 0 END) as check_in_late_count,
        SUM(CASE WHEN COALESCE(eas.check_in_status, 'absent') = 'absent' THEN 1 ELSE 0 END) as check_in_absent_count,
        SUM(CASE WHEN COALESCE(eas.check_in_status, 'absent') IN ('excuse', 'excused') THEN 1 ELSE 0 END) as check_in_excuse_count,
        SUM(CASE WHEN COALESCE(eas.check_out_status, 'absent') = 'present' THEN 1 ELSE 0 END) as check_out_present_count,
        SUM(CASE WHEN COALESCE(eas.check_out_status, 'absent') = 'late' THEN 1 ELSE 0 END) as check_out_late_count,
        SUM(CASE WHEN COALESCE(eas.check_out_status, 'absent') = 'absent' THEN 1 ELSE 0 END) as check_out_absent_count,
        SUM(CASE WHEN COALESCE(eas.check_out_status, 'absent') IN ('excuse', 'excused') THEN 1 ELSE 0 END) as check_out_excuse_count
    " . $from_clause . $where_clause;

$summary_stmt = $pdo->prepare($summary_query);
$bindNamedParams($summary_stmt, $params);
$summary_stmt->execute();
$summary_row = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$summary_stats = [
    'total_records' => $total_records,
    'total_cadets' => (int)($summary_row['total_cadets'] ?? 0),
    'total_events' => (int)($summary_row['total_events'] ?? 0),
    'present_count' => (int)($summary_row['check_in_present_count'] ?? 0),
    'late_count' => (int)($summary_row['check_in_late_count'] ?? 0),
    'absent_count' => (int)($summary_row['check_in_absent_count'] ?? 0),
    'excuse_count' => (int)($summary_row['check_in_excuse_count'] ?? 0),
    'check_in_present_count' => (int)($summary_row['check_in_present_count'] ?? 0),
    'check_in_late_count' => (int)($summary_row['check_in_late_count'] ?? 0),
    'check_in_absent_count' => (int)($summary_row['check_in_absent_count'] ?? 0),
    'check_in_excuse_count' => (int)($summary_row['check_in_excuse_count'] ?? 0),
    'check_out_present_count' => (int)($summary_row['check_out_present_count'] ?? 0),
    'check_out_late_count' => (int)($summary_row['check_out_late_count'] ?? 0),
    'check_out_absent_count' => (int)($summary_row['check_out_absent_count'] ?? 0),
    'check_out_excuse_count' => (int)($summary_row['check_out_excuse_count'] ?? 0),
    'check_in_attendance_rate' => 0,
    'check_out_attendance_rate' => 0
];

$total_expected = (int)$summary_stats['total_records'];
$check_in_attended = $summary_stats['check_in_present_count'] + $summary_stats['check_in_late_count'];
$check_out_attended = $summary_stats['check_out_present_count'] + $summary_stats['check_out_late_count'];

if ($total_expected > 0) {
    $summary_stats['check_in_attendance_rate'] = round(($check_in_attended / $total_expected) * 100, 2);
    $summary_stats['check_out_attendance_rate'] = round(($check_out_attended / $total_expected) * 100, 2);
}

$start_record = $total_records > 0 ? ($offset + 1) : 0;
$end_record = $total_records > 0 ? min($offset + $per_page, $total_records) : 0;

$base_query_params = $_GET;
unset($base_query_params['page'], $base_query_params['export']);
$export_query_params = $base_query_params;
$export_query_params['export'] = 'csv';
$export_url = '?' . http_build_query($export_query_params);
$pagination_start = max(1, $page - 2);
$pagination_end = min($total_pages, $page + 2);

// Stream full filtered dataset as CSV on demand (memory-safe for large exports)
if ($is_csv_export) {
    $export_query = $select_clause . $from_clause . $where_clause . " ORDER BY e.event_date DESC, c.last_name ASC, c.first_name ASC";
    $export_stmt = $pdo->prepare($export_query);
    $bindNamedParams($export_stmt, $params);
    $export_stmt->execute();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Date',
        'Cadet Name',
        'Username',
        'Platoon',
        'Company',
        'Course',
        'Event Name',
        'Event Location',
        'Start Time',
        'End Time',
        'Check-in Time',
        'Check-out Time',
        'Check-in Status',
        'Check-out Status',
        'Late Check-in',
        'Early Check-out',
        'Duration (minutes)',
        'Remarks'
    ]);

    while ($record = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
        $check_in_status = $record['status'] === 'excused' ? 'excuse' : ($record['status'] ?? 'absent');
        if (!in_array($check_in_status, ['present', 'late', 'absent', 'excuse'], true)) {
            $check_in_status = 'absent';
        }

        $check_out_status = $record['check_out_status'] === 'excused' ? 'excuse' : ($record['check_out_status'] ?? 'absent');
        if (!in_array($check_out_status, ['present', 'late', 'absent', 'excuse'], true)) {
            $check_out_status = 'absent';
        }

        fputcsv($output, [
            $record['event_date'] ? date('Y-m-d', strtotime($record['event_date'])) : '',
            ($record['last_name'] ?? '') . ', ' . ($record['first_name'] ?? ''),
            $record['username'] ?? '',
            $record['platoon'] ?? '',
            $record['company'] ?? '',
            $record['course'] ?? '',
            $record['event_name'] ?? '',
            $record['location'] ?? '',
            $record['start_time'] ?? '',
            $record['end_time'] ?? '',
            $record['check_in_time'] ?? '',
            $record['check_out_time'] ?? '',
            ucfirst($check_in_status),
            ucfirst($check_out_status),
            !empty($record['is_late_check_in']) ? 'Yes' : 'No',
            !empty($record['is_early_check_out']) ? 'Yes' : 'No',
            $record['total_duration_minutes'] ?? 0,
            $record['remarks'] ?? ''
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Total Records', $summary_stats['total_records']]);
    fputcsv($output, ['Total Cadets', $summary_stats['total_cadets']]);
    fputcsv($output, ['Total Events', $summary_stats['total_events']]);
    fputcsv($output, ['Check-in Present', $summary_stats['check_in_present_count']]);
    fputcsv($output, ['Check-in Late', $summary_stats['check_in_late_count']]);
    fputcsv($output, ['Check-in Absent', $summary_stats['check_in_absent_count']]);
    fputcsv($output, ['Check-in Excused', $summary_stats['check_in_excuse_count']]);
    fputcsv($output, ['Check-out Present', $summary_stats['check_out_present_count']]);
    fputcsv($output, ['Check-out Late', $summary_stats['check_out_late_count']]);
    fputcsv($output, ['Check-out Absent', $summary_stats['check_out_absent_count']]);
    fputcsv($output, ['Check-out Excused', $summary_stats['check_out_excuse_count']]);
    fputcsv($output, ['Check-in Attendance Rate', $summary_stats['check_in_attendance_rate'] . '%']);
    fputcsv($output, ['Check-out Attendance Rate', $summary_stats['check_out_attendance_rate'] . '%']);
    fclose($output);
    exit;
}

// Get monthly trend data
$monthly_trend_query = "
    SELECT 
        DATE_FORMAT(e.event_date, '%Y-%m') as month,
        DATE_FORMAT(e.event_date, '%M %Y') as month_name,
        COUNT(DISTINCT e.id) as event_count,
        COUNT(DISTINCT c.id) as cadet_count,
        COUNT(eas.id) as attendance_count,
        SUM(CASE WHEN eas.check_in_status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN eas.check_in_status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN eas.check_in_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN eas.check_in_status IN ('excuse', 'excused') THEN 1 ELSE 0 END) as excuse_count,
        ROUND((SUM(CASE WHEN eas.check_in_status IN ('present', 'late') THEN 1 ELSE 0 END) / 
            (COUNT(DISTINCT c.id) * COUNT(DISTINCT e.id)) * 100), 2) as attendance_rate
    FROM events e
    CROSS JOIN cadet_accounts c
    LEFT JOIN event_attendance_summary eas ON e.id = eas.event_id AND c.id = eas.cadet_id
    WHERE e.deleted_at IS NULL
    AND e.event_date BETWEEN :trend_date_from AND :trend_date_to
    AND c.is_archived = FALSE
    AND c.status = 'approved'
";

$monthly_trend_params = [
    'trend_date_from' => $filter_date_from,
    'trend_date_to' => $filter_date_to
];

if (!empty($filter_platoon)) {
    $monthly_trend_query .= " AND c.platoon = :trend_platoon";
    $monthly_trend_params['trend_platoon'] = $filter_platoon;
}

if (!empty($filter_company)) {
    $monthly_trend_query .= " AND c.company = :trend_company";
    $monthly_trend_params['trend_company'] = $filter_company;
}

if ($filter_cadet > 0) {
    $monthly_trend_query .= " AND c.id = :trend_cadet_id";
    $monthly_trend_params['trend_cadet_id'] = $filter_cadet;
}

if ($filter_event > 0) {
    $monthly_trend_query .= " AND e.id = :trend_event_id";
    $monthly_trend_params['trend_event_id'] = $filter_event;
}

$monthly_trend_query .= "
    GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";

$monthly_trend = $pdo->prepare($monthly_trend_query);
$bindNamedParams($monthly_trend, $monthly_trend_params);
$monthly_trend->execute();
$trend_data = $monthly_trend->fetchAll();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Professional Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f3f4f6;
        }
        
        .report-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 20px;
            color: white;
        }
        
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }
        
        .filter-select, .filter-input, .filter-date {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
        }
        
        .filter-select:focus, .filter-input:focus, .filter-date:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-secondary {
            background: white;
            border: 2px solid #e5e7eb;
            color: #4b5563;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-present {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-late {
            background: #fed7aa;
            color: #92400e;
        }
        
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-excuse {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .table-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            color: white;
            padding: 20px;
        }
        
        .table-row:hover {
            background: #f9fafb;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .status-chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        .main-content {
            margin-left: 16rem;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: 5rem;
        }
        
        @media print {
            .sidebar, .filter-card, .btn-primary, .btn-secondary, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            .report-card, .table-container {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
            .stat-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: #e5e7eb;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .dataTables_wrapper {
            padding: 20px;
        }
        
        .search-box {
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            width: 300px;
        }
        
        .export-btn {
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .export-btn:hover {
            background: #059669;
        }

        .active-filters {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
        }
        
        .filter-tag {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-tag i {
            color: #64748b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content min-h-screen transition-all duration-300" id="mainContent">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-8 py-6 no-print sticky-header">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Attendance Reports</h1>
                    <p class="text-gray-600 mt-1">Comprehensive attendance analytics with detailed breakdown</p>
                </div>
                <div class="flex gap-3">
                    <a href="<?php echo htmlspecialchars($export_url); ?>" class="export-btn flex items-center gap-2">
                        <i class="fas fa-file-excel"></i>
                        Export to Excel
                    </a>
                    <button onclick="exportToPDF()" class="btn-secondary flex items-center gap-2">
                        <i class="fas fa-file-pdf"></i>
                        Export PDF
                    </button>
                    <button onclick="window.print()" class="btn-secondary flex items-center gap-2">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </div>
            
            <!-- Active Filters Display -->
            <?php if (!empty($filter_platoon) || !empty($filter_company) || !empty($filter_status) || !empty($filter_search) || $filter_event > 0 || $filter_cadet > 0): ?>
            <div class="active-filters flex flex-wrap items-center gap-2 mt-4">
                <span class="text-sm font-medium text-gray-700 mr-2"><i class="fas fa-filter mr-1"></i>Active Filters:</span>
                
                <?php if (!empty($filter_platoon)): ?>
                <span class="filter-tag">
                    <i class="fas fa-users"></i> Platoon: <?php echo htmlspecialchars($filter_platoon); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['platoon' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($filter_company)): ?>
                <span class="filter-tag">
                    <i class="fas fa-building"></i> Company: <?php echo htmlspecialchars($filter_company); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['company' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>

                <?php if ($filter_cadet > 0): ?>
                <span class="filter-tag">
                    <i class="fas fa-user"></i> Cadet: <?php echo htmlspecialchars($selected_cadet_label); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['cadet_id' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>

                <?php if ($filter_event > 0): ?>
                <span class="filter-tag">
                    <i class="fas fa-calendar-alt"></i> Event: <?php echo htmlspecialchars($selected_event_label); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['event_id' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($filter_status)): ?>
                <span class="filter-tag">
                    <i class="fas fa-tag"></i> Status: <?php echo ucfirst($filter_status); ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
                
                <?php if (!empty($filter_search)): ?>
                <span class="filter-tag">
                    <i class="fas fa-search"></i> Search: "<?php echo htmlspecialchars($filter_search); ?>"
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="ml-1 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </a>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Main Content -->
        <div class="px-8 py-6">
            <!-- Filter Section -->
            <div class="filter-card no-print">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filter Reports</h2>
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">By Event</label>
                            <select name="event_id" class="filter-select">
                                <option value="">All Events</option>
                                <?php foreach ($events_list as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $filter_event == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['event_name']); ?> 
                                        (<?php echo date('M j, Y', strtotime($event['event_date'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">By Cadet</label>
                            <select name="cadet_id" class="filter-select">
                                <option value="">All Cadets</option>
                                <?php foreach ($cadets as $cadet): ?>
                                    <option value="<?php echo $cadet['id']; ?>" <?php echo $filter_cadet == $cadet['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cadet['last_name'] . ', ' . $cadet['first_name']); ?>
                                        (<?php echo htmlspecialchars($cadet['platoon'] . ' - ' . $cadet['company']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Platoon</label>
                            <select name="platoon" class="filter-select">
                                <option value="">All Platoons</option>
                                <?php foreach ($platoons as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $filter_platoon == $p ? 'selected' : ''; ?>>
                                        Platoon <?php echo htmlspecialchars($p); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                            <select name="company" class="filter-select">
                                <option value="">All Companies</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filter_company == $c ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c); ?> Company
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                            <input type="date" name="date_from" class="filter-date" value="<?php echo $filter_date_from; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                            <input type="date" name="date_to" class="filter-date" value="<?php echo $filter_date_to; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="present" <?php echo $filter_status == 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $filter_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="excuse" <?php echo $filter_status == 'excuse' ? 'selected' : ''; ?>>Excuse</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                            <input type="text" name="search" class="filter-input" placeholder="Name, event, platoon..." 
                                   value="<?php echo htmlspecialchars($filter_search); ?>">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Rows Per Page</label>
                            <select name="per_page" class="filter-select">
                                <?php foreach ($per_page_options as $page_size): ?>
                                    <option value="<?php echo $page_size; ?>" <?php echo $per_page === $page_size ? 'selected' : ''; ?>>
                                        <?php echo $page_size; ?> rows
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2">
                        <button type="submit" class="btn-primary flex items-center gap-2">
                            <i class="fas fa-filter"></i>
                            Generate Report
                        </button>
                        <a href="reports.php" class="btn-secondary flex items-center gap-2">
                            <i class="fas fa-redo"></i>
                            Reset All Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Summary Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="report-card animate-fade-in">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Total Records</p>
                        <i class="fas fa-database text-blue-500 text-xl"></i>
                    </div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo number_format($summary_stats['total_records']); ?></p>
                    <p class="text-xs text-gray-500 mt-1">Attendance entries</p>
                </div>
                
                <div class="report-card animate-fade-in" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Cadets / Events</p>
                        <i class="fas fa-users text-green-500 text-xl"></i>
                    </div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $summary_stats['total_cadets']; ?> / <?php echo $summary_stats['total_events']; ?></p>
                    <p class="text-xs text-gray-500 mt-1">Active cadets & events</p>
                </div>
                
                <div class="report-card animate-fade-in" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Check-in Attendance Rate</p>
                        <i class="fas fa-chart-line text-purple-500 text-xl"></i>
                    </div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $summary_stats['check_in_attendance_rate']; ?>%</p>
                    <div class="progress-bar mt-2">
                        <div class="progress-fill bg-green-500" style="width: <?php echo $summary_stats['check_in_attendance_rate']; ?>%"></div>
                    </div>
                </div>
                
                <div class="report-card animate-fade-in" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Check-out Attendance Rate</p>
                        <i class="fas fa-sign-out-alt text-yellow-500 text-xl"></i>
                    </div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $summary_stats['check_out_attendance_rate']; ?>%</p>
                    <div class="progress-bar mt-2">
                        <div class="progress-fill bg-yellow-500" style="width: <?php echo $summary_stats['check_out_attendance_rate']; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <!-- Status Distribution Cards -->
            <div class="mb-3">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Check-in Status</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 rounded-xl p-4 border-l-4 border-green-500">
                        <p class="text-sm text-green-600 font-medium">Present</p>
                        <p class="text-2xl font-bold text-green-700"><?php echo number_format($summary_stats['check_in_present_count']); ?></p>
                        <p class="text-xs text-green-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_in_present_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-xl p-4 border-l-4 border-yellow-500">
                        <p class="text-sm text-yellow-600 font-medium">Late</p>
                        <p class="text-2xl font-bold text-yellow-700"><?php echo number_format($summary_stats['check_in_late_count']); ?></p>
                        <p class="text-xs text-yellow-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_in_late_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-red-50 rounded-xl p-4 border-l-4 border-red-500">
                        <p class="text-sm text-red-600 font-medium">Absent</p>
                        <p class="text-2xl font-bold text-red-700"><?php echo number_format($summary_stats['check_in_absent_count']); ?></p>
                        <p class="text-xs text-red-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_in_absent_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-blue-50 rounded-xl p-4 border-l-4 border-blue-500">
                        <p class="text-sm text-blue-600 font-medium">Excused</p>
                        <p class="text-2xl font-bold text-blue-700"><?php echo number_format($summary_stats['check_in_excuse_count']); ?></p>
                        <p class="text-xs text-blue-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_in_excuse_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Check-out Status</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 rounded-xl p-4 border-l-4 border-green-500">
                        <p class="text-sm text-green-600 font-medium">Present</p>
                        <p class="text-2xl font-bold text-green-700"><?php echo number_format($summary_stats['check_out_present_count']); ?></p>
                        <p class="text-xs text-green-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_out_present_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-xl p-4 border-l-4 border-yellow-500">
                        <p class="text-sm text-yellow-600 font-medium">Late</p>
                        <p class="text-2xl font-bold text-yellow-700"><?php echo number_format($summary_stats['check_out_late_count']); ?></p>
                        <p class="text-xs text-yellow-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_out_late_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-red-50 rounded-xl p-4 border-l-4 border-red-500">
                        <p class="text-sm text-red-600 font-medium">Absent</p>
                        <p class="text-2xl font-bold text-red-700"><?php echo number_format($summary_stats['check_out_absent_count']); ?></p>
                        <p class="text-xs text-red-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_out_absent_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                    
                    <div class="bg-blue-50 rounded-xl p-4 border-l-4 border-blue-500">
                        <p class="text-sm text-blue-600 font-medium">Excused</p>
                        <p class="text-2xl font-bold text-blue-700"><?php echo number_format($summary_stats['check_out_excuse_count']); ?></p>
                        <p class="text-xs text-blue-600 mt-1"><?php echo $total_expected > 0 ? round(($summary_stats['check_out_excuse_count'] / $total_expected) * 100, 1) : 0; ?>% of total</p>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8 no-print">
                <!-- Status Distribution Chart -->
                <div class="report-card">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Attendance Status Distribution</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-medium text-gray-600 text-center mb-2">Check-in</p>
                            <div class="status-chart-container">
                                <canvas id="checkInStatusChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 text-center mb-2">Check-out</p>
                            <div class="status-chart-container">
                                <canvas id="checkOutStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Trend Chart -->
                <div class="report-card">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Attendance Trend</h3>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Detailed Attendance Table -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Detailed Attendance Records</h3>
                    <span class="text-sm text-gray-500">
                        Showing <?php echo number_format($start_record); ?>-<?php echo number_format($end_record); ?>
                        of <?php echo number_format($total_records); ?> records
                    </span>
                </div>
                
                <div class="table-container">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cadet Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platoon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-out</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-chart-bar text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Records Found</h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                No attendance records match your current filters. Try adjusting your search criteria.
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($report_data as $record): ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($record['username']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($record['event_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($record['location'] ?: '—'); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php echo date('M j, Y', strtotime($record['event_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($record['company'] ?: '—'); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($record['platoon'] ?: '—'); ?></td>
                                            <td class="px-6 py-4">
                                                <div class="whitespace-nowrap">
                                                    <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '&mdash;'; ?>
                                                    <?php if ($record['is_late_check_in']): ?>
                                                        <span class="ml-1 text-xs text-yellow-600" title="Late check-in">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                                $check_in_key = $record['status'] === 'excused' ? 'excuse' : $record['status'];
                                                $check_in_key = in_array($check_in_key, ['present', 'late', 'absent', 'excuse'], true) ? $check_in_key : 'absent';
                                                $check_in_label = $check_in_key === 'excuse' ? 'Excused' : ucfirst($check_in_key);
                                                $check_in_icon = [
                                                    'present' => 'fa-check-circle',
                                                    'late' => 'fa-clock',
                                                    'absent' => 'fa-times-circle',
                                                    'excuse' => 'fa-file-excel'
                                                ][$check_in_key];
                                                ?>
                                                <div class="mt-1">
                                                    <span class="status-badge status-<?php echo $check_in_key; ?>">
                                                        <i class="fas <?php echo $check_in_icon; ?>"></i>
                                                        <?php echo $check_in_label; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="whitespace-nowrap">
                                                    <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '&mdash;'; ?>
                                                    <?php if ($record['is_early_check_out']): ?>
                                                        <span class="ml-1 text-xs text-orange-600" title="Early check-out">
                                                            <i class="fas fa-clock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                                $check_out_raw = $record['check_out_status'] ?? 'absent';
                                                $check_out_key = $check_out_raw === 'excused' ? 'excuse' : $check_out_raw;
                                                $check_out_key = in_array($check_out_key, ['present', 'late', 'absent', 'excuse'], true) ? $check_out_key : 'absent';
                                                $check_out_label = $check_out_key === 'excuse' ? 'Excused' : ucfirst($check_out_key);
                                                $check_out_icon = [
                                                    'present' => 'fa-check-circle',
                                                    'late' => 'fa-clock',
                                                    'absent' => 'fa-times-circle',
                                                    'excuse' => 'fa-file-excel'
                                                ][$check_out_key];
                                                ?>
                                                <div class="mt-1">
                                                    <span class="status-badge status-<?php echo $check_out_key; ?>">
                                                        <i class="fas <?php echo $check_out_icon; ?>"></i>
                                                        <?php echo $check_out_label; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 max-w-xs truncate">
                                                <?php echo htmlspecialchars($record['remarks'] ?: '—'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between no-print">
                    <p class="text-sm text-gray-600">
                        Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a
                                href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $page - 1])); ?>"
                                class="btn-secondary text-sm"
                            >
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="btn-secondary text-sm opacity-50 cursor-not-allowed">Previous</span>
                        <?php endif; ?>

                        <?php for ($page_number = $pagination_start; $page_number <= $pagination_end; $page_number++): ?>
                            <a
                                href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $page_number])); ?>"
                                class="<?php echo $page_number === $page ? 'btn-primary text-sm' : 'btn-secondary text-sm'; ?>"
                            >
                                <?php echo number_format($page_number); ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a
                                href="?<?php echo http_build_query(array_merge($base_query_params, ['page' => $page + 1])); ?>"
                                class="btn-secondary text-sm"
                            >
                                Next
                            </a>
                        <?php else: ?>
                            <span class="btn-secondary text-sm opacity-50 cursor-not-allowed">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar state management
        function updateMainContentMargin() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar && mainContent) {
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.add('sidebar-collapsed');
                } else {
                    mainContent.classList.remove('sidebar-collapsed');
                }
            }
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            updateMainContentMargin();
            
            // Observe sidebar class changes
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            updateMainContentMargin();
                        }
                    });
                });
                observer.observe(sidebar, { attributes: true });
            }
            
            // Status Distribution Chart
            const statusLabels = ['Present', 'Late', 'Absent', 'Excuse'];
            const statusColors = ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'];

            function initStatusDonutChart(canvasId, values) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: values,
                            backgroundColor: statusColors,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 16
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '62%'
                    }
                });
            }

            initStatusDonutChart('checkInStatusChart', [
                <?php echo $summary_stats['check_in_present_count']; ?>,
                <?php echo $summary_stats['check_in_late_count']; ?>,
                <?php echo $summary_stats['check_in_absent_count']; ?>,
                <?php echo $summary_stats['check_in_excuse_count']; ?>
            ]);

            initStatusDonutChart('checkOutStatusChart', [
                <?php echo $summary_stats['check_out_present_count']; ?>,
                <?php echo $summary_stats['check_out_late_count']; ?>,
                <?php echo $summary_stats['check_out_absent_count']; ?>,
                <?php echo $summary_stats['check_out_excuse_count']; ?>
            ]);
            
            // Monthly Trend Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: [<?php 
                        $months = array_reverse($trend_data);
                        foreach ($months as $index => $month) {
                            echo "'" . $month['month_name'] . "'";
                            if ($index < count($months) - 1) echo ',';
                        }
                    ?>],
                    datasets: [
                        {
                            label: 'Present',
                            data: [<?php 
                                foreach ($months as $index => $month) {
                                    echo $month['present_count'] ?? 0;
                                    if ($index < count($months) - 1) echo ',';
                                }
                            ?>],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'Late',
                            data: [<?php 
                                foreach ($months as $index => $month) {
                                    echo $month['late_count'] ?? 0;
                                    if ($index < count($months) - 1) echo ',';
                                }
                            ?>],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        },
                        {
                            label: 'Attendance Rate %',
                            data: [<?php 
                                foreach ($months as $index => $month) {
                                    echo $month['attendance_rate'] ?? 0;
                                    if ($index < count($months) - 1) echo ',';
                                }
                            ?>],
                            borderColor: '#8b5cf6',
                            backgroundColor: 'transparent',
                            tension: 0.4,
                            borderWidth: 2,
                            borderDash: [5, 5],
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Rate (%)'
                            }
                        }
                    }
                }
            });
        });
        
        // Export functions
        function exportToPDF() {
            window.print();
        }
    </script>
</body>
</html>
