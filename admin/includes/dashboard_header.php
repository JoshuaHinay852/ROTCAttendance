<?php
// sidebar.php
// This file should be included in admin pages after session checks

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin (this is a backup check)
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}
?>
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
        <a href="dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-tachometer-alt w-6 text-center"></i>
            <span class="sidebar-text font-medium">Dashboard</span>
        </a>
        
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Account Management</p>
        </div>
        
        <a href="cadets.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'cadets.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-user-graduate w-6 text-center"></i>
            <span class="sidebar-text">Cadet Accounts</span>
        </a>
        
        <a href="mp_accounts.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'mp_accounts.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-user-shield w-6 text-center"></i>
            <span class="sidebar-text">MP Accounts</span>
        </a>
        
        <a href="admins.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'admins.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-user-tie w-6 text-center"></i>
            <span class="sidebar-text">Admin Accounts</span>
        </a>
        
        <!-- Archives Link -->
        <a href="archives.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'archives.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-archive w-6 text-center"></i>
            <span class="sidebar-text">Archives</span>
        </a>
        
        <!-- Event Management section -->
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Event Management</p>
        </div>
        
        <a href="events.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-calendar-alt w-6 text-center"></i>
            <span class="sidebar-text">Events</span>
        </a>
        
        <a href="event_attendance.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'event_attendance.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-chart-bar w-6 text-center"></i>
            <span class="sidebar-text">Event Attendance</span>
        </a>
        
        <a href="event_reports.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'event_reports.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-chart-bar w-6 text-center"></i>
            <span class="sidebar-text">Event Reports</span>
        </a>
        
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Reports & Analytics</p>
        </div>
        
        <a href="reports_dashboard.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'reports_dashboard.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-tachometer-alt w-6 text-center"></i>
            <span class="sidebar-text">Reports Dashboard</span>
        </a>
        
        <!-- Announcements -->
        <a href="announcements.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-colors <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active-nav' : ''; ?>">
            <i class="fas fa-bullhorn w-6 text-center"></i>
            <span class="sidebar-text">Announcements</span>
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