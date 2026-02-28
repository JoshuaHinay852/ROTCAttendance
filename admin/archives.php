<?php
// archives.php
// Include database connection
require_once '../config/database.php';
// Include archive functions
require_once 'archive_functions.php'; 
// Include auth check functions
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}




// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}


$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Handle actions
// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore_account'])) {
        $archive_id = $_POST['archive_id'];
        $account_type = $_POST['account_type'];
        
        // Set to pending when restoring (true parameter)
        if (restoreFromArchive($account_type, $archive_id, true)) {
            $_SESSION['message'] = ucfirst($account_type) . " account restored successfully with pending status!";
        } else {
            $_SESSION['error'] = "Failed to restore account. Please try again.";
        }
        header('Location: archives.php?type=' . $account_type);
        exit();
        
    } elseif (isset($_POST['permanent_delete'])) {
        $archive_id = $_POST['archive_id'];
        $account_type = $_POST['account_type'];
        
        if (permanentDeleteFromArchive($account_type, $archive_id)) {
            $_SESSION['message'] = "Account permanently deleted from archives!";
        } else {
            $_SESSION['error'] = "Failed to permanently delete account. Please try again.";
        }
        header('Location: archives.php?type=' . $account_type);
        exit();
        
    } elseif (isset($_POST['bulk_restore'])) {
        if (isset($_POST['selected_archives']) && is_array($_POST['selected_archives'])) {
            $success_count = 0;
            $fail_count = 0;
            
            foreach ($_POST['selected_archives'] as $archive_data) {
                list($archive_id, $account_type) = explode('|', $archive_data);
                
                // Set to pending when restoring (true parameter)
                if (restoreFromArchive($account_type, $archive_id, true)) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            
            if ($success_count > 0) {
                $_SESSION['message'] = "Successfully restored $success_count account(s) with pending status.";
            }
            if ($fail_count > 0) {
                $_SESSION['error'] = "Failed to restore $fail_count account(s).";
            }
        }
        header('Location: archives.php?type=' . $type);
        exit();
        
    } elseif (isset($_POST['bulk_delete'])) {
        if (isset($_POST['selected_archives']) && is_array($_POST['selected_archives'])) {
            $success_count = 0;
            $fail_count = 0;
            
            foreach ($_POST['selected_archives'] as $archive_data) {
                list($archive_id, $account_type) = explode('|', $archive_data);
                
                if (permanentDeleteFromArchive($account_type, $archive_id)) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            
            if ($success_count > 0) {
                $_SESSION['message'] = "Successfully permanently deleted $success_count account(s).";
            }
            if ($fail_count > 0) {
                $_SESSION['error'] = "Failed to delete $fail_count account(s).";
            }
        }
        header('Location: archives.php?type=' . $type);
        exit();
    

        
    } elseif (isset($_POST['bulk_delete'])) {
        if (isset($_POST['selected_archives']) && is_array($_POST['selected_archives'])) {
            $success_count = 0;
            $fail_count = 0;
            
            foreach ($_POST['selected_archives'] as $archive_data) {
                list($archive_id, $account_type) = explode('|', $archive_data);
                
                if (permanentDeleteFromArchive($account_type, $archive_id)) {
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }
            
            if ($success_count > 0) {
                $_SESSION['message'] = "Successfully permanently deleted $success_count account(s).";
            }
            if ($fail_count > 0) {
                $_SESSION['error'] = "Failed to delete $fail_count account(s).";
            }
        }
        header('Location: archives.php?type=' . $type);
        exit();
    }
}

// Get archive statistics
$archive_stats = getArchiveStats();

// Get archived data based on type
// Get archived data based on type
$archives = [];
$total_archives = 0;

try {
    switch ($type) {
        case 'admin':
            $stmt = $pdo->prepare("
                SELECT 
                    aa.*,
                    CONCAT('admin') as account_type
                FROM admin_archives aa
                ORDER BY aa.deleted_at DESC
            ");
            $stmt->execute();
            $archives = $stmt->fetchAll();
            $total_archives = count($archives);
            break;
            
        case 'cadet':
            $stmt = $pdo->prepare("
                SELECT 
                    ca.*,
                    CONCAT('cadet') as account_type
                FROM cadet_archives ca
                ORDER BY ca.deleted_at DESC
            ");
            $stmt->execute();
            $archives = $stmt->fetchAll();
            $total_archives = count($archives);
            break;
            
        case 'mp':
            $stmt = $pdo->prepare("
                SELECT 
                    ma.*,
                    CONCAT('mp') as account_type
                FROM mp_archives ma
                ORDER BY ma.deleted_at DESC
            ");
            $stmt->execute();
            $archives = $stmt->fetchAll();
            $total_archives = count($archives);
            break;
            
        case 'all':
        default:
            // Get all archives combined - fixed version with correct column names
            $stmt = $pdo->prepare("
                (SELECT 
                    id, original_id, username, email, full_name as name, 'admin' as account_type,
                    account_status, deleted_at, deleted_by, reason, 'Admin' as type_label,
                    CONCAT('bg-red-100 text-red-800') as badge_class
                FROM admin_archives)
                
                UNION ALL
                
                (SELECT 
                    id, original_id, username, email, CONCAT(first_name, ' ', last_name) as name, 
                    'cadet' as account_type, status as account_status, deleted_at, deleted_by, reason, 'Cadet' as type_label,
                    CONCAT('bg-green-100 text-green-800') as badge_class
                FROM cadet_archives)
                
                UNION ALL
                
                (SELECT 
                    id, original_id, username, email, CONCAT(first_name, ' ', last_name) as name, 
                    'mp' as account_type, status as account_status, deleted_at, deleted_by, reason, 'MP' as type_label,
                    CONCAT('bg-purple-100 text-purple-800') as badge_class
                FROM mp_archives)
                
                ORDER BY deleted_at DESC
                LIMIT 100
            ");
            $stmt->execute();
            $archives = $stmt->fetchAll();
            $total_archives = count($archives);
            break;
            }
} catch (PDOException $e) {
    error_log("Archive fetch error: " . $e->getMessage());
    error_log("SQL Error Info: " . print_r($pdo->errorInfo(), true));
    $error = "Failed to load archived accounts. Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives | ROTC Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .type-admin { background-color: #fee2e2; color: #991b1b; }
        .type-cadet { background-color: #d1fae5; color: #065f46; }
        .type-mp { background-color: #e9d5ff; color: #7c3aed; }
        
        .checkbox-cell {
            width: 50px;
            text-align: center;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .sticky-checkbox-header {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Include dashboard header -->
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Archives</h2>
                    <p class="text-gray-600">Manage deleted accounts - restore or permanently delete</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-archive mr-1"></i>
                        Total Archived: <span class="font-bold"><?php echo $archive_stats['total_archived']; ?></span>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="p-8 fade-in">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- All Archives Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover <?php echo $type == 'all' ? 'ring-2 ring-blue-500' : ''; ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">All Archives</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $archive_stats['total_archived']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-archive text-gray-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?type=all" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            View all <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Admin Archives Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover <?php echo $type == 'admin' ? 'ring-2 ring-red-500' : ''; ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Admin Archives</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $archive_stats['admin_archives']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <i class="fas fa-user-tie text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?type=admin" class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
                            View admins <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Cadet Archives Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover <?php echo $type == 'cadet' ? 'ring-2 ring-green-500' : ''; ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Cadet Archives</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $archive_stats['cadet_archives']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="fas fa-user-graduate text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?type=cadet" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                            View cadets <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
                
                <!-- MP Archives Card -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover <?php echo $type == 'mp' ? 'ring-2 ring-purple-500' : ''; ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">MP Archives</p>
                            <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $archive_stats['mp_archives']; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-user-shield text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="?type=mp" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center">
                            View MP <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions Bar - FIXED VERSION -->
            <div id="bulkActionsBar" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span id="selectedCount" class="font-medium text-blue-800">0 accounts selected</span>
                        <div class="flex space-x-2">
                            <!-- These buttons should submit the form -->
                            <button type="button" onclick="submitBulkAction('restore')" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                                <i class="fas fa-undo mr-2"></i>Restore Selected
                            </button>
                            <button type="button" onclick="submitBulkAction('delete')" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                                <i class="fas fa-trash-alt mr-2"></i>Permanently Delete
                            </button>
                        </div>
                    </div>
                    <button type="button" onclick="clearSelection()" 
                            class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                        Clear Selection
                    </button>
                </div>
            </div>
            
            <!-- Archives Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <?php 
                            echo $type == 'all' ? 'All Archived Accounts' : 
                                 ($type == 'admin' ? 'Archived Admin Accounts' : 
                                 ($type == 'cadet' ? 'Archived Cadet Accounts' : 'Archived MP Accounts'));
                        ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (<?php echo $total_archives; ?> accounts)
                        </span>
                    </h3>
                    
                    <!-- Search and Filter -->
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text" id="archiveSearch" placeholder="Search archives..." 
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                            <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                        </div>
                        
                        <div class="w-48">
                            <select id="timeFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Time</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="365">Last year</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <form id="bulkActionForm" method="POST">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="sticky-checkbox-header px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="archiveTableBody">
                                <?php if (empty($archives)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                            <i class="fas fa-archive text-4xl mb-4 text-gray-300"></i>
                                            <p class="text-lg">No archived accounts found</p>
                                            <p class="text-sm mt-2">
                                                <?php if ($type == 'all'): ?>
                                                    No accounts have been deleted yet.
                                                <?php else: ?>
                                                    No <?php echo $type; ?> accounts have been deleted yet.
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($archives as $archive): ?>
                                        <?php 
                                            $account_type = isset($archive['account_type']) ? $archive['account_type'] : $type;
                                            $badge_class = $type == 'all' ? 
                                                ($archive['badge_class'] ?? 'type-' . $account_type) : 
                                                'type-' . $account_type;
                                            $type_label = $type == 'all' ? 
                                                ($archive['type_label'] ?? ucfirst($account_type)) : 
                                                ucfirst($account_type);
                                            
                                            // Format deleted date
                                            $deleted_date = date('M j, Y', strtotime($archive['deleted_at']));
                                            $deleted_time = date('g:i A', strtotime($archive['deleted_at']));
                                            
                                            // Get account name
                                            $account_name = isset($archive['name']) ? $archive['name'] : 
                                                          (isset($archive['full_name']) ? $archive['full_name'] : 
                                                          (isset($archive['first_name']) ? $archive['first_name'] . ' ' . $archive['last_name'] : 'Unknown'));
                                        ?>
                                        <tr class="archive-row table-row-hover" data-type="<?php echo $account_type; ?>" data-date="<?php echo $archive['deleted_at']; ?>">
                                            <td class="checkbox-cell px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="selected_archives[]" 
                                                       value="<?php echo $archive['id'] . '|' . $account_type; ?>" 
                                                       class="archive-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full 
                                                            <?php echo $account_type == 'admin' ? 'bg-gradient-to-r from-red-500 to-pink-500' : 
                                                                   ($account_type == 'cadet' ? 'bg-gradient-to-r from-green-500 to-teal-500' : 
                                                                   'bg-gradient-to-r from-purple-500 to-indigo-500'); ?> 
                                                            flex items-center justify-center text-white font-bold">
                                                            <?php echo strtoupper(substr($account_name, 0, 1)); ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($account_name); ?>
                                                            <?php if (isset($archive['original_id'])): ?>
                                                                <span class="text-xs text-gray-500 ml-2">ID: <?php echo $archive['original_id']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            @<?php echo htmlspecialchars($archive['username']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-400">
                                                            <?php echo htmlspecialchars($archive['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo $type_label; ?>
                                                </span>
                                                <?php if ($account_type == 'mp' && isset($archive['mp_rank'])): ?>
                                                    <div class="text-xs text-gray-600 mt-1">
                                                        <?php echo htmlspecialchars($archive['mp_rank']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <i class="fas fa-calendar-day text-gray-400 mr-1"></i>
                                                    <?php echo $deleted_date; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-clock text-gray-400 mr-1"></i>
                                                    <?php echo $deleted_time; ?>
                                                </div>
                                                <div class="text-xs text-gray-600 mt-1">
                                                    By: <?php echo htmlspecialchars($archive['deleted_by_name'] ?? 'System'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-700 max-w-xs">
                                                    <?php if (!empty($archive['reason'])): ?>
                                                        <?php echo htmlspecialchars($archive['reason']); ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 italic">No reason provided</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button type="button" 
                                                            onclick="showRestoreModal(<?php echo $archive['id']; ?>, '<?php echo $account_type; ?>', '<?php echo htmlspecialchars(addslashes($account_name)); ?>')"
                                                            class="text-green-600 hover:text-green-900 px-3 py-1 rounded border border-green-200 hover:bg-green-50 transition-colors">
                                                        <i class="fas fa-undo mr-1"></i>Restore
                                                    </button>
                                                    <button type="button" 
                                                            onclick="showPermanentDeleteModal(<?php echo $archive['id']; ?>, '<?php echo $account_type; ?>', '<?php echo htmlspecialchars(addslashes($account_name)); ?>')"
                                                            class="text-red-600 hover:text-red-900 px-3 py-1 rounded border border-red-200 hover:bg-red-50 transition-colors">
                                                        <i class="fas fa-trash-alt mr-1"></i>Delete
                                                    </button>
                                                    <?php if ($type == 'all'): ?>
                                                        <a href="?type=<?php echo $account_type; ?>" 
                                                           class="text-blue-600 hover:text-blue-900 px-3 py-1 rounded border border-blue-200 hover:bg-blue-50 transition-colors">
                                                            <i class="fas fa-filter mr-1"></i>Filter
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
                
                <!-- Empty State (if no archives after search/filter) -->
                <div id="emptySearchState" class="hidden px-6 py-12 text-center text-gray-500">
                    <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">No matching archives found</p>
                    <p class="text-sm mt-2">Try adjusting your search or filter</p>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Restore Confirmation Modal -->
    <div id="restoreModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <i class="fas fa-undo text-green-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Restore Account</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="restoreMessage">
                        Are you sure you want to restore this account?
                    </p>
                </div>
                <div class="items-center px-4 py-3 mt-4">
                    <form id="restoreForm" method="POST" class="flex justify-center space-x-4">
                        <input type="hidden" name="archive_id" id="restoreArchiveId">
                        <input type="hidden" name="account_type" id="restoreAccountType">
                        <input type="hidden" name="restore_account" value="1">
                        <button type="button" onclick="closeRestoreModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            Restore Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Permanent Delete Confirmation Modal -->
    <div id="permanentDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Permanently Delete</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="permanentDeleteMessage">
                        Are you sure you want to permanently delete this account from archives?
                    </p>
                    <p class="text-sm text-red-600 font-medium mt-2">
                        This action cannot be undone!
                    </p>
                </div>
                <div class="items-center px-4 py-3 mt-4">
                    <form id="permanentDeleteForm" method="POST" class="flex justify-center space-x-4">
                        <input type="hidden" name="archive_id" id="deleteArchiveId">
                        <input type="hidden" name="account_type" id="deleteAccountType">
                        <input type="hidden" name="permanent_delete" value="1">
                        <button type="button" onclick="closePermanentDeleteModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Restore Modal - UPDATED -->
<div id="bulkRestoreModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <i class="fas fa-undo text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Restore Multiple Accounts</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500" id="bulkRestoreCount">
                    Restore 0 selected accounts?
                </p>
                <p class="text-sm text-gray-600 mt-2">
                    This will restore all selected accounts to their original tables.
                </p>
            </div>
            <div class="items-center px-4 py-3 mt-4">
                <!-- This form should be inside the main form or handle submission differently -->
                <form method="POST" id="bulkRestoreForm" class="flex justify-center space-x-4">
                    <input type="hidden" name="bulk_restore" value="1">
                    <!-- Selected checkboxes will be added dynamically via JavaScript -->
                    <button type="button" onclick="closeBulkRestoreModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Restore Selected
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal - UPDATED -->
<div id="bulkDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Permanently Delete Multiple</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500" id="bulkDeleteCount">
                    Permanently delete 0 selected accounts?
                </p>
                <p class="text-sm text-red-600 font-medium mt-2">
                    This action cannot be undone! All selected accounts will be permanently deleted from archives.
                </p>
            </div>
            <div class="items-center px-4 py-3 mt-4">
                <form method="POST" id="bulkDeleteForm" class="flex justify-center space-x-4">
                    <input type="hidden" name="bulk_delete" value="1">
                    <!-- Selected checkboxes will be added dynamically via JavaScript -->
                    <button type="button" onclick="closeBulkDeleteModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Delete Permanently
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
    <script>
        // Selection Management
        let selectedCount = 0;
        
// Add to your existing JavaScript in archives.php

function submitBulkAction(actionType) {
    const selectedCheckboxes = document.querySelectorAll('.archive-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one account.');
        return;
    }
    
    if (actionType === 'restore') {
        showBulkRestoreModal();
    } else if (actionType === 'delete') {
        showBulkDeleteModal();
    }
}

function showBulkRestoreModal() {
    const selectedCount = document.querySelectorAll('.archive-checkbox:checked').length;
    if (selectedCount > 0) {
        document.getElementById('bulkRestoreCount').textContent = 
            `Restore ${selectedCount} selected account(s)?`;
        document.getElementById('bulkRestoreModal').classList.remove('hidden');
    }
}

function showBulkDeleteModal() {
    const selectedCount = document.querySelectorAll('.archive-checkbox:checked').length;
    if (selectedCount > 0) {
        document.getElementById('bulkDeleteCount').textContent = 
            `Permanently delete ${selectedCount} selected account(s)?`;
        document.getElementById('bulkDeleteModal').classList.remove('hidden');
    }
}

// Update the modal forms to include all selected checkboxes
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for bulk restore form submission
    document.querySelector('#bulkRestoreModal form')?.addEventListener('submit', function(e) {
        // Make sure selected checkboxes are included
        const selectedCheckboxes = document.querySelectorAll('.archive-checkbox:checked');
        selectedCheckboxes.forEach(cb => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_archives[]';
            hiddenInput.value = cb.value;
            this.appendChild(hiddenInput);
        });
    });
    
    // Add event listener for bulk delete form submission
    document.querySelector('#bulkDeleteModal form')?.addEventListener('submit', function(e) {
        // Make sure selected checkboxes are included
        const selectedCheckboxes = document.querySelectorAll('.archive-checkbox:checked');
        selectedCheckboxes.forEach(cb => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_archives[]';
            hiddenInput.value = cb.value;
            this.appendChild(hiddenInput);
        });
    });
});

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.archive-checkbox:checked');
            selectedCount = checkboxes.length;
            
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            if (selectedCount > 0) {
                bulkActionsBar.classList.remove('hidden');
                selectedCountSpan.textContent = selectedCount + ' account(s) selected';
            } else {
                bulkActionsBar.classList.add('hidden');
            }
            
            // Update bulk modal counts
            document.getElementById('bulkRestoreCount').textContent = 
                'Restore ' + selectedCount + ' selected account(s)?';
            document.getElementById('bulkDeleteCount').textContent = 
                'Permanently delete ' + selectedCount + ' selected account(s)?';
        }
        
        function clearSelection() {
            document.querySelectorAll('.archive-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            selectedCount = 0;
            updateSelection();
        }
        
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('.archive-checkbox');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateSelection();
        });
        
        // Individual checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('archive-checkbox')) {
                updateSelection();
                
                // Update select all checkbox
                const totalCheckboxes = document.querySelectorAll('.archive-checkbox').length;
                const checkedCheckboxes = document.querySelectorAll('.archive-checkbox:checked').length;
                document.getElementById('selectAll').checked = totalCheckboxes === checkedCheckboxes;
                document.getElementById('selectAll').indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
            }
        });
        
        // Search functionality
        document.getElementById('archiveSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.archive-row');
            let visibleRows = 0;
            
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide empty state
            const emptyState = document.getElementById('emptySearchState');
            const tableBody = document.getElementById('archiveTableBody');
            
            if (visibleRows === 0 && searchTerm !== '') {
                tableBody.style.display = 'none';
                emptyState.classList.remove('hidden');
            } else {
                tableBody.style.display = '';
                emptyState.classList.add('hidden');
            }
            
            clearSelection();
        });
        
        // Time filter functionality
        document.getElementById('timeFilter').addEventListener('change', function(e) {
            const days = parseInt(e.target.value);
            const rows = document.querySelectorAll('.archive-row');
            let visibleRows = 0;
            
            rows.forEach(row => {
                if (!days) {
                    row.style.display = '';
                    visibleRows++;
                    return;
                }
                
                const rowDate = new Date(row.getAttribute('data-date'));
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - days);
                
                if (rowDate >= cutoffDate) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show/hide empty state
            const emptyState = document.getElementById('emptySearchState');
            const tableBody = document.getElementById('archiveTableBody');
            
            if (visibleRows === 0 && days) {
                tableBody.style.display = 'none';
                emptyState.classList.remove('hidden');
            } else {
                tableBody.style.display = '';
                emptyState.classList.add('hidden');
            }
            
            clearSelection();
        });
        
        // Modal Functions
        function showRestoreModal(archiveId, accountType, accountName) {
            document.getElementById('restoreArchiveId').value = archiveId;
            document.getElementById('restoreAccountType').value = accountType;
            document.getElementById('restoreMessage').innerHTML = 
                `Are you sure you want to restore <strong>${accountName}</strong>?`;
            document.getElementById('restoreModal').classList.remove('hidden');
        }
        
        function closeRestoreModal() {
            document.getElementById('restoreModal').classList.add('hidden');
        }
        
        function showPermanentDeleteModal(archiveId, accountType, accountName) {
            document.getElementById('deleteArchiveId').value = archiveId;
            document.getElementById('deleteAccountType').value = accountType;
            document.getElementById('permanentDeleteMessage').innerHTML = 
                `Are you sure you want to permanently delete <strong>${accountName}</strong> from archives?`;
            document.getElementById('permanentDeleteModal').classList.remove('hidden');
        }
        
        function closePermanentDeleteModal() {
            document.getElementById('permanentDeleteModal').classList.add('hidden');
        }
        
        function showBulkRestoreModal() {
            if (selectedCount > 0) {
                document.getElementById('bulkRestoreModal').classList.remove('hidden');
            }
        }
        
        function closeBulkRestoreModal() {
            document.getElementById('bulkRestoreModal').classList.add('hidden');
        }
        
        function showBulkDeleteModal() {
            if (selectedCount > 0) {
                document.getElementById('bulkDeleteModal').classList.remove('hidden');
            }
        }
        
        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['restoreModal', 'permanentDeleteModal', 'bulkRestoreModal', 'bulkDeleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.classList.add('hidden');
                }
            });
        }
        
        // Auto-refresh archive stats every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>