<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Check if admin is logged in
requireAdminLogin();
validateSessionTimeout();
checkAccountStatus();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$attendance_records = [];
$events = [];

try {
    // Get all events for dropdown
    $stmt = $pdo->query("SELECT id, event_name, event_date FROM events ORDER BY event_date DESC");
    $events = $stmt->fetchAll();
    
    // Get attendance records
    $query = "
        SELECT 
            ea.*,
            e.event_name,
            e.event_date,
            CONCAT(c.first_name, ' ', c.last_name) as cadet_name,
            c.student_id as cadet_id,
            CONCAT(m.first_name, ' ', m.last_name) as mp_name
        FROM event_attendance ea
        LEFT JOIN events e ON ea.event_id = e.id
        LEFT JOIN cadet_accounts c ON ea.cadet_id = c.id
        LEFT JOIN mp_accounts m ON ea.mp_id = m.id
        ORDER BY ea.check_in_time DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->query($query);
    $attendance_records = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error loading attendance data: ' . $e->getMessage();
    error_log("Attendance error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records | ROTC Attendance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .sidebar-text {
            display: none;
        }
        
        .sidebar.collapsed .logo-text {
            display: none;
        }
        
        .main-content {
            transition: all 0.3s ease;
        }
        
        .active-nav {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
            border-left: 4px solid #3b82f6;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-present { background-color: #d1fae5; color: #065f46; }
        .status-late { background-color: #fef3c7; color: #92400e; }
        .status-absent { background-color: #fee2e2; color: #991b1b; }
        .status-excused { background-color: #e5e7eb; color: #374151; }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Sidebar -->
    <div class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white flex flex-col">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-800 flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center">
                <i class="fas fa-shield-alt text-white"></i>
            </div>
            <div class="logo-text">
                <h1 class="text-xl font-bold">ROTC Admin</h1>
                <p class="text-xs text-gray-400">BISU Balilihan</p>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span class="sidebar-text font-medium">Dashboard</span>
            </a>
            
            <div class="pt-4">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Account Management</p>
            </div>
            
            <a href="cadets.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-user-graduate w-6 text-center"></i>
                <span class="sidebar-text">Cadet Accounts</span>
            </a>
            
            <a href="mp_accounts.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-user-shield w-6 text-center"></i>
                <span class="sidebar-text">MP Accounts</span>
            </a>
            
            <a href="admins.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-user-tie w-6 text-center"></i>
                <span class="sidebar-text">Admin Accounts</span>
            </a>
            
            <a href="archives.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-archive w-6 text-center"></i>
                <span class="sidebar-text">Archives</span>
            </a>
            
            <div class="pt-4">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Event Management</p>
            </div>
            
            <a href="events.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-calendar-alt w-6 text-center"></i>
                <span class="sidebar-text">Events</span>
            </a>
            
            <a href="attendance.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors active-nav">
                <i class="fas fa-clipboard-check w-6 text-center"></i>
                <span class="sidebar-text">Attendance</span>
            </a>
            
            <div class="pt-4">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Reports</p>
            </div>
            
            <a href="reports.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors">
                <i class="fas fa-chart-bar w-6 text-center"></i>
                <span class="sidebar-text">Reports</span>
            </a>
        </nav>
        
        <!-- User Profile -->
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center">
                    <?php echo isset($_SESSION['admin_name']) ? strtoupper(substr($_SESSION['admin_name'], 0, 1)) : 'A'; ?>
                </div>
                <div class="flex-1 sidebar-text">
                    <p class="font-medium text-sm"><?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?></p>
                    <p class="text-xs text-gray-400"><?php echo isset($_SESSION['admin_role']) ? htmlspecialchars($_SESSION['admin_role']) : 'Administrator'; ?></p>
                </div>
                <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-800 rounded-lg transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content ml-64 min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Attendance Records</h2>
                    <p class="text-gray-600">View and manage attendance records</p>
                </div>
                
                <!-- Profile Dropdown -->
                <div class="relative">
                    <button onclick="toggleProfileDropdown()" class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white text-sm font-bold">
                            <?php echo isset($_SESSION['admin_name']) ? strtoupper(substr($_SESSION['admin_name'], 0, 1)) : 'A'; ?>
                        </div>
                        <i class="fas fa-chevron-down text-gray-600"></i>
                    </button>
                    
                    <!-- Profile Dropdown Menu -->
                    <div id="profileDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50">
                        <div class="p-4 border-b border-gray-200">
                            <p class="font-semibold text-gray-800"><?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?></p>
                            <p class="text-sm text-gray-600"><?php echo isset($_SESSION['admin_email']) ? htmlspecialchars($_SESSION['admin_email']) : 'admin@example.com'; ?></p>
                        </div>
                        <div class="py-2">
                            <a href="profile.php" class="flex items-center space-x-3 px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle w-5 text-center"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="settings.php" class="flex items-center space-x-3 px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog w-5 text-center"></i>
                                <span>Settings</span>
                            </a>
                            <div class="border-t border-gray-200 my-2"></div>
                            <a href="../logout.php" class="flex items-center space-x-3 px-4 py-2 text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="p-8">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold mb-4">Filter Attendance</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event</label>
                        <select name="event_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Events</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>">
                                    <?php echo htmlspecialchars($event['event_name']); ?> (<?php echo $event['event_date']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 w-full">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Attendance Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Recent Attendance Records</h3>
                        <p class="text-sm text-gray-600">Showing latest 50 records</p>
                    </div>
                    <button onclick="exportAttendance()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cadet</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">MP</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($attendance_records)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-clipboard-check text-4xl mb-2 text-gray-300"></i>
                                        <p>No attendance records found</p>
                                        <p class="text-sm mt-2">Attendance records will appear here once cadets check in for events</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['event_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($record['event_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['cadet_name']); ?></div>
                                            <div class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($record['cadet_id']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo date('M d, Y g:i A', strtotime($record['check_in_time'])); ?>
                                            <?php if ($record['check_out_time']): ?>
                                                <div class="text-xs text-gray-500">
                                                    Out: <?php echo date('g:i A', strtotime($record['check_out_time'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="status-badge status-<?php echo $record['attendance_status']; ?>">
                                                <?php echo ucfirst($record['attendance_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($record['mp_name']); ?></td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <button onclick="editAttendance(<?php echo $record['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteAttendance(<?php echo $record['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <p class="text-sm text-gray-600">
                            Total records: <?php echo count($attendance_records); ?>
                        </p>
                        <div class="flex space-x-2">
                            <button class="px-3 py-1 border border-gray-300 rounded text-sm">Previous</button>
                            <button class="px-3 py-1 bg-blue-600 text-white rounded text-sm">1</button>
                            <button class="px-3 py-1 border border-gray-300 rounded text-sm">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-20');
                document.querySelector('.sidebar i.fa-chevron-left').classList.remove('fa-chevron-left');
                document.querySelector('.sidebar i.fa-chevron-left').classList.add('fa-chevron-right');
            } else {
                mainContent.classList.remove('ml-20');
                mainContent.classList.add('ml-64');
                document.querySelector('.sidebar i.fa-chevron-right').classList.remove('fa-chevron-right');
                document.querySelector('.sidebar i.fa-chevron-right').classList.add('fa-chevron-left');
            }
        }
        
        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profileDropdown');
            if (!event.target.closest('.relative')) {
                profileDropdown.classList.add('hidden');
            }
        });
        
        // Attendance functions
        function editAttendance(id) {
            alert('Edit attendance feature coming soon!');
            // Implement edit modal
        }
        
        function deleteAttendance(id) {
            if (confirm('Are you sure you want to delete this attendance record?')) {
                // Implement delete functionality
                alert('Delete feature coming soon!');
            }
        }
        
        function exportAttendance() {
            alert('Export feature coming soon!');
            // Implement export to CSV/Excel
        }
    </script>
</body>
</html>