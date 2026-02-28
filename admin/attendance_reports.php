<?php
// attendance_reports.php
// Comprehensive attendance reporting

require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get summary statistics
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.event_name,
        e.event_date,
        e.start_time,
        e.end_time,
        COUNT(DISTINCT eas.cadet_id) as total_attendees,
        SUM(CASE WHEN eas.check_in_status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN eas.check_in_status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN eas.check_in_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN eas.check_in_status = 'excuse' THEN 1 ELSE 0 END) as excuse_count,
        SUM(CASE WHEN eas.check_out_time IS NOT NULL THEN 1 ELSE 0 END) as checked_out_count,
        AVG(eas.total_duration_minutes) as avg_duration
    FROM events e
    LEFT JOIN event_attendance_summary eas ON e.id = eas.event_id
    WHERE e.deleted_at IS NULL 
      AND e.event_date BETWEEN ? AND ?
    GROUP BY e.id
    ORDER BY e.event_date DESC
");
$stmt->execute([$start_date, $end_date]);
$reports = $stmt->fetchAll();

// Calculate totals
$totals = [
    'events' => count($reports),
    'attendees' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'excuse' => 0,
    'checked_out' => 0
];

foreach ($reports as $report) {
    $totals['attendees'] += $report['total_attendees'];
    $totals['present'] += $report['present_count'];
    $totals['late'] += $report['late_count'];
    $totals['absent'] += $report['absent_count'];
    $totals['excuse'] += $report['excuse_count'];
    $totals['checked_out'] += $report['checked_out_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Attendance Reports</h1>
            <p class="text-gray-600">Comprehensive attendance analytics and summaries</p>
        </div>
        
        <!-- Date Filter -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="border rounded-lg px-4 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="border rounded-lg px-4 py-2">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Apply Filter
                </button>
            </form>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <p class="text-sm text-gray-600">Total Events</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $totals['events']; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <p class="text-sm text-gray-600">Total Attendance</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $totals['attendees']; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <p class="text-sm text-gray-600">Avg Duration</p>
                <p class="text-3xl font-bold text-blue-600">
                    <?php 
                    $avg = array_sum(array_column($reports, 'avg_duration')) / max(1, count($reports));
                    echo round($avg) . ' min';
                    ?>
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <p class="text-sm text-gray-600">Check-out Rate</p>
                <p class="text-3xl font-bold text-purple-600">
                    <?php 
                    $rate = $totals['checked_out'] / max(1, $totals['attendees']) * 100;
                    echo round($rate) . '%';
                    ?>
                </p>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Attendance by Status</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Attendance Trend</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        
        <!-- Reports Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h3 class="text-lg font-semibold text-gray-900">Event Attendance Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Present</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Late</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Absent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Excuse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Checked Out</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Avg Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($reports as $report): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($report['event_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M j, Y', strtotime($report['event_date'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-green-600 font-medium"><?php echo $report['present_count']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-yellow-600 font-medium"><?php echo $report['late_count']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-red-600 font-medium"><?php echo $report['absent_count']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-blue-600 font-medium"><?php echo $report['excuse_count']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-purple-600 font-medium"><?php echo $report['checked_out_count']; ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo round($report['avg_duration']); ?> min
                                </td>
                                <td class="px-6 py-4">
                                    <a href="event_attendance.php?event_id=<?php echo $report['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'Absent', 'Excuse'],
                datasets: [{
                    data: [
                        <?php echo $totals['present']; ?>,
                        <?php echo $totals['late']; ?>,
                        <?php echo $totals['absent']; ?>,
                        <?php echo $totals['excuse']; ?>
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6']
                }]
            }
        });
        
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($r) { 
                    return date('M j', strtotime($r['event_date'])); 
                }, $reports)); ?>,
                datasets: [{
                    label: 'Present',
                    data: <?php echo json_encode(array_column($reports, 'present_count')); ?>,
                    borderColor: '#10b981'
                }, {
                    label: 'Late',
                    data: <?php echo json_encode(array_column($reports, 'late_count')); ?>,
                    borderColor: '#f59e0b'
                }]
            }
        });
    </script>
</body>
</html>