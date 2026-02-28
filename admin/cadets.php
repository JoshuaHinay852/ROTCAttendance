<?php
// cadets.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once 'archive_functions.php';
requireAdminLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

$course_options = [
    'Bachelor of Science in Electrical Technology',
    'Bachelor of Science in Electronics Technology',
    'Bachelor of Science in Industrial Technology major in Food Preparation and Service Management',
    'Bachelor of Science in Information Technology',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology'
];

// Function to log status changes
function logCadetStatusChange($pdo, $cadet_id, $new_status, $reason, $changed_by) {
    // Get current status
    $current_stmt = $pdo->prepare("SELECT status FROM cadet_accounts WHERE id = ?");
    $current_stmt->execute([$cadet_id]);
    $current_status = $current_stmt->fetchColumn();
    
    $stmt = $pdo->prepare("INSERT INTO cadet_status_logs 
        (cadet_id, previous_status, new_status, reason, changed_by_admin_id) 
        VALUES (?, ?, ?, ?, ?)");
    
    return $stmt->execute([$cadet_id, $current_status, $new_status, $reason, $changed_by]);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_cadet'])) {
        // Add new cadet
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $middle_name = sanitize_input($_POST['middle_name']);
        $course = sanitize_input($_POST['course']);
        $full_address = sanitize_input($_POST['full_address']);
        $platoon = sanitize_input($_POST['platoon']);
        $company = sanitize_input($_POST['company']);
        $dob = sanitize_input($_POST['dob']);
        $mothers_name = sanitize_input($_POST['mothers_name']);
        $fathers_name = sanitize_input($_POST['fathers_name']);
        
        // Validate passwords match
        if (!in_array($course, $course_options, true)) {
            $error = "Please select a valid course.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM cadet_accounts WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Username or email already exists.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert cadet with default 'pending' status
                $stmt = $pdo->prepare("INSERT INTO cadet_accounts 
                    (username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, 
                     fathers_name, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                
                if ($stmt->execute([
                    $username, $email, $hashed_password, $first_name, $last_name, 
                    $middle_name, $course, $full_address, $platoon, $company, 
                    $dob, $mothers_name, $fathers_name, $_SESSION['admin_id']
                ])) {
                    $_SESSION['message'] = "Cadet added successfully! Status: Pending";
                    header('Location: cadets.php');
                    exit();
                } else {
                    $error = "Failed to add cadet. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['edit_cadet'])) {
        // Edit cadet
        $id = $_POST['id'];
        $first_name = sanitize_input($_POST['first_name']);
        $last_name = sanitize_input($_POST['last_name']);
        $middle_name = sanitize_input($_POST['middle_name']);
        $course = sanitize_input($_POST['course']);
        $full_address = sanitize_input($_POST['full_address']);
        $platoon = sanitize_input($_POST['platoon']);
        $company = sanitize_input($_POST['company']);
        $dob = sanitize_input($_POST['dob']);
        $mothers_name = sanitize_input($_POST['mothers_name']);
        $fathers_name = sanitize_input($_POST['fathers_name']);
        $status = sanitize_input($_POST['status']);

        if (!in_array($course, $course_options, true)) {
            $error = "Please select a valid course.";
        } else {
            $stmt = $pdo->prepare("UPDATE cadet_accounts SET 
                first_name = ?, last_name = ?, middle_name = ?, course = ?, 
                full_address = ?, platoon = ?, company = ?, dob = ?, 
                mothers_name = ?, fathers_name = ?, status = ?, updated_at = NOW() 
                WHERE id = ?");
            
            if ($stmt->execute([
                $first_name, $last_name, $middle_name, $course, $full_address, 
                $platoon, $company, $dob, $mothers_name, $fathers_name, 
                $status, $id
            ])) {
                $_SESSION['message'] = "Cadet updated successfully!";
                header('Location: cadets.php');
                exit();
            } else {
                $error = "Failed to update cadet. Please try again.";
            }
        }
    } elseif (isset($_POST['update_cadet_status'])) {
        // Update cadet status
        $id = $_POST['id'];
        $status = sanitize_input($_POST['status']);
        $status_reason = isset($_POST['status_reason']) ? sanitize_input($_POST['status_reason']) : '';
        
        $stmt = $pdo->prepare("UPDATE cadet_accounts SET 
            status = ?, updated_at = NOW() 
            WHERE id = ?");
        
        if ($stmt->execute([$status, $id])) {
            // Log the status change - pass $pdo as first parameter
            logCadetStatusChange($pdo, $id, $status, $status_reason, $_SESSION['admin_id']);
            
            $_SESSION['message'] = "Cadet status updated to " . strtoupper($status) . "!";
        } else {
            $_SESSION['error'] = "Failed to update cadet status.";
        }
        header('Location: cadets.php');
        exit();
    } elseif (isset($_POST['delete_cadet'])) {
        // Move to archive instead of deleting
        $id = $_POST['id'];
        $reason = isset($_POST['delete_reason']) ? sanitize_input($_POST['delete_reason']) : '';
        
        if (moveToArchive('cadet', $id, $reason)) {
            $_SESSION['message'] = "Cadet moved to archives successfully!";
        } else {
            $_SESSION['error'] = "Failed to archive cadet. Please try again.";
        }
        header('Location: cadets.php');
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
            $stmt = $pdo->prepare("UPDATE cadet_accounts SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $id])) {
                $_SESSION['message'] = "Password reset successfully!";
            } else {
                $_SESSION['error'] = "Failed to reset password.";
            }
        }
        header('Location: cadets.php');
        exit();
    }
}

// Get cadet data for edit
$cadet = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM cadet_accounts WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $cadet = $stmt->fetch();
    if (!$cadet) {
        $action = 'list';
        $error = "Cadet not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Management | ROTC Command Center</title>
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
            border-left: 4px solid var(--accent-blue);
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
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }

        /* Ensure input text always starts after left icons */
        .field-with-icon {
            position: relative;
        }

        .field-with-icon .field-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
            z-index: 1;
        }

        .field-with-icon .input-field,
        .field-with-icon .select-field {
            padding-left: 42px;
        }

        .field-with-icon.textarea-icon .field-icon {
            top: 12px;
            transform: none;
        }

        .field-with-icon.textarea-icon .input-field {
            padding-left: 42px;
        }
        
        .section-header {
            background: linear-gradient(90deg, var(--primary-military) 0%, var(--secondary-military) 100%);
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
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
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
            background: linear-gradient(135deg, var(--primary-military) 0%, var(--accent-blue) 100%);
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
            border-color: var(--accent-blue);
            background: rgba(49, 130, 206, 0.05);
        }
        
        .pagination-btn.active {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
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
        
        .action-btn.view:hover {
            background: var(--accent-green);
            color: white;
            border-color: var(--accent-green);
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
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
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
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-600 to-indigo-800 flex items-center justify-center">
                            <i class="fas fa-user-shield text-white"></i>
                        </div>
                        <span>Cadet Command Center</span>
                    </h2>
                    <p class="text-gray-600 mt-1">Manage cadet accounts, status, and information</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="military-btn flex items-center gap-2">
                            <i class="fas fa-user-plus"></i>
                            <span>Enlist New Cadet</span>
                        </a>
                    <?php else: ?>
                        <a href="cadets.php" class="military-btn flex items-center gap-2 bg-gray-700 hover:bg-gray-800">
                            <i class="fas fa-arrow-left"></i>
                            <span>Return to Roster</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <main class="px-8 pb-8">
            <!-- Stats Overview -->
            <?php if ($action === 'list'): ?>
                <?php
                $status_counts = $pdo->query("
                    SELECT status, COUNT(*) as count 
                    FROM cadet_accounts 
                    WHERE is_archived = FALSE 
                    GROUP BY status
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                $pending_count = 0;
                $approved_count = 0;
                $denied_count = 0;
                $total_count = 0;
                
                foreach ($status_counts as $row) {
                    if ($row['status'] == 'pending') $pending_count = $row['count'];
                    if ($row['status'] == 'approved') $approved_count = $row['count'];
                    if ($row['status'] == 'denied') $denied_count = $row['count'];
                    $total_count += $row['count'];
                }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">

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
                                <span>All cadet accounts</span>
                            </div>
                        </div>
                    </div>

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
                                <span>Active cadets</span>
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
                            <?php echo $action === 'add' ? 'Enlist New Cadet' : 'Update Cadet Record'; ?>
                        </h3>
                        <p class="text-blue-100 text-sm mt-1">
                            <?php echo $action === 'add' ? 'Fill all required fields to enlist a new cadet' : 'Update cadet information and status'; ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="" class="space-y-8">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $cadet['id']; ?>">
                                <input type="hidden" name="edit_cadet" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_cadet" value="1">
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
                                        <div class="field-with-icon">
                                            <input type="text" name="username" required 
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['username']) : ''; ?>"
                                                   class="input-field w-full pl-10"
                                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                            <div class="field-icon">
                                                <i class="fas fa-at"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Email <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="email" name="email" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['email']) : ''; ?>"
                                                   class="input-field w-full pl-10"
                                                   <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                            <div class="field-icon">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($action === 'add'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Password <span class="text-red-500">*</span>
                                            </label>
                                            <div class="field-with-icon">
                                                <input type="password" name="password" id="cadetPassword" required minlength="8"
                                                       class="input-field w-full pl-10 pr-10">
                                                <div class="field-icon">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                                <button type="button"
                                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                                        onclick="togglePasswordVisibility('cadetPassword', 'cadetPasswordToggle')">
                                                    <i id="cadetPasswordToggle" class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">Minimum 8 characters</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Confirm Password <span class="text-red-500">*</span>
                                            </label>
                                            <div class="field-with-icon">
                                                <input type="password" name="confirm_password" id="cadetConfirmPassword" required minlength="8"
                                                       class="input-field w-full pl-10 pr-10">
                                                <div class="field-icon">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                                <button type="button"
                                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                                        onclick="togglePasswordVisibility('cadetConfirmPassword', 'cadetConfirmPasswordToggle')">
                                                    <i id="cadetConfirmPasswordToggle" class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-id-card mr-2"></i>
                                    Personal Information
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            First Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="text" name="first_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['first_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Last Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="text" name="last_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['last_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-user-tag"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Middle Name
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="text" name="middle_name"
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['middle_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-font"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <?php
                                        $selected_course = $action === 'edit'
                                            ? (string) $cadet['course']
                                            : (isset($_POST['course']) ? sanitize_input($_POST['course']) : '');
                                        ?>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Course <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <select name="course" required class="select-field w-full pl-10">
                                                <option value="">Select Course</option>
                                                <?php foreach ($course_options as $course_option): ?>
                                                    <option value="<?php echo htmlspecialchars($course_option); ?>"
                                                        <?php echo $selected_course === $course_option ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course_option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="field-icon">
                                                <i class="fas fa-graduation-cap"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Date of Birth <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="date" name="dob" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['dob']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Family Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-home mr-2"></i>
                                    Family Information
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Mother's Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="text" name="mothers_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['mothers_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-female"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Father's Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <input type="text" name="fathers_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($cadet['fathers_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="field-icon">
                                                <i class="fas fa-male"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ROTC Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-flag mr-2"></i>
                                    ROTC Assignment
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Full Address <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon textarea-icon">
                                            <textarea name="full_address" required rows="3"
                                                      class="input-field w-full pl-10"><?php echo $action === 'edit' ? htmlspecialchars($cadet['full_address']) : ''; ?></textarea>
                                            <div class="field-icon">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Platoon <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <select name="platoon" required class="select-field w-full pl-10">
                                                <option value="">Select Platoon</option>
                                                <option value="1" <?php echo ($action === 'edit' && $cadet['platoon'] == '1') ? 'selected' : ''; ?>>Platoon 1</option>
                                                <option value="2" <?php echo ($action === 'edit' && $cadet['platoon'] == '2') ? 'selected' : ''; ?>>Platoon 2</option>
                                                <option value="3" <?php echo ($action === 'edit' && $cadet['platoon'] == '3') ? 'selected' : ''; ?>>Platoon 3</option>
                                            </select>
                                            <div class="field-icon">
                                                <i class="fas fa-flag"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Company <span class="text-red-500">*</span>
                                        </label>
                                        <div class="field-with-icon">
                                            <select name="company" required class="select-field w-full pl-10">
                                                <option value="">Select Company</option>
                                                <option value="Alpha" <?php echo ($action === 'edit' && $cadet['company'] == 'Alpha') ? 'selected' : ''; ?>>Alpha Company</option>
                                                <option value="Bravo" <?php echo ($action === 'edit' && $cadet['company'] == 'Bravo') ? 'selected' : ''; ?>>Bravo Company</option>
                                                <option value="Charlie" <?php echo ($action === 'edit' && $cadet['company'] == 'Charlie') ? 'selected' : ''; ?>>Charlie Company</option>
                                                <option value="Delta" <?php echo ($action === 'edit' && $cadet['company'] == 'Delta') ? 'selected' : ''; ?>>Delta Company</option>
                                                <option value="Echo" <?php echo ($action === 'edit' && $cadet['company'] == 'Echo') ? 'selected' : ''; ?>>Echo Company</option>
                                                <option value="Foxtrot" <?php echo ($action === 'edit' && $cadet['company'] == 'Foxtrot') ? 'selected' : ''; ?>>Foxtrot Company</option>
                                                <option value="Golf" <?php echo ($action === 'edit' && $cadet['company'] == 'Golf') ? 'selected' : ''; ?>>Golf Company</option>
                                            </select>
                                            <div class="field-icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($action === 'edit'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Account Status <span class="text-red-500">*</span>
                                            </label>
                                            <div class="field-with-icon">
                                                <select name="status" required class="select-field w-full pl-10">
                                                    <option value="pending" <?php echo $cadet['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="approved" <?php echo $cadet['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                    <option value="denied" <?php echo $cadet['status'] == 'denied' ? 'selected' : ''; ?>>Denied</option>
                                                </select>
                                                <div class="field-icon">
                                                    <i class="fas fa-toggle-on"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Initial Status
                                            </label>
                                            <div class="field-with-icon">
                                                <div class="input-field bg-gray-50 text-gray-500 pl-10">
                                                    Pending (Default for new cadets)
                                                </div>
                                                <div class="field-icon">
                                                    <i class="fas fa-hourglass-half"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">New cadets will be created with 'Pending' status</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                <a href="cadets.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                    Cancel
                                </a>
                                <button type="submit" class="military-btn px-6 py-3 flex items-center gap-2">
                                    <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                    <?php echo $action === 'add' ? 'Enlist Cadet' : 'Update Record'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- Cadets List -->
            <?php else: ?>
                <!-- Search and Filter -->
                <!-- Search and Filter -->
                <div class="glass-card rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Cadet Roster</h3>
                        <div class="flex items-center gap-3">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-filter mr-1"></i>
                                <span>Filter & Search</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <input type="text" id="searchInput" placeholder="Search cadets..." 
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                class="search-input w-full">
                        </div>
                        
                        <div>
                            <select id="platoonFilter" class="select-field w-full">
                                <option value="">All Platoons</option>
                                <option value="1" <?php echo (isset($_GET['platoon']) && $_GET['platoon'] == '1') ? 'selected' : ''; ?>>Platoon 1</option>
                                <option value="2" <?php echo (isset($_GET['platoon']) && $_GET['platoon'] == '2') ? 'selected' : ''; ?>>Platoon 2</option>
                                <option value="3" <?php echo (isset($_GET['platoon']) && $_GET['platoon'] == '3') ? 'selected' : ''; ?>>Platoon 3</option>
                            </select>
                        </div>
                        
                        <div>
                            <select id="companyFilter" class="select-field w-full">
                                <option value="">All Companies</option>
                                <option value="Alpha" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Alpha') ? 'selected' : ''; ?>>Alpha Company</option>
                                <option value="Bravo" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Bravo') ? 'selected' : ''; ?>>Bravo Company</option>
                                <option value="Charlie" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Charlie') ? 'selected' : ''; ?>>Charlie Company</option>
                                <option value="Delta" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Delta') ? 'selected' : ''; ?>>Delta Company</option>
                                <option value="Echo" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Echo') ? 'selected' : ''; ?>>Echo Company</option>
                                <option value="Foxtrot" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Foxtrot') ? 'selected' : ''; ?>>Foxtrot Company</option>
                                <option value="Golf" <?php echo (isset($_GET['company']) && $_GET['company'] == 'Golf') ? 'selected' : ''; ?>>Golf Company</option>
                            </select>
                        </div>
                        
                        <div>
                            <select id="statusFilter" class="select-field w-full">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="denied" <?php echo (isset($_GET['status']) && $_GET['status'] == 'denied') ? 'selected' : ''; ?>>Denied</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-end">
                        <a href="cadets.php" class="px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium flex items-center gap-2">
                            <i class="fas fa-redo"></i>
                            <span>Reset All Filters</span>
                        </a>
                    </div>
                </div>
                
                <!-- Cadets Table -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">Cadet Accounts</h3>
                        <p class="text-blue-100 text-sm mt-1">
                            <?php
                            // Build search/filter query values (treat empty filter params as "all")
                            $search_input = isset($_GET['search']) ? trim(sanitize_input($_GET['search'])) : '';
                            $platoon_input = isset($_GET['platoon']) ? trim(sanitize_input($_GET['platoon'])) : '';
                            $company_input = isset($_GET['company']) ? trim(sanitize_input($_GET['company'])) : '';
                            $status_input = isset($_GET['status']) ? trim(sanitize_input($_GET['status'])) : '';

                            $search = $search_input !== '' ? "%{$search_input}%" : '%';
                            $platoon = $platoon_input !== '' ? $platoon_input : '%';
                            $company = $company_input !== '' ? $company_input : '%';
                            $status = $status_input !== '' ? $status_input : '%';
                            $limit = 10;
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cadet_accounts 
                                                  WHERE (CONCAT(first_name, ' ', last_name) LIKE ? 
                                                         OR username LIKE ? 
                                                         OR email LIKE ?)
                                                  AND (platoon LIKE ? OR ? = '%')
                                                  AND (company LIKE ? OR ? = '%')
                                                  AND (status LIKE ? OR ? = '%')
                                                  AND is_archived = FALSE");
                            $stmt->execute([$search, $search, $search, $platoon, $platoon, $company, $company, $status, $status]);
                            $total = $stmt->fetchColumn();
                            echo "Showing " . min($total, $limit) . " of $total cadets";
                            ?>
                        </p>
                    </div>
                    
                    <div class="table-container">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cadet Information</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact & Course</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ROTC Assignment</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Account Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Activity</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                // Get cadets with pagination
                                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                                $totalPages = max(1, (int) ceil($total / $limit));
                                if ($page > $totalPages) {
                                    $page = $totalPages;
                                }
                                
                                $offset = ($page - 1) * $limit;
                                
                                $stmt = $pdo->prepare("SELECT * FROM cadet_accounts 
                                                      WHERE (CONCAT(first_name, ' ', last_name) LIKE ? 
                                                             OR username LIKE ? 
                                                             OR email LIKE ?)
                                                      AND (platoon LIKE ? OR ? = '%')
                                                      AND (company LIKE ? OR ? = '%')
                                                      AND (status LIKE ? OR ? = '%')
                                                      AND is_archived = FALSE
                                                      ORDER BY 
                                                          CASE status 
                                                              WHEN 'pending' THEN 1
                                                              WHEN 'approved' THEN 2
                                                              WHEN 'denied' THEN 3
                                                              ELSE 4
                                                          END,
                                                          created_at DESC 
                                                      LIMIT ? OFFSET ?");
                                $stmt->bindParam(1, $search);
                                $stmt->bindParam(2, $search);
                                $stmt->bindParam(3, $search);
                                $stmt->bindParam(4, $platoon);
                                $stmt->bindParam(5, $platoon);
                                $stmt->bindParam(6, $company);
                                $stmt->bindParam(7, $company);
                                $stmt->bindParam(8, $status);
                                $stmt->bindParam(9, $status);
                                $stmt->bindParam(10, $limit, PDO::PARAM_INT);
                                $stmt->bindParam(11, $offset, PDO::PARAM_INT);
                                $stmt->execute();
                                $cadets = $stmt->fetchAll();
                                
                                if (empty($cadets)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user-graduate text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Cadets Found</h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                No cadet records match your current search criteria. Try adjusting your filters or add a new cadet.
                                            </p>
                                            <a href="?action=add" class="inline-flex items-center gap-2 military-btn">
                                                <i class="fas fa-user-plus"></i>
                                                Enlist New Cadet
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cadets as $cadet): ?>
                                        <tr class="table-row-hover" id="cadet-row-<?php echo $cadet['id']; ?>">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="avatar-initial">
                                                        <?php echo strtoupper(substr($cadet['first_name'], 0, 1) . substr($cadet['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?>
                                                            <?php if ($cadet['middle_name']): ?>
                                                                <?php echo htmlspecialchars(' ' . $cadet['middle_name'][0] . '.'); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                                                            <i class="fas fa-user"></i>
                                                            <span>@<?php echo htmlspecialchars($cadet['username']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <i class="fas fa-envelope text-gray-400"></i>
                                                    <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($cadet['email']); ?>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-graduation-cap text-gray-400"></i>
                                                    <div class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($cadet['course']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <i class="fas fa-flag text-blue-600 text-xs"></i>
                                                        </div>
                                                        <span class="text-sm font-medium text-gray-900">
                                                            Platoon <?php echo htmlspecialchars($cadet['platoon']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center">
                                                            <i class="fas fa-users text-indigo-600 text-xs"></i>
                                                        </div>
                                                        <span class="text-sm text-gray-600">
                                                            <?php echo htmlspecialchars($cadet['company']); ?> Company
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <?php
                                                    $cadet_status = isset($cadet['status']) ? $cadet['status'] : 'pending';
                                                    $cadet_status_class = 'status-' . $cadet_status;
                                                    $cadet_status_text = ucfirst($cadet_status);
                                                    $cadet_status_icon = $cadet_status == 'approved' ? 'check-circle' : ($cadet_status == 'pending' ? 'clock' : 'times-circle');
                                                    ?>
                                                    <span class="status-badge <?php echo $cadet_status_class; ?>">
                                                        <i class="fas fa-<?php echo $cadet_status_icon; ?>"></i>
                                                        <?php echo $cadet_status_text; ?>
                                                    </span>
                                                    
                                                    <div class="mt-2">
                                                        <button onclick="showUpdateStatusModal(<?php echo $cadet['id']; ?>, '<?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?>', '<?php echo $cadet_status; ?>')"
                                                                class="text-xs px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors font-medium">
                                                            <i class="fas fa-edit mr-1"></i>
                                                            Update Status
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <i class="fas fa-sign-in-alt text-blue-600 text-xs"></i>
                                                        </div>
                                                        <div class="text-sm">
                                                            <?php if ($cadet['last_login']): ?>
                                                                <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($cadet['last_login'])); ?></div>
                                                                <div class="text-gray-600 text-xs"><?php echo date('g:i A', strtotime($cadet['last_login'])); ?></div>
                                                            <?php else: ?>
                                                                <span class="text-gray-400 font-medium">Never logged in</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($cadet['updated_at']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <i class="fas fa-history mr-1"></i>
                                                            Updated: <?php echo date('M j, Y', strtotime($cadet['updated_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <a href="?action=edit&id=<?php echo $cadet['id']; ?>" 
                                                       class="action-btn edit" title="Edit Cadet">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="showResetPasswordModal(<?php echo $cadet['id']; ?>, '<?php echo htmlspecialchars($cadet['username']); ?>')"
                                                            class="action-btn reset" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button onclick="showUpdateStatusModal(<?php echo $cadet['id']; ?>, '<?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?>', '<?php echo $cadet_status; ?>')"
                                                            class="action-btn status" title="Update Status">
                                                        <i class="fas fa-toggle-on"></i>
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $cadet['id']; ?>, '<?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?>')"
                                                            class="action-btn delete" title="Archive Cadet">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                    <a href="cadet_profile.php?id=<?php echo $cadet['id']; ?>" 
                                                       class="action-btn view" title="View Profile">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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
                            $pagination_params = [];
                            if ($search_input !== '') {
                                $pagination_params['search'] = $search_input;
                            }
                            if ($platoon_input !== '') {
                                $pagination_params['platoon'] = $platoon_input;
                            }
                            if ($company_input !== '') {
                                $pagination_params['company'] = $company_input;
                            }
                            if ($status_input !== '') {
                                $pagination_params['status'] = $status_input;
                            }

                            $build_page_url = function ($target_page) use ($pagination_params) {
                                return '?' . http_build_query(array_merge($pagination_params, ['page' => $target_page]));
                            };
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <?php echo min($offset + 1, $total); ?> to <?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> cadets
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="<?php echo htmlspecialchars($build_page_url($page - 1)); ?>"
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
                                                <a href="<?php echo htmlspecialchars($build_page_url($i)); ?>"
                                                   class="pagination-btn">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?php echo htmlspecialchars($build_page_url($page + 1)); ?>"
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Archive Cadet</h3>
                        <p class="text-sm text-gray-600 mb-6" id="deleteMessage">
                            Are you sure you want to archive this cadet? This action can be reversed from archives.
                        </p>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="deleteId">
                        <input type="hidden" name="delete_cadet" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Archiving (Optional)
                            </label>
                            <textarea id="deleteReason" name="delete_reason"
                                      class="input-field w-full text-sm"
                                      rows="3"
                                      placeholder="Provide a reason for archiving this cadet..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Archive Cadet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
        <!-- Reset Password Modal -->
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
                    <form id="resetPasswordForm" method="POST" class="space-y-4" onsubmit="return false;">
                        <input type="hidden" name="id" id="resetId">
                        <input type="hidden" name="reset_password" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Password
                            </label>
                            <div class="relative">
                                <input type="password" name="new_password" id="newPassword" required 
                                       class="input-field w-full pr-10 focus:border-blue-800 focus:ring-2 focus:ring-blue-800/20"
                                       oninput="validatePassword()">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        onclick="togglePasswordVisibility('newPassword', 'newPasswordToggle')">
                                    <i id="newPasswordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2 space-y-1" id="passwordRequirements">
                                <div class="flex items-center text-xs" id="reqLength">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">Minimum 8 characters</span>
                                </div>
                                <div class="flex items-center text-xs" id="reqUppercase">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">At least 1 uppercase letter</span>
                                </div>
                                <div class="flex items-center text-xs" id="reqLowercase">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">At least 1 lowercase letter</span>
                                </div>
                                <div class="flex items-center text-xs" id="reqNumber">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">At least 1 number</span>
                                </div>
                                <div class="flex items-center text-xs" id="reqSpecial">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">At least 1 special character (!@#$%^&*)</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirmPassword" required
                                       class="input-field w-full pr-10 focus:border-blue-800 focus:ring-2 focus:ring-blue-800/20"
                                       oninput="validatePasswordMatch()">
                                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        onclick="togglePasswordVisibility('confirmPassword', 'confirmPasswordToggle')">
                                    <i id="confirmPasswordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2" id="passwordMatch">
                                <div class="flex items-center text-xs">
                                    <i class="fas fa-times text-red-500 mr-2"></i>
                                    <span class="text-gray-600">Passwords must match</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeResetPasswordModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" id="submitPasswordBtn"
                                    class="flex-1 px-4 py-3 text-white rounded-lg transition-colors font-medium opacity-50 cursor-not-allowed" 
                                    style="background-color: #233754; border-color: #233754;" disabled>
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Update Cadet Status</h3>
                        <p class="text-sm text-gray-600">
                            Update account status for <span id="statusUsername" class="font-semibold text-blue-700"></span>
                        </p>
                    </div>
                    <form id="updateStatusForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="statusId">
                        <input type="hidden" name="update_cadet_status" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                New Status <span class="text-red-500">*</span>
                            </label>
                            <select name="status" required class="select-field w-full" id="statusSelect">
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

        // Auto-filter functionality
let searchTimeout;
let filterTimeout;
const resetPasswordVisibilityState = {
    newPassword: false,
    confirmPassword: false
};

        function setPasswordVisibility(inputId, iconId, isVisible) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            if (!passwordInput || !eyeIcon || !eyeIcon.parentElement) {
                return;
            }

            passwordInput.type = isVisible ? 'text' : 'password';
            eyeIcon.className = isVisible ? 'fas fa-eye-slash' : 'fas fa-eye';
            eyeIcon.parentElement.classList.toggle('text-blue-600', isVisible);
        }

               // Toggle password visibility function
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            if (!passwordInput || !eyeIcon) {
                return;
            }
            
            const nextVisible = passwordInput.type === 'password';
            setPasswordVisibility(inputId, iconId, nextVisible);

            if (Object.prototype.hasOwnProperty.call(resetPasswordVisibilityState, inputId)) {
                resetPasswordVisibilityState[inputId] = nextVisible;
            }
        }
        
        // Password validation functions
        function validatePassword() {
            const password = document.getElementById('newPassword').value;
            const requirements = document.getElementById('passwordRequirements');
            
            // Check each requirement
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            
            // Update requirement indicators
            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUppercase', hasUppercase);
            updateRequirement('reqLowercase', hasLowercase);
            updateRequirement('reqNumber', hasNumber);
            updateRequirement('reqSpecial', hasSpecial);
            
            // Validate password match if confirm password is filled
            if (document.getElementById('confirmPassword').value) {
                validatePasswordMatch();
            }
            
            // Enable/disable submit button based on all requirements
            updateSubmitButton();
        }
        
        function validatePasswordMatch() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchElement = document.getElementById('passwordMatch');
            
            const passwordsMatch = password === confirmPassword && password.length > 0;
            
            if (confirmPassword.length === 0) {
                matchElement.innerHTML = `
                    <div class="flex items-center text-xs">
                        <i class="fas fa-times text-red-500 mr-2"></i>
                        <span class="text-gray-600">Passwords must match</span>
                    </div>`;
            } else if (passwordsMatch) {
                matchElement.innerHTML = `
                    <div class="flex items-center text-xs">
                        <i class="fas fa-check text-green-500 mr-2"></i>
                        <span class="text-green-600">Passwords match</span>
                    </div>`;
            } else {
                matchElement.innerHTML = `
                    <div class="flex items-center text-xs">
                        <i class="fas fa-times text-red-500 mr-2"></i>
                        <span class="text-red-600">Passwords do not match</span>
                    </div>`;
            }
            
            updateSubmitButton();
        }
        
        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('i');
            const text = element.querySelector('span');
            
            if (isValid) {
                icon.className = 'fas fa-check text-green-500 mr-2';
                text.className = 'text-green-600';
            } else {
                icon.className = 'fas fa-times text-red-500 mr-2';
                text.className = 'text-gray-600';
            }
        }
        
        function updateSubmitButton() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = document.getElementById('submitPasswordBtn');
            
            // Check all requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            const passwordsMatch = password === confirmPassword;
            
            const allValid = hasLength && hasUppercase && hasLowercase && 
                           hasNumber && hasSpecial && passwordsMatch;
            
            if (allValid) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('hover:opacity-90');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('hover:opacity-90');
            }
        }
        
        // Updated validatePasswordForm function
function validatePasswordForm(event) {
    // Always prevent default form submission first
    if (event) {
        event.preventDefault();
        event.stopPropagation(); // Add this to prevent event bubbling
    }
    
    const password = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    // Final validation before submission
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*]/.test(password);
    const passwordsMatch = password === confirmPassword;
    
    let errorMessage = '';
    
    if (!hasLength) {
        errorMessage = 'Password must be at least 8 characters long.';
    } else if (!hasUppercase) {
        errorMessage = 'Password must contain at least one uppercase letter.';
    } else if (!hasLowercase) {
        errorMessage = 'Password must contain at least one lowercase letter.';
    } else if (!hasNumber) {
        errorMessage = 'Password must contain at least one number.';
    } else if (!hasSpecial) {
        errorMessage = 'Password must contain at least one special character (!@#$%^&*).';
    } else if (!passwordsMatch) {
        errorMessage = 'Passwords do not match.';
    }
    
    if (errorMessage) {
        // Show error toast instead of alert
        showToast(errorMessage, 'error');
        return false;
    }
    
    // If all validations pass, submit the form programmatically
    document.getElementById('resetPasswordForm').submit();
    return true;
}
        
        // Reset password modal functions
        function showResetPasswordModal(id, username) {
            document.getElementById('resetId').value = id;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
            setPasswordVisibility('newPassword', 'newPasswordToggle', !!resetPasswordVisibilityState.newPassword);
            setPasswordVisibility('confirmPassword', 'confirmPasswordToggle', !!resetPasswordVisibilityState.confirmPassword);
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
            setPasswordVisibility('newPassword', 'newPasswordToggle', !!resetPasswordVisibilityState.newPassword);
            setPasswordVisibility('confirmPassword', 'confirmPasswordToggle', !!resetPasswordVisibilityState.confirmPassword);
            
            // Reset validation indicators
            const requirements = ['reqLength', 'reqUppercase', 'reqLowercase', 'reqNumber', 'reqSpecial'];
            requirements.forEach(id => {
                const element = document.getElementById(id);
                const icon = element.querySelector('i');
                const text = element.querySelector('span');
                icon.className = 'fas fa-times text-red-500 mr-2';
                text.className = 'text-gray-600';
            });
            
            // Reset password match indicator
            document.getElementById('passwordMatch').innerHTML = `
                <div class="flex items-center text-xs">
                    <i class="fas fa-times text-red-500 mr-2"></i>
                    <span class="text-gray-600">Passwords must match</span>
                </div>`;
            
            // Reset submit button
            const submitBtn = document.getElementById('submitPasswordBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.classList.remove('hover:opacity-90');
        }
        
        // Add event listener to handle form submission
        document.addEventListener('DOMContentLoaded', function() {
            const resetPasswordForm = document.getElementById('resetPasswordForm');
            if (resetPasswordForm) {
                // Remove any existing event listeners to prevent duplicates
                resetPasswordForm.removeEventListener('submit', validatePasswordForm);
                // Add the event listener properly
                resetPasswordForm.addEventListener('submit', validatePasswordForm);
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
            
            // Rest of your existing DOMContentLoaded code...
            const searchInput = document.getElementById('searchInput');
            const platoonFilter = document.getElementById('platoonFilter');
            const companyFilter = document.getElementById('companyFilter');
            const statusFilter = document.getElementById('statusFilter');
            
            // Add event listeners for automatic filtering
            if (searchInput) {
                searchInput.addEventListener('input', debounceSearch);
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyFilters();
                    }
                });
            }
            
            if (platoonFilter) {
                platoonFilter.addEventListener('change', debounceFilter);
            }
            
            if (companyFilter) {
                companyFilter.addEventListener('change', debounceFilter);
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', debounceFilter);
            }
            
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
        });

function applyFilters() {
    const searchInput = document.getElementById('searchInput');
    const platoonFilter = document.getElementById('platoonFilter');
    const companyFilter = document.getElementById('companyFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    let params = new URLSearchParams(window.location.search);
    
    // Update search parameter
    if (searchInput && searchInput.value.trim()) {
        params.set('search', searchInput.value.trim());
    } else {
        params.delete('search');
    }
    
    // Update platoon parameter
    if (platoonFilter && platoonFilter.value) {
        params.set('platoon', platoonFilter.value);
    } else {
        params.delete('platoon');
    }
    
    // Update company parameter
    if (companyFilter && companyFilter.value) {
        params.set('company', companyFilter.value);
    } else {
        params.delete('company');
    }
    
    // Update status parameter
    if (statusFilter && statusFilter.value) {
        params.set('status', statusFilter.value);
    } else {
        params.delete('status');
    }
    
    // Reset to page 1 when filtering
    params.set('page', '1');
    
    // Update URL without full page reload
    const newUrl = 'cadets.php?' + params.toString();
    window.history.replaceState({}, '', newUrl);
    
    // Reload the page to apply filters
    window.location.href = newUrl;
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 500);
}

function debounceFilter() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 300);
}

// Initialize automatic filtering
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const platoonFilter = document.getElementById('platoonFilter');
    const companyFilter = document.getElementById('companyFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    // Add event listeners for automatic filtering
    if (searchInput) {
        searchInput.addEventListener('input', debounceSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });
    }
    
    if (platoonFilter) {
        platoonFilter.addEventListener('change', debounceFilter);
    }
    
    if (companyFilter) {
        companyFilter.addEventListener('change', debounceFilter);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', debounceFilter);
    }
    
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
});
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
        function confirmDelete(id, name, type = 'cadet') {
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
        function showResetPasswordModal(id, username) {
            document.getElementById('resetId').value = id;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
            setPasswordVisibility('newPassword', 'newPasswordToggle', !!resetPasswordVisibilityState.newPassword);
            setPasswordVisibility('confirmPassword', 'confirmPasswordToggle', !!resetPasswordVisibilityState.confirmPassword);
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
            setPasswordVisibility('newPassword', 'newPasswordToggle', !!resetPasswordVisibilityState.newPassword);
            setPasswordVisibility('confirmPassword', 'confirmPasswordToggle', !!resetPasswordVisibilityState.confirmPassword);
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
        
        // Initialize animations
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
        });
    </script>
</body>
</html>
