<?php
// announcements.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_announcement'])) {
        // Add new announcement
        $title = sanitize_input($_POST['title']);
        $content = sanitize_input($_POST['content']);
        $announcement_date = sanitize_input($_POST['announcement_date']);
        $announcement_time = sanitize_input($_POST['announcement_time']);
        $end_time = sanitize_input($_POST['end_time']);
        $location = sanitize_input($_POST['location']);
        $dress_code = sanitize_input($_POST['dress_code']);
        $notes = sanitize_input($_POST['notes']);
        $target_audience = sanitize_input($_POST['target_audience']);
        $priority = sanitize_input($_POST['priority']);
        $status = sanitize_input($_POST['status']);
        
        // Calculate expires_at (7 days after announcement date)
        $expires_at = date('Y-m-d H:i:s', strtotime($announcement_date . ' ' . $end_time . ' +7 days'));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements 
                (title, content, announcement_date, announcement_time, end_time, 
                 location, dress_code, notes, target_audience, priority, status, 
                 created_by, published_at, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
            
            if ($stmt->execute([
                $title, $content, $announcement_date, $announcement_time, $end_time,
                $location, $dress_code, $notes, $target_audience, $priority, $status,
                $_SESSION['admin_id'], $published_at, $expires_at
            ])) {
                $announcement_id = $pdo->lastInsertId();
                
                $_SESSION['message'] = "Announcement created successfully!";
                header('Location: announcements.php?action=edit&id=' . $announcement_id);
                exit();
            }
        } catch (PDOException $e) {
            $error = "Failed to create announcement: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['edit_announcement'])) {
        // Edit announcement
        $id = $_POST['id'];
        $title = sanitize_input($_POST['title']);
        $content = sanitize_input($_POST['content']);
        $announcement_date = sanitize_input($_POST['announcement_date']);
        $announcement_time = sanitize_input($_POST['announcement_time']);
        $end_time = sanitize_input($_POST['end_time']);
        $location = sanitize_input($_POST['location']);
        $dress_code = sanitize_input($_POST['dress_code']);
        $notes = sanitize_input($_POST['notes']);
        $target_audience = sanitize_input($_POST['target_audience']);
        $priority = sanitize_input($_POST['priority']);
        $status = sanitize_input($_POST['status']);
        
        // Get current status to check if we need to update published_at
        $stmt = $pdo->prepare("SELECT status FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        $current_status = $stmt->fetchColumn();
        
        $published_at = null;
        if ($status === 'published' && $current_status !== 'published') {
            $published_at = date('Y-m-d H:i:s');
        }
        
        // Update expires_at
        $expires_at = date('Y-m-d H:i:s', strtotime($announcement_date . ' ' . $end_time . ' +7 days'));
        
        try {
            $query = "UPDATE announcements SET 
                title = ?, content = ?, announcement_date = ?, announcement_time = ?, 
                end_time = ?, location = ?, dress_code = ?, notes = ?, 
                target_audience = ?, priority = ?, status = ?, updated_at = NOW(), 
                expires_at = ?";
            
            $params = [
                $title, $content, $announcement_date, $announcement_time, $end_time,
                $location, $dress_code, $notes, $target_audience, $priority, $status,
                $expires_at
            ];
            
            if ($published_at) {
                $query .= ", published_at = ?";
                $params[] = $published_at;
            }
            
            $query .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($query);
            
            if ($stmt->execute($params)) {
                $_SESSION['message'] = "Announcement updated successfully!";
                header('Location: announcements.php');
                exit();
            }
        } catch (PDOException $e) {
            $error = "Failed to update announcement: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['delete_announcement'])) {
        // Delete announcement
        $id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['message'] = "Announcement deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete announcement.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: announcements.php');
        exit();
        
    } elseif (isset($_POST['publish_announcement'])) {
        // Publish announcement
        $id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE announcements SET 
                status = 'published', 
                published_at = NOW(),
                updated_at = NOW()
                WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                $_SESSION['message'] = "Announcement published successfully!";
            } else {
                $_SESSION['error'] = "Failed to publish announcement.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: announcements.php');
        exit();
        
    } elseif (isset($_POST['duplicate_announcement'])) {
        // Duplicate announcement
        $id = $_POST['id'];
        
        try {
            // Get the original announcement
            $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $original = $stmt->fetch();
            
            if ($original) {
                // Insert as new announcement
                $stmt = $pdo->prepare("INSERT INTO announcements 
                    (title, content, announcement_date, announcement_time, end_time, 
                     location, dress_code, notes, target_audience, priority, status, 
                     created_by, published_at, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NULL, ?)");
                
                $new_title = $original['title'] . ' (Copy)';
                $expires_at = date('Y-m-d H:i:s', strtotime($original['announcement_date'] . ' ' . $original['end_time'] . ' +7 days'));
                
                if ($stmt->execute([
                    $new_title, $original['content'], $original['announcement_date'], 
                    $original['announcement_time'], $original['end_time'], $original['location'],
                    $original['dress_code'], $original['notes'], $original['target_audience'],
                    $original['priority'], $_SESSION['admin_id'], $expires_at
                ])) {
                    $_SESSION['message'] = "Announcement duplicated successfully!";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: announcements.php');
        exit();
    }
}

// Get announcement data for edit/view
$announcement = null;
if (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT a.*, ad.full_name as creator_name 
                          FROM announcements a 
                          LEFT JOIN admins ad ON a.created_by = ad.id 
                          WHERE a.id = ?");
    $stmt->execute([$_GET['id']]);
    $announcement = $stmt->fetch();
    
    if (!$announcement) {
        $action = 'list';
        $error = "Announcement not found.";
    }
}

// Get read statistics for view
$read_stats = null;
if ($action === 'view' && $announcement) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN user_type = 'cadet' THEN 1 END) as cadets_read,
            COUNT(CASE WHEN user_type = 'mp' THEN 1 END) as mp_read,
            COUNT(*) as total_read
        FROM announcement_read_logs 
        WHERE announcement_id = ?
    ");
    $stmt->execute([$announcement['id']]);
    $read_stats = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Management | ROTC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-draft { background: #d1d5db; color: #374151; }
        .status-published { background: #dcfce7; color: #166534; }
        .status-expired { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-low { background: #dbeafe; color: #1e40af; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-high { background: #fecaca; color: #991b1b; }
        .priority-urgent { background: #fca5a5; color: #7f1d1d; }
        
        .target-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .announcement-card {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .announcement-card.priority-low { border-left-color: #3b82f6; }
        .announcement-card.priority-medium { border-left-color: #f59e0b; }
        .announcement-card.priority-high { border-left-color: #ef4444; }
        .announcement-card.priority-urgent { border-left-color: #dc2626; }
        
        .table-row-hover:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 px-8 py-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Announcement Management</h2>
                    <p class="text-gray-600">Create and manage ROTC announcements for cadets and MP</p>
                </div>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                        <i class="fas fa-bullhorn"></i>
                        <span>Create Announcement</span>
                    </a>
                <?php else: ?>
                    <a href="announcements.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Announcements</span>
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <main class="px-8 pb-8">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
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
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
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
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                        <i class="fas fa-<?php echo $action === 'add' ? 'plus-circle' : 'edit'; ?> text-blue-600"></i>
                        <?php echo $action === 'add' ? 'Create New Announcement' : 'Edit Announcement'; ?>
                    </h3>
                    
                    <form method="POST" action="" class="space-y-6">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
                            <input type="hidden" name="edit_announcement" value="1">
                        <?php else: ?>
                            <input type="hidden" name="add_announcement" value="1">
                        <?php endif; ?>
                        
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Announcement Title <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="title" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['title']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Enter announcement title">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Announcement Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="announcement_date" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['announcement_date']) : date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Start Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="announcement_time" required
                                           value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['announcement_time']) : '07:00'; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        End Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="end_time" required
                                           value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['end_time']) : '17:00'; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Location <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="location" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['location']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Enter location (e.g., ROTC Ground, Gymnasium)">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    What to Wear (Dress Code) <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="dress_code" required
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($announcement['dress_code']) : ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="e.g., Complete Uniform, PT Uniform, Civilian Clothes">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Target Audience <span class="text-red-500">*</span>
                                </label>
                                <select name="target_audience" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="all" <?php echo ($action === 'edit' && $announcement['target_audience'] == 'all') ? 'selected' : ''; ?>>All (Cadets & MP)</option>
                                    <option value="cadets" <?php echo ($action === 'edit' && $announcement['target_audience'] == 'cadets') ? 'selected' : ''; ?>>Cadets Only</option>
                                    <option value="mp" <?php echo ($action === 'edit' && $announcement['target_audience'] == 'mp') ? 'selected' : ''; ?>>MP Only</option>
                                    <option value="both" <?php echo ($action === 'edit' && $announcement['target_audience'] == 'both') ? 'selected' : ''; ?>>Both Cadets & MP (Separate)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Priority Level <span class="text-red-500">*</span>
                                </label>
                                <select name="priority" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="low" <?php echo ($action === 'edit' && $announcement['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo ($action === 'edit' && $announcement['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo ($action === 'edit' && $announcement['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo ($action === 'edit' && $announcement['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select name="status" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="draft" <?php echo $announcement['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $announcement['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="cancelled" <?php echo $announcement['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="status" value="draft">
                            <?php endif; ?>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes (Optional)
                                </label>
                                <textarea name="notes" rows="3"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Additional notes or instructions..."><?php echo $action === 'edit' ? htmlspecialchars($announcement['notes']) : ''; ?></textarea>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Announcement Content <span class="text-red-500">*</span>
                                </label>
                                <textarea name="content" required rows="8"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Enter detailed announcement content..."><?php echo $action === 'edit' ? htmlspecialchars($announcement['content']) : ''; ?></textarea>
                                <p class="text-xs text-gray-500 mt-2">
                                    Include all important details: What, Who, When, Where, What to Wear, Special Instructions
                                </p>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Preview</h4>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                                <div class="announcement-card priority-medium bg-white rounded-lg p-6 shadow">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h4 id="previewTitle" class="text-xl font-bold text-gray-800">
                                                <?php echo $action === 'edit' ? htmlspecialchars($announcement['title']) : 'Announcement Title'; ?>
                                            </h4>
                                            <div class="flex items-center gap-3 mt-2">
                                                <span id="previewDate" class="text-sm text-gray-600">
                                                    <?php echo $action === 'edit' ? date('j F Y', strtotime($announcement['announcement_date'])) : date('j F Y'); ?>
                                                </span>
                                                <span id="previewTime" class="text-sm text-gray-600">
                                                    <?php echo $action === 'edit' ? date('Hi', strtotime($announcement['announcement_time'])) . 'H-' . date('Hi', strtotime($announcement['end_time'])) . 'H' : '0700H-1700H'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <span id="previewPriority" class="priority-badge priority-medium">Medium</span>
                                            <span id="previewTarget" class="target-badge">All</span>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-700">Location:</div>
                                            <div id="previewLocation" class="text-sm text-gray-600">
                                                <?php echo $action === 'edit' ? htmlspecialchars($announcement['location']) : 'ROTC Ground'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-700">Dress Code:</div>
                                            <div id="previewDress" class="text-sm text-gray-600">
                                                <?php echo $action === 'edit' ? htmlspecialchars($announcement['dress_code']) : 'Complete Uniform'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="text-sm font-medium text-gray-700">Content:</div>
                                        <div id="previewContent" class="text-gray-700 mt-1 whitespace-pre-line">
                                            <?php echo $action === 'edit' ? htmlspecialchars($announcement['content']) : 'Announcement details will appear here...'; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($action === 'edit' && $announcement['notes']): ?>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="text-sm font-medium text-yellow-800">Note:</div>
                                        <div id="previewNotes" class="text-sm text-yellow-700 mt-1">
                                            <?php echo htmlspecialchars($announcement['notes']); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                            <a href="announcements.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </a>
                            
                            <?php if ($action === 'edit' && $announcement['status'] === 'draft'): ?>
                                <button type="submit" name="publish_announcement" value="1" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                                    <i class="fas fa-paper-plane"></i>
                                    Publish Now
                                </button>
                            <?php endif; ?>
                            
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                                <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                <?php echo $action === 'add' ? 'Save as Draft' : 'Update Announcement'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            
            <!-- View Announcement -->
            <?php elseif ($action === 'view' && $announcement): ?>
                <div class="glass-card rounded-xl p-6">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="flex items-center gap-4 mt-2">
                                <span class="status-badge status-<?php echo $announcement['status']; ?>">
                                    <?php echo ucfirst($announcement['status']); ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    Created by: <?php echo htmlspecialchars($announcement['creator_name']); ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    Created: <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                </span>
                                <?php if ($announcement['published_at']): ?>
                                    <span class="text-sm text-gray-600">
                                        Published: <?php echo date('M j, Y g:i A', strtotime($announcement['published_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="?action=edit&id=<?php echo $announcement['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="announcements.php" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                <i class="fas fa-arrow-left"></i>
                                Back
                            </a>
                        </div>
                    </div>
                    
                    <!-- Announcement Details -->
                    <div class="announcement-card priority-<?php echo $announcement['priority']; ?> bg-white rounded-lg p-8 shadow-lg mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Left Column -->
                            <div>
                                <div class="space-y-6">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 mb-2">Event Details</h4>
                                        <div class="space-y-3">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                    <i class="fas fa-calendar-day text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-700">Date</div>
                                                    <div class="text-lg font-bold text-gray-900">
                                                        <?php echo date('j F Y', strtotime($announcement['announcement_date'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                                    <i class="fas fa-clock text-green-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-700">Time</div>
                                                    <div class="text-lg font-bold text-gray-900">
                                                        <?php echo date('Hi', strtotime($announcement['announcement_time'])) . 'H-' . date('Hi', strtotime($announcement['end_time'])) . 'H'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                                                    <i class="fas fa-map-marker-alt text-purple-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-700">Location</div>
                                                    <div class="text-lg font-bold text-gray-900">
                                                        <?php echo htmlspecialchars($announcement['location']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 mb-2">Target Audience</h4>
                                        <div class="flex items-center gap-3">
                                            <span class="target-badge text-lg px-4 py-2">
                                                <?php 
                                                $audience_text = [
                                                    'all' => 'All Personnel (Cadets & MP)',
                                                    'cadets' => 'Cadets Only',
                                                    'mp' => 'MP Only',
                                                    'both' => 'Both Cadets & MP'
                                                ];
                                                echo $audience_text[$announcement['target_audience']];
                                                ?>
                                            </span>
                                            <span class="priority-badge priority-<?php echo $announcement['priority']; ?> text-lg px-4 py-2">
                                                <?php echo ucfirst($announcement['priority']); ?> Priority
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div>
                                <div class="space-y-6">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 mb-2">Dress Code</h4>
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-tshirt text-yellow-600"></i>
                                            </div>
                                            <div class="text-2xl font-bold text-gray-900">
                                                <?php echo htmlspecialchars($announcement['dress_code']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($announcement['notes']): ?>
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 mb-2">Important Notes</h4>
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <div class="text-yellow-800 whitespace-pre-line"><?php echo htmlspecialchars($announcement['notes']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($read_stats): ?>
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-800 mb-2">Read Statistics</h4>
                                        <div class="grid grid-cols-3 gap-4">
                                            <div class="text-center p-3 bg-blue-50 rounded-lg">
                                                <div class="text-2xl font-bold text-blue-600"><?php echo $read_stats['cadets_read']; ?></div>
                                                <div class="text-sm text-blue-800">Cadets Read</div>
                                            </div>
                                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                                <div class="text-2xl font-bold text-green-600"><?php echo $read_stats['mp_read']; ?></div>
                                                <div class="text-sm text-green-800">MP Read</div>
                                            </div>
                                            <div class="text-center p-3 bg-purple-50 rounded-lg">
                                                <div class="text-2xl font-bold text-purple-600"><?php echo $read_stats['total_read']; ?></div>
                                                <div class="text-sm text-purple-800">Total Read</div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">Announcement Content</h4>
                            <div class="prose max-w-none">
                                <div class="text-gray-700 whitespace-pre-line text-lg leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <div>
                            <?php if ($announcement['status'] === 'draft'): ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
                                    <input type="hidden" name="publish_announcement" value="1">
                                    <button type="submit" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 transition-colors">
                                        <i class="fas fa-paper-plane"></i>
                                        Publish Announcement
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex gap-2">
                            <form method="POST" action="" class="inline" 
                                  onsubmit="return confirm('Duplicate this announcement?');">
                                <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
                                <input type="hidden" name="duplicate_announcement" value="1">
                                <button type="submit" 
                                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                    <i class="fas fa-copy"></i>
                                    Duplicate
                                </button>
                            </form>
                            
                            <button onclick="confirmDelete(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars(addslashes($announcement['title'])); ?>')"
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            
            <!-- Announcements List -->
            <?php else: ?>
                <!-- Stats Overview -->
                <?php
                try {
                    $stats = $pdo->query("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                            SUM(CASE WHEN announcement_date = CURDATE() THEN 1 ELSE 0 END) as today,
                            SUM(CASE WHEN announcement_date > CURDATE() THEN 1 ELSE 0 END) as upcoming
                        FROM announcements
                    ")->fetch();
                } catch (PDOException $e) {
                    $stats = ['total' => 0, 'draft' => 0, 'published' => 0, 'expired' => 0, 'cancelled' => 0, 'today' => 0, 'upcoming' => 0];
                }
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Announcements</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-bullhorn text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Published</p>
                                <p class="text-2xl font-bold text-green-600"><?php echo $stats['published']; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-paper-plane text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Draft</p>
                                <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['draft']; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-edit text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Today's Announcements</p>
                                <p class="text-2xl font-bold text-purple-600"><?php echo $stats['today']; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="glass-card rounded-xl p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Announcements</h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="all">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="expired">Expired</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="all">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience</label>
                            <select name="target" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="all">All Audiences</option>
                                <option value="all">All (Cadets & MP)</option>
                                <option value="cadets">Cadets Only</option>
                                <option value="mp">MP Only</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                            <select name="date_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="all">All Dates</option>
                                <option value="today">Today</option>
                                <option value="tomorrow">Tomorrow</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="past">Past</option>
                                <option value="future">Future</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-4 flex justify-end gap-3 mt-4">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex items-center gap-2">
                                <i class="fas fa-filter"></i>
                                Apply Filters
                            </button>
                            <a href="announcements.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg flex items-center gap-2">
                                <i class="fas fa-redo"></i>
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Announcements Table -->
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">All Announcements</h3>
                        <span class="text-sm text-gray-600">
                            <?php echo $stats['total']; ?> announcements found
                        </span>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Announcement Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audience & Priority</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT a.*, ad.full_name as creator_name,
                                               (SELECT COUNT(*) FROM announcement_read_logs WHERE announcement_id = a.id) as read_count
                                        FROM announcements a
                                        LEFT JOIN admins ad ON a.created_by = ad.id
                                        ORDER BY 
                                            CASE a.priority
                                                WHEN 'urgent' THEN 1
                                                WHEN 'high' THEN 2
                                                WHEN 'medium' THEN 3
                                                WHEN 'low' THEN 4
                                            END,
                                            a.announcement_date DESC,
                                            a.created_at DESC
                                    ");
                                    $announcements = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    $announcements = [];
                                }
                                
                                if (empty($announcements)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                <i class="fas fa-bullhorn text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Announcements Found</h3>
                                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                                You haven't created any announcements yet. Start by creating your first announcement.
                                            </p>
                                            <a href="?action=add" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                                                <i class="fas fa-bullhorn"></i>
                                                Create First Announcement
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($announcements as $ann): ?>
                                        <tr class="table-row-hover">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                                        <i class="fas fa-bullhorn text-blue-600"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($ann['title']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-600 truncate max-w-xs">
                                                            <?php echo htmlspecialchars($ann['location']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Dress: <?php echo htmlspecialchars($ann['dress_code']); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            Created by: <?php echo htmlspecialchars($ann['creator_name']); ?>
                                                            <?php if ($ann['read_count'] > 0): ?>
                                                                • <span class="text-green-600"><?php echo $ann['read_count']; ?> reads</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo date('j F Y', strtotime($ann['announcement_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    <?php echo date('Hi', strtotime($ann['announcement_time'])) . 'H-' . date('Hi', strtotime($ann['end_time'])) . 'H'; ?>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <span class="target-badge">
                                                        <?php 
                                                        $audience_text = [
                                                            'all' => 'All',
                                                            'cadets' => 'Cadets',
                                                            'mp' => 'MP',
                                                            'both' => 'Both'
                                                        ];
                                                        echo $audience_text[$ann['target_audience']];
                                                        ?>
                                                    </span>
                                                    <span class="priority-badge priority-<?php echo $ann['priority']; ?>">
                                                        <?php echo ucfirst($ann['priority']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-2">
                                                    <span class="status-badge status-<?php echo $ann['status']; ?>">
                                                        <?php echo ucfirst($ann['status']); ?>
                                                    </span>
                                                    <?php if ($ann['status'] === 'published' && $ann['published_at']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo date('M j, g:i A', strtotime($ann['published_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <a href="?action=view&id=<?php echo $ann['id']; ?>" 
                                                       class="w-8 h-8 rounded-lg bg-blue-100 hover:bg-blue-200 text-blue-600 flex items-center justify-center transition-colors"
                                                       title="View">
                                                        <i class="fas fa-eye text-sm"></i>
                                                    </a>
                                                    
                                                    <a href="?action=edit&id=<?php echo $ann['id']; ?>" 
                                                       class="w-8 h-8 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition-colors"
                                                       title="Edit">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </a>
                                                    
                                                    <?php if ($ann['status'] === 'draft'): ?>
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                                            <input type="hidden" name="publish_announcement" value="1">
                                                            <button type="submit" 
                                                                    class="w-8 h-8 rounded-lg bg-yellow-100 hover:bg-yellow-200 text-yellow-600 flex items-center justify-center transition-colors"
                                                                    title="Publish">
                                                                <i class="fas fa-paper-plane text-sm"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" action="" class="inline" 
                                                          onsubmit="return confirm('Duplicate this announcement?');">
                                                        <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                                        <input type="hidden" name="duplicate_announcement" value="1">
                                                        <button type="submit" 
                                                                class="w-8 h-8 rounded-lg bg-purple-100 hover:bg-purple-200 text-purple-600 flex items-center justify-center transition-colors"
                                                                title="Duplicate">
                                                            <i class="fas fa-copy text-sm"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <button onclick="confirmDelete(<?php echo $ann['id']; ?>, '<?php echo htmlspecialchars(addslashes($ann['title'])); ?>')"
                                                            class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-colors"
                                                            title="Delete">
                                                        <i class="fas fa-trash text-sm"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Delete Announcement</h3>
                        <p class="text-sm text-gray-600 mb-6" id="deleteMessage">
                            Are you sure you want to delete this announcement?
                        </p>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-4">
                        <input type="hidden" name="id" id="deleteId">
                        <input type="hidden" name="delete_announcement" value="1">
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeDeleteModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Delete Announcement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Live preview update
        function updatePreview() {
            const title = document.querySelector('input[name="title"]')?.value || 'Announcement Title';
            const date = document.querySelector('input[name="announcement_date"]')?.value || '<?php echo date("Y-m-d"); ?>';
            const startTime = document.querySelector('input[name="announcement_time"]')?.value || '07:00';
            const endTime = document.querySelector('input[name="end_time"]')?.value || '17:00';
            const location = document.querySelector('input[name="location"]')?.value || 'ROTC Ground';
            const dressCode = document.querySelector('input[name="dress_code"]')?.value || 'Complete Uniform';
            const content = document.querySelector('textarea[name="content"]')?.value || 'Announcement details will appear here...';
            const notes = document.querySelector('textarea[name="notes"]')?.value || '';
            const priority = document.querySelector('select[name="priority"]')?.value || 'medium';
            const target = document.querySelector('select[name="target_audience"]')?.value || 'all';
            
            // Update preview elements
            const previewTitle = document.getElementById('previewTitle');
            const previewDate = document.getElementById('previewDate');
            const previewTime = document.getElementById('previewTime');
            const previewLocation = document.getElementById('previewLocation');
            const previewDress = document.getElementById('previewDress');
            const previewContent = document.getElementById('previewContent');
            const previewNotes = document.getElementById('previewNotes');
            const previewPriority = document.getElementById('previewPriority');
            const previewTarget = document.getElementById('previewTarget');
            
            if (previewTitle) previewTitle.textContent = title;
            if (previewDate) previewDate.textContent = formatDate(date);
            if (previewTime) previewTime.textContent = formatTime(startTime) + 'H-' + formatTime(endTime) + 'H';
            if (previewLocation) previewLocation.textContent = location;
            if (previewDress) previewDress.textContent = dressCode;
            if (previewContent) previewContent.textContent = content;
            if (previewNotes) previewNotes.textContent = notes;
            if (previewPriority) {
                previewPriority.textContent = capitalizeFirst(priority);
                previewPriority.className = 'priority-badge priority-' + priority;
            }
            if (previewTarget) {
                const targetTexts = {
                    'all': 'All',
                    'cadets': 'Cadets',
                    'mp': 'MP',
                    'both': 'Both'
                };
                previewTarget.textContent = targetTexts[target] || 'All';
            }
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const day = date.getDate();
            const month = date.toLocaleString('default', { month: 'long' }).toUpperCase();
            const year = date.getFullYear();
            return `${day} ${month} ${year}`;
        }
        
        function formatTime(timeString) {
            return timeString.replace(':', '');
        }
        
        function capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
        
        // Add event listeners for live preview
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('input, select, textarea');
            formInputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
            
            // Initial preview update
            updatePreview();
            
            // Add fade-in animation to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });
        
        // Delete modal functions
        function confirmDelete(id, title) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete the announcement <strong>"${title}"</strong>?<br>This action cannot be undone.`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Auto-expire announcements
        function checkExpiredAnnouncements() {
            // This could be called via AJAX to auto-update statuses
            // For now, it's a placeholder for future enhancement
        }
    </script>
</body>
</html>