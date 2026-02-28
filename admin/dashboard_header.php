<?php
// dashboard_header.php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin (this is a backup check)
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize PDO if not already available
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get pending count for badge - with error handling
$pending_count = 0;
try {
    $stmt = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM cadet_excuses WHERE status = 'pending' AND (is_archived IS NULL OR is_archived = FALSE)) + 
        (SELECT COUNT(*) FROM mp_excuses WHERE status = 'pending' AND (is_archived IS NULL OR is_archived = FALSE)) as pending_count");
    $stmt->execute();
    $pending_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // Silently fail - tables might not exist yet
    error_log("Error fetching pending count: " . $e->getMessage());
}

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>
<!-- Sidebar -->
<div class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white flex flex-col">
    <!-- Logo -->
    <div class="p-6 border-b border-gray-800 flex items-center space-x-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center overflow-hidden">
            <img src="../assets/images/unit_logo-removebg-preview.png" alt="ROTC Logo" class="w-full h-full object-contain">
        </div>
        <div class="logo-text">
            <h1 class="text-xl font-bold">ROTC Admin</h1>
            <p class="text-xs text-gray-400">BISU Balilihan</p>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        <!-- Dashboard -->
        <a href="dashboard.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'dashboard.php' ? 'active-nav' : ''; ?>"
           data-page="dashboard">
            <i class="fas fa-tachometer-alt w-6 text-center"></i>
            <span class="sidebar-text font-medium">Dashboard</span>
        </a>
        
        <!-- Account Management Section -->
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Account Management</p>
        </div>
        
        <a href="cadets.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'cadets.php' ? 'active-nav' : ''; ?>"
           data-page="cadets">
            <i class="fas fa-user-graduate w-6 text-center"></i>
            <span class="sidebar-text">Cadet Accounts</span>
        </a>
        
        <a href="mp_accounts.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'mp_accounts.php' ? 'active-nav' : ''; ?>"
           data-page="mp">
            <i class="fas fa-user-shield w-6 text-center"></i>
            <span class="sidebar-text">MP Accounts</span>
        </a>
        
        <a href="admins.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'admins.php' ? 'active-nav' : ''; ?>"
           data-page="admins">
            <i class="fas fa-user-tie w-6 text-center"></i>
            <span class="sidebar-text">Admin Accounts</span>
        </a>
        
        <a href="archives.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'archives.php' ? 'active-nav' : ''; ?>"
           data-page="archives">
            <i class="fas fa-archive w-6 text-center"></i>
            <span class="sidebar-text">Archives</span>
        </a>
        
        <!-- Event Management Section -->
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Event Management</p>
        </div>

        <a href="events.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'events.php' ? 'active-nav' : ''; ?>"
           data-page="events">
            <i class="fas fa-calendar-alt w-6 text-center"></i>
            <span class="sidebar-text">Events</span>
        </a>

        <!-- In the sidebar navigation menu, add this link -->
        <a href="reports.php" class="sidebar-menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie w-6"></i>
            <span class="sidebar-text">Reports</span>
        </a>

    

        <!-- Excuse Management Section -->
        <div class="pt-4">
            <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Excuse Management</p>
        </div>

        <a href="excuse_management.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'excuse_management.php' ? 'active-nav' : ''; ?>"
           data-page="excuse">
            <i class="fas fa-file-signature w-6 text-center"></i>
            <span class="sidebar-text">Excuse Requests</span>
            <?php if ($pending_count > 0): ?>
                <span class="ml-auto bg-gradient-to-r from-yellow-500 to-amber-600 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg animate-pulse">
                    <?php echo $pending_count; ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="announcements.php" 
           class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'announcements.php' ? 'active-nav' : ''; ?>"
           data-page="announcements">
            <i class="fas fa-bullhorn w-6 text-center"></i>
            <span class="sidebar-text">Announcements</span>
        </a>

        <!-- Add this in the Account Management section or create a new section -->
<div class="pt-4">
    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider sidebar-text">Organization</p>
</div>

