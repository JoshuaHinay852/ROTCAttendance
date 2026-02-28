<?php
// mp_profile.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
requireAdminLogin();

// Get MP ID from URL
$mp_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$mp_id) {
    header('Location: mp_accounts.php');
    exit();
}

// Fetch MP data
$stmt = $pdo->prepare("SELECT * FROM mp_accounts WHERE id = ?");
$stmt->execute([$mp_id]);
$mp = $stmt->fetch();

if (!$mp) {
    $_SESSION['error'] = "MP account not found.";
    header('Location: mp_accounts.php');
    exit();
}

// Fetch created by admin name
$created_by_name = "System";
if ($mp['created_by']) {
    $stmt = $pdo->prepare("SELECT full_name FROM admins WHERE id = ?");
    $stmt->execute([$mp['created_by']]);
    $admin = $stmt->fetch();
    if ($admin) {
        $created_by_name = $admin['full_name'];
    }
}

// Calculate age from DOB
$age = '';
if ($mp['dob']) {
    $dob = new DateTime($mp['dob']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

// Handle status badge class
$status_classes = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'denied' => 'bg-red-100 text-red-800'
];

$status_class = $status_classes[$mp['status']] ?? 'bg-gray-100 text-gray-800';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?> | MP Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-header {
            background: #27337d;
        }
        
        .info-card {
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .section-divider {
            border-top: 2px solid #e5e7eb;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .platoon-badge {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .company-badge {
            background-color: #fce7f3;
            color: #9d174d;
        }
        
        .card-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        
        .card-label i {
            margin-right: 8px;
            font-size: 16px;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'dashboard_header.php'; ?>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Back Button -->
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <a href="mp_accounts.php" class="inline-flex items-center text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to MP List
            </a>
        </div>
        
        <main class="p-8">
            <!-- Profile Header -->
            <div class="profile-header rounded-xl shadow-lg overflow-hidden mb-8">
                <div class="p-8 text-white">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                        <div class="flex items-center space-x-6">
                            <div class="relative">
                                <div class="w-24 h-24 rounded-full bg-white/20 flex items-center justify-center text-4xl font-bold border-4 border-white/30">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <span class="absolute bottom-0 right-0 w-8 h-8 <?php echo $status_class; ?> rounded-full flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-xs"></i>
                                </span>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold">
                                    <?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>
                                    <?php if ($mp['middle_name']): ?>
                                        <span class="text-white/80"><?php echo htmlspecialchars($mp['middle_name'][0] . '.'); ?></span>
                                    <?php endif; ?>
                                </h1>
                                <p class="text-white/90 mt-2">
                                    <span class="badge platoon-badge">Platoon <?php echo htmlspecialchars($mp['platoon']); ?></span>
                                    <span class="badge company-badge ml-2"><?php echo htmlspecialchars($mp['company']); ?> Company</span>
                                    <span class="badge <?php echo $status_class; ?> ml-2">
                                        <?php echo ucfirst($mp['status']); ?>
                                    </span>
                                </p>
                                <p class="text-white/80 mt-2">
                                    <i class="fas fa-graduation-cap mr-2"></i><?php echo htmlspecialchars($mp['course']); ?>
                                    <i class="fas fa-id-badge ml-4 mr-2"></i>MP ID: <?php echo $mp['id']; ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <div class="flex space-x-3">
                                <a href="mp_accounts.php?action=edit&id=<?php echo $mp['id']; ?>" 
                                   class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors">
                                    <i class="fas fa-edit mr-2"></i>Edit Profile
                                </a>
                                <a href="mp_accounts.php" 
                                   class="px-4 py-2 bg-white text-blue-800 hover:bg-gray-100 rounded-lg transition-colors font-medium">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROW 1: Military Police Information & ROTC Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Military Police Information Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden info-card">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-user-shield mr-2 text-blue-800"></i>Military Police Information
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="mb-4">
                                    <div class="card-label">
                                        <i class="fas fa-user text-gray-500"></i>Full Name
                                    </div>
                                    <p class="text-gray-900 font-medium">
                                        <?php echo htmlspecialchars($mp['first_name'] . ' ' . $mp['last_name']); ?>
                                        <?php if ($mp['middle_name']): ?>
                                            <span class="text-gray-600">(<?php echo htmlspecialchars($mp['middle_name']); ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="card-label">
                                        <i class="fas fa-birthday-cake text-gray-500"></i>Date of Birth
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <p class="text-gray-900 font-medium">
                                            <?php echo date('F j, Y', strtotime($mp['dob'])); ?>
                                        </p>
                                        <?php if ($age): ?>
                                            <span class="text-sm text-gray-500">(<?php echo $age; ?> years old)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="mb-4">
                                    <div class="card-label">
                                        <i class="fas fa-graduation-cap text-gray-500"></i>Course
                                    </div>
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($mp['course']); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="card-label">
                                        <i class="fas fa-female text-gray-500"></i>Mother's Name
                                    </div>
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($mp['mothers_name']); ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="card-label">
                                        <i class="fas fa-male text-gray-500"></i>Father's Name
                                    </div>
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($mp['fathers_name']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="card-label">
                                <i class="fas fa-map-marker-alt text-gray-500"></i>Full Address
                            </div>
                            <p class="text-gray-900"><?php echo htmlspecialchars($mp['full_address']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- ROTC Information Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden info-card">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-flag mr-2 text-red-600"></i>ROTC Information
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <div class="card-label">
                                    <i class="fas fa-flag text-gray-500"></i>Platoon Assignment
                                </div>
                                <div class="flex items-center mt-2">
                                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                        <span class="text-blue-600 font-bold text-lg"><?php echo htmlspecialchars($mp['platoon']); ?></span>
                                    </div>
                                    <div>
                                        <p class="text-gray-900 font-medium text-lg">Platoon <?php echo htmlspecialchars($mp['platoon']); ?></p>
                                        <p class="text-sm text-gray-500">Assigned Platoon</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div class="card-label">
                                    <i class="fas fa-users text-gray-500"></i>Company Assignment
                                </div>
                                <div class="flex items-center mt-2">
                                    <div class="w-12 h-12 rounded-full bg-pink-100 flex items-center justify-center mr-4">
                                        <span class="text-pink-600 font-bold text-lg">
                                            <?php echo strtoupper(substr($mp['company'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-gray-900 font-medium text-lg"><?php echo htmlspecialchars($mp['company']); ?> Company</p>
                                        <p class="text-sm text-gray-500">Assigned Company</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div class="card-label">
                                    <i class="fas fa-calendar-alt text-gray-500"></i>Member Since
                                </div>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-calendar text-gray-400 mr-4 text-lg"></i>
                                    <div>
                                        <p class="text-gray-900 font-medium">
                                            <?php echo date('F j, Y', strtotime($mp['created_at'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo date('g:i A', strtotime($mp['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROW 2: Account Information & Account Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Account Information Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden info-card">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-id-card mr-2 text-blue-800"></i>Account Information
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="mb-6">
                                    <div class="card-label">
                                        <i class="fas fa-user-circle text-gray-500"></i>Username
                                    </div>
                                    <p class="text-gray-900 font-medium text-lg">@<?php echo htmlspecialchars($mp['username']); ?></p>
                                </div>
                                
                                <div class="mb-6">
                                    <div class="card-label">
                                        <i class="fas fa-envelope text-gray-500"></i>Email Address
                                    </div>
                                    <p class="text-gray-900 font-medium text-lg"><?php echo htmlspecialchars($mp['email']); ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <div class="mb-6">
                                    <div class="card-label">
                                        <i class="fas fa-shield-alt text-gray-500"></i>Account Status
                                    </div>
                                    <span class="badge <?php echo $status_class; ?> text-sm">
                                        <?php echo ucfirst($mp['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="mb-6">
                                    <div class="card-label">
                                        <i class="fas fa-sign-in-alt text-gray-500"></i>Last Login
                                    </div>
                                    <p class="text-gray-900 font-medium">
                                        <?php if ($mp['last_login']): ?>
                                            <?php echo date('F j, Y', strtotime($mp['last_login'])); ?>
                                            <span class="block text-sm text-gray-500">
                                                <?php echo date('g:i A', strtotime($mp['last_login'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500 italic">Never logged in</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Account Stats Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden info-card">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-chart-bar mr-2 text-blue-800"></i>Account Stats
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div>
                                <div class="card-label">
                                    <i class="fas fa-user-tie text-gray-500"></i>Account Created By
                                </div>
                                <div class="flex items-center mt-2">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                                        <i class="fas fa-user-tie text-gray-600"></i>
                                    </div>
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($created_by_name); ?></p>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div class="card-label">
                                    <i class="fas fa-sync-alt text-gray-500"></i>Last Profile Update
                                </div>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-sync text-gray-400 mr-4 text-lg"></i>
                                    <div>
                                        <p class="text-gray-900 font-medium">
                                            <?php if ($mp['updated_at']): ?>
                                                <?php echo date('F j, Y', strtotime($mp['updated_at'])); ?>
                                            <?php else: ?>
                                                <span class="text-gray-500 italic">Never updated</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($mp['updated_at']): ?>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('g:i A', strtotime($mp['updated_at'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div class="card-label">
                                    <i class="fas fa-hashtag text-gray-500"></i>Account ID
                                </div>
                                <div class="flex items-center mt-2">
                                    <i class="fas fa-hashtag text-gray-400 mr-4 text-lg"></i>
                                    <p class="text-gray-900 font-medium text-lg font-mono"><?php echo $mp['id']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ROW 4: Status Timeline -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden info-card">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-history mr-2 text-gray-600"></i>Account Timeline
                    </h2>
                </div>
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-user-shield text-blue-800"></i>
                            </div>
                            <div class="w-0.5 h-16 bg-blue-200 mt-2"></div>
                        </div>
                        <div class="flex-1">
                            <div class="mb-6">
                                <h3 class="font-medium text-gray-900">MP Account Created</h3>
                                <p class="text-sm text-gray-600"><?php echo date('F j, Y g:i A', strtotime($mp['created_at'])); ?></p>
                                <p class="text-sm text-gray-500 mt-1">MP account was created by <?php echo htmlspecialchars($created_by_name); ?></p>
                            </div>
                            
                            <?php if ($mp['last_login']): ?>
                                <div class="flex items-start space-x-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-sign-in-alt text-blue-600"></i>
                                        </div>
                                        <div class="w-0.5 h-16 bg-blue-200 mt-2"></div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-gray-900">Last Login</h3>
                                        <p class="text-sm text-gray-600"><?php echo date('F j, Y g:i A', strtotime($mp['last_login'])); ?></p>
                                        <p class="text-sm text-gray-500 mt-1">Last successful login to the system</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($mp['updated_at'] && $mp['updated_at'] != $mp['created_at']): ?>
                                <div class="flex items-start space-x-4 mt-6">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                            <i class="fas fa-sync-alt text-yellow-600"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-gray-900">Last Updated</h3>
                                        <p class="text-sm text-gray-600"><?php echo date('F j, Y g:i A', strtotime($mp['updated_at'])); ?></p>
                                        <p class="text-sm text-gray-500 mt-1">Profile information was last updated</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                    <i class="fas fa-key text-blue-800 text-xl"></i>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4 text-center">Reset Password</h3>
                <div class="mt-4">
                    <p class="text-sm text-gray-600 text-center mb-4" id="resetMessage">
                        Reset password for <span id="resetUsername" class="font-semibold"></span>
                    </p>
                    <form id="resetPasswordForm" method="POST" action="mp_accounts.php">
                        <input type="hidden" name="id" id="resetId">
                        <input type="hidden" name="reset_password" value="1">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" name="new_password" required minlength="8" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-800 focus:border-blue-800">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input type="password" name="confirm_password" required minlength="8"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-800 focus:border-blue-800">
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeResetPasswordModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-800 text-white rounded-md hover:bg-blue-900">
                                Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Reset Password Modal Functions
        function showResetPasswordModal(id, username, type = 'mp') {
            document.getElementById('resetId').value = id;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
            document.getElementById('resetPasswordForm').reset();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const resetModal = document.getElementById('resetPasswordModal');
            
            if (event.target == resetModal) {
                closeResetPasswordModal();
            }
        }
    </script>
</body>
</html>