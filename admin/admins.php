<?php
// admins.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once 'archive_functions.php';


// Check if admin is logged in and has permission to manage admins
requireAdminLogin();

// Only approved admins can access this page
if (!hasPermission()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$account_status_filter = isset($_GET['account_status']) ? $_GET['account_status'] : '';

// Function to log status changes
function logStatusChange($admin_id, $new_status, $reason, $changed_by) {
    global $pdo;
    
    // Get current status
    $current_stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = ?");
    $current_stmt->execute([$admin_id]);
    $current_status = $current_stmt->fetchColumn();
    
    $stmt = $pdo->prepare("INSERT INTO admin_status_logs 
        (admin_id, previous_status, new_status, reason, changed_by_admin_id) 
        VALUES (?, ?, ?, ?, ?)");
    
    return $stmt->execute([$admin_id, $current_status, $new_status, $reason, $changed_by]);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        // Add new admin
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitize_input($_POST['full_name']);
        $account_status = 'pending'; // Default status for new admins
        
        // Validate passwords match
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert admin - only 'admin' role now
                $stmt = $pdo->prepare("INSERT INTO admins 
                    (username, email, password, full_name, role, account_status) 
                    VALUES (?, ?, ?, ?, 'admin', ?)");
                
                if ($stmt->execute([$username, $email, $hashed_password, $full_name, $account_status])) {
                    $_SESSION['message'] = "Admin account added successfully with 'pending' status!";
                    header('Location: admins.php');
                    exit();
                } else {
                    $error = "Failed to add admin account. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['edit_admin'])) {
        // Edit admin
        $id = $_POST['id'];
        $full_name = sanitize_input($_POST['full_name']);
        $account_status = sanitize_input($_POST['account_status']);
        
        // Cannot edit your own account status
        if ($id == $_SESSION['admin_id']) {
            $error = "You cannot edit your own account status.";
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET 
                full_name = ?, account_status = ?, updated_at = NOW() 
                WHERE id = ?");
            
            if ($stmt->execute([$full_name, $account_status, $id])) {
                $_SESSION['message'] = "Admin account updated successfully!";
                header('Location: admins.php');
                exit();
            } else {
                $error = "Failed to update admin account. Please try again.";
            }
        }
    } elseif (isset($_POST['update_status'])) {
        // Bulk update status
        $id = $_POST['id'];
        $account_status = sanitize_input($_POST['account_status']);
        $status_reason = isset($_POST['status_reason']) ? sanitize_input($_POST['status_reason']) : '';
        
        // Cannot update your own status
        if ($id == $_SESSION['admin_id']) {
            $_SESSION['error'] = "You cannot change your own account status.";
            header('Location: admins.php');
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE admins SET 
            account_status = ?, status_reason = ?, updated_at = NOW() 
            WHERE id = ?");
        
        if ($stmt->execute([$account_status, $status_reason, $id])) {
            // Log the status change
            logStatusChange($id, $account_status, $status_reason, $_SESSION['admin_id']);
            
            $_SESSION['message'] = "Admin account status updated to " . strtoupper($account_status) . "!";
        } else {
            $_SESSION['error'] = "Failed to update admin account status.";
        }
        header('Location: admins.php');
        exit();
    } elseif (isset($_POST['delete_admin'])) {
        // Move to archive instead of deleting
        $id = $_POST['id'];
        $reason = isset($_POST['delete_reason']) ? sanitize_input($_POST['delete_reason']) : '';
        
        // Cannot delete yourself
        if ($id == $_SESSION['admin_id']) {
            $_SESSION['error'] = "You cannot archive your own account.";
        } else {
            if (moveToArchive('admin', $id, $reason)) {
                $_SESSION['message'] = "Admin account moved to archives successfully!";
            } else {
                $_SESSION['error'] = "Failed to archive admin account. Please try again.";
            }
        }
        header('Location: admins.php');
        exit();
    } elseif (isset($_POST['reset_password'])) {
        // Reset password
        $id = $_POST['id'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $id])) {
                $_SESSION['message'] = "Password reset successfully!";
            } else {
                $_SESSION['error'] = "Failed to reset password.";
            }
        }
        header('Location: admins.php');
        exit();
    }
}

// Get admin data for edit
$admin_data = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $admin_data = $stmt->fetch();
    if (!$admin_data) {
        $action = 'list';
        $error = "Admin account not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Command | ROTC Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-military: #1a365d;
            --secondary-military: #2d3748;
            --accent-gold: #d4af37;
            --accent-green: #38a169;
            --accent-red: #e53e3e;
            --accent-blue: #3182ce;
            --accent-orange: #ed8936;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        }
        
        .header-font {
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -0.025em;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        
        .table-container {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 12px;
        }
        
        .table-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.025em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: 1px solid #1d4ed8;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            color: #78350f;
            border: 1px solid #d97706;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #86efac 0%, #4ade80 100%);
            color: #14532d;
            border: 1px solid #4ade80;
        }
        
        .status-denied {
            background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
            color: #7f1d1d;
            border: 1px solid #f87171;
        }
        
        .status-active {
            background: linear-gradient(135deg, #86efac 0%, #4ade80 100%);
            color: #14532d;
            border: 1px solid #4ade80;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
            color: #7f1d1d;
            border: 1px solid #f87171;
        }
        
        .status-suspended {
            background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
            color: #7c2d12;
            border: 1px solid #fdba74;
        }
        
        .military-btn {
            background: linear-gradient(135deg, var(--primary-military) 0%, #2d3748 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .military-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .table-row-hover:hover {
            background: linear-gradient(90deg, rgba(237, 242, 247, 0.6) 0%, rgba(247, 250, 252, 0.3) 100%);
            border-left: 4px solid var(--accent-orange);
        }
        
        /* Modern toast */
        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            min-width: 320px;
            max-width: 400px;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast-success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.95) 0%, rgba(56, 161, 105, 0.95) 100%);
            color: white;
            border-left: 4px solid #38a169;
        }
        
        .toast-error {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.95) 0%, rgba(229, 62, 62, 0.95) 100%);
            color: white;
            border-left: 4px solid #e53e3e;
        }
        
        .toast-info {
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.95) 0%, rgba(49, 130, 206, 0.95) 100%);
            color: white;
            border-left: 4px solid #3182ce;
        }
        
        .input-field {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        
        .section-header {
            background: linear-gradient(90deg, var(--primary-military) 0%, #7c2d12 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .search-input {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 40px 10px 14px;
            font-size: 14px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234a5568'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        
        .avatar-initial {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            background: linear-gradient(135deg, var(--primary-military) 0%, var(--accent-orange) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover {
            border-color: var(--accent-orange);
            background: rgba(237, 137, 54, 0.05);
        }
        
        .pagination-btn.active {
            background: var(--accent-orange);
            color: white;
            border-color: var(--accent-orange);
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: white;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn.edit:hover {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
        }
        
        .action-btn.reset:hover {
            background: #9f7aea;
            color: white;
            border-color: #9f7aea;
        }
        
        .action-btn.delete:hover {
            background: var(--accent-red);
            color: white;
            border-color: var(--accent-red);
        }
        
        .action-btn.status:hover {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        .form-section h4 {
            color: var(--primary-military);
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .admin-gradient {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }
        
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fade-in 0.3s ease-out forwards;
        }
        
        .select-field {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234a5568'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 36px;
        }
        
        .select-field:focus {
            outline: none;
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        }
        
        /* Loading indicator */
        .loading-indicator {
            display: none;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .search-container {
            position: relative;
        }
        
        /* Password requirements styling */
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .password-requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
        }
        
        .password-requirement:last-child {
            margin-bottom: 0;
        }
        
        .password-requirement-icon {
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 10px;
        }
        
        .password-requirement-icon.valid {
            background: #10b981;
            color: white;
        }
        
        .password-requirement-icon.invalid {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .password-strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 12px;
            overflow: hidden;
        }
        
        .password-strength-meter-fill {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        /* Password match indicator */
        .match-valid {
            color: #10b981;
        }
        
        .match-invalid {
            color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toastContainer"></div>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="glass-card border-b border-gray-200 px-8 py-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl header-font font-bold text-gray-900 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg admin-gradient flex items-center justify-center">
                            <i class="fas fa-user-cog text-white"></i>
                        </div>
                        <span>Administration Command</span>
                    </h2>
                    <p class="text-gray-600 mt-1">Manage administrator accounts and status (Pending/Approved/Denied)</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="military-btn flex items-center gap-2">
                            <i class="fas fa-user-plus"></i>
                            <span>Add New Admin</span>
                        </a>
                    <?php else: ?>
                        <a href="admins.php" class="military-btn flex items-center gap-2 bg-gray-700 hover:bg-gray-800">
                            <i class="fas fa-arrow-left"></i>
                            <span>Return to List</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <main class="px-8 pb-8">
            <!-- Stats Overview -->
            <?php if ($action === 'list'): ?>
                <?php
                // Get counts for different statuses with filters applied
                $search_sql = $search ? "%$search%" : "%";
                $status_filter_sql = $account_status_filter ? $account_status_filter : "%";
                
                // Build WHERE clause for stats
                $stats_where = "";
                $stats_params = [];
                
                if ($search || $account_status_filter) {
                    $stats_where = "WHERE (full_name LIKE ? OR username LIKE ? OR email LIKE ?) ";
                    if ($account_status_filter) {
                        $stats_where .= "AND account_status = ?";
                        $stats_params = array_merge([$search_sql, $search_sql, $search_sql], [$account_status_filter]);
                    } else {
                        $stats_params = [$search_sql, $search_sql, $search_sql];
                    }
                }
                
                // Get filtered counts
                $pending_count = 0;
                $approved_count = 0;
                $denied_count = 0;
                $total_count = 0;
                
                if ($stats_where) {
                    // Get pending count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins $stats_where AND account_status = 'pending'");
                    $stmt->execute($stats_params);
                    $pending_count = $stmt->fetchColumn();
                    
                    // Get approved count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins $stats_where AND account_status = 'approved'");
                    $stmt->execute($stats_params);
                    $approved_count = $stmt->fetchColumn();
                    
                    // Get denied count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins $stats_where AND account_status = 'denied'");
                    $stmt->execute($stats_params);
                    $denied_count = $stmt->fetchColumn();
                    
                    // Get total count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins $stats_where");
                    $stmt->execute($stats_params);
                    $total_count = $stmt->fetchColumn();
                } else {
                    // Get all counts without filters
                    $status_counts = $pdo->query("
                        SELECT account_status, COUNT(*) as count 
                        FROM admins 
                        GROUP BY account_status
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($status_counts as $row) {
                        if ($row['account_status'] == 'pending') $pending_count = $row['count'];
                        if ($row['account_status'] == 'approved') $approved_count = $row['count'];
                        if ($row['account_status'] == 'denied') $denied_count = $row['count'];
                        $total_count += $row['count'];
                    }
                }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Review</p>
                                <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $pending_count; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-hourglass-half mr-2"></i>
                                <span>Awaiting approval</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Approved</p>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $approved_count; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-user-check mr-2"></i>
                                <span>Active administrators</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Denied</p>
                                <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $denied_count; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-ban mr-2"></i>
                                <span>Rejected accounts</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Accounts</p>
                                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $total_count; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-users-cog text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-database mr-2"></i>
                                <span>All admin accounts</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-green-500 hover:text-green-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Add/Edit Form -->
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">
                            <?php echo $action === 'add' ? 'Add New Administrator' : 'Update Administrator Account'; ?>
                        </h3>
                        <p class="text-orange-100 text-sm mt-1">
                            <?php echo $action === 'add' ? 'Fill all required fields to add a new administrator' : 'Update administrator information and status'; ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="" class="space-y-8">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $admin_data['id']; ?>">
                                <input type="hidden" name="edit_admin" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_admin" value="1">
                            <?php endif; ?>
                            
                            <!-- Account Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-user-circle mr-2"></i>
                                    Account Credentials
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Username <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="text" name="username" required 
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($admin_data['username']) : ''; ?>"
                                                   class="input-field w-full pl-10"
                                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                <i class="fas fa-at"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Email <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="email" name="email" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($admin_data['email']) : ''; ?>"
                                                   class="input-field w-full pl-10"
                                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($action === 'add'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Password <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <input type="password" name="password" required minlength="8"
                                                       class="input-field w-full pl-10">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">Minimum 8 characters</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Confirm Password <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <input type="password" name="confirm_password" required minlength="8"
                                                       class="input-field w-full pl-10">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-id-card mr-2"></i>
                                    Administrator Information
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Full Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="full_name" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($admin_data['full_name']) : ''; ?>"
                                               class="input-field w-full">
                                    </div>
                                    
                                    <?php if ($action === 'edit'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Account Status <span class="text-red-500">*</span>
                                            </label>
                                            <select name="account_status" required class="select-field w-full">
                                                <option value="pending" <?php echo isset($admin_data['account_status']) && $admin_data['account_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo isset($admin_data['account_status']) && $admin_data['account_status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="denied" <?php echo isset($admin_data['account_status']) && $admin_data['account_status'] == 'denied' ? 'selected' : ''; ?>>Denied</option>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Initial Status
                                            </label>
                                            <div class="input-field bg-gray-50 text-gray-500">
                                                Pending (Default for new accounts)
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">New accounts will be created with 'Pending' status</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($action === 'edit' && $admin_data['id'] == $_SESSION['admin_id']): ?>
                                <div class="form-section bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200">
                                    <h4 class="text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Important Notice
                                    </h4>
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                                <i class="fas fa-user text-yellow-600"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-yellow-800">You are editing your own account</p>
                                            <p class="text-sm text-yellow-700 mt-1">Note: You cannot change your own account status.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                <a href="admins.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                    Cancel
                                </a>
                                <button type="submit" class="military-btn px-6 py-3 flex items-center gap-2">
                                    <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                    <?php echo $action === 'add' ? 'Add Admin' : 'Update Account'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- Admin Accounts List -->
            <?php else: ?>
                <!-- Search and Filter -->
                <div class="glass-card rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Administrator Accounts</h3>
                        <div class="flex items-center gap-3">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-filter mr-1"></i>
                                <span>Filter & Search</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="search-container">
                            <input type="text" name="search" placeholder="Search administrators..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   id="searchInput"
                                   class="search-input w-full" 
                                   oninput="performSearch()">
                            <div id="searchLoading" class="loading-indicator">
                                <div class="w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin"></div>
                            </div>
                        </div>
                        
                        <div>
                            <select name="account_status" id="statusFilter" class="select-field w-full" onchange="performSearch()">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($account_status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($account_status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="denied" <?php echo ($account_status_filter == 'denied') ? 'selected' : ''; ?>>Denied</option>
                            </select>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button onclick="clearFilters()" class="px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium flex items-center gap-2 flex-1 justify-center">
                                <i class="fas fa-redo"></i>
                                <span>Clear Filters</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Accounts Table -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">Administrator Accounts</h3>
                        <p class="text-orange-100 text-sm mt-1" id="resultsCount">
                            <?php
                            // Build search query for results count
                            $search_sql = $search ? "%$search%" : "%";
                            $status_filter_sql = $account_status_filter ? $account_status_filter : "%";
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins 
                                                  WHERE (full_name LIKE ? 
                                                         OR username LIKE ? 
                                                         OR email LIKE ?)
                                                  AND (account_status LIKE ? OR ? = '%')");
                            $stmt->execute([$search_sql, $search_sql, $search_sql, $status_filter_sql, $status_filter_sql]);
                            $total = $stmt->fetchColumn();
                            echo "Showing " . min($total, 10) . " of $total administrators";
                            ?>
                        </p>
                    </div>
                    
                    <div class="table-container">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Administrator</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact Information</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Account Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Activity</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="adminTableBody" class="bg-white divide-y divide-gray-200">
                                <?php
                                // Get admins with pagination
                                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                                $limit = 10;
                                $offset = ($page - 1) * $limit;
                                
                                $stmt = $pdo->prepare("SELECT * FROM admins 
                                                      WHERE (full_name LIKE ? 
                                                             OR username LIKE ? 
                                                             OR email LIKE ?)
                                                      AND (account_status LIKE ? OR ? = '%')
                                                      ORDER BY 
                                                          CASE account_status 
                                                              WHEN 'pending' THEN 1
                                                              WHEN 'approved' THEN 2
                                                              WHEN 'denied' THEN 3
                                                              ELSE 4
                                                          END,
                                                          created_at DESC 
                                                      LIMIT ? OFFSET ?");
                                $stmt->bindParam(1, $search_sql);
                                $stmt->bindParam(2, $search_sql);
                                $stmt->bindParam(3, $search_sql);
                                $stmt->bindParam(4, $status_filter_sql);
                                $stmt->bindParam(5, $status_filter_sql);
                                $stmt->bindParam(6, $limit, PDO::PARAM_INT);
                                $stmt->bindParam(7, $offset, PDO::PARAM_INT);
                                $stmt->execute();
                                $admins = $stmt->fetchAll();
                                
                                if (empty($admins)): ?>
                                    <tr id="noResultsRow">
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user-tie text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Administrators Found</h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                No administrator accounts match your current search criteria. Try adjusting your filters or add a new administrator.
                                            </p>
                                            <a href="?action=add" class="inline-flex items-center gap-2 military-btn">
                                                <i class="fas fa-user-plus"></i>
                                                Add New Administrator
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr class="table-row-hover animate-fade-in">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="avatar-initial">
                                                        <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                                            <?php echo htmlspecialchars($admin['full_name']); ?>
                                                            <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full font-medium">You</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                                                            <i class="fas fa-user-cog"></i>
                                                            <span>@<?php echo htmlspecialchars($admin['username']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <i class="fas fa-envelope text-gray-400"></i>
                                                    <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($admin['email']); ?>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-calendar text-gray-400"></i>
                                                    <div class="text-sm text-gray-600">
                                                        Joined <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div>
                                                    <?php
                                                    $role_class = 'role-admin';
                                                    $role_text = 'Administrator';
                                                    ?>
                                                    <span class="status-badge <?php echo $role_class; ?>">
                                                        <i class="fas fa-user-tie"></i>
                                                        <?php echo $role_text; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <?php
                                                    $account_status = isset($admin['account_status']) ? $admin['account_status'] : 'pending';
                                                    $account_status_class = 'status-' . $account_status;
                                                    $account_status_text = ucfirst($account_status);
                                                    $account_status_icon = $account_status == 'approved' ? 'check-circle' : ($account_status == 'pending' ? 'clock' : 'times-circle');
                                                    ?>
                                                    <span class="status-badge <?php echo $account_status_class; ?>">
                                                        <i class="fas fa-<?php echo $account_status_icon; ?>"></i>
                                                        <?php echo $account_status_text; ?>
                                                    </span>
                                                    
                                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                    <div class="mt-2">
                                                        <button onclick="showUpdateStatusModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['full_name']); ?>', '<?php echo $account_status; ?>')"
                                                                class="text-xs px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors font-medium">
                                                            <i class="fas fa-edit mr-1"></i>
                                                            Update Status
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <i class="fas fa-sign-in-alt text-blue-600 text-xs"></i>
                                                        </div>
                                                        <div class="text-sm">
                                                            <?php if ($admin['last_login']): ?>
                                                                <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($admin['last_login'])); ?></div>
                                                                <div class="text-gray-600 text-xs"><?php echo date('g:i A', strtotime($admin['last_login'])); ?></div>
                                                            <?php else: ?>
                                                                <span class="text-gray-400 font-medium">Never logged in</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($admin['updated_at']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <i class="fas fa-history mr-1"></i>
                                                            Updated: <?php echo date('M j, Y', strtotime($admin['updated_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <a href="?action=edit&id=<?php echo $admin['id']; ?>" 
                                                       class="action-btn edit" title="Edit Administrator">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="showResetPasswordModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>', 'admin')"
                                                            class="action-btn reset" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                        <button onclick="showUpdateStatusModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['full_name']); ?>', '<?php echo $account_status; ?>')"
                                                                class="action-btn status" title="Update Status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                        <button onclick="confirmDelete(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['full_name']); ?>', 'admin')"
                                                                class="action-btn delete" title="Archive Administrator">
                                                            <i class="fas fa-archive"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total > $limit): ?>
                        <div class="px-6 py-4 border-t border-gray-200">
                            <?php
                            $totalPages = ceil($total / $limit);
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <?php echo min($offset + 1, $total); ?> to <?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> administrators
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&account_status=<?php echo urlencode($account_status_filter); ?>" 
                                           class="pagination-btn">
                                            <i class="fas fa-chevron-left mr-2"></i>
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center gap-1">
                                        <?php 
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $startPage + 4);
                                        $startPage = max(1, $endPage - 4);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-btn active"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&account_status=<?php echo urlencode($account_status_filter); ?>"
                                                   class="pagination-btn">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&account_status=<?php echo urlencode($account_status_filter); ?>"
                                           class="pagination-btn">
                                            Next
                                            <i class="fas fa-chevron-right ml-2"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96 modal-content">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Archive Administrator</h3>
                        <p class="text-sm text-gray-600 mb-6" id="deleteMessage">
                            Are you sure you want to archive this administrator? This action can be reversed from archives.
                        </p>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="deleteId">
                        <input type="hidden" name="delete_admin" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Archiving (Optional)
                            </label>
                            <textarea id="deleteReason" name="delete_reason"
                                      class="input-field w-full text-sm"
                                      rows="3"
                                      placeholder="Provide a reason for archiving this administrator..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Archive Administrator
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96 modal-content">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full" style="background-color: #233754;">
                            <i class="fas fa-key text-white text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Reset Password</h3>
                        <p class="text-sm text-gray-600" id="resetMessage">
                            Reset password for <span id="resetUsername" class="font-semibold" style="color: #233754;"></span>
                        </p>
                    </div>
                    <form id="resetPasswordForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="resetId">
                        <input type="hidden" name="reset_password" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Password
                            </label>
                            <div class="relative">
                                <input type="password" name="new_password" id="newPasswordReset" required 
                                       class="input-field w-full pr-10 focus:border-blue-800 focus:ring-2 focus:ring-blue-800/20"
                                       oninput="validateResetPassword()">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        onclick="togglePasswordVisibility('newPasswordReset', 'newPasswordToggle')">
                                    <i id="newPasswordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-requirements mt-2" id="resetPasswordRequirements">
                                <div class="password-requirement" id="reset-req-length">
                                    <span class="password-requirement-icon invalid">✗</span>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="password-requirement" id="reset-req-uppercase">
                                    <span class="password-requirement-icon invalid">✗</span>
                                    <span>At least one uppercase letter</span>
                                </div>
                                <div class="password-requirement" id="reset-req-lowercase">
                                    <span class="password-requirement-icon invalid">✗</span>
                                    <span>At least one lowercase letter</span>
                                </div>
                                <div class="password-requirement" id="reset-req-number">
                                    <span class="password-requirement-icon invalid">✗</span>
                                    <span>At least one number</span>
                                </div>
                                <div class="password-requirement" id="reset-req-special">
                                    <span class="password-requirement-icon invalid">✗</span>
                                    <span>At least one special character</span>
                                </div>
                                <div class="password-strength-meter">
                                    <div class="password-strength-meter-fill" id="resetPasswordStrength" style="width: 0%; background-color: #ef4444;"></div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirmPasswordReset" required
                                       class="input-field w-full pr-10 focus:border-blue-800 focus:ring-2 focus:ring-blue-800/20"
                                       oninput="validateResetPasswordMatch()">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        onclick="togglePasswordVisibility('confirmPasswordReset', 'confirmPasswordToggle')">
                                    <i id="confirmPasswordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="resetPasswordMatch" class="mt-2 text-sm hidden">
                                <span id="resetMatchIcon" class="mr-1"></span>
                                <span id="resetMatchText"></span>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeResetPasswordModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" id="submitPasswordBtn"
                                    class="flex-1 px-4 py-3 military-btn opacity-50 cursor-not-allowed" disabled>
                                Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96 modal-content">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                            <i class="fas fa-user-check text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Update Account Status</h3>
                        <p class="text-sm text-gray-600">
                            Update account status for <span id="statusUsername" class="font-semibold text-blue-700"></span>
                        </p>
                    </div>
                    <form id="updateStatusForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="statusId">
                        <input type="hidden" name="update_status" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Status <span class="text-red-500">*</span>
                            </label>
                            <select name="account_status" required class="select-field w-full" id="statusSelect">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="denied">Denied</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Status Change (Optional)
                            </label>
                            <textarea name="status_reason" 
                                      class="input-field w-full text-sm"
                                      rows="3"
                                      placeholder="Provide a reason for changing the status..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeUpdateStatusModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-colors font-medium">
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        // Toast notification functions
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            const toastHTML = `
                <div id="${toastId}" class="toast toast-${type}">
                    <div class="toast-icon text-xl">
                        <i class="fas ${icons[type]}"></i>
                    </div>
                    <div class="toast-content text-sm font-medium">
                        ${message}
                    </div>
                    <button class="toast-close" onclick="closeToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            const toast = document.getElementById(toastId);
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }
        
        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }
        
        // Delete modal function
        function confirmDelete(id, name, type = 'admin') {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to archive <strong>${name}</strong>?`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteForm').reset();
        }
        
        // Reset password modal functions
        function showResetPasswordModal(id, username, type = 'admin') {
            document.getElementById('resetId').value = id;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }
        
        // Password visibility toggle
        function togglePasswordVisibility(passwordFieldId, toggleIconId) {
            const passwordField = document.getElementById(passwordFieldId);
            const toggleIcon = document.getElementById(toggleIconId);
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Validate password strength for reset password
        function validateResetPassword() {
            const password = document.getElementById('newPasswordReset').value;
            const strengthBar = document.getElementById('resetPasswordStrength');
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            // Check requirements
            const hasMinLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            // Update requirement icons
            updateRequirement('reset-req-length', hasMinLength);
            updateRequirement('reset-req-uppercase', hasUppercase);
            updateRequirement('reset-req-lowercase', hasLowercase);
            updateRequirement('reset-req-number', hasNumber);
            updateRequirement('reset-req-special', hasSpecial);
            
            // Calculate strength score
            let strength = 0;
            if (hasMinLength) strength += 1;
            if (hasUppercase) strength += 1;
            if (hasLowercase) strength += 1;
            if (hasNumber) strength += 1;
            if (hasSpecial) strength += 1;
            
            // Update strength bar
            const percentage = (strength / 5) * 100;
            strengthBar.style.width = percentage + '%';
            
            // Update color based on strength
            if (strength <= 2) {
                strengthBar.style.backgroundColor = '#ef4444'; // Red
            } else if (strength <= 4) {
                strengthBar.style.backgroundColor = '#f59e0b'; // Orange
            } else {
                strengthBar.style.backgroundColor = '#10b981'; // Green
            }
            
            // Validate overall password
            validateResetPasswordMatch();
        }
        
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('.password-requirement-icon');
            
            if (isValid) {
                icon.classList.remove('invalid');
                icon.classList.add('valid');
                icon.textContent = '✓';
            } else {
                icon.classList.remove('valid');
                icon.classList.add('invalid');
                icon.textContent = '✗';
            }
        }
        
        // Validate password match for reset password
        function validateResetPasswordMatch() {
            const password = document.getElementById('newPasswordReset').value;
            const confirmPassword = document.getElementById('confirmPasswordReset').value;
            const matchContainer = document.getElementById('resetPasswordMatch');
            const matchIcon = document.getElementById('resetMatchIcon');
            const matchText = document.getElementById('resetMatchText');
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            // Check all requirements
            const hasMinLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            const passwordsMatch = password === confirmPassword && password !== '';
            
            const allRequirementsMet = hasMinLength && hasUppercase && hasLowercase && hasNumber && hasSpecial && passwordsMatch;
            
            // Update match indicator
            if (confirmPassword === '') {
                matchContainer.classList.add('hidden');
            } else {
                matchContainer.classList.remove('hidden');
                if (passwordsMatch) {
                    matchIcon.innerHTML = '<i class="fas fa-check-circle match-valid"></i>';
                    matchIcon.className = 'match-valid';
                    matchText.textContent = 'Passwords match';
                    matchText.className = 'match-valid';
                } else {
                    matchIcon.innerHTML = '<i class="fas fa-times-circle match-invalid"></i>';
                    matchIcon.className = 'match-invalid';
                    matchText.textContent = 'Passwords do not match';
                    matchText.className = 'match-invalid';
                }
            }
            
            // Enable/disable submit button
            if (allRequirementsMet) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('hover:from-blue-800', 'hover:to-blue-900');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('hover:from-blue-800', 'hover:to-blue-900');
            }
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
            
            // Reset validation UI
            const requirements = document.querySelectorAll('#resetPasswordRequirements .password-requirement-icon');
            requirements.forEach(icon => {
                icon.classList.remove('valid');
                icon.classList.add('invalid');
                icon.textContent = '✗';
            });
            
            document.getElementById('resetPasswordStrength').style.width = '0%';
            document.getElementById('resetPasswordStrength').style.backgroundColor = '#ef4444';
            document.getElementById('resetPasswordMatch').classList.add('hidden');
            
            const submitBtn = document.getElementById('submitPasswordBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        
        // Update status modal functions
        function showUpdateStatusModal(id, name, currentStatus) {
            document.getElementById('statusId').value = id;
            document.getElementById('statusUsername').textContent = name;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('updateStatusModal').classList.remove('hidden');
        }
        
        function closeUpdateStatusModal() {
            document.getElementById('updateStatusModal').classList.add('hidden');
            document.getElementById('updateStatusForm').reset();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const resetModal = document.getElementById('resetPasswordModal');
            const statusModal = document.getElementById('updateStatusModal');
            
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
            if (event.target == resetModal) {
                closeResetPasswordModal();
            }
            if (event.target == statusModal) {
                closeUpdateStatusModal();
            }
        }
        
        // Automatic search and filter functions
        let searchTimeout;
        let isSearching = false;
        
        function performSearch() {
            clearTimeout(searchTimeout);
            
            // Show loading indicator
            document.getElementById('searchLoading').style.display = 'block';
            
            // Get current search and filter values
            const searchValue = document.getElementById('searchInput').value;
            const statusValue = document.getElementById('statusFilter').value;
            
            // Add a small delay to prevent too many requests
            searchTimeout = setTimeout(() => {
                updateURLAndReload(searchValue, statusValue);
            }, 500);
        }
        
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            updateURLAndReload('', '');
        }
        
        function updateURLAndReload(search, status) {
            // Build URL with current parameters
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);
            
            // Update search parameter
            if (search) {
                params.set('search', search);
            } else {
                params.delete('search');
            }
            
            // Update status filter parameter
            if (status) {
                params.set('account_status', status);
            } else {
                params.delete('account_status');
            }
            
            // Reset to first page when searching
            params.set('page', '1');
            
            // Update URL without page reload (using History API)
            const newUrl = `${url.pathname}?${params.toString()}`;
            window.history.pushState({}, '', newUrl);
            
            // Reload the page to apply filters
            window.location.reload();
        }
        
        // Initialize animations and search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('animate-fade-in');
            });
            
            // Show success/error messages as toasts
            const successMessage = document.querySelector('.bg-green-50');
            const errorMessage = document.querySelector('.bg-red-50');
            
            if (successMessage) {
                const messageText = successMessage.querySelector('p').textContent;
                showToast(messageText, 'success');
                setTimeout(() => {
                    successMessage.remove();
                }, 3000);
            }
            
            if (errorMessage) {
                const messageText = errorMessage.querySelector('p').textContent;
                showToast(messageText, 'error');
                setTimeout(() => {
                    errorMessage.remove();
                }, 3000);
            }
            
            // Hide loading indicator after page loads
            document.getElementById('searchLoading').style.display = 'none';
            
            // Add real-time search feedback
            const searchInput = document.getElementById('searchInput');
            if (searchInput.value) {
                searchInput.classList.add('bg-yellow-50', 'border-yellow-300');
            }
            
            // Add event listener for input to show loading
            searchInput.addEventListener('input', function() {
                if (this.value) {
                    this.classList.add('bg-yellow-50', 'border-yellow-300');
                } else {
                    this.classList.remove('bg-yellow-50', 'border-yellow-300');
                }
            });
            
            // Initialize password validation for reset modal
            document.getElementById('newPasswordReset')?.addEventListener('input', validateResetPassword);
            document.getElementById('confirmPasswordReset')?.addEventListener('input', validateResetPasswordMatch);
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            window.location.reload();
        });
    </script>
</body>
</html>