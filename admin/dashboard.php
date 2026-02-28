<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once 'archive_functions.php';
require_once '../includes/event_status_helper.php';

// Check if admin is logged in and account is approved
requireAdminLogin();

// Optional: Check session timeout
validateSessionTimeout();

// Check account status (will redirect if pending/denied)
checkAccountStatus();

// Keep event statuses aligned with current date/time before dashboard metrics.
ensureEventStatusesAreCurrent($pdo);

try {
    $event_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'scheduled' AND event_date = CURDATE() THEN 1 ELSE 0 END) as today_events,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_events
        FROM events
    ")->fetch();
} catch (PDOException $e) {
    $event_stats = ['total_events' => 0, 'today_events' => 0, 'ongoing_events' => 0];
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get archive statistics (using the function from archive_functions.php)
$archiveStats = getArchiveStats();

// Get statistics for dashboard
try {
    // Get admin counts with different statuses
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_admins,
        SUM(CASE WHEN account_status = 'approved' THEN 1 ELSE 0 END) as approved_admins,
        SUM(CASE WHEN account_status = 'pending' THEN 1 ELSE 0 END) as pending_admins,
        SUM(CASE WHEN account_status = 'denied' THEN 1 ELSE 0 END) as denied_admins
        FROM admins");
    $adminStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $adminCount = $adminStats['total_admins'] ?? 0;
    $approvedAdmins = $adminStats['approved_admins'] ?? 0;
    $pendingAdmins = $adminStats['pending_admins'] ?? 0;
    $deniedAdmins = $adminStats['denied_admins'] ?? 0;
    
    // Get cadet and MP counts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_accounts WHERE status = 'active'");
    $cadetResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $cadetCount = $cadetResult['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mp_accounts WHERE status = 'active'");
    $mpResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $mpCount = $mpResult['count'] ?? 0;
    
    // Get total cadets (including inactive)
    $stmt = $pdo->query("SELECT COUNT(*) as total_count FROM cadet_accounts");
    $cadetTotalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $cadetTotalCount = $cadetTotalResult['total_count'] ?? 0;
    
    // Get total MP (including inactive)
    $stmt = $pdo->query("SELECT COUNT(*) as total_count FROM mp_accounts");
    $mpTotalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $mpTotalCount = $mpTotalResult['total_count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'scheduled'");
    $eventResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $eventCount = $eventResult['count'] ?? 0;
    
    // Get recent activity
    $stmt = $pdo->query("
        (SELECT 'cadet' as type, CONCAT(first_name, ' ', last_name) as name, 
                'Account created' as action, created_at as timestamp
         FROM cadet_accounts 
         ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'mp' as type, CONCAT(first_name, ' ', last_name) as name, 
                'Account created' as action, created_at as timestamp
         FROM mp_accounts 
         ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'event' as type, event_name as name, 
                'Event scheduled' as action, created_at as timestamp
         FROM events 
         ORDER BY created_at DESC LIMIT 3)
        UNION
        (SELECT 'admin' as type, CONCAT(full_name, ' (', account_status, ')') as name, 
                'Admin account created' as action, created_at as timestamp
         FROM admins 
         ORDER BY created_at DESC LIMIT 3)
        ORDER BY timestamp DESC LIMIT 8
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Unable to load dashboard data. Please try again.";
    error_log("Dashboard error: " . $e->getMessage());
    
    // Set default values on error
    $adminCount = $approvedAdmins = $pendingAdmins = $deniedAdmins = 0;
    $cadetCount = $mpCount = $cadetTotalCount = $mpTotalCount = $eventCount = 0;
    $recentActivity = [];
}

// Set page title for header
$pageTitle = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ROTC Attendance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
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
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .active-nav {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
            border-left: 4px solid #3b82f6;
        }
        
        .table-row-hover:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .dropdown-menu {
            animation: slideDown 0.2s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 6px;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .stat-approved { color: #10b981; }
        .stat-pending { color: #f59e0b; }
        .stat-denied { color: #ef4444; }
        .stat-active { color: #10b981; }
        .stat-inactive { color: #6b7280; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    
    <?php include 'dashboard_header.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content ml-64 min-h-screen">
        <?php include 'includes/dashboard_topbar.php'; ?>
        
        <!-- Dashboard Content -->
        <main class="p-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Admin Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Admin Accounts</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $adminCount; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-user-tie text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="admins.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            View all <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>

                <!-- Archived Accounts Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Archived Accounts</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $archiveStats['total_archived']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <i class="fas fa-archive text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="archives.php" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium flex items-center">
                            Manage archives <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Cadet Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Cadet Accounts</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $cadetTotalCount; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-user-graduate text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="cadets.php" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                            Manage cadets <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                
                <!-- MP Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">MP Accounts</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $mpTotalCount; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-user-shield text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="mp_accounts.php" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center">
                            Manage MP <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="p-4 hover:bg-gray-50 table-row-hover">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center 
                                            <?php echo $activity['type'] == 'cadet' ? 'bg-green-100 text-green-600' : 
                                                   ($activity['type'] == 'mp' ? 'bg-purple-100 text-purple-600' : 
                                                   ($activity['type'] == 'event' ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600')); ?>">
                                            <?php echo $activity['type'] == 'cadet' ? '<i class="fas fa-user-graduate text-sm"></i>' : 
                                                   ($activity['type'] == 'mp' ? '<i class="fas fa-user-shield text-sm"></i>' : 
                                                   ($activity['type'] == 'event' ? '<i class="fas fa-calendar-alt text-sm"></i>' : '<i class="fas fa-user-tie text-sm"></i>')); ?>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['name']); ?></p>
                                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($activity['action']); ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            <?php 
                                                $timestamp = strtotime($activity['timestamp']);
                                                echo date('M j, g:i A', $timestamp);
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <i class="fas fa-inbox text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-600">No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <a href="cadets.php?action=add" class="p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <i class="fas fa-user-plus text-blue-600 text-xl mb-2"></i>
                                        <p class="font-medium text-gray-800 group-hover:text-blue-700">Add Cadet</p>
                                    </div>
                                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-blue-600"></i>
                                </div>
                            </a>
                            
                            <a href="mp_accounts.php?action=add" class="p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <i class="fas fa-user-shield text-purple-600 text-xl mb-2"></i>
                                        <p class="font-medium text-gray-800 group-hover:text-purple-700">Add MP</p>
                                    </div>
                                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-purple-600"></i>
                                </div>
                            </a>
                            
                            <a href="events.php?action=add" class="p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <i class="fas fa-calendar-plus text-orange-600 text-xl mb-2"></i>
                                        <p class="font-medium text-gray-800 group-hover:text-orange-700">Create Event</p>
                                    </div>
                                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-orange-600"></i>
                                </div>
                            </a>
                            
                            <a href="archives.php" class="p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors group">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <i class="fas fa-archive text-yellow-600 text-xl mb-2"></i>
                                        <p class="font-medium text-gray-800 group-hover:text-yellow-700">Manage Archives</p>
                                    </div>
                                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-yellow-600"></i>
                                </div>
                            </a>
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
        
        // Toggle Notifications Dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.getElementById('profileDropdown').classList.add('hidden');
        }
        
        // Toggle Profile Dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.getElementById('notificationsDropdown').classList.add('hidden');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (!event.target.closest('.relative')) {
                notificationsDropdown.classList.add('hidden');
                profileDropdown.classList.add('hidden');
            }
        });
        
        // Auto-refresh dashboard every 60 seconds
        setTimeout(() => {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>
