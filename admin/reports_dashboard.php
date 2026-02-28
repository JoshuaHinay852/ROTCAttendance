<?php
// reports.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Get filter parameters
$filter_cadet = isset($_GET['cadet_id']) ? intval($_GET['cadet_id']) : '';
$filter_event = isset($_GET['event_id']) ? intval($_GET['event_id']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_platoon = isset($_GET['platoon']) ? $_GET['platoon'] : '';
$filter_company = isset($_GET['company']) ? $_GET['company'] : '';

// Get all cadets for dropdown
$cadets = $pdo->query("SELECT id, first_name, last_name, student_id, course, platoon, company 
                       FROM cadet_accounts ORDER BY last_name, first_name")->fetchAll();

// Get all events for dropdown
$events = $pdo->query("SELECT id, event_name, event_date, status 
                       FROM events ORDER BY event_date DESC")->fetchAll();

// Get unique platoons and companies for filters
$platoons = $pdo->query("SELECT DISTINCT platoon FROM cadet_accounts WHERE platoon IS NOT NULL ORDER BY platoon")->fetchAll();
$companies = $pdo->query("SELECT DISTINCT company FROM cadet_accounts WHERE company IS NOT NULL ORDER BY company")->fetchAll();

// Build query for attendance report
$sql = "SELECT 
            a.*,
            c.first_name as cadet_first,
            c.last_name as cadet_last,
            c.student_id,
            c.course as cadet_course,
            c.platoon as cadet_platoon,
            c.company as cadet_company,
            c.year_level,
            e.event_name,
            e.event_date,
            e.start_time,
            e.end_time,
            e.location as event_location,
            e.status as event_status,
            m.first_name as mp_first,
            m.last_name as mp_last,
            TIMEDIFF(a.check_out_time, a.check_in_time) as duration,
            CASE 
                WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                ELSE NULL 
            END as duration_minutes
        FROM event_attendance a
        INNER JOIN cadet_accounts c ON a.cadet_id = c.id
        INNER JOIN events e ON a.event_id = e.id
        LEFT JOIN mp_accounts m ON a.mp_id = m.id
        WHERE 1=1";

$params = [];

if (!empty($filter_cadet)) {
    $sql .= " AND a.cadet_id = ?";
    $params[] = $filter_cadet;
}

if (!empty($filter_event)) {
    $sql .= " AND a.event_id = ?";
    $params[] = $filter_event;
}

if (!empty($filter_date_from)) {
    $sql .= " AND DATE(e.event_date) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $sql .= " AND DATE(e.event_date) <= ?";
    $params[] = $filter_date_to;
}

if (!empty($filter_status)) {
    $sql .= " AND a.attendance_status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_platoon)) {
    $sql .= " AND c.platoon = ?";
    $params[] = $filter_platoon;
}

if (!empty($filter_company)) {
    $sql .= " AND c.company = ?";
    $params[] = $filter_company;
}

$sql .= " ORDER BY e.event_date DESC, a.check_in_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// Get summary statistics
$summary_sql = "SELECT 
                    COUNT(DISTINCT a.cadet_id) as total_cadets,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN a.attendance_status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN a.attendance_status = 'late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN a.attendance_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN a.attendance_status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                    AVG(CASE 
                        WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                        ELSE NULL 
                    END) as avg_duration,
                    COUNT(DISTINCT e.id) as total_events
                FROM event_attendance a
                INNER JOIN cadet_accounts c ON a.cadet_id = c.id
                INNER JOIN events e ON a.event_id = e.id
                WHERE 1=1";

$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch();

// Get event type breakdown
$event_type_sql = "SELECT 
                        e.status as event_status,
                        COUNT(*) as count
                    FROM event_attendance a
                    INNER JOIN events e ON a.event_id = e.id
                    WHERE 1=1";

$event_type_stmt = $pdo->prepare($event_type_sql . " GROUP BY e.status");
$event_type_stmt->execute($params);
$event_breakdown = $event_type_stmt->fetchAll();

// Get daily attendance for charts
$daily_sql = "SELECT 
                    DATE(e.event_date) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN a.attendance_status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.attendance_status = 'late' THEN 1 ELSE 0 END) as late
                FROM event_attendance a
                INNER JOIN events e ON a.event_id = e.id
                WHERE 1=1";

if (!empty($filter_date_from)) {
    $daily_sql .= " AND DATE(e.event_date) >= ?";
}
if (!empty($filter_date_to)) {
    $daily_sql .= " AND DATE(e.event_date) <= ?";
}

$daily_sql .= " GROUP BY DATE(e.event_date) ORDER BY date";

$daily_stmt = $pdo->prepare($daily_sql);
$daily_stmt->execute($params);
$daily_data = $daily_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports | ROTC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .report-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .filter-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 16px;
            color: white;
        }
        
        .filter-input {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            width: 100%;
            font-size: 14px;
            color: #1f2937;
        }
        
        .filter-input:focus {
            outline: none;
            ring: 2px solid white;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .export-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-present { background: #dcfce7; color: #166534; }
        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        .badge-excused { background: #dbeafe; color: #1e40af; }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th {
            background: #f3f4f6;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-table td {
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-table tr:hover {
            background: #f9fafb;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white;
            }
            .report-card {
                box-shadow: none;
                border: 1px solid #e5e7eb;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4 sticky top-0 z-10 no-print">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Attendance Reports</h1>
                    <p class="text-sm text-gray-600">Generate and export comprehensive attendance reports</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="printReport()" class="export-btn bg-gray-600 text-white">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                    <button onclick="exportToPDF()" class="export-btn bg-red-600 text-white">
                        <i class="fas fa-file-pdf"></i>
                        PDF
                    </button>
                    <button onclick="exportToExcel()" class="export-btn bg-green-600 text-white">
                        <i class="fas fa-file-excel"></i>
                        Excel
                    </button>
                    <button onclick="exportToCSV()" class="export-btn bg-blue-600 text-white">
                        <i class="fas fa-file-csv"></i>
                        CSV
                    </button>
                </div>
            </div>
        </header>
        
        <main class="px-8 py-6">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-2 no-print">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <p class="text-green-800 text-sm"><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3 flex items-center gap-2 no-print">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                    <p class="text-red-800 text-sm"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section mb-6 no-print">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Cadet</label>
                        <select name="cadet_id" class="filter-input">
                            <option value="">All Cadets</option>
                            <?php foreach ($cadets as $cadet): ?>
                                <option value="<?php echo $cadet['id']; ?>" <?php echo $filter_cadet == $cadet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cadet['last_name'] . ', ' . $cadet['first_name'] . ' (' . $cadet['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Event</label>
                        <select name="event_id" class="filter-input">
                            <option value="">All Events</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>" <?php echo $filter_event == $event['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['event_name'] . ' (' . date('M d, Y', strtotime($event['event_date'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>" class="filter-input">
                            <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>" class="filter-input">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Status</label>
                        <select name="status" class="filter-input">
                            <option value="">All Status</option>
                            <option value="present" <?php echo $filter_status == 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="absent" <?php echo $filter_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="excused" <?php echo $filter_status == 'excused' ? 'selected' : ''; ?>>Excused</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Platoon</label>
                        <select name="platoon" class="filter-input">
                            <option value="">All Platoons</option>
                            <?php foreach ($platoons as $p): ?>
                                <option value="<?php echo $p['platoon']; ?>" <?php echo $filter_platoon == $p['platoon'] ? 'selected' : ''; ?>>
                                    Platoon <?php echo htmlspecialchars($p['platoon']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-white mb-1">Company</label>
                        <select name="company" class="filter-input">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?php echo $c['company']; ?>" <?php echo $filter_company == $c['company'] ? 'selected' : ''; ?>>
                                    Company <?php echo htmlspecialchars($c['company']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-white text-purple-600 px-4 py-2 rounded-lg font-medium hover:bg-opacity-90 transition-colors">
                            <i class="fas fa-search mr-2"></i>
                            Apply Filters
                        </button>
                        <a href="reports.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Summary Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($summary['total_records'] ?? 0); ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Present</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $summary['present_count'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400">
                        <?php 
                        $total = $summary['total_records'] ?? 1;
                        $percent = round(($summary['present_count'] ?? 0) / $total * 100);
                        echo $percent . '% of total';
                        ?>
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Late</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $summary['late_count'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400">
                        <?php echo round(($summary['late_count'] ?? 0) / $total * 100); ?>% of total
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Absent</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $summary['absent_count'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400">
                        <?php echo round(($summary['absent_count'] ?? 0) / $total * 100); ?>% of total
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Excused</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $summary['excused_count'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400">
                        <?php echo round(($summary['excused_count'] ?? 0) / $total * 100); ?>% of total
                    </p>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 no-print">
                <div class="report-card p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Attendance Trend</h3>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div class="report-card p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Attendance Distribution</h3>
                    <div class="chart-container">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Report Title for Print -->
            <div class="print-only hidden mb-6">
                <h1 class="text-2xl font-bold text-center">ROTC Attendance Report</h1>
                <p class="text-center text-gray-600">
                    Generated on: <?php echo date('F j, Y \a\t g:i A'); ?>
                </p>
                <p class="text-center text-gray-600">
                    Period: <?php echo date('M d, Y', strtotime($filter_date_from)); ?> - <?php echo date('M d, Y', strtotime($filter_date_to)); ?>
                </p>
                <hr class="my-4">
            </div>
            
            <!-- Detailed Report Table -->
            <div class="report-card p-4">
                <div class="flex justify-between items-center mb-4 no-print">
                    <h3 class="text-lg font-semibold text-gray-800">Detailed Attendance Records</h3>
                    <span class="text-sm text-gray-600">
                        Showing <?php echo count($attendance_records); ?> records
                    </span>
                </div>
                
                <div class="table-container">
                    <table class="report-table" id="attendanceTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Cadet</th>
                                <th>Student ID</th>
                                <th>Course</th>
                                <th>Platoon</th>
                                <th>Company</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance_records)): ?>
                                <tr>
                                    <td colspan="13" class="text-center py-8 text-gray-500">
                                        <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                                        <p>No attendance records found matching your filters</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['event_date'])); ?></td>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($record['event_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($record['event_location']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['cadet_last'] . ', ' . $record['cadet_first']); ?></td>
                                        <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($record['cadet_course']); ?></td>
                                        <td><?php echo htmlspecialchars($record['cadet_platoon']); ?></td>
                                        <td><?php echo htmlspecialchars($record['cadet_company']); ?></td>
                                        <td>
                                            <?php if ($record['check_in_time']): ?>
                                                <?php echo date('g:i A', strtotime($record['check_in_time'])); ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d', strtotime($record['check_in_time'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['check_out_time']): ?>
                                                <?php echo date('g:i A', strtotime($record['check_out_time'])); ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('M d', strtotime($record['check_out_time'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['duration']): ?>
                                                <?php
                                                $minutes = $record['duration_minutes'];
                                                $hours = floor($minutes / 60);
                                                $mins = $minutes % 60;
                                                echo $hours > 0 ? $hours . 'h ' : '';
                                                echo $mins . 'm';
                                                ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $record['attendance_status']; ?>">
                                                <?php echo ucfirst($record['attendance_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['mp_first']): ?>
                                                <?php echo htmlspecialchars($record['mp_first'] . ' ' . $record['mp_last']); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['remarks']): ?>
                                                <span title="<?php echo htmlspecialchars($record['remarks']); ?>">
                                                    <i class="fas fa-comment text-gray-400"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-300">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Report Footer for Print -->
            <div class="print-only hidden mt-8">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <p class="font-bold">Prepared by:</p>
                        <p class="mt-8">___________________________</p>
                        <p><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></p>
                    </div>
                    <div>
                        <p class="font-bold">Noted by:</p>
                        <p class="mt-8">___________________________</p>
                        <p>ROTC Commandant</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Chart data
        const dailyLabels = <?php echo json_encode(array_column($daily_data, 'date')); ?>;
        const dailyPresent = <?php echo json_encode(array_column($daily_data, 'present')); ?>;
        const dailyLate = <?php echo json_encode(array_column($daily_data, 'late')); ?>;
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Trend Chart
            const trendCtx = document.getElementById('trendChart')?.getContext('2d');
            if (trendCtx && dailyLabels.length > 0) {
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: dailyLabels.map(date => new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                        datasets: [
                            {
                                label: 'Present',
                                data: dailyPresent,
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Late',
                                data: dailyLate,
                                borderColor: 'rgb(234, 179, 8)',
                                backgroundColor: 'rgba(234, 179, 8, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
            
            // Distribution Chart
            const distCtx = document.getElementById('distributionChart')?.getContext('2d');
            if (distCtx) {
                new Chart(distCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Late', 'Absent', 'Excused'],
                        datasets: [{
                            data: [
                                <?php echo $summary['present_count'] ?? 0; ?>,
                                <?php echo $summary['late_count'] ?? 0; ?>,
                                <?php echo $summary['absent_count'] ?? 0; ?>,
                                <?php echo $summary['excused_count'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(234, 179, 8, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(59, 130, 246, 0.8)'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        cutout: '60%'
                    }
                });
            }
        });
        
        // Print function
        function printReport() {
            window.print();
        }
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            
            // Add title
            doc.setFontSize(16);
            doc.text('ROTC Attendance Report', 14, 15);
            
            doc.setFontSize(10);
            doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);
            doc.text(`Period: <?php echo date('M d, Y', strtotime($filter_date_from)); ?> - <?php echo date('M d, Y', strtotime($filter_date_to)); ?>`, 14, 28);
            
            // Prepare table data
            const tableData = [];
            document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
                if (!row.querySelector('td[colspan]')) {
                    const rowData = [];
                    row.querySelectorAll('td').forEach((cell, index) => {
                        if (index < 12) { // Exclude remarks column for PDF
                            let text = cell.textContent.trim().replace(/\s+/g, ' ');
                            if (index === 10) { // Status column
                                const statusSpan = cell.querySelector('.badge');
                                if (statusSpan) text = statusSpan.textContent.trim();
                            }
                            rowData.push(text);
                        }
                    });
                    tableData.push(rowData);
                }
            });
            
            // Add table
            doc.autoTable({
                head: [['Date', 'Event', 'Cadet', 'Student ID', 'Course', 'Platoon', 'Company', 'Check In', 'Check Out', 'Duration', 'Status', 'Verified By']],
                body: tableData,
                startY: 35,
                styles: { fontSize: 7 },
                headStyles: { fillColor: [102, 126, 234] }
            });
            
            doc.save('attendance_report.pdf');
        }
        
        // Export to Excel
        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            
            // Main data sheet
            const mainData = [];
            
            // Add headers
            const headers = ['Date', 'Event', 'Location', 'Cadet Name', 'Student ID', 'Course', 'Platoon', 'Company', 
                           'Check In Time', 'Check In Date', 'Check Out Time', 'Check Out Date', 'Duration (minutes)', 
                           'Status', 'Verified By', 'Remarks'];
            mainData.push(headers);
            
            // Add data
            document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
                if (!row.querySelector('td[colspan]')) {
                    const rowData = [];
                    const cells = row.querySelectorAll('td');
                    
                    // Date
                    rowData.push(cells[0].textContent.trim());
                    
                    // Event
                    const eventDiv = cells[1].querySelector('.font-medium');
                    rowData.push(eventDiv ? eventDiv.textContent.trim() : cells[1].textContent.trim());
                    
                    // Location
                    const locationDiv = cells[1].querySelector('.text-xs');
                    rowData.push(locationDiv ? locationDiv.textContent.trim() : '');
                    
                    // Cadet Name
                    rowData.push(cells[2].textContent.trim());
                    
                    // Student ID
                    rowData.push(cells[3].textContent.trim());
                    
                    // Course
                    rowData.push(cells[4].textContent.trim());
                    
                    // Platoon
                    rowData.push(cells[5].textContent.trim());
                    
                    // Company
                    rowData.push(cells[6].textContent.trim());
                    
                    // Check In Time
                    const checkInDiv = cells[7].querySelector('.font-medium');
                    rowData.push(checkInDiv ? checkInDiv.textContent.trim() : '');
                    
                    // Check In Date
                    const checkInDate = cells[7].querySelector('.text-xs');
                    rowData.push(checkInDate ? checkInDate.textContent.trim() : '');
                    
                    // Check Out Time
                    const checkOutDiv = cells[8].querySelector('.font-medium');
                    rowData.push(checkOutDiv ? checkOutDiv.textContent.trim() : '');
                    
                    // Check Out Date
                    const checkOutDate = cells[8].querySelector('.text-xs');
                    rowData.push(checkOutDate ? checkOutDate.textContent.trim() : '');
                    
                    // Duration
                    const durationSpan = cells[9].querySelector('span');
                    rowData.push(durationSpan ? durationSpan.textContent.trim() : '');
                    
                    // Status
                    const statusSpan = cells[10].querySelector('.badge');
                    rowData.push(statusSpan ? statusSpan.textContent.trim() : cells[10].textContent.trim());
                    
                    // Verified By
                    rowData.push(cells[11].textContent.trim());
                    
                    // Remarks
                    const remarksIcon = cells[12].querySelector('.fa-comment');
                    rowData.push(remarksIcon ? 'Has remarks' : '');
                    
                    mainData.push(rowData);
                }
            });
            
            const ws = XLSX.utils.aoa_to_sheet(mainData);
            XLSX.utils.book_append_sheet(wb, ws, 'Attendance');
            
            // Summary sheet
            const summaryData = [
                ['Summary Statistics'],
                ['Total Records', '<?php echo $summary['total_records'] ?? 0; ?>'],
                ['Total Cadets', '<?php echo $summary['total_cadets'] ?? 0; ?>'],
                ['Total Events', '<?php echo $summary['total_events'] ?? 0; ?>'],
                ['Present', '<?php echo $summary['present_count'] ?? 0; ?>'],
                ['Late', '<?php echo $summary['late_count'] ?? 0; ?>'],
                ['Absent', '<?php echo $summary['absent_count'] ?? 0; ?>'],
                ['Excused', '<?php echo $summary['excused_count'] ?? 0; ?>'],
                ['Average Duration (minutes)', '<?php echo round($summary['avg_duration'] ?? 0); ?>']
            ];
            
            const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary');
            
            XLSX.writeFile(wb, 'attendance_report.xlsx');
        }
        
        // Export to CSV
        function exportToCSV() {
            const rows = [];
            
            // Add headers
            rows.push(['Date', 'Event', 'Location', 'Cadet', 'Student ID', 'Course', 'Platoon', 'Company', 
                      'Check In', 'Check Out', 'Duration', 'Status', 'Verified By', 'Remarks']);
            
            // Add data
            document.querySelectorAll('#attendanceTable tbody tr').forEach(row => {
                if (!row.querySelector('td[colspan]')) {
                    const rowData = [];
                    const cells = row.querySelectorAll('td');
                    
                    // Date
                    rowData.push(cells[0].textContent.trim());
                    
                    // Event
                    const eventDiv = cells[1].querySelector('.font-medium');
                    rowData.push(eventDiv ? eventDiv.textContent.trim() : cells[1].textContent.trim());
                    
                    // Location
                    const locationDiv = cells[1].querySelector('.text-xs');
                    rowData.push(locationDiv ? locationDiv.textContent.trim() : '');
                    
                    // Cadet
                    rowData.push(cells[2].textContent.trim());
                    
                    // Student ID
                    rowData.push(cells[3].textContent.trim());
                    
                    // Course
                    rowData.push(cells[4].textContent.trim());
                    
                    // Platoon
                    rowData.push(cells[5].textContent.trim());
                    
                    // Company
                    rowData.push(cells[6].textContent.trim());
                    
                    // Check In
                    const checkInDiv = cells[7].querySelector('.font-medium');
                    const checkInDate = cells[7].querySelector('.text-xs');
                    let checkIn = checkInDiv ? checkInDiv.textContent.trim() : '';
                    if (checkInDate) checkIn += ' ' + checkInDate.textContent.trim();
                    rowData.push(checkIn);
                    
                    // Check Out
                    const checkOutDiv = cells[8].querySelector('.font-medium');
                    const checkOutDate = cells[8].querySelector('.text-xs');
                    let checkOut = checkOutDiv ? checkOutDiv.textContent.trim() : '';
                    if (checkOutDate) checkOut += ' ' + checkOutDate.textContent.trim();
                    rowData.push(checkOut);
                    
                    // Duration
                    const durationSpan = cells[9].querySelector('span');
                    rowData.push(durationSpan ? durationSpan.textContent.trim() : '');
                    
                    // Status
                    const statusSpan = cells[10].querySelector('.badge');
                    rowData.push(statusSpan ? statusSpan.textContent.trim() : cells[10].textContent.trim());
                    
                    // Verified By
                    rowData.push(cells[11].textContent.trim());
                    
                    // Remarks
                    const remarksIcon = cells[12].querySelector('.fa-comment');
                    rowData.push(remarksIcon ? 'Has remarks' : '');
                    
                    rows.push(rowData.map(cell => `"${cell}"`));
                }
            });
            
            const csvContent = rows.map(row => row.join(',')).join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_report_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>