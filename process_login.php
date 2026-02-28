<?php
require_once 'config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session variables
if (!isset($_SESSION['login_error'])) {
    $_SESSION['login_error'] = '';
}

if (!isset($_SESSION['form_data'])) {
    $_SESSION['form_data'] = [];
}

// Clear previous error
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    // Basic validation with specific error messages
    if (empty($username) && empty($password)) {
        $_SESSION['login_error'] = 'Please enter your username/email and password';
        $_SESSION['form_data'] = ['username' => $username];
        header('Location: login.php');
        exit();
    }
    
    if (empty($username)) {
        $_SESSION['login_error'] = 'Please enter your username or email';
        $_SESSION['form_data'] = ['username' => $username];
        header('Location: login.php');
        exit();
    }
    
    if (empty($password)) {
        $_SESSION['login_error'] = 'Please enter your password';
        $_SESSION['form_data'] = ['username' => $username];
        header('Location: login.php');
        exit();
    }
    
    // Additional validation for email format
    if (strpos($username, '@') !== false) {
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['login_error'] = 'Please enter a valid email address';
            $_SESSION['form_data'] = ['username' => $username];
            header('Location: login.php');
            exit();
        }
    }
    
    try {
        // Check if admin exists
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE (username = :username OR email = :email)");
        $stmt->execute(['username' => $username, 'email' => $username]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Check account status
            if ($admin['account_status'] === 'pending') {
                $_SESSION['login_error'] = 'Your account is pending approval by an administrator. Please wait for approval.';
                $_SESSION['form_data'] = ['username' => $username];
                $_SESSION['account_status'] = 'pending';
                
                // Log failed attempt
                logLoginAttempt($username, false, 'Account pending approval');
                
                header('Location: login.php');
                exit();
            }
            
            if ($admin['account_status'] === 'denied') {
                $reason = $admin['status_reason'] ? " Reason: " . $admin['status_reason'] : '';
                $_SESSION['login_error'] = 'Your account has been denied.' . $reason . ' Please contact support.';
                $_SESSION['form_data'] = ['username' => $username];
                $_SESSION['account_status'] = 'denied';
                if ($admin['status_reason']) {
                    $_SESSION['status_reason'] = $admin['status_reason'];
                }
                
                // Log failed attempt
                logLoginAttempt($username, false, 'Account denied');
                
                header('Location: login.php');
                exit();
            }
            
            if ($admin['account_status'] !== 'approved') {
                $_SESSION['login_error'] = 'Your account is not approved for login. Please contact support.';
                $_SESSION['form_data'] = ['username' => $username];
                
                // Log failed attempt
                logLoginAttempt($username, false, 'Invalid account status: ' . $admin['account_status']);
                
                header('Location: login.php');
                exit();
            }
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Check if password needs rehashing
                if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_hash_stmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
                    $update_hash_stmt->execute(['password' => $new_hash, 'id' => $admin['id']]);
                }
                
                // Update last login
                $update_stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                $update_stmt->execute(['id' => $admin['id']]);
                
                // Clear session errors
                unset($_SESSION['login_error']);
                unset($_SESSION['form_data']);
                unset($_SESSION['account_status']);
                unset($_SESSION['status_reason']);
                
                // Set session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = 'admin'; // Only admin role now
                $_SESSION['admin_profile'] = isset($admin['profile_image']) ? $admin['profile_image'] : '';
                $_SESSION['login_time'] = time();
                $_SESSION['account_status'] = $admin['account_status'];
                
                // Set remember me cookie if checked
                if ($remember_me) {
                    $cookie_value = base64_encode($admin['id'] . ':' . hash('sha256', $admin['password']));
                    setcookie('remember_me', $cookie_value, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                }
                
                // Log successful login attempt
                logLoginAttempt($username, true, 'Login successful');
                
                // Log activity
                logActivity('login', 'User logged in', $admin['id']);
                
                // Check if there's a redirect URL
                if (isset($_SESSION['login_redirect'])) {
                    $redirect_url = $_SESSION['login_redirect'];
                    unset($_SESSION['login_redirect']);
                    header('Location: ' . $redirect_url);
                    exit();
                }
                
                // Redirect to dashboard
                header('Location: admin/dashboard.php');
                exit();
            } else {
                // Wrong password
                $_SESSION['login_error'] = 'Incorrect password. Please try again.';
                $_SESSION['form_data'] = ['username' => $username];
                
                // Log failed attempt
                logLoginAttempt($username, false, 'Incorrect password');
                
                header('Location: login.php');
                exit();
            }
        } else {
            // User not found - check if it's username or email
            $error_message = 'Username or email not found.';
            
            // Provide more specific feedback
            if (strpos($username, '@') !== false) {
                $error_message = 'Email address not registered. Please check your email or sign up.';
            } else {
                $error_message = 'Username not found. Please check your username or sign up.';
            }
            
            $_SESSION['login_error'] = $error_message;
            $_SESSION['form_data'] = ['username' => $username];
            
            // Log failed attempt
            logLoginAttempt($username, false, 'User not found');
            
            header('Location: login.php');
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['login_error'] = 'Login service temporarily unavailable. Please try again later.';
        $_SESSION['form_data'] = ['username' => $username];
        header('Location: login.php');
        exit();
    }
} else {
    // Not a POST request
    header('Location: login.php');
    exit();
}

// Helper functions
function logLoginAttempt($username, $success, $notes = '') {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, username, success, notes, attempt_time) 
            VALUES (:ip, :username, :success, :notes, NOW())
        ");
        $stmt->execute([
            'ip' => $ip, 
            'username' => $username, 
            'success' => $success ? 1 : 0,
            'notes' => $notes
        ]);
    } catch (Exception $e) {
        error_log("Login attempt logging error: " . $e->getMessage());
    }
}

function logActivity($action, $details = '', $admin_id = null) {
    global $pdo;
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, details, ip_address, user_agent) 
            VALUES (:admin_id, :action, :details, :ip_address, :user_agent)
        ");
        
        $stmt->execute([
            'admin_id' => $admin_id,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
?>