<a href="officers.php" 
   class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-800 transition-all duration-200 <?php echo $currentPage == 'officers.php' ? 'active-nav' : ''; ?>"
   data-page="officers">
    <i class="fas fa-chess-queen w-6 text-center"></i>
    <span class="sidebar-text">Officer Directory</span>
</a>
    </nav>
    
    <!-- User Profile -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center flex-shrink-0 font-bold text-white">
                <?php echo $admin_initial; ?>
            </div>
            <div class="flex-1 sidebar-text min-w-0">
                <p class="font-medium text-sm truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($admin_role); ?></p>
            </div>
            <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-800 rounded-lg transition-colors flex-shrink-0" id="sidebarToggleBtn" title="Toggle Sidebar">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
    </div>
</div>

<!-- Page Transition Overlay -->
<div id="pageTransition" class="fixed inset-0 z-[9999] bg-gradient-to-r from-blue-600 to-indigo-600 transition-all duration-300 pointer-events-none opacity-0"></div>

<script>
// Smooth page transition system
function navigateToPage(url) {
    const transition = document.getElementById('pageTransition');
    if (!transition) return;
    
    // Start transition
    transition.classList.remove('opacity-0');
    transition.classList.add('opacity-100');
    
    // Wait for fade in, then navigate
    setTimeout(() => {
        window.location.href = url;
    }, 300);
}

// Toggle Sidebar function
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');
    const logoText = document.querySelector('.logo-text');
    const sectionTitles = document.querySelectorAll('.sidebar .pt-4');
    const navLinks = document.querySelectorAll('.nav-link');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;
    
    if (!sidebar) return;
    
    sidebar.classList.toggle('collapsed');
    const isCollapsed = sidebar.classList.contains('collapsed');
    
    if (isCollapsed) {
        // Collapsed state - 80px width
        sidebar.style.width = '80px';
        
        if (mainContent) {
            mainContent.classList.remove('ml-64');
            mainContent.classList.add('ml-20');
        }
        
        // Hide all text elements
        sidebarTexts.forEach(text => {
            if (text) {
                text.style.display = 'none';
                text.style.opacity = '0';
                text.style.visibility = 'hidden';
            }
        });
        
        if (logoText) {
            logoText.style.display = 'none';
            logoText.style.opacity = '0';
            logoText.style.visibility = 'hidden';
        }
        
        // Hide section titles
        sectionTitles.forEach(section => {
            if (section) {
                section.style.display = 'none';
                section.style.opacity = '0';
                section.style.visibility = 'hidden';
            }
        });
        
        // Change icon to chevron-right
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }
        
        // Center nav items
        navLinks.forEach(link => {
            link.style.justifyContent = 'center';
            link.style.paddingLeft = '0.75rem';
            link.style.paddingRight = '0.75rem';
        });
        
        // Add collapsed class
        sidebar.classList.add('collapsed-state');
        
    } else {
        // Expanded state - 256px (16rem)
        sidebar.style.width = '16rem';
        
        if (mainContent) {
            mainContent.classList.remove('ml-20');
            mainContent.classList.add('ml-64');
        }
        
        // Show all text elements
        sidebarTexts.forEach(text => {
            if (text) {
                text.style.display = '';
                text.style.opacity = '';
                text.style.visibility = '';
            }
        });
        
        if (logoText) {
            logoText.style.display = '';
            logoText.style.opacity = '';
            logoText.style.visibility = '';
        }
        
        // Show section titles
        sectionTitles.forEach(section => {
            if (section) {
                section.style.display = '';
                section.style.opacity = '';
                section.style.visibility = '';
            }
        });
        
        // Reset icon to chevron-left
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
        
        // Reset nav items alignment
        navLinks.forEach(link => {
            link.style.justifyContent = '';
            link.style.paddingLeft = '';
            link.style.paddingRight = '';
        });
        
        // Remove collapsed class
        sidebar.classList.remove('collapsed-state');
    }
    
    // Save state to localStorage
    try {
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    } catch (e) {
        // Ignore localStorage errors
    }
    
    // Trigger resize event for any charts or responsive elements
    window.dispatchEvent(new Event('resize'));
    window.dispatchEvent(new CustomEvent('sidebarToggle', { 
        detail: { collapsed: isCollapsed } 
    }));
}

