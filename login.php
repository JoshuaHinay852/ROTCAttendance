<?php
require_once 'config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    if (file_exists('admin/dashboard.php')) {
        header('Location: admin/dashboard.php');
        exit();
    }
}

// Initialize variables
$error = '';
$form_username = '';
$success = '';

// Check for session-based error message first
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Also check for error from query parameter
if (isset($_GET['error']) && empty($error)) {
    $error = htmlspecialchars($_GET['error']);
}

// Check for success message
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// Get form data from session to repopulate
if (isset($_SESSION['form_data']['username'])) {
    $form_username = htmlspecialchars($_SESSION['form_data']['username']);
    unset($_SESSION['form_data']);
}

// Path to your background image - change this to your actual image path
$background_image = 'assets/images/IMG_0072.JPG'; // Change this to your image path
$fallback_background = 'bg-gradient-to-br from-gray-900 via-blue-900 to-indigo-900';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROTC Attendance System | Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        /* Background image styling */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: url('<?php echo $background_image; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        
        /* Dark overlay for better text readability */
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom right,
                rgba(0, 0, 0, 0.85),
                rgba(0, 0, 0, 0.7),
                rgba(30, 58, 138, 0.4)
            );
        }
        
        /* Animation styles */
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .animate-rotate-slow {
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .animate-pulse-once {
            animation: pulse-once 2s ease-in-out;
        }
        @keyframes pulse-once {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        .animate-bounce-in {
            animation: bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-slide-down {
            animation: slideDown 0.4s ease-out;
        }
        @keyframes slideDown {
            0% { transform: translateY(-20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .animate-vibrate {
            animation: vibrate 0.3s linear infinite;
        }
        @keyframes vibrate {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-2px); }
            75% { transform: translateX(2px); }
        }
        
        /* Error message animations */
        .error-shake {
            animation: errorShake 0.5s ease-in-out;
        }
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .error-pulse {
            animation: errorPulse 0.5s ease-in-out;
        }
        @keyframes errorPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .error-flash {
            animation: errorFlash 0.5s ease-in-out;
        }
        @keyframes errorFlash {
            0%, 100% { background-color: rgba(239, 68, 68, 0.2); }
            50% { background-color: rgba(239, 68, 68, 0.4); }
        }
        
        /* Auto-dismiss animation */
        .auto-dismiss {
            animation: fadeOutHide 0.5s ease 5s forwards;
            animation-fill-mode: forwards;
        }
        
        @keyframes fadeOutHide {
            0% { opacity: 1; visibility: visible; }
            99% { opacity: 0; visibility: visible; }
            100% { opacity: 0; visibility: hidden; height: 0; margin: 0; padding: 0; border: 0; }
        }
        
        /* Input error animation */
        .input-error {
            animation: inputError 0.5s ease-in-out;
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }
        
        @keyframes inputError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }
        
        /* Custom scrollbar */
        .form-container {
            max-height: 80vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4b5563 #1f2937;
        }
        .form-container::-webkit-scrollbar {
            width: 6px;
        }
        .form-container::-webkit-scrollbar-track {
            background: #1f2937;
            border-radius: 10px;
        }
        .form-container::-webkit-scrollbar-thumb {
            background: #4b5563;
            border-radius: 10px;
        }
        .form-container::-webkit-scrollbar-thumb:hover {
            background: #6b7280;
        }
        
        /* Custom logo styling */
        .rotc-logo {
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.5));
            transition: all 0.3s ease;
        }
        .rotc-logo:hover {
            filter: drop-shadow(0 6px 10px rgba(59, 130, 246, 0.3));
            transform: scale(1.05);
        }
        
        /* Toast notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
        }
        
        /* Success message */
        .success-gradient {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        /* Fallback background class */
        .fallback-bg {
            background: <?php echo $fallback_background; ?>;
        }
    </style>
</head>
<body class="min-h-screen font-sans overflow-x-hidden">

    <!-- Background Image Container -->
    <div class="background-container">
        <div class="background-overlay"></div>
        <!-- Optional animated elements over background -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-500/5 rounded-full mix-blend-soft-light filter blur-3xl animate-float"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-500/5 rounded-full mix-blend-soft-light filter blur-3xl animate-float" style="animation-delay: -3s;"></div>
        <div class="absolute top-1/3 right-1/3 w-64 h-64 bg-purple-500/5 rounded-full mix-blend-soft-light filter blur-3xl animate-rotate-slow"></div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 animate-fade-in">
            <!-- Header -->
            <div class="text-center">
                
                <div class="mx-auto h-24 w-24 mb-4">
                    <img src="assets/images/unit_logo-removebg-preview.png" 
                         alt="ROTC Logo" 
                         class="w-full h-full object-contain rotc-logo animate-pulse-once"
                         onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='block';">
                    
                    <div id="fallback-logo" class="hidden h-24 w-24 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center shadow-2xl">
                        <div class="text-center">
                            <div class="text-white text-2xl font-bold leading-tight">ROTC</div>
                            <div class="text-white text-xs font-medium mt-1">ATTENDANCE</div>
                            <div class="text-yellow-400 text-xs font-bold">SYSTEM</div>
                        </div>
                    </div>
                </div>
                
                <!-- Hero Section -->
                <div class="mb-6">
                    <h1 class="text-4xl font-bold text-white mb-2">
                        ROTC Attendance System
                    </h1>
                    <p class="text-lg text-gray-300 mb-4">
                        BISU BALILIHAN ROTC 'BLACKNIGHT' UNIT
                    </p>
                    <div class="inline-flex items-center justify-center px-4 py-2 bg-blue-600/20 border border-blue-500/30 rounded-full text-blue-300 text-sm animate-pulse-once">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Secure Admin Portal
                    </div>
                </div>
                
                <h2 class="mt-4 text-2xl font-extrabold text-white animate-slide-down">
                    COURAGE • INTEGRITY • LOYALTY
                </h2>
                <p class="mt-2 text-sm text-gray-300">
                    Enter your credentials to access the system
                </p>
                
                <!-- Success Message -->
                <?php if ($success): ?>
                    <div class="mt-4 p-4 success-gradient rounded-lg text-green-400 animate-bounce-in border-l-4 border-green-500">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-xl mr-3"></i>
                            <div>
                                <p class="font-semibold">Success!</p>
                                <p class="text-sm mt-1"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Login Form Container -->
            <div class="form-container bg-gray-900/70 backdrop-blur-lg p-8 rounded-2xl border border-gray-700/50 shadow-2xl space-y-6">
                <!-- Error Message with Multiple Animations -->
                <?php if ($error): ?>
                    <div id="login-error-message" 
                         class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-4 rounded-lg mb-6 animate-bounce-in relative overflow-hidden group error-flash">
                        
                        <!-- Animated border effect -->
                        <div class="absolute inset-0 border-2 border-red-500/30 rounded-lg animate-pulse"></div>
                        
                        <!-- Error icon with animation -->
                        <div class="flex items-start">
                            <div class="mr-3 mt-1 animate-vibrate">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-red-300">Login Failed</p>
                                        <p class="mt-1 text-red-400/90"><?php echo htmlspecialchars($error); ?></p>
                                    </div>
                                    <button type="button" 
                                            onclick="dismissErrorMessage()" 
                                            class="text-red-300 hover:text-white ml-2 transition-colors opacity-0 group-hover:opacity-100">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Error details -->
                                <div class="mt-3 pt-3 border-t border-red-500/20">
                                    <div class="flex items-center text-xs text-red-400/70">
                                        <i class="fas fa-lightbulb mr-2"></i>
                                        <span>Tip: Check your username/email and password</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Auto-dismiss progress bar -->
                        <div class="absolute bottom-0 left-0 right-0 h-1 bg-red-500/30 overflow-hidden">
                            <div id="error-progress-bar" class="h-full bg-red-500 w-full" style="animation: progressBar 5s linear forwards;"></div>
                        </div>
                        
                        <style>
                            @keyframes progressBar {
                                0% { width: 100%; }
                                100% { width: 0%; }
                            }
                        </style>
                    </div>
                <?php endif; ?>

                <!-- Account Status Messages -->
                <?php if (isset($_SESSION['account_status'])): ?>
                    <?php if ($_SESSION['account_status'] === 'pending'): ?>
                        <div class="mt-4 p-4 bg-yellow-500/20 border border-yellow-500/30 text-yellow-400 rounded-lg animate-bounce-in border-l-4 border-yellow-500">
                            <div class="flex items-center">
                                <i class="fas fa-clock text-xl mr-3"></i>
                                <div>
                                    <p class="font-semibold">Account Pending Approval</p>
                                    <p class="text-sm mt-1">Your account is pending approval by an administrator. Please wait for approval or contact support.</p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($_SESSION['account_status'] === 'denied'): ?>
                        <div class="mt-4 p-4 bg-red-500/20 border border-red-500/30 text-red-400 rounded-lg animate-bounce-in border-l-4 border-red-500">
                            <div class="flex items-center">
                                <i class="fas fa-ban text-xl mr-3"></i>
                                <div>
                                    <p class="font-semibold">Account Denied</p>
                                    <p class="text-sm mt-1">Your account has been denied. Please contact support for assistance.</p>
                                    <?php if (isset($_SESSION['status_reason'])): ?>
                                        <p class="text-xs mt-2 italic">Reason: <?php echo htmlspecialchars($_SESSION['status_reason']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php unset($_SESSION['account_status'], $_SESSION['status_reason']); ?>
                <?php endif; ?>

                <form action="process_login.php" method="POST" id="loginForm" class="space-y-4">
                    <div class="space-y-4">
                        <!-- Username/Email Field -->
                        <div class="group">
                            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-user mr-2"></i>Username or Email
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-card text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                               <input id="username" name="username" type="text" required 
                                value="<?php echo $form_username; ?>"
                                class="pl-10 pr-3 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                placeholder="Enter username or email">
                                <div id="username-error" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                            </div>
                            <div id="username-hint" class="mt-1 text-xs text-gray-400 opacity-0 transition-opacity duration-300">
                                <i class="fas fa-info-circle mr-1"></i> Enter your registered username or email
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="group">
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-key text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                                <input id="password" name="password" type="password" required 
                                       class="pl-10 pr-10 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                       placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-blue-400 transition-colors" id="toggleIcon"></i>
                                </button>
                                <div id="password-error" class="absolute right-10 top-1/2 transform -translate-y-1/2 hidden">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                            </div>
                            <div id="password-hint" class="mt-1 text-xs text-gray-400 opacity-0 transition-opacity duration-300">
                                <i class="fas fa-info-circle mr-1"></i> Minimum 8 characters with mixed case, numbers & symbols
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember_me" name="remember_me" type="checkbox" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-600/50 rounded bg-gray-900/70 transition-all duration-300 hover:scale-110">
                                <label for="remember_me" class="ml-2 block text-sm text-gray-300">
                                    Remember me
                                </label>
                            </div>
                            <div class="text-sm">
                                <a href="#" class="font-medium text-blue-400 hover:text-blue-300 transition-colors hover:underline">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button type="submit" 
                                id="submitBtn"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:scale-105 shadow-lg overflow-hidden">
                            
                            <!-- Button shine effect -->
                            <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent group-hover:translate-x-full transition-transform duration-1000"></div>
                            
                            <!-- Loading spinner (hidden by default) -->
                            <span id="loadingSpinner" class="absolute left-0 inset-y-0 flex items-center pl-3 hidden">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>
                            
                            <!-- Default icon -->
                            <span id="defaultIcon" class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt group-hover:rotate-12 transition-transform"></i>
                            </span>
                            
                            <!-- Button text -->
                            <span id="buttonText">Sign In</span>
                            
                            <!-- Arrow icon -->
                            <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </form>

                <!-- Registration and Info Section -->
                <div class="text-center pt-4 border-t border-gray-700/50 space-y-4">
                    <div>
                        <p class="text-sm text-gray-400 mb-2">
                            New to ROTC Attendance System?
                        </p>
                        <a href="signup.php" 
                           class="group inline-flex items-center justify-center w-full py-2.5 px-4 border border-yellow-500/30 text-sm font-medium rounded-lg text-yellow-400 bg-yellow-500/10 hover:bg-yellow-500/20 transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-0.5 animate-pulse-once">
                            <i class="fas fa-user-plus mr-2 group-hover:rotate-12 transition-transform duration-300"></i>
                            Create Admin Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                toggleIcon.classList.add('text-blue-400');
                
                // Add animation
                toggleIcon.classList.add('animate-pulse-once');
                setTimeout(() => {
                    toggleIcon.classList.remove('animate-pulse-once');
                }, 2000);
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash', 'text-blue-400');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Show input hints on focus
        document.getElementById('username').addEventListener('focus', function() {
            document.getElementById('username-hint').classList.remove('opacity-0');
            document.getElementById('username-hint').classList.add('opacity-100');
        });
        
        document.getElementById('username').addEventListener('blur', function() {
            if (!this.value) {
                document.getElementById('username-hint').classList.remove('opacity-100');
                document.getElementById('username-hint').classList.add('opacity-0');
            }
        });
        
        document.getElementById('password').addEventListener('focus', function() {
            document.getElementById('password-hint').classList.remove('opacity-0');
            document.getElementById('password-hint').classList.add('opacity-100');
        });
        
        document.getElementById('password').addEventListener('blur', function() {
            if (!this.value) {
                document.getElementById('password-hint').classList.remove('opacity-100');
                document.getElementById('password-hint').classList.add('opacity-0');
            }
        });

        // Manually dismiss error message
        function dismissErrorMessage() {
            const errorMsg = document.getElementById('login-error-message');
            if (errorMsg) {
                // Store dismissal
                const errorText = errorMsg.querySelector('.error-text')?.textContent?.trim() || '';
                if (errorText) {
                    localStorage.setItem('login-error-dismissed-' + btoa(errorText), 'true');
                }
                
                // Animate out
                errorMsg.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.parentNode.removeChild(errorMsg);
                    }
                }, 300);
            }
        }

        // Check for previously dismissed messages
        function checkDismissedLoginMessages() {
            const errorMsg = document.getElementById('login-error-message');
            if (errorMsg) {
                const errorText = errorMsg.querySelector('p:nth-child(2)')?.textContent?.trim() || '';
                if (errorText) {
                    const isDismissed = localStorage.getItem('login-error-dismissed-' + btoa(errorText));
                    if (isDismissed === 'true') {
                        errorMsg.remove();
                    }
                }
            }
        }

        // Enhanced form submission with animations
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const submitBtn = document.getElementById('submitBtn');
            const buttonText = document.getElementById('buttonText');
            const defaultIcon = document.getElementById('defaultIcon');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Reset any previous error states
            document.getElementById('username').classList.remove('input-error', 'border-red-500');
            document.getElementById('password').classList.remove('input-error', 'border-red-500');
            document.getElementById('username-error').classList.add('hidden');
            document.getElementById('password-error').classList.add('hidden');
            
            // Show loading state
            submitBtn.disabled = true;
            buttonText.textContent = 'Signing In...';
            defaultIcon.classList.add('hidden');
            loadingSpinner.classList.remove('hidden');
            submitBtn.classList.remove('hover:scale-105');
            submitBtn.classList.add('opacity-90');
            
            // Validate inputs
            let hasError = false;
            
            if (!username) {
                e.preventDefault();
                hasError = true;
                showInputError('username', 'Username or email is required');
            } else if (username.includes('@')) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(username)) {
                    e.preventDefault();
                    hasError = true;
                    showInputError('username', 'Please enter a valid email address');
                }
            }
            
            if (!password) {
                e.preventDefault();
                hasError = true;
                showInputError('password', 'Password is required');
            }
            
            // If errors, reset button and show toast
            if (hasError) {
                resetSubmitButton();
                if (!username || !password) {
                    showToastMessage('Please fill in all required fields', 'error');
                }
                return;
            }
            
            // If all validations pass, form will submit
            // The button will stay in loading state until page reloads
        });

        // Show input error with animation
        function showInputError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorIcon = document.getElementById(fieldId + '-error');
            
            // Add error classes and animation
            field.classList.add('input-error', 'border-red-500');
            errorIcon.classList.remove('hidden');
            
            // Shake animation
            field.classList.add('animate-shake');
            setTimeout(() => {
                field.classList.remove('animate-shake');
            }, 500);
            
            // Show toast for specific field error
            showToastMessage(message, 'error');
        }

        // Reset submit button to original state
        function resetSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const buttonText = document.getElementById('buttonText');
            const defaultIcon = document.getElementById('defaultIcon');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            submitBtn.disabled = false;
            buttonText.textContent = 'Sign In';
            defaultIcon.classList.remove('hidden');
            loadingSpinner.classList.add('hidden');
            submitBtn.classList.add('hover:scale-105');
            submitBtn.classList.remove('opacity-90');
        }

        // Toast notification system
        let toastCounter = 0;
        let activeToasts = new Set();
        
        function showToastMessage(message, type = 'error') {
            toastCounter++;
            const toastId = 'toast-' + toastCounter;
            
            // Don't show duplicate toasts
            if (activeToasts.has(message)) return;
            activeToasts.add(message);
            
            const toastContainer = document.getElementById('toast-container');
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast-message ${type} animate-slide-down mb-3`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            const bgColor = type === 'success' ? 'bg-green-500/90' : 'bg-red-500/90';
            const borderColor = type === 'success' ? 'border-green-500/30' : 'border-red-500/30';
            const iconColor = type === 'success' ? 'text-green-300' : 'text-red-300';
            
            toast.innerHTML = `
                <div class="${bgColor} backdrop-blur-sm border ${borderColor} text-white px-4 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <div class="${iconColor} mr-3 animate-pulse-once">
                            <i class="fas ${icon} text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium">${type === 'success' ? 'Success' : 'Error'}</p>
                            <p class="text-sm opacity-90 mt-1">${message}</p>
                        </div>
                        <button type="button" onclick="dismissToast('${toastId}', '${message}')" 
                                class="ml-3 text-white/70 hover:text-white transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="mt-2 h-1 bg-white/20 rounded-full overflow-hidden">
                        <div class="h-full ${type === 'success' ? 'bg-green-300' : 'bg-red-300'} toast-progress" 
                             style="animation: toastProgress 3s linear forwards;"></div>
                    </div>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto dismiss after 3 seconds
            setTimeout(() => {
                dismissToast(toastId, message);
            }, 3000);
            
            return toastId;
        }
        
        function dismissToast(toastId, message) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                    activeToasts.delete(message);
                }, 300);
            }
        }

        // Add CSS for toast progress bar
        const style = document.createElement('style');
        style.textContent = `
            @keyframes toastProgress {
                0% { width: 100%; }
                100% { width: 0%; }
            }
            
            @keyframes fadeOut {
                0% { opacity: 1; transform: translateY(0); }
                100% { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);

        // Auto-reset button state if page reloads with error
        document.addEventListener('DOMContentLoaded', function() {
            // Check for dismissed messages
            checkDismissedLoginMessages();
            
            // Check if background image loaded successfully
            const backgroundContainer = document.querySelector('.background-container');
            if (backgroundContainer) {
                const bgImage = new Image();
                bgImage.onload = function() {
                    // Image loaded successfully
                    console.log('Background image loaded successfully');
                };
                bgImage.onerror = function() {
                    // Image failed to load - add fallback background
                    console.log('Background image failed to load, using fallback');
                    backgroundContainer.classList.add('fallback-bg');
                    backgroundContainer.style.backgroundImage = 'none';
                };
                bgImage.src = '<?php echo $background_image; ?>';
            }
            
            // Auto-dismiss error message after 5 seconds
            const errorMsg = document.getElementById('login-error-message');
            if (errorMsg) {
                setTimeout(() => {
                    if (errorMsg.parentNode) {
                        errorMsg.style.animation = 'fadeOut 0.5s ease forwards';
                        setTimeout(() => {
                            if (errorMsg.parentNode) {
                                errorMsg.parentNode.removeChild(errorMsg);
                            }
                        }, 500);
                    }
                }, 5000);
            }
            
            // Reset button if there's an error
            const submitBtn = document.querySelector('#loginForm button[type="submit"]');
            if (submitBtn && errorMsg) {
                resetSubmitButton();
            }
            
            // Add focus animation to inputs
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-500', 'ring-opacity-50');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-500', 'ring-opacity-50');
                    if (this.value.trim()) {
                        this.classList.remove('border-red-500', 'input-error');
                        const errorIcon = document.getElementById(this.id + '-error');
                        if (errorIcon) errorIcon.classList.add('hidden');
                    }
                });
            });
            
            // Smooth scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Add keypress animation for inputs
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') return;
            this.classList.add('scale-105');
            setTimeout(() => {
                this.classList.remove('scale-105');
            }, 100);
        });
        
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') return;
            this.classList.add('scale-105');
            setTimeout(() => {
                this.classList.remove('scale-105');
            }, 100);
        });
    </script>
</body>
</html>