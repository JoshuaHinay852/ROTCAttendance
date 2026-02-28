<?php
// get_excuse_details.php
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
requireAdminLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$id || !$type) {
    echo '<div class="text-center text-red-600">Invalid request</div>';
    exit();
}

if ($type === 'cadet') {
    $stmt = $pdo->prepare("
        SELECT ce.*, 
               ca.first_name, ca.last_name, ca.middle_name, ca.username,
               ca.platoon, ca.company, ca.course, ca.email,
               a.first_name as admin_first_name, a.last_name as admin_last_name
        FROM cadet_excuses ce
        JOIN cadet_accounts ca ON ce.cadet_id = ca.id
        LEFT JOIN admin_accounts a ON ce.reviewed_by = a.id
        WHERE ce.id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT me.*, 
               ma.first_name, ma.last_name, ma.middle_name, ma.username,
               ma.platoon, ma.company, ma.email,
               a.first_name as admin_first_name, a.last_name as admin_last_name
        FROM mp_excuses me
        JOIN mp_accounts ma ON me.mp_id = ma.id
        LEFT JOIN admin_accounts a ON me.reviewed_by = a.id
        WHERE me.id = ?
    ");
}

$stmt->execute([$id]);
$excuse = $stmt->fetch();

if (!$excuse) {
    echo '<div class="text-center text-red-600">Excuse request not found</div>';
    exit();
}
?>

<div class="space-y-6">
    <!-- Requester Information -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100">
        <h4 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
            <i class="fas fa-user-circle text-[#1e3a5f]"></i>
            REQUESTER INFORMATION
        </h4>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-500">Full Name</p>
                <p class="font-medium text-gray-900">
                    <?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>
                    <?php if ($excuse['middle_name']): ?>
                        <?php echo ' ' . htmlspecialchars($excuse['middle_name'][0] . '.'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Username</p>
                <p class="font-medium text-gray-900">@<?php echo htmlspecialchars($excuse['username']); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Email</p>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($excuse['email']); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Unit</p>
                <p class="font-medium text-gray-900">
                    Platoon <?php echo htmlspecialchars($excuse['platoon']); ?> / 
                    <?php echo htmlspecialchars($excuse['company']); ?> Company
                </p>
            </div>
            <?php if ($type === 'cadet' && isset($excuse['course'])): ?>
                <div>
                    <p class="text-xs text-gray-500">Course</p>
                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($excuse['course']); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Excuse Details -->
    <div class="border rounded-xl p-5">
        <h4 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
            <i class="fas fa-file-alt text-[#1e3a5f]"></i>
            EXCUSE DETAILS
        </h4>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <p class="text-xs text-gray-500">Excuse Type</p>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mt-1">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $excuse['excuse_type']))); ?>
                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500">Date Filed</p>
                <p class="font-medium text-gray-900"><?php echo date('F d, Y', strtotime($excuse['created_at'])); ?></p>
                <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($excuse['created_at'])); ?></p>
            </div>
            <?php if ($excuse['start_date']): ?>
                <div>
                    <p class="text-xs text-gray-500">Start Date</p>
                    <p class="font-medium text-gray-900"><?php echo date('F d, Y', strtotime($excuse['start_date'])); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($excuse['end_date']): ?>
                <div>
                    <p class="text-xs text-gray-500">End Date</p>
                    <p class="font-medium text-gray-900"><?php echo date('F d, Y', strtotime($excuse['end_date'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <p class="text-xs text-gray-500 mb-2">Reason for Excuse</p>
            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-800 border border-gray-200">
                <?php echo nl2br(htmlspecialchars($excuse['reason'])); ?>
            </div>
        </div>
        
        <?php if (!empty($excuse['remarks'])): ?>
            <div class="mt-4">
                <p class="text-xs text-gray-500 mb-2">Admin Remarks</p>
                <div class="bg-blue-50 rounded-lg p-4 text-sm text-gray-800 border border-blue-200">
                    <?php echo nl2br(htmlspecialchars($excuse['remarks'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Status Timeline -->
    <div class="border rounded-xl p-5">
        <h4 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
            <i class="fas fa-history text-[#1e3a5f]"></i>
            STATUS TIMELINE
        </h4>
        
        <div class="space-y-4">
            <div class="flex gap-3">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-pen text-green-600 text-sm"></i>
                    </div>
                    <div class="w-0.5 h-full bg-gray-200"></div>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Request Submitted</p>
                    <p class="text-xs text-gray-500"><?php echo date('F d, Y h:i A', strtotime($excuse['created_at'])); ?></p>
                    <p class="text-xs text-gray-600 mt-1">Initial submission by requester</p>
                </div>
            </div>
            
            <?php if ($excuse['reviewed_by']): ?>
                <div class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full <?php echo $excuse['status'] === 'approved' ? 'bg-green-100' : 'bg-red-100'; ?> flex items-center justify-center">
                            <i class="fas <?php echo $excuse['status'] === 'approved' ? 'fa-check text-green-600' : 'fa-times text-red-600'; ?> text-sm"></i>
                        </div>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">
                            Request <?php echo ucfirst($excuse['status']); ?>
                        </p>
                        <p class="text-xs text-gray-500"><?php echo date('F d, Y h:i A', strtotime($excuse['reviewed_at'])); ?></p>
                        <p class="text-xs text-gray-600 mt-1">
                            Reviewed by: <?php echo htmlspecialchars($excuse['admin_first_name'] . ' ' . $excuse['admin_last_name']); ?>
                        </p>
                        <?php if ($excuse['remarks']): ?>
                            <p class="text-xs text-gray-600 mt-2 p-2 bg-gray-50 rounded-lg">
                                <span class="font-medium">Remarks:</span> <?php echo htmlspecialchars($excuse['remarks']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Attachment -->
    <?php if (!empty($excuse['attachment_path'])): ?>
        <div class="border rounded-xl p-5">
            <h4 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
                <i class="fas fa-paperclip text-[#1e3a5f]"></i>
                ATTACHMENT
            </h4>
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm text-gray-600"><?php echo basename($excuse['attachment_path']); ?></p>
                    <p class="text-xs text-gray-500 mt-1">
                        Uploaded: <?php echo date('F d, Y', strtotime($excuse['created_at'])); ?>
                    </p>
                </div>
                <button onclick="viewAttachment('<?php echo htmlspecialchars($excuse['attachment_path']); ?>', '<?php echo htmlspecialchars($excuse['first_name'] . ' ' . $excuse['last_name']); ?>')" 
                        class="px-4 py-2 bg-[#1e3a5f] text-white rounded-lg hover:bg-[#2d5a7a] transition-all flex items-center gap-2 text-sm">
                    <i class="fas fa-eye"></i>
                    View Attachment
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>