document.addEventListener('sidebarToggle', function(e) {
    const mainContent = document.getElementById('mainContent');
    if (mainContent) {
        if (e.detail.collapsed) {
            mainContent.classList.add('sidebar-collapsed');
        } else {
            mainContent.classList.remove('sidebar-collapsed');
        }
    }
});

// Initialize sidebar state
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const transition = document.getElementById('pageTransition');
    const sidebar = document.querySelector('.sidebar');
    
    // Page load animation
    setTimeout(() => {
        if (transition) {
            transition.style.opacity = '0';
            setTimeout(() => {
                transition.classList.add('opacity-0');
            }, 300);
        }
    }, 100);
    
    // Add click handlers to nav links
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Skip if special keys are pressed, external link, or current page
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || 
                this.target === '_blank' || 
                this.href === window.location.href) {
                return;
            }
            
            e.preventDefault();
            const url = this.href;
            
            // Add subtle click feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
            
            // Navigate with transition
            navigateToPage(url);
        });
    });
    
    // Hover effects
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active-nav') && !sidebar?.classList.contains('collapsed')) {
                this.style.transform = 'translateX(4px)';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Check if there's a saved sidebar state
    try {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed && sidebar) {
            // Apply collapsed state after a brief delay
            setTimeout(() => {
                toggleSidebar();
            }, 10);
        }
    } catch (e) {
        // Ignore localStorage errors
    }
    
    // Fix for main-content class if it exists
    const mainContent = document.querySelector('.main-content');
    if (mainContent && !mainContent.classList.contains('ml-64') && !mainContent.classList.contains('ml-20')) {
        mainContent.classList.add('ml-64');
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (!sidebar) return;
    
    if (window.innerWidth < 768) {
        // Mobile view - auto collapse
        const isCollapsed = sidebar.classList.contains('collapsed');
        if (!isCollapsed) {
            sidebar.style.width = '80px';
            if (mainContent) {
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-20');
            }
            
            // Hide text elements
            document.querySelectorAll('.sidebar-text, .logo-text, .sidebar .pt-4').forEach(el => {
                if (el) {
                    el.style.display = 'none';
                    el.style.opacity = '0';
                    el.style.visibility = 'hidden';
                }
            });
            
            // Center nav items
            document.querySelectorAll('.nav-link').forEach(link => {
                link.style.justifyContent = 'center';
                link.style.paddingLeft = '0.75rem';
                link.style.paddingRight = '0.75rem';
            });
            
            sidebar.classList.add('collapsed-state');
        }
    }
});
</script>

<style>

    /* Add to your existing sidebar styles */
.sidebar-menu-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    margin: 0.25rem 0;
    border-radius: 0.5rem;
    color: #4b5563;
    transition: all 0.2s;
}

.sidebar-menu-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.sidebar-menu-item.active {
    background: #3b82f6;
    color: white;
}

.sidebar-menu-item i {
    font-size: 1.1rem;
    width: 1.5rem;
}

