<?php
// includes/auth_check.php

// Check if database.php has been included
if (!isset($pdo)) {
    require_once 'config/database.php';
}

function checkAndRedirectIfPending() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            if ($admin && $admin['account_status'] === 'pending') {
                // Log out pending users
                session_unset();
                session_destroy();
                $_SESSION['login_error'] = 'Your account is still pending approval.';
                header('Location: ../login.php');
                exit();
            }
            
            if ($admin && $admin['account_status'] === 'denied') {
                // Log out denied users
                session_unset();
                session_destroy();
                $error_msg = 'Your account has been denied. Please contact support.';
                $_SESSION['login_error'] = $error_msg;
                header('Location: ../login.php');
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Account status check error: " . $e->getMessage());
        }
    }
}
function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if admin_id exists in session
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        return false;
    }
    
    // Additional check: verify account is still approved
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        // Check if admin exists and account is approved
        if (!$admin || $admin['account_status'] !== 'approved') {
            // Clear session if account is not approved
            session_unset();
            session_destroy();
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Auth check error: " . $e->getMessage());
        return false;
    }
}

// Add this function to auth_check.php
function isAccountApproved() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            return ($admin && $admin['account_status'] === 'approved');
        } catch (PDOException $e) {
            error_log("Account approval check error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

// Update the requireAdminLogin function to check account status
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        // Store attempted URL for redirect after login
        if (!isset($_SESSION['login_redirect'])) {
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        }
        
        // Set appropriate error message
        $error = 'Please login to access this page.';
        if (isset($_SESSION['account_status']) && $_SESSION['account_status'] === 'pending') {
            $error = 'Your account is pending approval.';
        } elseif (isset($_SESSION['account_status']) && $_SESSION['account_status'] === 'denied') {
            $error = 'Your account has been denied.';
        }
        
        $_SESSION['login_error'] = $error;
        header('Location: ../login.php');
        exit();
    }
    
    // Check if account is approved
    if (!isAccountApproved()) {
        session_unset();
        session_destroy();
        $_SESSION['login_error'] = 'Your account is not approved. Please contact an administrator.';
        header('Location: ../login.php');
        exit();
    }
}

function getAdminRole() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
}

// Since we only have one role now, this function always returns true for admin
function hasPermission() {
    return isAdminLoggedIn();
}

function getAdminAccountStatus() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT account_status FROM admins WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $result = $stmt->fetch();
            
            return $result ? $result['account_status'] : null;
        } catch (PDOException $e) {
            error_log("Account status check error: " . $e->getMessage());
            return null;
        }
    }
    
    return null;
}

function checkAccountStatus() {
    $status = getAdminAccountStatus();
    
    if ($status === 'pending') {
        session_unset();
        session_destroy();
        $_SESSION['login_error'] = 'Your account is pending approval. Please wait for administrator approval.';
        header('Location: login.php');
        exit();
    }
    
    if ($status === 'denied') {
        session_unset();
        session_destroy();
        $reason = getDenialReason();
        $error_msg = 'Your account has been denied.';
        if ($reason) {
            $error_msg .= ' Reason: ' . $reason;
        }
        $error_msg .= ' Please contact support.';
        $_SESSION['login_error'] = $error_msg;
        header('Location: login.php');
        exit();
    }
}

function getDenialReason() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT status_reason FROM admins WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['admin_id']]);
            $result = $stmt->fetch();
            
            return $result ? $result['status_reason'] : null;
        } catch (PDOException $e) {
            error_log("Denial reason check error: " . $e->getMessage());
            return null;
        }
    }
    
    return null;
}

function validateSessionTimeout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Session timeout (30 minutes)
    $timeout = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['login_time'])) {
        $session_life = time() - $_SESSION['login_time'];
        
        if ($session_life > $timeout) {
            // Session expired
            session_unset();
            session_destroy();
            $_SESSION['login_error'] = 'Session expired. Please login again.';
            header('Location: login.php');
            exit();
        } else {
            // Update last activity time
            $_SESSION['last_activity'] = time();
        }
    }
}

function checkCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = 'Invalid CSRF token. Please try again.';
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
    }
}

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function isAccountLocked($username) {
    global $pdo;
    
    try {
        // Check for failed login attempts in the last 15 minutes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = :username 
            AND success = 0 
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch();
        
        // Lock account after 5 failed attempts
        return $result['attempts'] >= 5;
    } catch (PDOException $e) {
        error_log("Account lock check error: " . $e->getMessage());
        return false;
    }
}

function logActivity($action, $details = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['admin_id'])) {
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
                'admin_id' => $_SESSION['admin_id'],
                'action' => $action,
                'details' => $details,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ]);
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}




// Initialize session security
function initSessionSecurity() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Call initialization
initSessionSecurity();