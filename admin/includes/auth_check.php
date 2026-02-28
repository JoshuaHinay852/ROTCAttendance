<?php
// includes/auth_check.php

function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

function getAdminRole() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : null;
}

function requireSuperAdmin() {
    requireAdminLogin();
    
    $role = getAdminRole();
    if ($role !== 'super_admin') {
        $_SESSION['error'] = 'Access denied. Super admin privileges required.';
        header('Location: dashboard.php');
        exit();
    }
}
function moveToArchive($type, $id, $reason = '') {
    global $pdo;
    
    $deleted_by = $_SESSION['admin_id'];
    
    switch($type) {
        case 'admin':
            // Get admin data
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO admin_archives 
                    (original_id, username, email, password, full_name, role, status, 
                     profile_image, last_login, deleted_by, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $admin['id'], $admin['username'], $admin['email'], $admin['password'],
                    $admin['full_name'], $admin['role'], $admin['status'],
                    $admin['profile_image'], $admin['last_login'], $deleted_by, $reason
                ]);
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                return $stmt->execute([$id]);
            }
            break;
            
        case 'cadet':
            // Get cadet data
            $stmt = $pdo->prepare("SELECT * FROM cadet_accounts WHERE id = ?");
            $stmt->execute([$id]);
            $cadet = $stmt->fetch();
            
            if ($cadet) {
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO cadet_archives 
                    (original_id, username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     profile_image, status, last_login, created_by, deleted_by, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $cadet['id'], $cadet['username'], $cadet['email'], $cadet['password'],
                    $cadet['first_name'], $cadet['last_name'], $cadet['middle_name'],
                    $cadet['course'], $cadet['full_address'], $cadet['platoon'], $cadet['company'],
                    $cadet['dob'], $cadet['mothers_name'], $cadet['fathers_name'],
                    $cadet['profile_image'], $cadet['status'], $cadet['last_login'],
                    $cadet['created_by'], $deleted_by, $reason
                ]);
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM cadet_accounts WHERE id = ?");
                return $stmt->execute([$id]);
            }
            break;
            
        case 'mp':
            // Get MP data
            $stmt = $pdo->prepare("SELECT * FROM mp_accounts WHERE id = ?");
            $stmt->execute([$id]);
            $mp = $stmt->fetch();
            
            if ($mp) {
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO mp_archives 
                    (original_id, username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     mp_rank, profile_image, status, last_login, created_by, deleted_by, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $mp['id'], $mp['username'], $mp['email'], $mp['password'],
                    $mp['first_name'], $mp['last_name'], $mp['middle_name'],
                    $mp['course'], $mp['full_address'], $mp['platoon'], $mp['company'],
                    $mp['dob'], $mp['mothers_name'], $mp['fathers_name'], $mp['mp_rank'],
                    $mp['profile_image'], $mp['status'], $mp['last_login'],
                    $mp['created_by'], $deleted_by, $reason
                ]);
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM mp_accounts WHERE id = ?");
                return $stmt->execute([$id]);
            }
            break;
    }
    
    return false;
}

function restoreFromArchive($type, $archive_id) {
    global $pdo;
    
    switch($type) {
        case 'admin':
            // Get archived admin data
            $stmt = $pdo->prepare("SELECT * FROM admin_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Insert back into admins table
                $stmt = $pdo->prepare("INSERT INTO admins 
                    (username, email, password, full_name, role, status, 
                     profile_image, last_login, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $admin['username'], $admin['email'], $admin['password'],
                    $admin['full_name'], $admin['role'], $admin['status'],
                    $admin['profile_image'], $admin['last_login']
                ]);
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM admin_archives WHERE id = ?");
                return $stmt->execute([$archive_id]);
            }
            break;
            
        case 'cadet':
            // Get archived cadet data
            $stmt = $pdo->prepare("SELECT * FROM cadet_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $cadet = $stmt->fetch();
            
            if ($cadet) {
                // Insert back into cadet_accounts table
                $stmt = $pdo->prepare("INSERT INTO cadet_accounts 
                    (username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     profile_image, status, last_login, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $cadet['username'], $cadet['email'], $cadet['password'],
                    $cadet['first_name'], $cadet['last_name'], $cadet['middle_name'],
                    $cadet['course'], $cadet['full_address'], $cadet['platoon'], $cadet['company'],
                    $cadet['dob'], $cadet['mothers_name'], $cadet['fathers_name'],
                    $cadet['profile_image'], $cadet['status'], $cadet['last_login'],
                    $cadet['created_by']
                ]);
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM cadet_archives WHERE id = ?");
                return $stmt->execute([$archive_id]);
            }
            break;
            
        case 'mp':
            // Get archived MP data
            $stmt = $pdo->prepare("SELECT * FROM mp_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $mp = $stmt->fetch();
            
            if ($mp) {
                // Insert back into mp_accounts table
                $stmt = $pdo->prepare("INSERT INTO mp_accounts 
                    (username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     mp_rank, profile_image, status, last_login, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $mp['username'], $mp['email'], $mp['password'],
                    $mp['first_name'], $mp['last_name'], $mp['middle_name'],
                    $mp['course'], $mp['full_address'], $mp['platoon'], $mp['company'],
                    $mp['dob'], $mp['mothers_name'], $mp['fathers_name'], $mp['mp_rank'],
                    $mp['profile_image'], $mp['status'], $mp['last_login'],
                    $mp['created_by']
                ]);
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM mp_archives WHERE id = ?");
                return $stmt->execute([$archive_id]);
            }
            break;
    }
    
    return false;
}

function permanentDeleteFromArchive($type, $archive_id) {
    global $pdo;
    
    switch($type) {
        case 'admin':
            $stmt = $pdo->prepare("DELETE FROM admin_archives WHERE id = ?");
            break;
        case 'cadet':
            $stmt = $pdo->prepare("DELETE FROM cadet_archives WHERE id = ?");
            break;
        case 'mp':
            $stmt = $pdo->prepare("DELETE FROM mp_archives WHERE id = ?");
            break;
        default:
            return false;
    }
    
    return $stmt->execute([$archive_id]);
}

function getArchiveStats() {
    global $pdo;
    
    $stats = [
        'total_archived' => 0,
        'admin_archives' => 0,
        'cadet_archives' => 0,
        'mp_archives' => 0,
        'recent_archives' => 0
    ];
    
    try {
        // Admin archives count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_archives");
        $stats['admin_archives'] = $stmt->fetch()['count'];
        
        // Cadet archives count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_archives");
        $stats['cadet_archives'] = $stmt->fetch()['count'];
        
        // MP archives count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mp_archives");
        $stats['mp_archives'] = $stmt->fetch()['count'];
        
        // Total archives
        $stats['total_archived'] = $stats['admin_archives'] + $stats['cadet_archives'] + $stats['mp_archives'];
        
        // Recent archives (last 7 days)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM (
            SELECT deleted_at FROM admin_archives WHERE deleted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT deleted_at FROM cadet_archives WHERE deleted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT deleted_at FROM mp_archives WHERE deleted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as recent_archives");
        $stmt->execute();
        $stats['recent_archives'] = $stmt->fetch()['count'];
        
    } catch (PDOException $e) {
        error_log("Archive stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// Note: sanitize_input() is already in your database.php
// No need to duplicate it here