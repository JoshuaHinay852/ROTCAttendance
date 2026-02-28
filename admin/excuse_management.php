<?php
// excuse_management.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
// require_once 'archive_functions.php';
requireAdminLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Handle excuse status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_excuse_status'])) {
        $excuse_id = $_POST['excuse_id'];
        $status = sanitize_input($_POST['status']);
        $remarks = sanitize_input($_POST['remarks']);
        $user_type = sanitize_input($_POST['user_type']);
        
        // Update excuse status
        if ($user_type === 'cadet') {
            $stmt = $pdo->prepare("UPDATE cadet_excuses SET 
                status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? 
                WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE mp_excuses SET 
                status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? 
                WHERE id = ?");
        }
        
        if ($stmt->execute([$status, $_SESSION['admin_id'], $remarks, $excuse_id])) {
            // Log the action
            logExcuseAction($pdo, $excuse_id, $user_type, $status, $_SESSION['admin_id']);
            $_SESSION['message'] = "Excuse request has been " . strtoupper($status) . " successfully!";
        } else {
            $_SESSION['error'] = "Failed to update excuse status.";
        }
        header('Location: excuse_management.php');
        exit();
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'update_status') {
        $bulk_status = $_POST['bulk_status'];
        $excuse_ids = $_POST['excuse_ids'] ?? [];
        $user_types = $_POST['user_types'] ?? [];
        
        $success_count = 0;
        $fail_count = 0;
        
        for ($i = 0; $i < count($excuse_ids); $i++) {
            $excuse_id = $excuse_ids[$i];
            $user_type = $user_types[$i];
            
            if ($user_type === 'cadet') {
                $stmt = $pdo->prepare("UPDATE cadet_excuses SET 
                    status = ?, reviewed_by = ?, reviewed_at = NOW() 
                    WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE mp_excuses SET 
                    status = ?, reviewed_by = ?, reviewed_at = NOW() 
                    WHERE id = ?");
            }
            
            if ($stmt->execute([$bulk_status, $_SESSION['admin_id'], $excuse_id])) {
                logExcuseAction($pdo, $excuse_id, $user_type, $bulk_status, $_SESSION['admin_id']);
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['message'] = "$success_count excuse requests updated to " . strtoupper($bulk_status) . " successfully!";
        }
        if ($fail_count > 0) {
            $_SESSION['error'] = "$fail_count requests failed to update.";
        }
        
        header('Location: excuse_management.php');
        exit();
    }
}

// Function to log excuse actions
function logExcuseAction($pdo, $excuse_id, $user_type, $action, $admin_id) {
    // Check if excuse_action_logs table exists
    try {
        $stmt = $pdo->prepare("INSERT INTO excuse_action_logs 
            (excuse_id, user_type, action, performed_by_admin_id) 
            VALUES (?, ?, ?, ?)");
        return $stmt->execute([$excuse_id, $user_type, $action, $admin_id]);
    } catch (Exception $e) {
        // Table might not exist, log error but don't fail
        error_log("Failed to log excuse action: " . $e->getMessage());
        return false;
    }
}

// Get stats
$pending_cadet = $pdo->query("SELECT COUNT(*) FROM cadet_excuses WHERE status = 'pending' AND (is_archived IS NULL OR is_archived = FALSE)")->fetchColumn();
$pending_mp = $pdo->query("SELECT COUNT(*) FROM mp_excuses WHERE status = 'pending' AND (is_archived IS NULL OR is_archived = FALSE)")->fetchColumn();
$total_pending = $pending_cadet + $pending_mp;

$approved_today = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT created_at FROM cadet_excuses WHERE DATE(created_at) = CURDATE() AND status = 'approved'
        UNION ALL
        SELECT created_at FROM mp_excuses WHERE DATE(created_at) = CURDATE() AND status = 'approved'
    ) as approved
")->fetchColumn();

$total_excuses = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT id FROM cadet_excuses WHERE is_archived IS NULL OR is_archived = FALSE
        UNION ALL
        SELECT id FROM mp_excuses WHERE is_archived IS NULL OR is_archived = FALSE
    ) as total
")->fetchColumn();

$cadet_count = $pdo->query("SELECT COUNT(*) FROM cadet_excuses WHERE is_archived IS NULL OR is_archived = FALSE")->fetchColumn();
$mp_count = $pdo->query("SELECT COUNT(*) FROM mp_excuses WHERE is_archived IS NULL OR is_archived = FALSE")->fetchColumn();

