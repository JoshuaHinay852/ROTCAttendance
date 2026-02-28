<?php
require_once 'config/database.php';
redirect_if_logged_in();

$error = '';
$success = '';

// Check for success message from query parameter (e.g., from login redirect)
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize_input($_POST['full_name']);

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = "All fields are required";
    } elseif (!validate_email($email)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $full_name)) {
        $error = "Full name should contain only letters and spaces";
    } else {
        $password_errors = validate_password($password);
        if (!empty($password_errors)) {
            $error = implode("<br>", $password_errors);
        } else {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                $error = "Username or email already exists";
            } else {
                // Hash password and insert
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
                    // Set success message for redirection
                    $success = "Registration successful! You can now login.";
                    // Store in session for persistence across redirect
                    $_SESSION['registration_success'] = $success;
                    header('Location: login.php?success=' . urlencode($success));
                    exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

// Path to your background image - change this to your actual image path
$background_image = 'assets/images/IMG_0072.JPG'; // Change this to your image path
$fallback_background = 'bg-gradient-to-br from-gray-900 via-blue-900 to-purple-900';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration | ROTC Attendance System</title>
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
        
        /* Animated elements over background */
        .animated-bg-element {
            position: absolute;
            mix-blend-mode: soft-light;
            filter: blur(3xl);
        }
        
        .password-strength {
            height: 4px;
            transition: all 0.3s ease;
        }
        .strength-0 { width: 0%; background-color: #ef4444; }
        .strength-1 { width: 25%; background-color: #f97316; }
        .strength-2 { width: 50%; background-color: #eab308; }
        .strength-3 { width: 75%; background-color: #3b82f6; }
        .strength-4 { width: 100%; background-color: #10b981; }
        
        .input-validation {
            transition: all 0.3s ease;
        }
        .input-valid {
            border-color: #10b981 !important;
        }
        .input-invalid {
            border-color: #ef4444 !important;
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
        
        /* Animation for background elements */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.1; }
            50% { opacity: 0.2; }
        }
        
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        .animate-pulse {
            animation: pulse 4s ease-in-out infinite;
        }
        
        .animate-spin-slow {
            animation: spin-slow 20s linear infinite;
        }
        
        /* Auto-dismiss animation for messages */
        .auto-dismiss {
            animation: fadeOutHide 0.5s ease 5s forwards;
            animation-fill-mode: forwards;
        }
        
        @keyframes fadeOutHide {
            0% { opacity: 1; visibility: visible; }
            99% { opacity: 0; visibility: visible; }
            100% { opacity: 0; visibility: hidden; height: 0; margin: 0; padding: 0; border: 0; }
        }
        
        /* Toast notification styles */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            max-width: 400px;
        }
        
        .toast-message {
            background: rgba(239, 68, 68, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-left: 4px solid #ef4444;
            color: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.2);
            margin-bottom: 10px;
            transform: translateX(400px);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        
        /* Success toast style */
        .toast-message.success {
            background: rgba(16, 185, 129, 0.95);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-left: 4px solid #10b981;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2);
        }
        
        .toast-message.show {
            transform: translateX(0);
        }
        
        .toast-message.hide {
            transform: translateX(400px);
        }
        
        .toast-content {
            display: flex;
            align-items: flex-start;
        }
        
        .toast-icon {
            margin-right: 12px;
            font-size: 20px;
            margin-top: 2px;
        }
        
        .toast-text {
            flex: 1;
        }
        
        .toast-close {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
            padding: 0;
            transition: color 0.2s;
        }
        
        .toast-close:hover {
            color: white;
        }
        
        /* Success message style */
        .success-message {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        
        /* Fallback background class */
        .fallback-bg {
            background: <?php echo $fallback_background; ?>;
        }
        
        /* Form container styling */
        .form-container {
            background: rgba(31, 41, 55, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(75, 85, 99, 0.3);
        }
    </style>
</head>
<body class="min-h-screen font-sans overflow-x-hidden">
    <!-- Background Image Container -->
    <div class="background-container">
        <div class="background-overlay"></div>
        <!-- Animated Background Elements -->
        <div class="animated-bg-element top-10 left-10 w-64 h-64 bg-purple-500/10 rounded-full animate-float"></div>
        <div class="animated-bg-element bottom-10 right-10 w-64 h-64 bg-blue-500/10 rounded-full animate-pulse"></div>
        <div class="animated-bg-element top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-500/5 rounded-full animate-spin-slow"></div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-notification"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8 animate-fade-in">
            <!-- Header -->
            <div class="text-center">
                <!-- Your Logo Picture -->
                <div class="mx-auto mb-4 animate-bounce">
                    <img src="assets/images/unit_logo-removebg-preview.png" 
                         alt="ROTC Logo" 
                         class="w-24 h-24 mx-auto object-contain rotc-logo"
                         onerror="this.style.display='none'; document.getElementById('fallback-logo').style.display='block';">
                    
                    <div id="fallback-logo" class="hidden w-24 h-24 mx-auto rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center shadow-2xl">
                        <div class="text-center">
                            <div class="text-white text-xl font-bold leading-tight">ROTC</div>
                            <div class="text-white text-xs font-medium mt-1">ATTENDANCE</div>
                            <div class="text-yellow-400 text-xs font-bold">SYSTEM</div>
                        </div>
                    </div>
                </div>
                <h2 class="mt-2 text-4xl font-extrabold text-white">
                    Admin Registration
                </h2>
                <p class="mt-2 text-lg text-gray-300">
                    Create your administrator account
                </p>
                <div class="mt-4 flex justify-center space-x-4">
                    <div class="flex items-center text-sm text-gray-400">
                        <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                        <span>Military-grade security</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-400">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mr-2 animate-pulse" style="animation-delay: 0.2s"></div>
                        <span>Encrypted data</span>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="form-container backdrop-blur-md p-8 rounded-2xl shadow-2xl">
                <?php if ($error): ?>
                    <!-- Error Message with 5-second auto-dismiss -->
                    <div id="error-message" class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg animate-shake mb-6 auto-dismiss relative" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                        <span class="error-text"><?php echo htmlspecialchars($error); ?></span>
                        <!-- Optional: Manual dismiss button -->
                        <button type="button" onclick="dismissErrorMessage()" class="absolute top-3 right-3 text-red-300 hover:text-white focus:outline-none">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <!-- Success Message with 5-second auto-dismiss -->
                    <div id="success-message" class="success-message border border-green-500/30 text-green-400 px-4 py-3 rounded-lg mb-6 auto-dismiss relative" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> 
                        <span class="success-text"><?php echo htmlspecialchars($success); ?></span>
                        <!-- Optional: Manual dismiss button -->
                        <button type="button" onclick="dismissSuccessMessage()" class="absolute top-3 right-3 text-green-300 hover:text-white focus:outline-none">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <form action="signup.php" method="POST" id="signupForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div class="group">
                            <label for="full_name" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-id-badge mr-2"></i>Full Name
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                                <input id="full_name" name="full_name" type="text" required 
                                       class="pl-10 pr-3 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                       placeholder="Enter your full name (letters only)"
                                       oninput="validateFullName(this)"
                                       onkeypress="return validateFullNameKeypress(event)">
                            </div>
                            <div class="text-xs text-gray-400 mt-1 validation-message" id="full_name_message"></div>
                        </div>

                        <!-- Username -->
                        <div class="group">
                            <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-at mr-2"></i>Username
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user-circle text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                                <input id="username" name="username" type="text" required 
                                       class="pl-10 pr-3 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                       placeholder="Choose a username"
                                       oninput="validateInput(this, 'username')">
                            </div>
                            <div class="text-xs text-gray-400 mt-1 validation-message"></div>
                        </div>

                        <!-- Email -->
                        <div class="group">
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                                <input id="email" name="email" type="email" required 
                                       class="pl-10 pr-3 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                       placeholder="Enter your email"
                                       oninput="validateInput(this, 'email')">
                            </div>
                            <div class="text-xs text-gray-400 mt-1 validation-message"></div>
                        </div>

                        <!-- Password -->
                        <div class="group">
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-key mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                                </div>
                                <input id="password" name="password" type="password" required 
                                       class="pl-10 pr-10 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                       placeholder="Create strong password"
                                       oninput="validatePassword()">
                                <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-blue-400 transition-colors" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="password-strength mb-1 rounded-full" id="passwordStrength"></div>
                                <div class="text-xs text-gray-400" id="passwordRequirements">
                                    Must contain: 8+ chars, uppercase, lowercase, number, special char
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password - Full Width -->
                    <div class="group">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-key mr-2"></i>Confirm Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400 group-focus-within:text-blue-400 transition-colors"></i>
                            </div>
                            <input id="confirm_password" name="confirm_password" type="password" required 
                                   class="pl-10 pr-10 py-3 w-full bg-gray-900/70 border border-gray-600/50 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300"
                                   placeholder="Confirm your password"
                                   oninput="validateConfirmPassword()">
                            <button type="button" onclick="togglePassword('confirm_password')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-eye text-gray-400 hover:text-blue-400 transition-colors" id="toggleConfirmIcon"></i>
                            </button>
                        </div>
                        <div class="text-xs text-gray-400 mt-2" id="confirmMessage"></div>
                    </div>

                    <!-- Terms -->
                    <div class="flex items-center">
                        <input id="terms" name="terms" type="checkbox" required 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-600/50 rounded bg-gray-900/70">
                        <label for="terms" class="ml-2 block text-sm text-gray-300">
                            I agree to the 
                            <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">Terms of Service</a>
                             and 
                            <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">Privacy Policy</a>
                        </label>
                    </div>

                    <!-- Submit Button - Changed to Blue Color -->
                    <div class="pt-4">
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:scale-105 shadow-xl">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-user-plus group-hover:rotate-90 transition-transform duration-500"></i>
                            </span>
                            Create Administrator Account
                            <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform"></i>
                        </button>
                    </div>

                    <!-- Login Link -->
                    <div class="text-center pt-6 border-t border-gray-700/50">
                        <p class="text-sm text-gray-400 mb-4">
                            Already have an account? 
                            <a href="login.php" class="font-medium text-yellow-400 hover:text-yellow-300 transition-colors animate-pulse">
                                Sign in here
                            </a>
                        </p>
                        
                        <!-- Back to Home Button (Added below login link) -->
                        <a href="index.php" 
                           class="group inline-flex items-center justify-center w-full py-2.5 px-4 border border-gray-700/50 text-sm font-medium rounded-lg text-gray-300 bg-gray-900/70 hover:bg-gray-800 hover:text-white transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-0.5">
                            <i class="fas fa-home mr-2 group-hover:rotate-12 transition-transform duration-300"></i>
                            Back to Homepage
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Check if background image loaded successfully
        document.addEventListener('DOMContentLoaded', function() {
            const backgroundContainer = document.querySelector('.background-container');
            if (backgroundContainer) {
                const bgImage = new Image();
                bgImage.onload = function() {
                    console.log('Background image loaded successfully');
                };
                bgImage.onerror = function() {
                    console.log('Background image failed to load, using fallback');
                    backgroundContainer.classList.add('fallback-bg');
                    backgroundContainer.style.backgroundImage = 'none';
                };
                bgImage.src = '<?php echo $background_image; ?>';
            }
            
            // Rest of your existing DOMContentLoaded code...
            checkDismissedMessages();
            
            const errorMsg = document.getElementById('error-message');
            if (errorMsg && !CSS.supports('animation-fill-mode', 'forwards')) {
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            const successMsg = document.getElementById('success-message');
            if (successMsg && !CSS.supports('animation-fill-mode', 'forwards')) {
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Clear localStorage for old dismissed messages (optional cleanup)
            const oneDayAgo = Date.now() - (24 * 60 * 60 * 1000);
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key.startsWith('error-dismissed-') || key.startsWith('success-dismissed-')) {
                    const timestamp = localStorage.getItem(key + '-timestamp');
                    if (timestamp && parseInt(timestamp) < oneDayAgo) {
                        localStorage.removeItem(key);
                        localStorage.removeItem(key + '-timestamp');
                    }
                }
            }
            
            // Smooth scroll to top when page loads
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId === 'password' ? 'togglePasswordIcon' : 'toggleConfirmIcon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password)) strength++;
            
            return strength;
        }

        function validatePassword() {
            const password = document.getElementById('password').value;
            const strength = checkPasswordStrength(password);
            const strengthBar = document.getElementById('passwordStrength');
            const requirements = document.getElementById('passwordRequirements');
            
            strengthBar.className = 'password-strength mb-1 rounded-full strength-' + strength;
            
            if (strength === 0) {
                requirements.innerHTML = 'Must contain: 8+ chars, uppercase, lowercase, number, special char';
                requirements.className = 'text-xs text-gray-400';
            } else if (strength <= 2) {
                requirements.innerHTML = 'Weak password. Add more character types.';
                requirements.className = 'text-xs text-red-400';
            } else if (strength <= 4) {
                requirements.innerHTML = 'Moderate password. Could be stronger.';
                requirements.className = 'text-xs text-yellow-400';
            } else {
                requirements.innerHTML = 'Strong password! Good job.';
                requirements.className = 'text-xs text-green-400';
            }
            
            validateConfirmPassword();
        }

        function validateConfirmPassword() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const message = document.getElementById('confirmMessage');
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirm === '') {
                message.innerHTML = 'Please confirm your password';
                message.className = 'text-xs text-gray-400';
                confirmInput.classList.remove('input-valid', 'input-invalid');
                return;
            }
            
            if (password === confirm) {
                message.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Passwords match';
                message.className = 'text-xs text-green-400';
                confirmInput.classList.add('input-valid');
                confirmInput.classList.remove('input-invalid');
            } else {
                message.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Passwords do not match';
                message.className = 'text-xs text-red-400';
                confirmInput.classList.add('input-invalid');
                confirmInput.classList.remove('input-valid');
            }
        }

        // Full name validation - Letters only
        function validateFullName(input) {
            const messageDiv = document.getElementById('full_name_message');
            const value = input.value.trim();
            
            if (value === '') {
                input.classList.remove('input-valid', 'input-invalid');
                messageDiv.innerHTML = '';
                return;
            }
            
            // Check if contains only letters and spaces
            const isValid = /^[a-zA-Z\s]+$/.test(value) && value.length >= 2;
            
            if (isValid) {
                input.classList.add('input-valid');
                input.classList.remove('input-invalid');
                messageDiv.className = 'text-xs text-green-400 mt-1';
                messageDiv.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Valid name';
            } else {
                input.classList.add('input-invalid');
                input.classList.remove('input-valid');
                messageDiv.className = 'text-xs text-red-400 mt-1';
                if (value.length < 2) {
                    messageDiv.innerHTML = '<i class="fas fa-times-circle mr-1"></i> At least 2 characters required';
                } else {
                    messageDiv.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Only letters and spaces allowed';
                }
            }
        }

        // Prevent typing invalid characters in full name field
        function validateFullNameKeypress(event) {
            const char = String.fromCharCode(event.which || event.keyCode);
            
            // Allow only letters (a-z, A-Z) and space
            if (!/^[a-zA-Z\s]$/.test(char)) {
                event.preventDefault();
                return false;
            }
            return true;
        }

        // Input validation for other fields
        function validateInput(input, type = 'text') {
            const messageDiv = input.parentElement.nextElementSibling;
            
            if (input.value.trim() === '') {
                input.classList.remove('input-valid', 'input-invalid');
                messageDiv.innerHTML = '';
                return;
            }
            
            let isValid = false;
            let message = '';
            
            switch(type) {
                case 'email':
                    isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
                    message = isValid ? 'Valid email format' : 'Please enter a valid email address';
                    break;
                case 'username':
                    isValid = /^[a-zA-Z0-9_]{3,20}$/.test(input.value);
                    message = isValid ? 'Valid username' : '3-20 characters, letters, numbers, underscores only';
                    break;
            }
            
            if (isValid) {
                input.classList.add('input-valid');
                input.classList.remove('input-invalid');
                messageDiv.className = 'text-xs text-green-400 mt-1';
            } else {
                input.classList.add('input-invalid');
                input.classList.remove('input-valid');
                messageDiv.className = 'text-xs text-red-400 mt-1';
            }
            
            messageDiv.innerHTML = message;
        }

        // Toast notification system
        let toastCounter = 0;
        
        function showToastMessage(message, type = 'error') {
            toastCounter++;
            const toastId = 'toast-' + toastCounter;
            const toastContainer = document.getElementById('toast-container');
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = 'toast-message ' + (type === 'success' ? 'success' : '');
            
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <div class="toast-icon">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="toast-text">
                        ${message}
                    </div>
                    <button class="toast-close" onclick="dismissToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                dismissToast(toastId);
            }, 5000);
            
            return toastId;
        }
        
        function dismissToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.remove('show');
                toast.classList.add('hide');
                
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 400);
            }
        }
        
        // Function to manually dismiss error message
        function dismissErrorMessage() {
            const errorMsg = document.getElementById('error-message');
            if (errorMsg) {
                const errorText = errorMsg.querySelector('.error-text').textContent.trim();
                localStorage.setItem('error-dismissed-' + btoa(errorText), 'true');
                
                errorMsg.style.animation = 'none';
                errorMsg.style.opacity = '0';
                errorMsg.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    errorMsg.style.display = 'none';
                }, 300);
            }
        }
        
        // Function to manually dismiss success message
        function dismissSuccessMessage() {
            const successMsg = document.getElementById('success-message');
            if (successMsg) {
                const successText = successMsg.querySelector('.success-text').textContent.trim();
                localStorage.setItem('success-dismissed-' + btoa(successText), 'true');
                
                successMsg.style.animation = 'none';
                successMsg.style.opacity = '0';
                successMsg.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    successMsg.style.display = 'none';
                }, 300);
            }
        }

        // Check localStorage for previously dismissed messages
        function checkDismissedMessages() {
            const errorMsg = document.getElementById('error-message');
            if (errorMsg) {
                const errorText = errorMsg.querySelector('.error-text').textContent.trim();
                const isDismissed = localStorage.getItem('error-dismissed-' + btoa(errorText));
                
                if (isDismissed === 'true') {
                    errorMsg.remove();
                }
            }
            
            const successMsg = document.getElementById('success-message');
            if (successMsg) {
                const successText = successMsg.querySelector('.success-text').textContent.trim();
                const isDismissed = localStorage.getItem('success-dismissed-' + btoa(successText));
                
                if (isDismissed === 'true') {
                    successMsg.remove();
                }
            }
        }

        // Form submission validation (replaced alerts with toast notifications)
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const full_name = document.getElementById('full_name').value;
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            // Check full name
            if (!/^[a-zA-Z\s]+$/.test(full_name) || full_name.trim().length < 2) {
                e.preventDefault();
                showToastMessage('Full name should contain only letters and spaces (minimum 2 characters)');
                document.getElementById('full_name').classList.add('animate-shake');
                setTimeout(() => {
                    document.getElementById('full_name').classList.remove('animate-shake');
                }, 500);
                return;
            }
            
            // Check terms
            if (!terms) {
                e.preventDefault();
                showToastMessage('You must agree to the terms and conditions');
                return;
            }
            
            // Check password match
            if (password !== confirm) {
                e.preventDefault();
                showToastMessage('Passwords do not match');
                document.getElementById('confirm_password').classList.add('animate-shake');
                setTimeout(() => {
                    document.getElementById('confirm_password').classList.remove('animate-shake');
                }, 500);
                return;
            }
            
            const strength = checkPasswordStrength(password);
            if (strength < 4) {
                e.preventDefault();
                showToastMessage('Please use a stronger password (include uppercase, lowercase, numbers, and special characters)');
                return;
            }
        });

        // Animate form inputs on focus
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.classList.add('ring-2', 'ring-blue-500', 'rounded-lg');
            });
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.classList.remove('ring-2', 'ring-blue-500', 'rounded-lg');
            });
        });
    </script>
</body>
</html>