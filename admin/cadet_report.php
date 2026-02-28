<?php
// cadet_report.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

$cadet_id = isset($_GET['cadet_id']) ? intval($_GET['cadet_id']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

if (!$cadet_id) {
    header('Location: reports.php');
    exit();
}

// Get cadet details
$stmt = $pdo->prepare("SELECT * FROM cadet_accounts WHERE id = ?");
$stmt->execute([$cadet_id]);
$cadet = $stmt->fetch();

if (!$cadet) {
    header('Location: reports.php');
    exit();
}

// Get attendance records for this cadet
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        e.location,
        e.status as event_status,
        TIMEDIFF(a.check_out_time, a.check_in_time) as duration,
        TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) as duration_minutes
    FROM event_attendance a
    INNER JOIN events e ON a.event_id = e.id
    WHERE a.cadet_id = ? AND YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?
    ORDER BY e.event_date DESC, a.check_in_time DESC
");
$stmt->execute([$cadet_id, $year, $month]);
$attendance = $stmt->fetchAll();

// Get monthly statistics
$stats_sql = "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN a.attendance_status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.attendance_status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.attendance_status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.attendance_status = 'excused' THEN 1 ELSE 0 END) as excused,
                AVG(CASE 
                    WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                    ELSE NULL 
                END) as avg_duration,
                SUM(CASE WHEN a.check_in_time IS NOT NULL AND a.check_out_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                    ELSE 0 END) as total_minutes
            FROM event_attendance a
            INNER JOIN events e ON a.event_id = e.id
            WHERE a.cadet_id = ? AND YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$cadet_id, $year, $month]);
$stats = $stmt->fetch();

// Get yearly summary
$yearly_sql = "SELECT 
                MONTH(e.event_date) as month,
                COUNT(*) as total,
                SUM(CASE WHEN a.attendance_status = 'present' THEN 1 ELSE 0 END) as present
            FROM event_attendance a
            INNER JOIN events e ON a.event_id = e.id
            WHERE a.cadet_id = ? AND YEAR(e.event_date) = ?
            GROUP BY MONTH(e.event_date)
            ORDER BY month";
$stmt = $pdo->prepare($yearly_sql);
$stmt->execute([$cadet_id, $year]);
$yearly_data = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Report | ROTC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
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
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <div class="p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6 no-print">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Cadet Attendance Report</h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="reports.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <!-- Month Selector -->
            <div class="bg-white rounded-lg shadow p-4 mb-6 no-print">
                <form method="GET" class="flex gap-4 items-end">
                    <input type="hidden" name="cadet_id" value="<?php echo $cadet_id; ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select name="year" class="border rounded-lg px-3 py-2">
                            <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                        <select name="month" class="border rounded-lg px-3 py-2">
                            <?php for($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                        View
                    </button>
                </form>
            </div>
            
            <!-- Cadet Info -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Student ID</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($cadet['student_id']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Course</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($cadet['course']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Platoon</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($cadet['platoon']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Company</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($cadet['company']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Events</p>
                    <p class="text-2xl font-bold"><?php echo $stats['total_events'] ?? 0; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Present</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['present'] ?? 0; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Late</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['late'] ?? 0; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Absent</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['absent'] ?? 0; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Attendance Rate</p>
                    <p class="text-2xl font-bold text-blue-600">
                        <?php 
                        $total = $stats['total_events'] ?? 1;
                        $present = ($stats['present'] ?? 0) + ($stats['late'] ?? 0);
                        echo round(($present / $total) * 100) . '%';
                        ?>
                    </p>
                </div>
            </div>
            
            <!-- Attendance Records -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h3 class="font-semibold">Attendance Records for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Check In</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Check Out</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        No attendance records for this period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($record['event_date'])); ?></td>
                                        <td class="px-4 py-2">
                                            <div class="font-medium"><?php echo htmlspecialchars($record['event_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($record['location']); ?></div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <?php echo date('g:i A', strtotime($record['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '—'; ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '—'; ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <?php
                                            if ($record['duration_minutes']) {
                                                $hours = floor($record['duration_minutes'] / 60);
                                                $mins = $record['duration_minutes'] % 60;
                                                echo $hours > 0 ? $hours . 'h ' : '';
                                                echo $mins . 'm';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 py-2">
                                            <span class="badge badge-<?php echo $record['attendance_status']; ?>">
                                                <?php echo ucfirst($record['attendance_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>