// Get courses and platoons for filters
$courses = $pdo->query("SELECT DISTINCT course FROM cadet_accounts WHERE course IS NOT NULL ORDER BY course")->fetchAll();
$platoons = $pdo->query("SELECT DISTINCT platoon FROM cadet_accounts WHERE platoon IS NOT NULL ORDER BY platoon")->fetchAll();
$companies = $pdo->query("SELECT DISTINCT company FROM cadet_accounts WHERE company IS NOT NULL ORDER BY company")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excuse Management | ROTC Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a5f;
            --secondary: #2d5a7a;
            --accent: #c49a6c;
            --success: #2e7d32;
            --warning: #b76e1e;
            --danger: #b71c1c;
            --info: #0d47a1;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%);
        }
        
        .header-font {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .military-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            color: #b76e1e;
            border-color: #ffb74d;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            color: #2e7d32;
            border-color: #81c784;
        }
        
        .status-denied {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #b71c1c;
            border-color: #e57373;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e0e7ff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .table-container {
            max-height: 700px;
            overflow-y: auto;
            border-radius: 12px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #e0e0e0;
        }
        
        .table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
        
        .excuse-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
        }
        
        .excuse-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
            border-left-color: var(--accent);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .file-preview {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .military-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary) 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .military-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(30, 58, 95, 0.3);
        }
        
        .filter-tag {
            background: #f1f5f9;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #475569;
        }
        
        .filter-tag i {
            color: var(--primary);
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            gap: 4px;
            padding: 0 16px;
        }
        
        .nav-tab {
            padding: 12px 24px;
            font-weight: 600;
            color: #64748b;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-tab.active {
            color: var(--primary);
        }
        
        .nav-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px 3px 0 0;
        }
        
        .nav-tab:hover:not(.active) {
            color: var(--primary);
            background: rgba(45, 90, 122, 0.05);
            border-radius: 8px 8px 0 0;
        }
        
        .priority-high {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-medium {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-low {
            background: #dcfce7;
            color: #166534;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .attachment-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .attachment-preview:hover {
            transform: scale(1.02);
        }
        
        .empty-state {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 48px 24px;
            text-align: center;
        }
        
        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(30, 58, 95, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(30, 58, 95, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(30, 58, 95, 0);
            }
        }
        
        .pulse {
            animation: pulse-ring 2s infinite;
        }
        
        .badge-cadet {
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-mp {
            background: #ede9fe;
            color: #5b21b6;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; // Fixed path - go up one level ?>
    
    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-9999 space-y-2"></div>
    
    <div class="main-content ml-64 min-h-screen transition-all duration-300">
        <!-- Hero Header -->
        <div class="bg-[#213656] px-8 py-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-lg flex items-center justify-center border border-white/20">
                        <i class="fas fa-file-signature text-3xl text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl header-font font-bold text-white flex items-center gap-3">
                            Excuse Management System
                            <span class="px-3 py-1 text-xs bg-yellow-400 text-gray-900 rounded-full">Command Center</span>
                        </h1>
                        <p class="text-blue-100 mt-1 flex items-center gap-2">
                            <i class="fas fa-shield-alt"></i>
                            Review, approve, and manage excuse requests from cadets and military personnel
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="bg-white/10 backdrop-blur-lg rounded-lg px-4 py-2 border border-white/20">
                        <span class="text-white text-sm">Today's Date</span>
                        <p class="text-white font-semibold text-lg"><?php echo date('F d, Y'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-5 border border-white/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Pending Review</p>
                            <p class="text-white text-2xl font-bold mt-1"><?php echo $total_pending; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-yellow-400/20 flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-300 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-xs text-blue-200 mt-2 block">Awaiting your action</span>
                </div>
                
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-5 border border-white/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Approved Today</p>
                            <p class="text-white text-2xl font-bold mt-1"><?php echo $approved_today; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-400/20 flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-300 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-xs text-blue-200 mt-2 block">+<?php echo $approved_today; ?> since midnight</span>
                </div>
                
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-5 border border-white/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Excuses</p>
                            <p class="text-white text-2xl font-bold mt-1"><?php echo $total_excuses; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-400/20 flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-300 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-xs text-blue-200 mt-2 block">Cadet: <?php echo $cadet_count; ?> | MP: <?php echo $mp_count; ?></span>
                </div>
                
                <div class="bg-white/10 backdrop-blur-lg rounded-xl p-5 border border-white/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">With Attachments</p>
                            <?php
                            $with_attachments = $pdo->query("
                                SELECT COUNT(*) FROM (
                                    SELECT id FROM cadet_excuses WHERE attachment_path IS NOT NULL AND (is_archived IS NULL OR is_archived = FALSE)
                                    UNION ALL
                                    SELECT id FROM mp_excuses WHERE attachment_path IS NOT NULL AND (is_archived IS NULL OR is_archived = FALSE)
                                ) as with_attachments
                            ")->fetchColumn();
                            ?>
                            <p class="text-white text-2xl font-bold mt-1"><?php echo $with_attachments; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-400/20 flex items-center justify-center">
                            <i class="fas fa-paperclip text-purple-300 text-xl"></i>
                        </div>
                    </div>
                    <span class="text-xs text-blue-200 mt-2 block">Requires document review</span>
                </div>
            </div>
        </div>
        
        <main class="px-8 pb-12">
            <?php if ($message): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-linear-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4 flex items-center gap-3 shadow-lg">
                        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-xl p-4 flex items-center gap-3 shadow-lg">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Main Content Tabs -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <!-- Tab Navigation -->
                <div class="nav-tabs bg-gray-50">
                    <?php
                    $current_tab = isset($_GET['user_type']) ? $_GET['user_type'] : (isset($_GET['status']) ? 'pending' : 'all');
                    ?>
                    <button onclick="switchTab('all')" id="tab-all" class="nav-tab <?php echo $current_tab == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-layer-group mr-2"></i>
                        All Excuses
                        <span class="ml-2 px-2 py-0.5 bg-gray-200 text-gray-700 rounded-full text-xs">
                            <?php echo $total_excuses; ?>
                        </span>
                    </button>
                    <button onclick="switchTab('cadet')" id="tab-cadet" class="nav-tab <?php echo $current_tab == 'cadet' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate mr-2"></i>
                        Cadet Excuses
                        <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs">
                            <?php echo $cadet_count; ?>
                        </span>
                    </button>
                    <button onclick="switchTab('mp')" id="tab-mp" class="nav-tab <?php echo $current_tab == 'mp' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield mr-2"></i>
                        MP Excuses
                        <span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs">
                            <?php echo $mp_count; ?>
                        </span>
                    </button>
                    <button onclick="switchTab('pending')" id="tab-pending" class="nav-tab <?php echo $current_tab == 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-hourglass-half mr-2"></i>
                        Pending
                        <?php if ($total_pending > 0): ?>
                            <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs pulse">
                                <?php echo $total_pending; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- Advanced Filter Section -->
                <div class="p-6 border-b border-gray-200 bg-white">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-filter text-[#1e3a5f]"></i>
                            Advanced Filters
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="filter-tag">
                                <i class="fas fa-sliders-h"></i>
                                Active Filters: <span id="activeFilterCount" class="font-bold">0</span>
                            </span>
                            <button onclick="resetAllFilters()" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                <i class="fas fa-undo-alt"></i>
                                Reset All
                            </button>
                        </div>
                    </div>
                    
                    <form id="filterForm" method="GET" action="excuse_management.php">
                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                            <!-- Search by Name -->
                            <div class="col-span-1 lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-search text-gray-400 mr-1"></i>
                                    Search by Name
                                </label>
                                <div class="relative">
                                    <input type="text" name="search" id="searchName" 
                                           placeholder="Enter cadet or MP name..."
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                           class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm">
                                    <div class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Platoon Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-flag text-gray-400 mr-1"></i>
                                    Platoon
                                </label>
                                <select name="platoon" id="filterPlatoon" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm bg-white">
                                    <option value="">All Platoons</option>
                                    <?php foreach ($platoons as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p['platoon']); ?>" <?php echo (isset($_GET['platoon']) && $_GET['platoon'] == $p['platoon']) ? 'selected' : ''; ?>>
                                            Platoon <?php echo htmlspecialchars($p['platoon']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Company Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-building text-gray-400 mr-1"></i>
                                    Company
                                </label>
                                <select name="company" id="filterCompany" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm bg-white">
                                    <option value="">All Companies</option>
                                    <?php foreach ($companies as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['company']); ?>" <?php echo (isset($_GET['company']) && $_GET['company'] == $c['company']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['company']); ?> Company
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
                            <!-- Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-circle text-gray-400 mr-1"></i>
                                    Status
                                </label>
                                <select name="status" id="filterStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm bg-white">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="denied" <?php echo (isset($_GET['status']) && $_GET['status'] == 'denied') ? 'selected' : ''; ?>>Denied</option>
                                </select>
                            </div>
                            
                            <!-- Course Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-graduation-cap text-gray-400 mr-1"></i>
                                    Course
                                </label>
                                <select name="course" id="filterCourse" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm bg-white">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['course']); ?>" <?php echo (isset($_GET['course']) && $_GET['course'] == $c['course']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Date Range Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt text-gray-400 mr-1"></i>
                                    From Date
                                </label>
                                <input type="date" name="date_from" id="dateFrom" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>" 
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt text-gray-400 mr-1"></i>
                                    To Date
                                </label>
                                <input type="date" name="date_to" id="dateTo" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>" 
                                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm">
                            </div>
                            
                            <!-- Attachment Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-paperclip text-gray-400 mr-1"></i>
                                    Attachments
                                </label>
                                <select name="attachment" id="filterAttachment" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20 transition-all text-sm bg-white">
                                    <option value="">All</option>
                                    <option value="with" <?php echo (isset($_GET['attachment']) && $_GET['attachment'] == 'with') ? 'selected' : ''; ?>>With Attachments</option>
                                    <option value="without" <?php echo (isset($_GET['attachment']) && $_GET['attachment'] == 'without') ? 'selected' : ''; ?>>Without Attachments</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-4 gap-2">
                            <button type="submit" class="px-6 py-2.5 bg-[#1e3a5f] text-white rounded-lg hover:bg-[#2d5a7a] transition-all flex items-center gap-2">
                                <i class="fas fa-search"></i>
                                Apply Filters
                            </button>
                            <a href="excuse_management.php" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                                Clear
                            </a>
                        </div>
                    </form>
                    
                    <!-- Active Filters Display -->
                    <div id="activeFilters" class="mt-4 flex flex-wrap gap-2">
                        <!-- Active filters will be displayed here via JavaScript -->
                    </div>
                </div>
                
                <!-- Excuses Table -->
                <div class="table-container">
                    <table class="w-full" id="excusesTable">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300 text-[#1e3a5f] focus:ring-[#1e3a5f]">
                                        <span>Requester</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type & Reason</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Unit Assignment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date Filed</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Attachment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                            <?php
                            // Pagination
                            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $limit = 15;
                            $offset = ($page - 1) * $limit;
                            
                            // Build query with filters
                            $search = isset($_GET['search']) ? "%{$_GET['search']}%" : '%%';
                            $platoon = isset($_GET['platoon']) && $_GET['platoon'] !== '' ? $_GET['platoon'] : null;
                            $company = isset($_GET['company']) && $_GET['company'] !== '' ? $_GET['company'] : null;
                            $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
                            $user_type = isset($_GET['user_type']) && $_GET['user_type'] !== '' ? $_GET['user_type'] : null;
                            $course = isset($_GET['course']) && $_GET['course'] !== '' ? $_GET['course'] : null;
                            $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
                            $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
                            $attachment = isset($_GET['attachment']) ? $_GET['attachment'] : null;
                            
                            // Build cadet query
                            $cadet_query = "SELECT 
                                ce.*, 
                                ca.first_name, ca.last_name, ca.middle_name, ca.username,
                                ca.platoon, ca.company, ca.course,
                                'cadet' as user_type,
                                ca.id as user_id,
                                CONCAT(ca.first_name, ' ', ca.last_name) as full_name,
                                ce.attachment_path
                            FROM cadet_excuses ce
                            JOIN cadet_accounts ca ON ce.cadet_id = ca.id
                            WHERE (ce.is_archived IS NULL OR ce.is_archived = FALSE)";
                            
                            $cadet_params = [];
                            if ($search !== '%%') {
                                $cadet_query .= " AND (ca.first_name LIKE ? OR ca.last_name LIKE ? OR CONCAT(ca.first_name, ' ', ca.last_name) LIKE ?)";
                                $cadet_params[] = $search;
                                $cadet_params[] = $search;
                                $cadet_params[] = $search;
                            }
                            if ($platoon) {
                                $cadet_query .= " AND ca.platoon = ?";
                                $cadet_params[] = $platoon;
                            }
                            if ($company) {
                                $cadet_query .= " AND ca.company = ?";
                                $cadet_params[] = $company;
                            }
                            if ($course) {
                                $cadet_query .= " AND ca.course = ?";
                                $cadet_params[] = $course;
                            }
                            if ($status) {
                                $cadet_query .= " AND ce.status = ?";
                                $cadet_params[] = $status;
                            }
                            if ($date_from) {
                                $cadet_query .= " AND DATE(ce.created_at) >= ?";
                                $cadet_params[] = $date_from;
                            }
                            if ($date_to) {
                                $cadet_query .= " AND DATE(ce.created_at) <= ?";
                                $cadet_params[] = $date_to;
                            }
                            if ($attachment === 'with') {
                                $cadet_query .= " AND ce.attachment_path IS NOT NULL AND ce.attachment_path != ''";
                            } elseif ($attachment === 'without') {
                                $cadet_query .= " AND (ce.attachment_path IS NULL OR ce.attachment_path = '')";
                            }
                            
                            // Build MP query
                            $mp_query = "SELECT 
                                me.*, 
                                ma.first_name, ma.last_name, ma.middle_name, ma.username,
                                ma.platoon, ma.company,
                                '' as course,
                                'mp' as user_type,
                                ma.id as user_id,
                                CONCAT(ma.first_name, ' ', ma.last_name) as full_name,
                                me.attachment_path
                            FROM mp_excuses me
                            JOIN mp_accounts ma ON me.mp_id = ma.id
                            WHERE (me.is_archived IS NULL OR me.is_archived = FALSE)";
                            
                            $mp_params = [];
                            if ($search !== '%%') {
                                $mp_query .= " AND (ma.first_name LIKE ? OR ma.last_name LIKE ? OR CONCAT(ma.first_name, ' ', ma.last_name) LIKE ?)";
                                $mp_params[] = $search;
                                $mp_params[] = $search;
                                $mp_params[] = $search;
                            }
                            if ($platoon) {
                                $mp_query .= " AND ma.platoon = ?";
                                $mp_params[] = $platoon;
                            }
                            if ($company) {
                                $mp_query .= " AND ma.company = ?";
                                $mp_params[] = $company;
                            }
                            if ($status) {
                                $mp_query .= " AND me.status = ?";
                                $mp_params[] = $status;
                            }
                            if ($date_from) {
                                $mp_query .= " AND DATE(me.created_at) >= ?";
                                $mp_params[] = $date_from;
                            }
                            if ($date_to) {
                                $mp_query .= " AND DATE(me.created_at) <= ?";
                                $mp_params[] = $date_to;
                            }
                            if ($attachment === 'with') {
                                $mp_query .= " AND me.attachment_path IS NOT NULL AND me.attachment_path != ''";
                            } elseif ($attachment === 'without') {
                                $mp_query .= " AND (me.attachment_path IS NULL OR me.attachment_path = '')";
                            }
                            
                            // Combine queries based on user_type filter
                            if ($user_type === 'cadet') {
                                $combined_query = $cadet_query . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                                $stmt = $pdo->prepare($combined_query);
                                $param_index = 1;
                                foreach ($cadet_params as $param) {
                                    $stmt->bindValue($param_index++, $param);
                                }
                            } elseif ($user_type === 'mp') {
                                $combined_query = $mp_query . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                                $stmt = $pdo->prepare($combined_query);
                                $param_index = 1;
                                foreach ($mp_params as $param) {
                                    $stmt->bindValue($param_index++, $param);
                                }
                            } else {
                                $combined_query = "($cadet_query) UNION ALL ($mp_query) ORDER BY created_at DESC LIMIT ? OFFSET ?";
                                $stmt = $pdo->prepare($combined_query);
                                $param_index = 1;
                                foreach ($cadet_params as $param) {
                                    $stmt->bindValue($param_index++, $param);
                                }
                                foreach ($mp_params as $param) {
                                    $stmt->bindValue($param_index++, $param);
                                }
                            }
                            
                            // Bind limit and offset
                            $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
                            $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
                            
                            $stmt->execute();
                            $excuses = $stmt->fetchAll();
                            
                            if (empty($excuses)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-16">
                                        <div class="empty-state">
                                            <div class="mx-auto w-24 h-24 mb-6 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-file-signature text-gray-400 text-4xl"></i>
                                            </div>
                                            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Excuse Requests Found</h3>
                                            <p class="text-gray-600 max-w-md mx-auto mb-8">
                                                No excuse requests match your current filters. Try adjusting your search criteria or clear filters to see all requests.
                                            </p>
                                            <button onclick="resetAllFilters()" class="inline-flex items-center gap-2 px-6 py-3 bg-[#1e3a5f] text-white rounded-xl hover:bg-[#2d5a7a] transition-all">
                                                <i class="fas fa-undo-alt"></i>
                                                Clear All Filters
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($excuses as $excuse): ?>
                                    <tr class="excuse-card hover:bg-gray-50/50 transition-all" data-excuse-id="<?php echo $excuse['id']; ?>" data-user-type="<?php echo $excuse['user_type']; ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-start gap-3">
                                                <input type="checkbox" class="row-checkbox w-4 h-4 mt-2 rounded border-gray-300 text-[#1e3a5f] focus:ring-[#1e3a5f]">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#1e3a5f] to-[#2d5a7a] flex items-center justify-center text-white font-semibold text-sm shadow-lg">
                                                            <?php echo strtoupper(substr($excuse['first_name'], 0, 1) . substr($excuse['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>
                                                            </div>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                                    <?php echo $excuse['user_type'] === 'cadet' ? 'bg-blue-100 text-blue-700' : 'bg-indigo-100 text-indigo-700'; ?>">
                                                                    <i class="fas <?php echo $excuse['user_type'] === 'cadet' ? 'fa-user-graduate' : 'fa-user-shield'; ?> mr-1"></i>
                                                                    <?php echo strtoupper($excuse['user_type']); ?>
                                                                </span>
                                                                <span class="text-xs text-gray-500">
                                                                    @<?php echo htmlspecialchars($excuse['username']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                    <i class="fas fa-tag mr-1"></i>
                                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $excuse['excuse_type'] ?? 'General'))); ?>
                                                </span>
                                                <div class="text-sm text-gray-700 line-clamp-2">
                                                    <?php echo htmlspecialchars($excuse['reason']); ?>
                                                </div>
                                                <?php if (!empty($excuse['remarks'])): ?>
                                                    <div class="text-xs text-gray-500 bg-gray-50 p-2 rounded-lg mt-1">
                                                        <span class="font-medium">Remarks:</span> 
                                                        <?php echo htmlspecialchars($excuse['remarks']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <i class="fas fa-flag text-blue-600 text-xs"></i>
                                                    </div>
                                                    <span class="text-sm font-medium text-gray-900">
                                                        Platoon <?php echo htmlspecialchars($excuse['platoon'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center">
                                                        <i class="fas fa-users text-indigo-600 text-xs"></i>
                                                    </div>
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($excuse['company'] ?? 'N/A'); ?> Company
                                                    </span>
                                                </div>
                                                <?php if ($excuse['user_type'] === 'cadet' && !empty($excuse['course'])): ?>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        <i class="fas fa-graduation-cap mr-1"></i>
                                                        <?php echo htmlspecialchars($excuse['course']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                                                        <i class="fas fa-calendar text-gray-600 text-xs"></i>
                                                    </div>
                                                    <span class="text-sm font-medium text-gray-900">
                                                        <?php echo date('M d, Y', strtotime($excuse['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                                                        <i class="fas fa-clock text-gray-600 text-xs"></i>
                                                    </div>
                                                    <span class="text-xs text-gray-600">
                                                        <?php echo date('h:i A', strtotime($excuse['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($excuse['reviewed_at'])): ?>
                                                    <div class="text-xs text-gray-500 mt-1 pt-1 border-t border-gray-100">
                                                        <i class="fas fa-check-circle text-green-600 mr-1"></i>
                                                        Reviewed: <?php echo date('M d, Y', strtotime($excuse['reviewed_at'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $excuse_status = $excuse['status'] ?? 'pending';
                                            $status_class = 'status-' . $excuse_status;
                                            $status_icon = $excuse_status == 'approved' ? 'fa-check-circle' : ($excuse_status == 'pending' ? 'fa-clock' : 'fa-times-circle');
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?>"></i>
                                                <?php echo ucfirst($excuse_status); ?>
                                            </span>
                                            
                                            <?php if ($excuse_status === 'pending'): ?>
                                                <div class="mt-2">
                                                    <span class="priority-medium">
                                                        <i class="fas fa-hourglass-half mr-1"></i>
                                                        Awaiting Review
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($excuse['attachment_path'])): ?>
                                                <button onclick="viewAttachment('<?php echo htmlspecialchars('../../uploads/excuses/' . $excuse['attachment_path']); ?>', '<?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>')" 
                                                        class="flex items-center gap-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all group">
                                                    <div class="relative">
                                                        <i class="fas fa-paperclip text-blue-600 group-hover:scale-110 transition-transform"></i>
                                                        <?php 
                                                        $ext = pathinfo($excuse['attachment_path'], PATHINFO_EXTENSION);
                                                        if ($ext === 'pdf'): ?>
                                                            <i class="fas fa-file-pdf text-xs text-red-500 absolute -bottom-1 -right-1"></i>
                                                        <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                            <i class="fas fa-file-image text-xs text-green-500 absolute -bottom-1 -right-1"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-file text-xs text-gray-500 absolute -bottom-1 -right-1"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-sm font-medium text-blue-700">View File</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-400 flex items-center gap-2">
                                                    <i class="fas fa-paperclip"></i>
                                                    No attachment
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <?php if (($excuse['status'] ?? 'pending') === 'pending'): ?>
                                                    <button onclick="showApproveModal(<?php echo $excuse['id']; ?>, '<?php echo $excuse['user_type']; ?>', '<?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>')"
                                                            class="p-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all shadow-sm hover:shadow-md"
                                                            title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button onclick="showDenyModal(<?php echo $excuse['id']; ?>, '<?php echo $excuse['user_type']; ?>', '<?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>')"
                                                            class="p-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all shadow-sm hover:shadow-md"
                                                            title="Deny">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium">
                                                        <?php echo ($excuse['status'] ?? '') === 'approved' ? 'Approved' : 'Denied'; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <button onclick="viewDetails(<?php echo $excuse['id']; ?>, '<?php echo $excuse['user_type']; ?>')"
                                                        class="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-all"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Bulk Actions & Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-700" id="selectedCount">0 selected</span>
                            <button onclick="selectAll()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Select All</button>
                            <button onclick="deselectAll()" class="text-xs text-gray-600 hover:text-gray-800 font-medium">Clear</button>
                        </div>
                        
                        <div class="h-4 w-px bg-gray-300"></div>
                        
                        <select id="bulkAction" class="text-sm border-2 border-gray-200 rounded-lg px-3 py-1.5 focus:border-[#1e3a5f] focus:ring-2 focus:ring-[#1e3a5f]/20">
                            <option value="">Bulk Actions</option>
                            <option value="approve">Approve Selected</option>
                            <option value="deny">Deny Selected</option>
                            <option value="export">Export Selected</option>
                        </select>
                        <button onclick="executeBulkAction()" class="px-4 py-1.5 bg-[#1e3a5f] text-white rounded-lg text-sm hover:bg-[#2d5a7a] transition-all">
                            Apply
                        </button>
                    </div>
                    
                    <!-- Pagination -->
                    <?php
                    // Get total count for pagination
                    if ($user_type === 'cadet') {
                        $count_query = "SELECT COUNT(*) FROM cadet_excuses WHERE (is_archived IS NULL OR is_archived = FALSE)";
                        $count_params = [];
                        if ($search !== '%%') {
                            $count_query = "SELECT COUNT(*) FROM cadet_excuses ce JOIN cadet_accounts ca ON ce.cadet_id = ca.id WHERE (ce.is_archived IS NULL OR ce.is_archived = FALSE) AND (ca.first_name LIKE ? OR ca.last_name LIKE ? OR CONCAT(ca.first_name, ' ', ca.last_name) LIKE ?)";
                        }
                    } elseif ($user_type === 'mp') {
                        $count_query = "SELECT COUNT(*) FROM mp_excuses WHERE (is_archived IS NULL OR is_archived = FALSE)";
                        if ($search !== '%%') {
                            $count_query = "SELECT COUNT(*) FROM mp_excuses me JOIN mp_accounts ma ON me.mp_id = ma.id WHERE (me.is_archived IS NULL OR me.is_archived = FALSE) AND (ma.first_name LIKE ? OR ma.last_name LIKE ? OR CONCAT(ma.first_name, ' ', ma.last_name) LIKE ?)";
                        }
                    } else {
                        $count_query = "SELECT COUNT(*) FROM (
                            SELECT id FROM cadet_excuses WHERE (is_archived IS NULL OR is_archived = FALSE)
                            UNION ALL
                            SELECT id FROM mp_excuses WHERE (is_archived IS NULL OR is_archived = FALSE)
                        ) as total";
                        if ($search !== '%%') {
                            $count_query = "SELECT COUNT(*) FROM (
                                SELECT ce.id FROM cadet_excuses ce JOIN cadet_accounts ca ON ce.cadet_id = ca.id WHERE (ce.is_archived IS NULL OR ce.is_archived = FALSE) AND (ca.first_name LIKE ? OR ca.last_name LIKE ? OR CONCAT(ca.first_name, ' ', ca.last_name) LIKE ?)
                                UNION ALL
                                SELECT me.id FROM mp_excuses me JOIN mp_accounts ma ON me.mp_id = ma.id WHERE (me.is_archived IS NULL OR me.is_archived = FALSE) AND (ma.first_name LIKE ? OR ma.last_name LIKE ? OR CONCAT(ma.first_name, ' ', ma.last_name) LIKE ?)
                            ) as total";
                        }
                    }
                    
                    if ($search !== '%%') {
                        $stmt = $pdo->prepare($count_query);
                        if ($user_type === 'cadet') {
                            $stmt->execute([$search, $search, $search]);
                        } elseif ($user_type === 'mp') {
                            $stmt->execute([$search, $search, $search]);
                        } else {
                            $stmt->execute([$search, $search, $search, $search, $search, $search]);
                        }
                        $total_items = $stmt->fetchColumn();
                    } else {
                        $total_items = $pdo->query($count_query)->fetchColumn();
                    }
                    
                    $total_pages = ceil($total_items / $limit);
                    ?>
                    
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-700">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" 
                                   class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 transition-all text-sm">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" 
                                   class="px-3 py-1.5 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 transition-all text-sm">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Approve Modal -->
    <div id="approveModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-md modal-content">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gradient-to-r from-green-100 to-emerald-100 mb-4">
                            <i class="fas fa-check-circle text-green-600 text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Approve Excuse Request</h3>
                        <p class="text-gray-600 mb-6" id="approveMessage">
                            Are you sure you want to approve this excuse request?
                        </p>
                    </div>
                    
                    <form id="approveForm" method="POST" class="space-y-4">
                        <input type="hidden" name="excuse_id" id="approveExcuseId">
                        <input type="hidden" name="user_type" id="approveUserType">
                        <input type="hidden" name="status" value="approved">
                        <input type="hidden" name="update_excuse_status" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-pen mr-1"></i>
                                Remarks (Optional)
                            </label>
                            <textarea name="remarks" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all text-sm"
                                      placeholder="Add any approval notes or conditions..."></textarea>
                        </div>
                        
                        <div class="bg-green-50 rounded-xl p-4 text-sm text-green-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            Approved excuses will be marked as resolved and the requester will be notified.
                        </div>
                        
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeApproveModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 transition-all font-medium shadow-lg">
                                <i class="fas fa-check mr-2"></i>
                                Confirm Approval
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Deny Modal -->
    <div id="denyModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-md modal-content">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gradient-to-r from-red-100 to-rose-100 mb-4">
                            <i class="fas fa-times-circle text-red-600 text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Deny Excuse Request</h3>
                        <p class="text-gray-600 mb-6" id="denyMessage">
                            Are you sure you want to deny this excuse request?
                        </p>
                    </div>
                    
                    <form id="denyForm" method="POST" class="space-y-4">
                        <input type="hidden" name="excuse_id" id="denyExcuseId">
                        <input type="hidden" name="user_type" id="denyUserType">
                        <input type="hidden" name="status" value="denied">
                        <input type="hidden" name="update_excuse_status" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-pen mr-1"></i>
                                Reason for Denial <span class="text-red-500">*</span>
                            </label>
                            <textarea name="remarks" rows="3" required
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all text-sm"
                                      placeholder="Please provide the reason for denying this request..."></textarea>
                        </div>
                        
                        <div class="bg-red-50 rounded-xl p-4 text-sm text-red-700">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            This action cannot be undone. The requester will be notified of the denial with your remarks.
                        </div>
                        
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeDenyModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:from-red-600 hover:to-red-700 transition-all font-medium shadow-lg">
                                <i class="fas fa-times mr-2"></i>
                                Deny Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-3xl modal-content">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-[#1e3a5f] to-[#2d5a7a] px-6 py-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-file-alt"></i>
                            Excuse Request Details
                        </h3>
                        <button onclick="closeDetailsModal()" class="text-white/80 hover:text-white">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6" id="detailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
                        <p class="text-gray-500 mt-2">Loading details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Attachment Preview Modal -->
    <div id="attachmentModal" class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 w-full max-w-4xl modal-content">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-paperclip text-[#1e3a5f]"></i>
                        Attachment Preview - <span id="attachmentUserName" class="font-bold"></span>
                    </h3>
                    <button onclick="closeAttachmentModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6" id="attachmentPreviewContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                    <button onclick="downloadAttachment()" class="px-4 py-2 bg-[#1e3a5f] text-white rounded-lg hover:bg-[#2d5a7a] transition-all flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        Download File
                    </button>
                    <button onclick="closeAttachmentModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables for current attachment
        let currentAttachmentPath = '';
        let currentAttachmentUser = '';
        
        // Tab switching functionality
        function switchTab(tab) {
            // Update active tab UI
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(`tab-${tab}`).classList.add('active');
            
            // Update URL parameter
            let params = new URLSearchParams(window.location.search);
            if (tab === 'all') {
                params.delete('user_type');
                params.delete('status');
            } else if (tab === 'pending') {
                params.delete('user_type');
                params.set('status', 'pending');
            } else {
                params.set('user_type', tab);
                params.delete('status');
            }
            params.set('page', '1');
            
            window.location.href = 'excuse_management.php?' + params.toString();
        }
        
        // Apply filters
        function applyFilters() {
            document.getElementById('filterForm').submit();
        }
        
        // Reset all filters
        function resetAllFilters() {
            window.location.href = 'excuse_management.php';
        }
        
        // Show active filters
        function updateActiveFilters() {
            const params = new URLSearchParams(window.location.search);
            const container = document.getElementById('activeFilters');
            let count = 0;
            
            if (!container) return;
            
            container.innerHTML = '';
            
            if (params.has('search')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-search"></i> Search: ${params.get('search')} <button onclick="removeFilter('search')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('platoon')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-flag"></i> Platoon ${params.get('platoon')} <button onclick="removeFilter('platoon')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('company')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-building"></i> ${params.get('company')} Co. <button onclick="removeFilter('company')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('course')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-graduation-cap"></i> ${params.get('course')} <button onclick="removeFilter('course')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('status')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-circle"></i> ${params.get('status')} <button onclick="removeFilter('status')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('date_from') || params.has('date_to')) {
                let dateRange = '';
                if (params.has('date_from')) dateRange += params.get('date_from');
                if (params.has('date_to')) dateRange += ` to ${params.get('date_to')}`;
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-calendar"></i> ${dateRange} <button onclick="removeFilter('date_from'); removeFilter('date_to')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            if (params.has('attachment')) {
                container.innerHTML += `<span class="filter-tag"><i class="fas fa-paperclip"></i> ${params.get('attachment')} attachments <button onclick="removeFilter('attachment')"><i class="fas fa-times ml-1"></i></button></span>`;
                count++;
            }
            
            document.getElementById('activeFilterCount').textContent = count;
        }
        
        // Remove specific filter
        function removeFilter(filterName) {
            let params = new URLSearchParams(window.location.search);
            params.delete(filterName);
            window.location.href = 'excuse_management.php?' + params.toString();
        }
        
        // Toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const colors = {
                success: 'bg-gradient-to-r from-green-500 to-emerald-600',
                error: 'bg-gradient-to-r from-red-500 to-rose-600',
                info: 'bg-gradient-to-r from-blue-500 to-indigo-600',
                warning: 'bg-gradient-to-r from-yellow-500 to-amber-600'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            const toastHTML = `
                <div id="${toastId}" class="flex items-center gap-3 px-6 py-4 ${colors[type]} text-white rounded-xl shadow-2xl transform transition-all duration-500 translate-x-full opacity-0">
                    <i class="fas ${icons[type]} text-xl"></i>
                    <p class="text-sm font-medium">${message}</p>
                    <button onclick="closeToast('${toastId}')" class="ml-4 text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            setTimeout(() => {
                const toast = document.getElementById(toastId);
                if (toast) {
                    toast.classList.remove('translate-x-full', 'opacity-0');
                }
            }, 100);
            
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }
        
        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }
        }
        
        // Modal functions for approve
        function showApproveModal(id, userType, name) {
            document.getElementById('approveExcuseId').value = id;
            document.getElementById('approveUserType').value = userType;
            document.getElementById('approveMessage').innerHTML = `Are you sure you want to approve the excuse request from <span class="font-bold">${name}</span>?`;
            document.getElementById('approveModal').classList.remove('hidden');
        }
        
        function closeApproveModal() {
            document.getElementById('approveModal').classList.add('hidden');
            document.getElementById('approveForm').reset();
        }
        
        // Modal functions for deny
        function showDenyModal(id, userType, name) {
            document.getElementById('denyExcuseId').value = id;
            document.getElementById('denyUserType').value = userType;
            document.getElementById('denyMessage').innerHTML = `Are you sure you want to deny the excuse request from <span class="font-bold">${name}</span>?`;
            document.getElementById('denyModal').classList.remove('hidden');
        }
        
        function closeDenyModal() {
            document.getElementById('denyModal').classList.add('hidden');
            document.getElementById('denyForm').reset();
        }
        
        // View details function
        function viewDetails(id, userType) {
            document.getElementById('detailsModal').classList.remove('hidden');
            
            // AJAX request to get details
            fetch(`get_excuse_details.php?id=${id}&type=${userType}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('detailsContent').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('detailsContent').innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                            <p>Failed to load details. Please try again.</p>
                        </div>
                    `;
                    showToast('Failed to load details', 'error');
                });
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        // Attachment functions
        function viewAttachment(path, userName) {
            currentAttachmentPath = path;
            currentAttachmentUser = userName;
            document.getElementById('attachmentUserName').textContent = userName;
            
            const previewContent = document.getElementById('attachmentPreviewContent');
            const fileExtension = path.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                previewContent.innerHTML = `<img src="${path}" alt="Attachment" class="max-w-full max-h-[60vh] mx-auto rounded-lg shadow-lg">`;
            } else if (fileExtension === 'pdf') {
                previewContent.innerHTML = `<iframe src="${path}" class="w-full h-[60vh] rounded-lg border border-gray-200" frameborder="0"></iframe>`;
            } else {
                previewContent.innerHTML = `
                    <div class="file-preview">
                        <i class="fas fa-file-alt text-5xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600">This file type cannot be previewed.</p>
                        <p class="text-sm text-gray-500 mt-2">${path.split('/').pop()}</p>
                    </div>
                `;
            }
            
            document.getElementById('attachmentModal').classList.remove('hidden');
        }
        
        function downloadAttachment() {
            if (currentAttachmentPath) {
                window.open(currentAttachmentPath, '_blank');
            }
        }
        
        function closeAttachmentModal() {
            document.getElementById('attachmentModal').classList.add('hidden');
            currentAttachmentPath = '';
            currentAttachmentUser = '';
        }
        
        // Bulk actions
        function selectAll() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        }
        
        function deselectAll() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const count = document.querySelectorAll('.row-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = count + ' selected';
        }
        
        function executeBulkAction() {
            const action = document.getElementById('bulkAction').value;
            if (!action) {
                showToast('Please select an action', 'warning');
                return;
            }
            
            const selected = [];
            document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                const row = cb.closest('tr');
                selected.push({
                    id: row.dataset.excuseId,
                    userType: row.dataset.userType
                });
            });
            
            if (selected.length === 0) {
                showToast('Please select at least one item', 'warning');
                return;
            }
            
            if (action === 'approve') {
                // Bulk approve logic
                bulkUpdateStatus(selected, 'approved');
            } else if (action === 'deny') {
                // Bulk deny logic
                bulkUpdateStatus(selected, 'denied');
            } else if (action === 'export') {
                // Export logic
                exportSelected(selected);
            }
        }
        
        function bulkUpdateStatus(selected, status) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'excuse_management.php';
            
            selected.forEach((item, index) => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = `excuse_ids[${index}]`;
                idInput.value = item.id;
                form.appendChild(idInput);
                
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = `user_types[${index}]`;
                typeInput.value = item.userType;
                form.appendChild(typeInput);
            });
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_action';
            actionInput.value = 'update_status';
            form.appendChild(actionInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'bulk_status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function exportSelected(selected) {
            // Implement export functionality
            showToast('Export feature coming soon', 'info');
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update active filters display
            updateActiveFilters();
            
            // Add event listeners for checkboxes
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
            
            document.getElementById('selectAll')?.addEventListener('change', function(e) {
                if (e.target.checked) {
                    selectAll();
                } else {
                    deselectAll();
                }
            });
            
            // Check for pending items and show notification
            const pendingCount = <?php echo $total_pending; ?>;
            if (pendingCount > 0) {
                showToast(`You have ${pendingCount} pending excuse request${pendingCount > 1 ? 's' : ''} to review`, 'info');
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['approveModal', 'denyModal', 'detailsModal', 'attachmentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    if (modalId === 'approveModal') closeApproveModal();
                    if (modalId === 'denyModal') closeDenyModal();
                    if (modalId === 'detailsModal') closeDetailsModal();
                    if (modalId === 'attachmentModal') closeAttachmentModal();
                }
            });
        }
    </script>
</body>
</html>