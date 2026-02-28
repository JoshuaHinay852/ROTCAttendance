<?php
// header.php
// Top navigation bar
?>
<!-- Top Bar -->
<header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800"><?php echo isset($pageTitle) ? $pageTitle : 'Admin Panel'; ?></h2>
            <p class="text-gray-600">Welcome back, <?php echo isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin'; ?>!</p>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
                <button onclick="toggleNotifications()" class="p-2 text-gray-600 hover:text-blue-600 relative">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if (isset($pendingAdmins) && $pendingAdmins > 0): ?>
                        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notificationsDropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50">
                    <!-- Notifications content would go here -->
                    <div class="p-4 text-center">
                        <p class="text-sm text-gray-500">Notifications dropdown</p>
                    </div>
                </div>
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
    </div>
</header>