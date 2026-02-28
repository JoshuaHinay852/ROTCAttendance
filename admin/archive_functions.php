// archive_functions.php
<?php

// Check if database.php has been included
if (!isset($pdo)) {
    require_once '../config/database.php';
}

// Include auth functions if needed functions aren't defined
if (!function_exists('isAdminLoggedIn')) {
    require_once '../includes/auth_check.php';
}

if (!function_exists('logActivity')) {
    require_once '../includes/auth_check.php';
}

function moveToArchive($type, $id, $reason = '') {
    global $pdo;
    
    // Check if admin is logged in and has permission
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $deleted_by = $_SESSION['admin_id'];
    
    try {
        $pdo->beginTransaction();
        
        switch($type) {
            case 'admin':
                // Get admin data
                $stmt = $pdo->prepare("SELECT 
                    id, username, email, password, full_name, role, 
                    account_status, status_reason, profile_image, last_login 
                    FROM admins WHERE id = ?");
                $stmt->execute([$id]);
                $admin = $stmt->fetch();
                
                if (!$admin) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Check if not deleting yourself
                if ($admin['id'] == $_SESSION['admin_id']) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO admin_archives 
                    (original_id, username, email, password, full_name, role, account_status, 
                     profile_image, last_login, deleted_by, reason, deleted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $admin['id'], 
                    $admin['username'] ?? '', 
                    $admin['email'] ?? '', 
                    $admin['password'] ?? '',
                    $admin['full_name'] ?? '', 
                    $admin['role'] ?? 'admin', 
                    $admin['account_status'] ?? 'pending',
                    $admin['profile_image'] ?? NULL, 
                    $admin['last_login'] ?? NULL, 
                    $deleted_by, 
                    $reason
                ]);
                
                // Log the activity
                logActivity('admin_deleted', "Archived admin: {$admin['username']} (ID: {$admin['id']})");
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                return true;
                
            case 'cadet':
                // Get cadet data
                $stmt = $pdo->prepare("SELECT 
                    id, username, email, password, first_name, last_name, middle_name,
                    course, full_address, platoon, company, dob, mothers_name, fathers_name,
                    profile_image, status, last_login, created_by 
                    FROM cadet_accounts WHERE id = ?");
                $stmt->execute([$id]);
                $cadet = $stmt->fetch();
                
                if (!$cadet) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO cadet_archives 
                    (original_id, username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     profile_image, status, last_login, created_by, deleted_by, reason, deleted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $cadet['id'], 
                    $cadet['username'] ?? '', 
                    $cadet['email'] ?? '', 
                    $cadet['password'] ?? '',
                    $cadet['first_name'] ?? '', 
                    $cadet['last_name'] ?? '', 
                    $cadet['middle_name'] ?? '',
                    $cadet['course'] ?? '', 
                    $cadet['full_address'] ?? '', 
                    $cadet['platoon'] ?? '', 
                    $cadet['company'] ?? '',
                    $cadet['dob'] ?? NULL, 
                    $cadet['mothers_name'] ?? '', 
                    $cadet['fathers_name'] ?? '',
                    $cadet['profile_image'] ?? NULL, 
                    $cadet['status'] ?? 'active', 
                    $cadet['last_login'] ?? NULL,
                    $cadet['created_by'] ?? NULL, 
                    $deleted_by, 
                    $reason
                ]);
                
                // Log the activity
                logActivity('cadet_deleted', "Archived cadet: {$cadet['username']} (ID: {$cadet['id']})");
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM cadet_accounts WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                return true;
                
            case 'mp':
                // Get MP data
                $stmt = $pdo->prepare("SELECT 
                    id, username, email, password, first_name, last_name, middle_name,
                    course, full_address, platoon, company, dob, mothers_name, fathers_name,
                    profile_image, status, last_login, created_by 
                    FROM mp_accounts WHERE id = ?");
                $stmt->execute([$id]);
                $mp = $stmt->fetch();
                
                if (!$mp) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Insert into archive
                $stmt = $pdo->prepare("INSERT INTO mp_archives 
                    (original_id, username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     mp_rank, profile_image, status, last_login, created_by, deleted_by, reason, deleted_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Private', ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $mp['id'], 
                    $mp['username'] ?? '', 
                    $mp['email'] ?? '', 
                    $mp['password'] ?? '',
                    $mp['first_name'] ?? '', 
                    $mp['last_name'] ?? '', 
                    $mp['middle_name'] ?? '',
                    $mp['course'] ?? '', 
                    $mp['full_address'] ?? '', 
                    $mp['platoon'] ?? '', 
                    $mp['company'] ?? '',
                    $mp['dob'] ?? NULL, 
                    $mp['mothers_name'] ?? '', 
                    $mp['fathers_name'] ?? '',
                    $mp['profile_image'] ?? NULL, 
                    $mp['status'] ?? 'active', 
                    $mp['last_login'] ?? NULL,
                    $mp['created_by'] ?? NULL, 
                    $deleted_by, 
                    $reason
                ]);
                
                // Log the activity
                logActivity('mp_deleted', "Archived MP: {$mp['username']} (ID: {$mp['id']})");
                
                // Delete from original table
                $stmt = $pdo->prepare("DELETE FROM mp_accounts WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                return true;
                
            default:
                $pdo->rollBack();
                return false;
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Move to archive error: " . $e->getMessage() . " - Type: $type, ID: $id - SQL Error: " . $e->getMessage());
        return false;
    }
}

function restoreFromArchive($type, $archive_id, $set_pending = true) {
    global $pdo;
    
    // Check if admin is logged in and has permission
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        switch($type) {
            case 'admin':
                // Get archived admin data
                $stmt = $pdo->prepare("SELECT * FROM admin_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                $admin = $stmt->fetch();
                
                if (!$admin) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
                $check_stmt->execute([$admin['username'], $admin['email']]);
                
                if ($check_stmt->rowCount() > 0) {
                    $pdo->rollBack();
                    return false; // Username or email already in use
                }
                
                // Determine status based on $set_pending parameter
                $status = $set_pending ? 'pending' : ($admin['account_status'] ?? 'pending');
                $status_reason = $set_pending ? 
                    'Restored from archive - requires re-approval' : 
                    ($admin['reason'] ?? 'Restored from archive');
                
                // Insert back into admins table
                $stmt = $pdo->prepare("INSERT INTO admins 
                    (username, email, password, full_name, role, account_status, status_reason, 
                     profile_image, last_login, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $admin['username'], 
                    $admin['email'], 
                    $admin['password'],
                    $admin['full_name'], 
                    $admin['role'] ?? 'admin',
                    $status,
                    $status_reason,
                    $admin['profile_image'], 
                    $admin['last_login']
                ]);
                
                // Log the activity
                $status_text = $set_pending ? 'pending' : $status;
                logActivity('admin_restored', "Restored admin: {$admin['username']} (ID: {$admin['original_id']}) - Status: $status_text");
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM admin_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                
                $pdo->commit();
                return true;
                
            case 'cadet':
                // Get archived cadet data
                $stmt = $pdo->prepare("SELECT * FROM cadet_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                $cadet = $stmt->fetch();
                
                if (!$cadet) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id FROM cadet_accounts WHERE username = ? OR email = ?");
                $check_stmt->execute([$cadet['username'], $cadet['email']]);
                
                if ($check_stmt->rowCount() > 0) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Determine status based on $set_pending parameter
                $status = $set_pending ? 'pending' : ($cadet['status'] ?? 'pending');
                
                // Insert back into cadet_accounts table
                $stmt = $pdo->prepare("INSERT INTO cadet_accounts 
                    (username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     profile_image, status, last_login, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $cadet['username'], 
                    $cadet['email'], 
                    $cadet['password'],
                    $cadet['first_name'], 
                    $cadet['last_name'], 
                    $cadet['middle_name'],
                    $cadet['course'], 
                    $cadet['full_address'], 
                    $cadet['platoon'], 
                    $cadet['company'],
                    $cadet['dob'], 
                    $cadet['mothers_name'], 
                    $cadet['fathers_name'],
                    $cadet['profile_image'], 
                    $status,
                    $cadet['last_login'],
                    $cadet['created_by']
                ]);
                
                // Log the activity
                $status_text = $set_pending ? 'pending' : $status;
                logActivity('cadet_restored', "Restored cadet: {$cadet['username']} - Status: $status_text");
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM cadet_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                
                $pdo->commit();
                return true;
                
            case 'mp':
                // Get archived MP data
                $stmt = $pdo->prepare("SELECT * FROM mp_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                $mp = $stmt->fetch();
                
                if (!$mp) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id FROM mp_accounts WHERE username = ? OR email = ?");
                $check_stmt->execute([$mp['username'], $mp['email']]);
                
                if ($check_stmt->rowCount() > 0) {
                    $pdo->rollBack();
                    return false;
                }
                
                // Determine status based on $set_pending parameter
                $status = $set_pending ? 'pending' : ($mp['status'] ?? 'pending');
                
                // Insert back into mp_accounts table
                $stmt = $pdo->prepare("INSERT INTO mp_accounts 
                    (username, email, password, first_name, last_name, middle_name, 
                     course, full_address, platoon, company, dob, mothers_name, fathers_name,
                     profile_image, status, last_login, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $stmt->execute([
                    $mp['username'], 
                    $mp['email'], 
                    $mp['password'],
                    $mp['first_name'], 
                    $mp['last_name'], 
                    $mp['middle_name'],
                    $mp['course'], 
                    $mp['full_address'], 
                    $mp['platoon'], 
                    $mp['company'],
                    $mp['dob'], 
                    $mp['mothers_name'], 
                    $mp['fathers_name'],
                    $mp['profile_image'], 
                    $status,
                    $mp['last_login'],
                    $mp['created_by']
                ]);
                
                // Log the activity
                $status_text = $set_pending ? 'pending' : $status;
                logActivity('mp_restored', "Restored MP: {$mp['username']} - Status: $status_text");
                
                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM mp_archives WHERE id = ?");
                $stmt->execute([$archive_id]);
                
                $pdo->commit();
                return true;
                
            default:
                $pdo->rollBack();
                return false;
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Restore error: " . $e->getMessage() . " - Type: $type, ID: $archive_id");
        return false;
    }
}

function permanentDeleteFromArchive($type, $archive_id) {
    global $pdo;
    
    // Check if admin is logged in and has permission
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    switch($type) {
        case 'admin':
            // Log before deletion
            $stmt = $pdo->prepare("SELECT username FROM admin_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                logActivity('admin_permanently_deleted', "Permanently deleted admin: {$admin['username']}");
            }
            
            $stmt = $pdo->prepare("DELETE FROM admin_archives WHERE id = ?");
            break;
        case 'cadet':
            // Log before deletion
            $stmt = $pdo->prepare("SELECT username FROM cadet_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $cadet = $stmt->fetch();
            
            if ($cadet) {
                logActivity('cadet_permanently_deleted', "Permanently deleted cadet: {$cadet['username']}");
            }
            
            $stmt = $pdo->prepare("DELETE FROM cadet_archives WHERE id = ?");
            break;
        case 'mp':
            // Log before deletion
            $stmt = $pdo->prepare("SELECT username FROM mp_archives WHERE id = ?");
            $stmt->execute([$archive_id]);
            $mp = $stmt->fetch();
            
            if ($mp) {
                logActivity('mp_permanently_deleted', "Permanently deleted MP: {$mp['username']}");
            }
            
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

?>