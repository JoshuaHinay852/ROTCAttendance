<?php
// archive_functions.php
// Functions for archiving records

/**
 * Move a record to archive
 * @param PDO $pdo - Database connection
 * @param string $type - Type of record (cadet, mp, excuse, etc.)
 * @param int $id - Record ID
 * @param string $reason - Reason for archiving
 * @return bool - Success status
 */
function moveToArchive($type, $id, $reason = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        switch($type) {
            case 'cadet':
                // Archive cadet
                $stmt = $pdo->prepare("UPDATE cadet_accounts SET is_archived = TRUE, archived_at = NOW(), archive_reason = ? WHERE id = ?");
                $stmt->execute([$reason, $id]);
                break;
                
            case 'mp':
                // Archive MP
                $stmt = $pdo->prepare("UPDATE mp_accounts SET is_archived = TRUE, archived_at = NOW(), archive_reason = ? WHERE id = ?");
                $stmt->execute([$reason, $id]);
                break;
                
            case 'excuse_cadet':
                // Archive cadet excuse
                $stmt = $pdo->prepare("UPDATE cadet_excuses SET is_archived = TRUE, archived_at = NOW(), archive_reason = ? WHERE id = ?");
                $stmt->execute([$reason, $id]);
                break;
                
            case 'excuse_mp':
                // Archive MP excuse
                $stmt = $pdo->prepare("UPDATE mp_excuses SET is_archived = TRUE, archived_at = NOW(), archive_reason = ? WHERE id = ?");
                $stmt->execute([$reason, $id]);
                break;
                
            default:
                $pdo->rollBack();
                return false;
        }
        
        // Log the archive action
        $log_stmt = $pdo->prepare("
            INSERT INTO archive_logs (record_type, record_id, reason, archived_by, archived_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([$type, $id, $reason, $_SESSION['admin_id'] ?? null]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Archive error: " . $e->getMessage());
        return false;
    }
}

/**
 * Restore a record from archive
 * @param string $type - Type of record
 * @param int $id - Record ID
 * @return bool - Success status
 */
function restoreFromArchive($type, $id) {
    global $pdo;
    
    try {
        switch($type) {
            case 'cadet':
                $stmt = $pdo->prepare("UPDATE cadet_accounts SET is_archived = FALSE, archived_at = NULL, archive_reason = NULL WHERE id = ?");
                break;
            case 'mp':
                $stmt = $pdo->prepare("UPDATE mp_accounts SET is_archived = FALSE, archived_at = NULL, archive_reason = NULL WHERE id = ?");
                break;
            case 'excuse_cadet':
                $stmt = $pdo->prepare("UPDATE cadet_excuses SET is_archived = FALSE, archived_at = NULL, archive_reason = NULL WHERE id = ?");
                break;
            case 'excuse_mp':
                $stmt = $pdo->prepare("UPDATE mp_excuses SET is_archived = FALSE, archived_at = NULL, archive_reason = NULL WHERE id = ?");
                break;
            default:
                return false;
        }
        
        return $stmt->execute([$id]);
        
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete a record
 * @param string $type - Type of record
 * @param int $id - Record ID
 * @return bool - Success status
 */
function permanentlyDelete($type, $id) {
    global $pdo;
    
    try {
        switch($type) {
            case 'cadet':
                $stmt = $pdo->prepare("DELETE FROM cadet_accounts WHERE id = ?");
                break;
            case 'mp':
                $stmt = $pdo->prepare("DELETE FROM mp_accounts WHERE id = ?");
                break;
            case 'excuse_cadet':
                $stmt = $pdo->prepare("DELETE FROM cadet_excuses WHERE id = ?");
                break;
            case 'excuse_mp':
                $stmt = $pdo->prepare("DELETE FROM mp_excuses WHERE id = ?");
                break;
            default:
                return false;
        }
        
        return $stmt->execute([$id]);
        
    } catch (Exception $e) {
        error_log("Permanent delete error: " . $e->getMessage());
        return false;
    }
}
?>