<?php
require_once 'config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $full_name = isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : '';
    
    // Initialize errors array
    $errors = [];
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3-20 characters and contain only letters, numbers, and underscores";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validate_email($email)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (!preg_match('/^[a-zA-Z\s]{2,100}$/', $full_name)) {
        $errors[] = "Full name should contain only letters and spaces (2-100 characters)";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        $password_errors = validate_password($password);
        if (!empty($password_errors)) {
            $errors = array_merge($errors, $password_errors);
        }
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check terms
    if (!isset($_POST['terms'])) {
        $errors[] = "You must agree to the terms and conditions";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE username = :username OR email = :email");
            $check_stmt->execute(['username' => $username, 'email' => $email]);
            
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new admin with 'pending' status
                $insert_stmt = $pdo->prepare("
                    INSERT INTO admins (username, email, password, full_name, role, account_status) 
                    VALUES (:username, :email, :password, :full_name, 'admin', 'pending')
                ");
                
                $insert_stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashed_password,
                    'full_name' => $full_name
                ]);
                
                // Get the new admin ID
                $admin_id = $pdo->lastInsertId();
                
                // Log the registration
                logActivity('admin_registered', "New admin registered: {$username} (ID: {$admin_id})", $admin_id);
                
                // Redirect to login with pending message
                $_SESSION['registration_success'] = 'Registration submitted successfully! Your account is pending approval by an administrator.';
                header('Location: login.php?success=' . urlencode('Registration submitted successfully! Your account is pending approval by an administrator.'));
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Signup error: " . $e->getMessage());
            $errors[] = "Registration failed due to a system error. Please try again.";
        }
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['form_data'] = [
            'username' => $username,
            'email' => $email,
            'full_name' => $full_name
        ];
        header('Location: signup.php');
        exit();
    }
    
} else {
    // Not a POST request
    header('Location: signup.php');
    exit();
}

// Helper function to log activity (simplified version)
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