.sidebar.collapsed .sidebar-text {
    display: none;
}

    /* Pending badge animation */
    @keyframes gentle-pulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.9;
            transform: scale(1.05);
        }
    }

    .nav-link .animate-pulse {
        animation: gentle-pulse 2s infinite;
    }

    /* Active state for excuse management */
    .nav-link.active-nav i.fa-file-signature {
        color: #60a5fa;
        filter: drop-shadow(0 0 8px rgba(96, 165, 250, 0.5));
    }

    /* Sidebar base styles */
    .sidebar {
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-x: hidden;
        overflow-y: auto;
        width: 16rem; /* 256px */
    }

    /* Sidebar collapsed state */
    .sidebar.collapsed-state {
        width: 80px !important;
    }

    /* Sidebar hover effects */
    .nav-link {
        transition: all 0.2s ease;
        position: relative;
        white-space: nowrap;
    }

    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .sidebar:not(.collapsed) .nav-link:hover {
        padding-left: 1.25rem;
    }

    /* Active nav state */
    .nav-link.active-nav {
        background-color: rgba(59, 130, 246, 0.1);
        border-left: 3px solid #3b82f6;
    }

    .nav-link.active-nav:hover {
        background-color: rgba(59, 130, 246, 0.15);
    }

    /* Active nav icon subtle animation */
    .nav-link.active-nav i {
        color: #60a5fa;
        animation: iconPulse 2s infinite;
    }

    @keyframes iconPulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }

    /* Text elements transition */
    .sidebar-text,
    .logo-text,
    .sidebar .pt-4 {
        transition: opacity 0.2s ease, visibility 0.2s ease;
        white-space: nowrap;
    }

    /* Page transition overlay */
    #pageTransition {
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    }

    /* Smooth page load animation */
    .main-content {
        animation: fadeInUp 0.4s ease-out;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Subtle sidebar item animations */
    .nav-link i {
        transition: transform 0.2s ease;
    }

    .nav-link:hover i {
        transform: translateX(2px);
    }

    .sidebar.collapsed-state .nav-link:hover i {
        transform: scale(1.1);
    }

    /* Active state indicator */
    .nav-link.active-nav::after {
        content: '';
        position: absolute;
        right: 1rem;
        width: 6px;
        height: 6px;
        background-color: #60a5fa;
        border-radius: 50%;
        animation: blink 2s infinite;
    }

    .sidebar.collapsed-state .nav-link.active-nav::after {
        right: 0.5rem;
    }

    @keyframes blink {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    /* Card hover effects */
    .card-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }

    /* Button hover effects */
    button:hover, 
    a.button:hover {
        transform: translateY(-1px);
        transition: transform 0.2s ease;
    }

    /* Dropdown animations */
    .dropdown-menu {
        animation: slideDown 0.15s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Loading states */
    .loading {
        position: relative;
        overflow: hidden;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: shimmer 1.5s infinite;
    }

    @keyframes shimmer {
        100% {
            left: 100%;
        }
    }

    /* Collapsed sidebar specific styles */
    .sidebar.collapsed-state .nav-link {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
        justify-content: center !important;
    }

    .sidebar.collapsed-state .nav-link i {
        margin-right: 0 !important;
        font-size: 1.25rem;
    }

    .sidebar.collapsed-state .flex.items-center.space-x-3 {
        justify-content: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    /* Tooltip for collapsed sidebar */
    .sidebar.collapsed-state .nav-link {
        position: relative;
    }

    .sidebar.collapsed-state .nav-link:hover::before {
        content: attr(data-page);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: #1f2937;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        white-space: nowrap;
        margin-left: 1rem;
        z-index: 60;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        pointer-events: none;
        text-transform: capitalize;
    }

    .sidebar.collapsed-state .nav-link:hover::after {
        content: '';
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 0.5rem;
        border-width: 5px;
        border-style: solid;
        border-color: transparent #1f2937 transparent transparent;
        z-index: 60;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar {
            width: 80px !important;
        }
        
        .sidebar .sidebar-text,
        .sidebar .logo-text,
        .sidebar .pt-4 {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        .main-content {
            margin-left: 80px !important;
        }
        
        .nav-link {
            justify-content: center !important;
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
        
        .nav-link i {
            margin-right: 0 !important;
            font-size: 1.25rem;
        }
    }

    /* Print styles */
    @media print {
        .sidebar {
            display: none;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
    }

    /* Custom scrollbar for sidebar */
    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: #1f2937;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #4b5563;
        border-radius: 2px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #6b7280;
    }

    /* Smooth width transition for all elements */
    * {
        transition-property: width, margin, padding, transform, opacity, visibility;
        transition-duration: 0.2s;
        transition-timing-function: ease;
    }

    /* Fix for badge in collapsed state */
    .sidebar.collapsed-state .nav-link .ml-auto {
        display: none !important;
    }
</style>