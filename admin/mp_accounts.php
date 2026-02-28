<?php
// mp_accounts.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once 'archive_functions.php';
requireAdminLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Function to log status changes
function logMpStatusChange($pdo, $mp_id, $new_status, $reason, $changed_by) {
    try {
        // Get current status
        $current_stmt = $pdo->prepare("SELECT status FROM mp_accounts WHERE id = ?");
        $current_stmt->execute([$mp_id]);
        $current_status = $current_stmt->fetchColumn();
        
        // Check if the mp_status_logs table exists
        $table_exists = $pdo->query("SHOW TABLES LIKE 'mp_status_logs'")->rowCount() > 0;
        
        if ($table_exists) {
            $stmt = $pdo->prepare("INSERT INTO mp_status_logs 
                (mp_id, previous_status, new_status, reason, changed_by_admin_id) 
                VALUES (?, ?, ?, ?, ?)");
            
            return $stmt->execute([$mp_id, $current_status, $new_status, $reason, $changed_by]);
        } else {
            // Table doesn't exist - log to admin_logs as a fallback or just return true
            // You could also create the table here automatically
            error_log("MP Status change logged: MP ID $mp_id changed from $current_status to $new_status by admin $changed_by");
            return true;
        }
    } catch (PDOException $e) {
        // Log the error but don't break the main functionality
        error_log("Error logging MP status change: " . $e->getMessage());
        return false;
    }
}

// Function to validate password
function validatePassword($password) {
    $errors = [];
    
    // Check minimum length
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    // Check for at least one special character
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*(),.?\":{}|<>)";
    }
    
    return $errors;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_mp'])) {
        // Add new MP (same structure as cadet)
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
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Validate password strength for new MP creation
            $passwordErrors = validatePassword($password);
            if (!empty($passwordErrors)) {
                $error = "Password does not meet requirements:<br>" . implode("<br>", $passwordErrors);
            } else {
                // Check if username or email exists
                $stmt = $pdo->prepare("SELECT id FROM mp_accounts WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = "Username or email already exists.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert MP with default 'pending' status (same as cadet)
                    $stmt = $pdo->prepare("INSERT INTO mp_accounts 
                        (username, email, password, first_name, last_name, middle_name, 
                         course, full_address, platoon, company, dob, mothers_name, 
                         fathers_name, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                    
                    if ($stmt->execute([
                        $username, $email, $hashed_password, $first_name, $last_name, 
                        $middle_name, $course, $full_address, $platoon, $company, 
                        $dob, $mothers_name, $fathers_name, $_SESSION['admin_id']
                    ])) {
                        $_SESSION['message'] = "MP Cadet added successfully! Status: Pending";
                        header('Location: mp_accounts.php');
                        exit();
                    } else {
                        $error = "Failed to add MP Cadet. Please try again.";
                    }
                }
            }
        }
    } elseif (isset($_POST['edit_mp'])) {
        // Edit MP (same as cadet)
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
        
        $stmt = $pdo->prepare("UPDATE mp_accounts SET 
            first_name = ?, last_name = ?, middle_name = ?, course = ?, 
            full_address = ?, platoon = ?, company = ?, dob = ?, 
            mothers_name = ?, fathers_name = ?, status = ?, updated_at = NOW() 
            WHERE id = ?");
        
        if ($stmt->execute([
            $first_name, $last_name, $middle_name, $course, $full_address, 
            $platoon, $company, $dob, $mothers_name, $fathers_name, 
            $status, $id
        ])) {
            $_SESSION['message'] = "MP Cadet updated successfully!";
            header('Location: mp_accounts.php');
            exit();
        } else {
            $error = "Failed to update MP Cadet. Please try again.";
        }
    } elseif (isset($_POST['update_mp_status'])) {
        // Update MP status
        $id = $_POST['id'];
        $status = sanitize_input($_POST['status']);
        $status_reason = isset($_POST['status_reason']) ? sanitize_input($_POST['status_reason']) : '';
        
        $stmt = $pdo->prepare("UPDATE mp_accounts SET 
            status = ?, updated_at = NOW() 
            WHERE id = ?");
        
        if ($stmt->execute([$status, $id])) {
            // Log the status change
            logMpStatusChange($pdo, $id, $status, $status_reason, $_SESSION['admin_id']);
            
            $_SESSION['message'] = "MP Cadet status updated to " . strtoupper($status) . "!";
        } else {
            $_SESSION['error'] = "Failed to update MP cadet status.";
        }
        header('Location: mp_accounts.php');
        exit();
    } elseif (isset($_POST['delete_mp'])) {
        // Move to archive instead of deleting (same as cadet)
        $id = $_POST['id'];
        $reason = isset($_POST['delete_reason']) ? sanitize_input($_POST['delete_reason']) : '';
        
        if (moveToArchive('mp', $id, $reason)) {
            $_SESSION['message'] = "MP Cadet moved to archives successfully!";
        } else {
            $_SESSION['error'] = "Failed to archive MP Cadet. Please try again.";
        }
        header('Location: mp_accounts.php');
        exit();
    } elseif (isset($_POST['reset_password'])) {
        // Reset password (same as cadet) with validation
        $id = $_POST['id'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
        } else {
            // Validate password strength
            $passwordErrors = validatePassword($new_password);
            if (!empty($passwordErrors)) {
                $_SESSION['error'] = "Password does not meet requirements:<br>" . implode("<br>", $passwordErrors);
            } else {
                // Check if new password is different from current password
                $stmt = $pdo->prepare("SELECT password FROM mp_accounts WHERE id = ?");
                $stmt->execute([$id]);
                $current_hashed_password = $stmt->fetchColumn();
                
                if (password_verify($new_password, $current_hashed_password)) {
                    $_SESSION['error'] = "New password cannot be the same as the current password.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE mp_accounts SET password = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $id])) {
                        $_SESSION['message'] = "Password reset successfully! The MP cadet must use the new password for their next login.";
                    } else {
                        $_SESSION['error'] = "Failed to reset password.";
                    }
                }
            }
        }
        header('Location: mp_accounts.php');
        exit();
    }
}

// Get MP data for edit
$mp = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM mp_accounts WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $mp = $stmt->fetch();
    if (!$mp) {
        $action = 'list';
        $error = "MP Cadet not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Military Police Command | ROTC Command Center</title>
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
            --accent-purple: #9f7aea;
            --reset-modal-color: #27337d;
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
            border-left: 4px solid var(--accent-purple);
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
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(159, 122, 234, 0.1);
        }
        
        .section-header {
            background: linear-gradient(90deg, var(--primary-military) 0%, #3730a3 100%);
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
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(159, 122, 234, 0.1);
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
            background: linear-gradient(135deg, var(--primary-military) 0%, var(--accent-purple) 100%);
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
            border-color: var(--accent-purple);
            background: rgba(159, 122, 234, 0.05);
        }
        
        .pagination-btn.active {
            background: var(--accent-purple);
            color: white;
            border-color: var(--accent-purple);
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
            background: var(--reset-modal-color);
            color: white;
            border-color: var(--reset-modal-color);
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
            border-color: var(--accent-purple);
            box-shadow: 0 0 0 3px rgba(159, 122, 234, 0.1);
        }
        
        .mp-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .password-requirement {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            color: #64748b;
        }
        
        .password-requirement.valid {
            color: #10b981;
        }
        
        .password-requirement.invalid {
            color: #ef4444;
        }
        
        .password-requirement-icon {
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 6px;
            font-size: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
        }
        
        .password-requirement-icon.valid {
            background: #10b981;
            color: white;
        }
        
        .password-requirement-icon.invalid {
            background: #ef4444;
            color: white;
        }
        
        .password-strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-meter-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .reset-modal-gradient {
            background: linear-gradient(135deg, var(--reset-modal-color) 0%, #1e3a8a 100%);
        }
        
        .reset-modal-btn {
            background: linear-gradient(135deg, var(--reset-modal-color) 0%, #1e3a8a 100%);
            color: white;
        }
        
        .reset-modal-btn:hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #27337d 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- <?php include 'dashboard_header.php'; ?> -->
    
    <!-- Toast Notification Container -->
    <div id="toastContainer"></div>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="glass-card border-b border-gray-200 px-8 py-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl header-font font-bold text-gray-900 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg mp-gradient flex items-center justify-center">
                            <i class="fas fa-user-shield text-white"></i>
                        </div>
                        <span>Military Police Command</span>
                    </h2>
                    <p class="text-gray-600 mt-1">Manage Military Police cadet accounts and information</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="military-btn flex items-center gap-2">
                            <i class="fas fa-user-shield"></i>
                            <span>Enlist New MP Cadet</span>
                        </a>
                    <?php else: ?>
                        <a href="mp_accounts.php" class="military-btn flex items-center gap-2 bg-gray-700 hover:bg-gray-800">
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
                    FROM mp_accounts 
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
                                <span>All MP cadet accounts</span>
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
                            <?php echo $action === 'add' ? 'Enlist New MP Cadet' : 'Update MP Cadet Record'; ?>
                        </h3>
                        <p class="text-purple-100 text-sm mt-1">
                            <?php echo $action === 'add' ? 'Fill all required fields to enlist a new MP cadet' : 'Update MP cadet information and status'; ?>
                        </p>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="" class="space-y-8">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $mp['id']; ?>">
                                <input type="hidden" name="edit_mp" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_mp" value="1">
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
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($mp['username']) : ''; ?>"
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
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($mp['email']) : ''; ?>"
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
                                                       class="input-field w-full pl-10" id="newPassword">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            </div>
                                            <div class="password-requirements">
                                                <div class="password-requirement" id="req-length">
                                                    <span class="password-requirement-icon invalid">✗</span>
                                                    <span>At least 8 characters</span>
                                                </div>
                                                <div class="password-requirement" id="req-uppercase">
                                                    <span class="password-requirement-icon invalid">✗</span>
                                                    <span>At least one uppercase letter</span>
                                                </div>
                                                <div class="password-requirement" id="req-lowercase">
                                                    <span class="password-requirement-icon invalid">✗</span>
                                                    <span>At least one lowercase letter</span>
                                                </div>
                                                <div class="password-requirement" id="req-number">
                                                    <span class="password-requirement-icon invalid">✗</span>
                                                    <span>At least one number</span>
                                                </div>
                                                <div class="password-requirement" id="req-special">
                                                    <span class="password-requirement-icon invalid">✗</span>
                                                    <span>At least one special character</span>
                                                </div>
                                                <div class="password-strength-meter">
                                                    <div class="password-strength-meter-fill" id="passwordStrength" style="width: 0%; background-color: #ef4444;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Confirm Password <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <input type="password" name="confirm_password" required minlength="8"
                                                       class="input-field w-full pl-10" id="confirmPassword">
                                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                    <i class="fas fa-lock"></i>
                                                </div>
                                            </div>
                                            <div id="passwordMatch" class="mt-2 text-sm hidden">
                                                <span id="matchIcon" class="mr-1"></span>
                                                <span id="matchText"></span>
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
                                        <input type="text" name="first_name" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($mp['first_name']) : ''; ?>"
                                               class="input-field w-full">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Last Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="last_name" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($mp['last_name']) : ''; ?>"
                                               class="input-field w-full">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Middle Name
                                        </label>
                                        <input type="text" name="middle_name"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($mp['middle_name']) : ''; ?>"
                                               class="input-field w-full">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Course <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="course" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($mp['course']) : ''; ?>"
                                               class="input-field w-full">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Date of Birth <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" name="dob" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($mp['dob']) : ''; ?>"
                                               class="input-field w-full">
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
                                        <div class="relative">
                                            <input type="text" name="mothers_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($mp['mothers_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                <i class="fas fa-female"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Father's Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="text" name="fathers_name" required
                                                   value="<?php echo $action === 'edit' ? htmlspecialchars($mp['fathers_name']) : ''; ?>"
                                                   class="input-field w-full pl-10">
                                            <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                <i class="fas fa-male"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ROTC Information -->
                            <div class="form-section">
                                <h4>
                                    <i class="fas fa-shield-alt mr-2"></i>
                                    ROTC Assignment
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Full Address <span class="text-red-500">*</span>
                                        </label>
                                        <textarea name="full_address" required rows="3"
                                                  class="input-field w-full"><?php echo $action === 'edit' ? htmlspecialchars($mp['full_address']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Platoon <span class="text-red-500">*</span>
                                        </label>
                                        <select name="platoon" required class="select-field w-full">
                                            <option value="">Select Platoon</option>
                                            <option value="1" <?php echo ($action === 'edit' && $mp['platoon'] == '1') ? 'selected' : ''; ?>>Platoon 1</option>
                                            <option value="2" <?php echo ($action === 'edit' && $mp['platoon'] == '2') ? 'selected' : ''; ?>>Platoon 2</option>
                                            <option value="3" <?php echo ($action === 'edit' && $mp['platoon'] == '3') ? 'selected' : ''; ?>>Platoon 3</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Company <span class="text-red-500">*</span>
                                        </label>
                                        <select name="company" required class="select-field w-full">
                                            <option value="">Select Company</option>
                                            <option value="Alpha" <?php echo ($action === 'edit' && $mp['company'] == 'Alpha') ? 'selected' : ''; ?>>Alpha Company</option>
                                            <option value="Bravo" <?php echo ($action === 'edit' && $mp['company'] == 'Bravo') ? 'selected' : ''; ?>>Bravo Company</option>
                                            <option value="Charlie" <?php echo ($action === 'edit' && $mp['company'] == 'Charlie') ? 'selected' : ''; ?>>Charlie Company</option>
                                            <option value="Delta" <?php echo ($action === 'edit' && $mp['company'] == 'Delta') ? 'selected' : ''; ?>>Delta Company</option>
                                            <option value="Echo" <?php echo ($action === 'edit' && $mp['company'] == 'Echo') ? 'selected' : ''; ?>>Echo Company</option>
                                            <option value="Foxtrot" <?php echo ($action === 'edit' && $mp['company'] == 'Foxtrot') ? 'selected' : ''; ?>>Foxtrot Company</option>
                                            <option value="Golf" <?php echo ($action === 'edit' && $mp['company'] == 'Golf') ? 'selected' : ''; ?>>Golf Company</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($action === 'edit'): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Account Status <span class="text-red-500">*</span>
                                            </label>
                                            <select name="status" required class="select-field w-full">
                                                <option value="pending" <?php echo $mp['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $mp['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="denied" <?php echo $mp['status'] == 'denied' ? 'selected' : ''; ?>>Denied</option>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Initial Status
                                            </label>
                                            <div class="input-field bg-gray-50 text-gray-500">
                                                Pending (Default for new cadets)
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">New MP cadets will be created with 'Pending' status</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                <a href="mp_accounts.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                    Cancel
                                </a>
                                <button type="submit" class="military-btn px-6 py-3 flex items-center gap-2">
                                    <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                    <?php echo $action === 'add' ? 'Enlist MP Cadet' : 'Update Record'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- MP Cadets List -->
            <?php else: ?>
                <!-- Search and Filter -->
             
                <div class="glass-card rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">MP Cadet Roster</h3>
                        <div class="flex items-center gap-3">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-filter mr-1"></i>
                                <span>Filter & Search</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <input type="text" id="searchInput" placeholder="Search MP cadets..." 
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
                        <a href="mp_accounts.php" class="px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium flex items-center gap-2">
                            <i class="fas fa-redo"></i>
                            <span>Reset All Filters</span>
                        </a>
                    </div>
                </div>
                
                <!-- MP Cadets Table -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">MP Cadet Accounts</h3>
                        <p class="text-purple-100 text-sm mt-1">
                            <?php
                            // Build search query
                            $search = isset($_GET['search']) ? "%{$_GET['search']}%" : '%';
                            $platoon = isset($_GET['platoon']) ? $_GET['platoon'] : '%';
                            $company = isset($_GET['company']) ? $_GET['company'] : '%';
                            $status = isset($_GET['status']) ? $_GET['status'] : '%';
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mp_accounts 
                                                  WHERE (CONCAT(first_name, ' ', last_name) LIKE ? 
                                                         OR username LIKE ? 
                                                         OR email LIKE ?)
                                                  AND (platoon LIKE ? OR ? = '%')
                                                  AND (company LIKE ? OR ? = '%')
                                                  AND (status LIKE ? OR ? = '%')
                                                  AND is_archived = FALSE");
                            $stmt->execute([$search, $search, $search, $platoon, $platoon, $company, $company, $status, $status]);
                            $total = $stmt->fetchColumn();
                            
                            // Get current page and calculate showing range
                            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $limit = 10;
                            $offset = ($page - 1) * $limit;
                            $start = $offset + 1;
                            $end = min($offset + $limit, $total);
                            
                            if ($total > 0) {
                                echo "Showing $start to $end of $total MP cadets";
                            } else {
                                echo "No MP cadets found";
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="table-container">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">MP Cadet Information</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact & Course</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ROTC Assignment</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Account Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Activity</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                // Get MP cadets with pagination
                                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                                $limit = 10;
                                $offset = ($page - 1) * $limit;
                                
                                $stmt = $pdo->prepare("SELECT * FROM mp_accounts 
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
                                $mps = $stmt->fetchAll();
                                
                                // Count actual rows on this page
                                $rows_on_page = count($mps);
                                
                                if (empty($mps)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user-shield text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No MP Cadets Found</h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                No Military Police cadet records match your current search criteria. Try adjusting your filters or add a new MP cadet.
                                            </p>
                                            <a href="?action=add" class="inline-flex items-center gap-2 military-btn">
                                                <i class="fas fa-user-shield"></i>
                                                Enlist New MP Cadet
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mps as $mp): ?>
                                        <tr class="table-row-hover" id="mp-row-<?php echo $mp['id']; ?>">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="avatar-initial">
                                                        <?php echo strtoupper(substr($mp['first_name'], 0, 1) . substr($mp['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>
                                                            <?php if ($mp['middle_name']): ?>
                                                                <?php echo htmlspecialchars(' ' . $mp['middle_name'][0] . '.'); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                                                            <i class="fas fa-user-shield"></i>
                                                            <span>@<?php echo htmlspecialchars($mp['username']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <i class="fas fa-envelope text-gray-400"></i>
                                                    <div class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($mp['email']); ?>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-graduation-cap text-gray-400"></i>
                                                    <div class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($mp['course']); ?>
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
                                                            Platoon <?php echo htmlspecialchars($mp['platoon']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center">
                                                            <i class="fas fa-users text-purple-600 text-xs"></i>
                                                        </div>
                                                        <span class="text-sm text-gray-600">
                                                            <?php echo htmlspecialchars($mp['company']); ?> Company
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <?php
                                                    $mp_status = isset($mp['status']) ? $mp['status'] : 'pending';
                                                    $mp_status_class = 'status-' . $mp_status;
                                                    $mp_status_text = ucfirst($mp_status);
                                                    $mp_status_icon = $mp_status == 'approved' ? 'check-circle' : ($mp_status == 'pending' ? 'clock' : 'times-circle');
                                                    ?>
                                                    <span class="status-badge <?php echo $mp_status_class; ?>">
                                                        <i class="fas fa-<?php echo $mp_status_icon; ?>"></i>
                                                        <?php echo $mp_status_text; ?>
                                                    </span>
                                                    
                                                    <div class="mt-2">
                                                        <button onclick="showUpdateStatusModal(<?php echo $mp['id']; ?>, '<?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>', '<?php echo $mp_status; ?>')"
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
                                                            <?php if ($mp['last_login']): ?>
                                                                <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($mp['last_login'])); ?></div>
                                                                <div class="text-gray-600 text-xs"><?php echo date('g:i A', strtotime($mp['last_login'])); ?></div>
                                                            <?php else: ?>
                                                                <span class="text-gray-400 font-medium">Never logged in</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($mp['updated_at']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <i class="fas fa-history mr-1"></i>
                                                            Updated: <?php echo date('M j, Y', strtotime($mp['updated_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <a href="?action=edit&id=<?php echo $mp['id']; ?>" 
                                                       class="action-btn edit" title="Edit MP Cadet">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="showResetPasswordModal(<?php echo $mp['id']; ?>, '<?php echo htmlspecialchars($mp['username']); ?>')"
                                                            class="action-btn reset" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button onclick="showUpdateStatusModal(<?php echo $mp['id']; ?>, '<?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>', '<?php echo $mp_status; ?>')"
                                                            class="action-btn status" title="Update Status">
                                                        <i class="fas fa-toggle-on"></i>
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $mp['id']; ?>, '<?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>', 'mp')"
                                                            class="action-btn delete" title="Archive MP Cadet">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                    <a href="mp_profile.php?id=<?php echo $mp['id']; ?>" 
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
                            $totalPages = ceil($total / $limit);
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <?php echo min($offset + 1, $total); ?> to <?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> MP cadets
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&platoon=<?php echo urlencode($_GET['platoon'] ?? ''); ?>&company=<?php echo urlencode($_GET['company'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>" 
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
                                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&platoon=<?php echo urlencode($_GET['platoon'] ?? ''); ?>&company=<?php echo urlencode($_GET['company'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"
                                                   class="pagination-btn">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&platoon=<?php echo urlencode($_GET['platoon'] ?? ''); ?>&company=<?php echo urlencode($_GET['company'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"
                                           class="pagination-btn">
                                            Next
                                            <i class="fas fa-chevron-right ml-2"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Show "Showing X of X" even when there's only one page -->
                        <?php if ($total > 0): ?>
                            <div class="px-6 py-4 border-t border-gray-200">
                                <div class="text-sm text-gray-700">
                                    Showing 1 to <?php echo $rows_on_page; ?> of <?php echo $total; ?> MP cadets
                                </div>
                            </div>
                        <?php endif; ?>
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Archive MP Cadet</h3>
                        <p class="text-sm text-gray-600 mb-6" id="deleteMessage">
                            Are you sure you want to archive this MP cadet? This action can be reversed from archives.
                        </p>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="deleteId">
                        <input type="hidden" name="delete_mp" value="1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Archiving (Optional)
                            </label>
                            <textarea id="deleteReason" name="delete_reason"
                                      class="input-field w-full text-sm"
                                      rows="3"
                                      placeholder="Provide a reason for archiving this MP cadet..."></textarea>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Archive MP Cadet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal - Fixed IDs -->
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Update MP Cadet Status</h3>
                        <p class="text-sm text-gray-600">
                            Update account status for <span id="statusUsername" class="font-semibold text-blue-700"></span>
                        </p>
                    </div>
                    <form id="updateStatusForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="statusId">
                        <input type="hidden" name="update_mp_status" value="1">
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

        // Auto-filter functionality
        let searchTimeout;
        let filterTimeout;

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
            const newUrl = 'mp_accounts.php?' + params.toString();
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

        // Password validation functions
        function validatePasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            // Calculate strength score (0-5)
            let strength = 0;
            Object.values(requirements).forEach(met => {
                if (met) strength++;
            });
            
            return { requirements, strength };
        }

        function updatePasswordStrengthUI(password, isResetModal = false) {
            const prefix = isResetModal ? 'reset-' : '';
            const result = validatePasswordStrength(password);
            
            // Update requirement indicators
            const reqLength = document.getElementById(`${prefix}req-length`);
            const reqUppercase = document.getElementById(`${prefix}req-uppercase`);
            const reqLowercase = document.getElementById(`${prefix}req-lowercase`);
            const reqNumber = document.getElementById(`${prefix}req-number`);
            const reqSpecial = document.getElementById(`${prefix}req-special`);
            
            if (reqLength) {
                reqLength.className = `password-requirement ${result.requirements.length ? 'valid' : 'invalid'}`;
                reqLength.querySelector('.password-requirement-icon').className = `password-requirement-icon ${result.requirements.length ? 'valid' : 'invalid'}`;
                reqLength.querySelector('.password-requirement-icon').innerHTML = result.requirements.length ? '✓' : '✗';
            }
            
            if (reqUppercase) {
                reqUppercase.className = `password-requirement ${result.requirements.uppercase ? 'valid' : 'invalid'}`;
                reqUppercase.querySelector('.password-requirement-icon').className = `password-requirement-icon ${result.requirements.uppercase ? 'valid' : 'invalid'}`;
                reqUppercase.querySelector('.password-requirement-icon').innerHTML = result.requirements.uppercase ? '✓' : '✗';
            }
            
            if (reqLowercase) {
                reqLowercase.className = `password-requirement ${result.requirements.lowercase ? 'valid' : 'invalid'}`;
                reqLowercase.querySelector('.password-requirement-icon').className = `password-requirement-icon ${result.requirements.lowercase ? 'valid' : 'invalid'}`;
                reqLowercase.querySelector('.password-requirement-icon').innerHTML = result.requirements.lowercase ? '✓' : '✗';
            }
            
            if (reqNumber) {
                reqNumber.className = `password-requirement ${result.requirements.number ? 'valid' : 'invalid'}`;
                reqNumber.querySelector('.password-requirement-icon').className = `password-requirement-icon ${result.requirements.number ? 'valid' : 'invalid'}`;
                reqNumber.querySelector('.password-requirement-icon').innerHTML = result.requirements.number ? '✓' : '✗';
            }
            
            if (reqSpecial) {
                reqSpecial.className = `password-requirement ${result.requirements.special ? 'valid' : 'invalid'}`;
                reqSpecial.querySelector('.password-requirement-icon').className = `password-requirement-icon ${result.requirements.special ? 'valid' : 'invalid'}`;
                reqSpecial.querySelector('.password-requirement-icon').innerHTML = result.requirements.special ? '✓' : '✗';
            }
            
            // Update strength meter
            const strengthMeter = document.getElementById(isResetModal ? 'resetPasswordStrength' : 'passwordStrength');
            if (strengthMeter) {
                const percentage = (result.strength / 5) * 100;
                
                let color;
                if (result.strength <= 1) color = '#ef4444'; // red
                else if (result.strength <= 3) color = '#f59e0b'; // yellow
                else if (result.strength <= 4) color = '#3b82f6'; // blue
                else color = '#10b981'; // green
                
                strengthMeter.style.width = `${percentage}%`;
                strengthMeter.style.backgroundColor = color;
            }
            
            return result;
        }

        function checkPasswordMatch(password, confirmPassword, isResetModal = false) {
            const matchDiv = document.getElementById(isResetModal ? 'resetPasswordMatch' : 'passwordMatch');
            const matchIcon = document.getElementById(isResetModal ? 'resetMatchIcon' : 'matchIcon');
            const matchText = document.getElementById(isResetModal ? 'resetMatchText' : 'matchText');
            
            if (!matchDiv || !matchIcon || !matchText) return false;
            
            if (!password || !confirmPassword) {
                matchDiv.classList.add('hidden');
                return false;
            }
            
            if (password === confirmPassword) {
                matchDiv.classList.remove('hidden');
                matchDiv.className = 'mt-2 text-sm text-green-600';
                matchIcon.innerHTML = '✓';
                matchText.textContent = 'Passwords match';
                return true;
            } else {
                matchDiv.classList.remove('hidden');
                matchDiv.className = 'mt-2 text-sm text-red-600';
                matchIcon.innerHTML = '✗';
                matchText.textContent = 'Passwords do not match';
                return false;
            }
        }

        // Reset password validation functions
        function validateResetPassword() {
            const newPassword = document.getElementById('newPasswordReset').value;
            const confirmPassword = document.getElementById('confirmPasswordReset').value;
            
            // Update strength UI
            const strengthResult = updatePasswordStrengthUI(newPassword, true);
            
            // Check if all requirements are met
            const allRequirementsMet = Object.values(strengthResult.requirements).every(met => met);
            const passwordsMatch = checkPasswordMatch(newPassword, confirmPassword, true);
            
            // Enable/disable submit button
            const submitBtn = document.getElementById('submitPasswordBtn');
            if (allRequirementsMet && passwordsMatch) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('cursor-pointer');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('cursor-pointer');
            }
            
            return allRequirementsMet;
        }

        function validateResetPasswordMatch() {
            const newPassword = document.getElementById('newPasswordReset').value;
            const confirmPassword = document.getElementById('confirmPasswordReset').value;
            const passwordsMatch = checkPasswordMatch(newPassword, confirmPassword, true);
            
            // Enable/disable submit button
            const submitBtn = document.getElementById('submitPasswordBtn');
            const strengthResult = validatePasswordStrength(newPassword);
            const allRequirementsMet = Object.values(strengthResult.requirements).every(met => met);
            
            if (allRequirementsMet && passwordsMatch) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('cursor-pointer');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('cursor-pointer');
            }
            
            return passwordsMatch;
        }

        // Toggle password visibility
        function togglePasswordVisibility(inputId, toggleIconId) {
            const input = document.getElementById(inputId);
            const toggleIcon = document.getElementById(toggleIconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // Delete modal function
        function confirmDelete(id, name, type = 'mp') {
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
            
            // Clear previous inputs and validation
            document.getElementById('newPasswordReset').value = '';
            document.getElementById('confirmPasswordReset').value = '';
            
            // Reset UI
            const matchDiv = document.getElementById('resetPasswordMatch');
            if (matchDiv) matchDiv.classList.add('hidden');
            
            updatePasswordStrengthUI('', true);
            
            // Disable submit button
            const submitBtn = document.getElementById('submitPasswordBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.classList.remove('cursor-pointer');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
            
            const matchDiv = document.getElementById('resetPasswordMatch');
            if (matchDiv) matchDiv.classList.add('hidden');
            
            updatePasswordStrengthUI('', true);
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

        // Initialize animations and event listeners
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
            
            // Add event listeners for automatic filtering
            const searchInput = document.getElementById('searchInput');
            const platoonFilter = document.getElementById('platoonFilter');
            const companyFilter = document.getElementById('companyFilter');
            const statusFilter = document.getElementById('statusFilter');
            
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
            
            // Add password validation for new MP creation
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            
            if (newPasswordInput && confirmPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    updatePasswordStrengthUI(this.value);
                    checkPasswordMatch(this.value, confirmPasswordInput.value);
                });
                
                confirmPasswordInput.addEventListener('input', function() {
                    checkPasswordMatch(newPasswordInput.value, this.value);
                });
            }
            
            // Add form submission handler for reset password modal
            const resetPasswordForm = document.getElementById('resetPasswordForm');
            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const newPassword = document.getElementById('newPasswordReset').value;
                    const confirmPassword = document.getElementById('confirmPasswordReset').value;
                    
                    // Final validation
                    const strengthResult = validatePasswordStrength(newPassword);
                    const allRequirementsMet = Object.values(strengthResult.requirements).every(met => met);
                    const passwordsMatch = newPassword === confirmPassword;
                    
                    if (!allRequirementsMet) {
                        showToast('Password does not meet all requirements', 'error');
                        return false;
                    }
                    
                    if (!passwordsMatch) {
                        showToast('Passwords do not match', 'error');
                        return false;
                    }
                    
                    // Submit the form
                    this.submit();
                });
            }
        });
    </script>
</body>
</html>