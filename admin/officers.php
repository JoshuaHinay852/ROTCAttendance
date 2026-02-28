<?php
// officers.php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once 'archive_functions.php';
requireAdminLogin();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message'], $_SESSION['error']);

// Function to get rank abbreviation
function getRankAbbreviation($rank) {
    $rank_abbreviations = [
        'Cadet Officer' => 'C/Off',
        'Cadet Sergeant' => 'C/Sgt',
        'Cadet Lieutenant' => 'C/Lt',
        'Cadet Captain' => 'C/Capt',
        'Cadet Major' => 'C/Maj',
        'Cadet Colonel' => 'C/Col',
        'Training Officer' => 'TO',
        'Executive Officer' => 'XO',
        'Commandant' => 'Comdt',
        'Battalion Commander' => 'BC',
        'Company Commander' => 'CC',
        'Platoon Leader' => 'PL',
        'Squad Leader' => 'SL'
    ];
    
    return isset($rank_abbreviations[$rank]) ? $rank_abbreviations[$rank] : $rank;
}

// Function to format officer name with rank abbreviation
function formatOfficerName($name, $rank, $class) {
    // Get rank abbreviation
    $rank_abbr = getRankAbbreviation($rank);
    
    // Split the full name into parts
    $name_parts = explode(' ', trim($name));
    
    if (count($name_parts) >= 3) {
        // Has first, middle, and last name
        $firstname = $name_parts[0];
        $middlename = $name_parts[1];
        $lastname = implode(' ', array_slice($name_parts, 2));
        
        // Get middle initial
        $middle_initial = strtoupper(substr($middlename, 0, 1)) . '.';
        
        // Format: RANK_ABBR FIRSTNAME MIDDLE INITIAL LASTNAME, CLASS
        $formatted_name = $rank_abbr . ' ' . $firstname . ' ' . $middle_initial . ' ' . $lastname;
    } elseif (count($name_parts) == 2) {
        // Has first and last name only
        $firstname = $name_parts[0];
        $lastname = $name_parts[1];
        
        // Format: RANK_ABBR FIRSTNAME LASTNAME, CLASS
        $formatted_name = $rank_abbr . ' ' . $firstname . ' ' . $lastname;
    } else {
        // Single name
        $formatted_name = $rank_abbr . ' ' . $name;
    }
    
    // Add class if exists
    if (!empty($class)) {
        // Convert class to abbreviation
        $class_abbr = str_replace(['1st Class', '2nd Class', '3rd Class', '4rth Class', '5th Class'], 
                                   ['1CL', '2CL', '3CL', '4CL', '5CL'], $class);
        $formatted_name .= ', ' . $class_abbr;
    }
    
    return $formatted_name;
}

// Define rank options
$rank_options = [
    'Cadet Officer',
    'Cadet Sergeant',
    'Cadet Lieutenant',
    'Cadet Captain',
    'Cadet Major',
    'Cadet Colonel',
    'Training Officer',
    'Executive Officer',
    'Commandant',
    'Battalion Commander',
    'Company Commander',
    'Platoon Leader',
    'Squad Leader'
];

// Define position options
$position_options = [
    'Battalion Commander',
    'Battalion Executive Officer',
    'Battalion Sergeant Major',
    'Company Commander',
    'Company Executive Officer',
    'Company First Sergeant',
    'Platoon Leader',
    'Platoon Sergeant',
    'Squad Leader',
    'Assistant Squad Leader',
    'Training NCO',
    'Administrative NCO',
    'Logistics NCO',
    'Operations NCO',
    'Intelligence NCO',
    'Communication NCO',
    'Medical NCO',
    'Public Affairs Officer'
];

// Define officer class options
$class_options = [
    '1st Class',
    '2nd Class',
    '3rd Class',
    '4rth Class',
    '5th Class'
];

// Enhanced file upload with background removal and compression
function uploadProfilePicture($file) {
    $target_dir = "../uploads/officers/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, GIF & WEBP files are allowed.'];
    }
    
    // Increased file size limit to 20MB
    if ($file["size"] > 20000000) { // 20MB max
        return ['success' => false, 'message' => 'File is too large. Maximum size is 20MB.'];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Compress and process image
    $result = processAndCompressImage($file["tmp_name"], $target_file, $file_extension);
    
    if ($result['success']) {
        return ['success' => true, 'filename' => $result['filename']];
    } else {
        return ['success' => false, 'message' => $result['message']];
    }
}

// New function to process and compress images
function processAndCompressImage($source, $destination, $extension) {
    try {
        // Set maximum dimensions
        $max_width = 1200;
        $max_height = 1200;
        $quality = 85; // JPEG quality (0-100)
        
        // Get image dimensions
        list($width, $height) = getimagesize($source);
        
        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Create image resource based on type
        switch($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'png':
                $image = imagecreatefrompng($source);
                break;
            case 'gif':
                $image = imagecreatefromgif($source);
                break;
            case 'webp':
                $image = imagecreatefromwebp($source);
                break;
            default:
                return ['success' => false, 'message' => 'Unsupported image type'];
        }
        
        if (!$image) {
            return ['success' => false, 'message' => 'Failed to create image resource'];
        }
        
        // Create new true color image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($extension == 'png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize image
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // Auto background removal simulation (create a soft edge)
        if ($extension == 'png') {
            // For PNG, we'll keep transparency
            $png_filename = pathinfo($destination, PATHINFO_FILENAME) . '.png';
            $png_file = dirname($destination) . '/' . $png_filename;
            imagepng($new_image, $png_file, 9); // 9 is max compression for PNG
            $final_filename = $png_filename;
        } else {
            // For other formats, save as compressed JPEG
            $jpeg_filename = pathinfo($destination, PATHINFO_FILENAME) . '.jpg';
            $jpeg_file = dirname($destination) . '/' . $jpeg_filename;
            imagejpeg($new_image, $jpeg_file, $quality);
            $final_filename = $jpeg_filename;
        }
        
        // Clean up
        imagedestroy($image);
        imagedestroy($new_image);
        
        return ['success' => true, 'filename' => $final_filename];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error processing image: ' . $e->getMessage()];
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Officer
    if (isset($_POST['add_officer'])) {
        $name = sanitize_input($_POST['name']);
        $rank = sanitize_input($_POST['rank']);
        $officer_class = sanitize_input($_POST['class'] ?? '');
        $position = sanitize_input($_POST['position']);
        $contact_number = sanitize_input($_POST['contact_number']);
        $facebook_name = sanitize_input($_POST['facebook_name']);
        $facebook_link = sanitize_input($_POST['facebook_link']);
        $email = sanitize_input($_POST['email']);
        $date_commissioned = !empty($_POST['date_commissioned']) ? sanitize_input($_POST['date_commissioned']) : null;
        $specialization = sanitize_input($_POST['specialization']);
        $bio = sanitize_input($_POST['bio']);
        $order_number = intval($_POST['order_number']);
        
        if (!in_array($officer_class, $class_options, true)) {
            $error = "Please select a valid class.";
        }
        
        // Handle profile picture upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $upload_result = uploadProfilePicture($_FILES['profile_picture']);
            if ($upload_result['success']) {
                $profile_picture = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO officers 
                (profile_picture, name, `rank`, `class`, position, contact_number, facebook_name, 
                 facebook_link, email, date_commissioned, specialization, bio, order_number, 
                 status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            
            if ($stmt->execute([
                $profile_picture, $name, $rank, $officer_class, $position, $contact_number, 
                $facebook_name, $facebook_link, $email, $date_commissioned, 
                $specialization, $bio, $order_number, $_SESSION['admin_id']
            ])) {
                $_SESSION['message'] = "Officer added successfully!";
                header('Location: officers.php');
                exit();
            } else {
                $error = "Failed to add officer. Please try again.";
            }
        }
    }
    
    // Edit Officer
    elseif (isset($_POST['edit_officer'])) {
        $id = $_POST['id'];
        $name = sanitize_input($_POST['name']);
        $rank = sanitize_input($_POST['rank']);
        $officer_class = sanitize_input($_POST['class'] ?? '');
        $position = sanitize_input($_POST['position']);
        $contact_number = sanitize_input($_POST['contact_number']);
        $facebook_name = sanitize_input($_POST['facebook_name']);
        $facebook_link = sanitize_input($_POST['facebook_link']);
        $email = sanitize_input($_POST['email']);
        $date_commissioned = !empty($_POST['date_commissioned']) ? sanitize_input($_POST['date_commissioned']) : null;
        $specialization = sanitize_input($_POST['specialization']);
        $bio = sanitize_input($_POST['bio']);
        $order_number = intval($_POST['order_number']);
        
        if (!in_array($officer_class, $class_options, true)) {
            $error = "Please select a valid class.";
        }
        
        // Get current profile picture
        $stmt = $pdo->prepare("SELECT profile_picture FROM officers WHERE id = ?");
        $stmt->execute([$id]);
        $current_picture = $stmt->fetchColumn();
        $profile_picture = $current_picture;
        
        // Handle new profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $upload_result = uploadProfilePicture($_FILES['profile_picture']);
            if ($upload_result['success']) {
                // Delete old picture if exists
                if ($current_picture && file_exists("../uploads/officers/" . $current_picture)) {
                    unlink("../uploads/officers/" . $current_picture);
                }
                $profile_picture = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        if (empty($error)) {
            $stmt = $pdo->prepare("UPDATE officers SET 
                profile_picture = ?, name = ?, `rank` = ?, `class` = ?, position = ?, contact_number = ?,
                facebook_name = ?, facebook_link = ?, email = ?, date_commissioned = ?,
                specialization = ?, bio = ?, order_number = ?, updated_by = ?
                WHERE id = ?");
            
            if ($stmt->execute([
                $profile_picture, $name, $rank, $officer_class, $position, $contact_number,
                $facebook_name, $facebook_link, $email, $date_commissioned,
                $specialization, $bio, $order_number, $_SESSION['admin_id'], $id
            ])) {
                $_SESSION['message'] = "Officer updated successfully!";
                header('Location: officers.php');
                exit();
            } else {
                $error = "Failed to update officer. Please try again.";
            }
        }
    }
    
    // Archive Officer
    elseif (isset($_POST['archive_officer'])) {
        $id = $_POST['id'];
        $reason = isset($_POST['archive_reason']) ? sanitize_input($_POST['archive_reason']) : '';
        
        $stmt = $pdo->prepare("UPDATE officers SET 
            is_archived = TRUE, 
            archived_at = NOW(), 
            archived_by = ?, 
            archive_reason = ? 
            WHERE id = ?");
        
        if ($stmt->execute([$_SESSION['admin_id'], $reason, $id])) {
            $_SESSION['message'] = "Officer archived successfully!";
        } else {
            $_SESSION['error'] = "Failed to archive officer.";
        }
        header('Location: officers.php');
        exit();
    }
    
    // Update Order
    elseif (isset($_POST['update_order'])) {
        $orders = json_decode($_POST['orders'], true);
        $success = true;
        
        foreach ($orders as $order) {
            $stmt = $pdo->prepare("UPDATE officers SET order_number = ? WHERE id = ?");
            if (!$stmt->execute([$order['order'], $order['id']])) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order']);
        }
        exit();
    }
}

// Get officer for editing
$officer = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ? AND is_archived = FALSE");
    $stmt->execute([$_GET['id']]);
    $officer = $stmt->fetch();
    if (!$officer) {
        $action = 'list';
        $error = "Officer not found.";
    }
}

// Get officer for viewing
$view_officer = null;
if ($action === 'view' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ? AND is_archived = FALSE");
    $stmt->execute([$_GET['id']]);
    $view_officer = $stmt->fetch();
    if (!$view_officer) {
        $action = 'list';
        $error = "Officer not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Directory | ROTC Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-military: #1a365d;
            --secondary-military: #2d3748;
            --accent-gold: #d4af37;
            --accent-green: #38a169;
            --accent-red: #e53e3e;
            --accent-blue: #3182ce;
            --accent-bronze: #cd7f32;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        }
        
        .header-font {
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -0.025em;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        
        .military-btn {
            background: linear-gradient(135deg, var(--primary-military) 0%, #2d3748 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .military-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .officer-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .officer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.2);
        }
        
        .officer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-military), var(--accent-gold));
        }
        
        .profile-image-container {
            width: 120px;
            height: 120px;
            margin: -40px auto 10px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #e2e8f0, #cbd5e0);
            position: relative;
            z-index: 10;
        }
        
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.5s ease;
        }
        
        .profile-image-container.default-bg {
            background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%);
            position: relative;
        }
        
        .profile-image-container.default-bg::after {
            content: '\f007';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 48px;
            color: rgba(255, 255, 255, 0.3);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        
        .officer-card:hover .profile-image {
            transform: scale(1.05);
        }
        
        .rank-badge {
            background: linear-gradient(135deg, var(--primary-military), var(--secondary-military));
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.025em;
        }
        
        .input-field {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        
        .input-field.error {
            border-color: var(--accent-red);
        }
        
        .input-field.error:focus {
            border-color: var(--accent-red);
            box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
        }
        
        .select-field {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234a5568'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 36px;
        }
        
        .select-field:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }
        
        .field-with-icon {
            position: relative;
        }
        
        .field-with-icon .field-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
            z-index: 1;
        }
        
        .field-with-icon .input-field,
        .field-with-icon .select-field {
            padding-left: 42px;
        }
        
        .field-with-icon.textarea-icon .field-icon {
            top: 12px;
            transform: none;
        }
        
        .field-with-icon.textarea-icon .input-field {
            padding-left: 42px;
        }
        
        .error-message {
            color: var(--accent-red);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            min-width: 320px;
            max-width: 400px;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast-success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.95) 0%, rgba(56, 161, 105, 0.95) 100%);
            color: white;
            border-left: 4px solid #38a169;
        }
        
        .toast-error {
            background: linear-gradient(135deg, rgba(245, 101, 101, 0.95) 0%, rgba(229, 62, 62, 0.95) 100%);
            color: white;
            border-left: 4px solid #e53e3e;
        }
        
        .toast-info {
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.95) 0%, rgba(49, 130, 206, 0.95) 100%);
            color: white;
            border-left: 4px solid #3182ce;
        }
        
        .toast-warning {
            background: linear-gradient(135deg, rgba(236, 201, 75, 0.95) 0%, rgba(214, 158, 46, 0.95) 100%);
            color: white;
            border-left: 4px solid #d69e2e;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: white;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn.edit:hover {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
        }
        
        .action-btn.view:hover {
            background: var(--accent-green);
            color: white;
            border-color: var(--accent-green);
        }
        
        .action-btn.archive:hover {
            background: var(--accent-bronze);
            color: white;
            border-color: var(--accent-bronze);
        }
        
        .drag-handle {
            cursor: move;
            cursor: grab;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .section-header {
            background: linear-gradient(90deg, var(--primary-military) 0%, var(--secondary-military) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .avatar-initial {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            background: linear-gradient(135deg, var(--primary-military) 0%, var(--accent-blue) 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card-content {
            padding: 1.5rem 1.5rem 1rem;
            flex: 1;
            text-align: center;
        }
        
        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid var(--primary-military);
            text-align: left;
        }
        
        .info-icon {
            width: 20px;
            color: var(--primary-military);
            margin-top: 2px;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-size: 0.85rem;
            font-weight: 500;
            color: #1e293b;
            word-break: break-word;
        }
        
        .info-value.empty {
            color: #94a3b8;
            font-style: italic;
        }
        
        .class-badge {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .card-footer {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .profile-image-container.view-profile {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            border-radius: 50%;
        }
        
        .bg-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .card-header-bg {
            height: 80px;
            background: linear-gradient(135deg, var(--primary-military) 0%, var(--secondary-military) 100%);
            position: relative;
        }
        
        .name-title {
            text-align: center;
            margin-bottom: 0.75rem;
            padding: 0 0.5rem;
        }
        
        .name-title h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.4;
            word-break: break-word;
        }
        
        .formatted-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.4;
            letter-spacing: -0.01em;
        }
        
        .rank-abbr {
            font-weight: 800;
            color: var(--primary-military);
        }
        
        .class-suffix {
            font-weight: 600;
            color: var(--accent-gold);
            font-size: 0.9rem;
        }
        
        .validation-hint {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .upload-progress {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            text-align: center;
        }

        .upload-progress.show {
            display: block;
        }

        .progress-bar {
            width: 300px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 15px 0 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-military), var(--accent-blue));
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'dashboard_header.php'; ?>
    
    <div id="toastContainer"></div>
    <div id="uploadProgress" class="upload-progress">
        <i class="fas fa-cloud-upload-alt text-4xl text-blue-600 mb-3"></i>
        <h3 class="font-semibold text-gray-800">Uploading Image</h3>
        <p class="text-sm text-gray-600 mb-3">Processing your image...</p>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <p class="text-xs text-gray-500" id="progressText">Compressing and optimizing</p>
    </div>
    
    <div class="main-content ml-64 min-h-screen">
        <!-- Header -->
        <header class="glass-card border-b border-gray-200 px-8 py-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-2xl header-font font-bold text-gray-900 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-yellow-600 to-amber-800 flex items-center justify-center">
                            <i class="fas fa-chess-queen text-white"></i>
                        </div>
                        <span>Officer Directory</span>
                    </h2>
                    <p class="text-gray-600 mt-1">Manage ROTC officers, their ranks, and contact information</p>
                </div>
                <div>
                    <?php if ($action === 'list'): ?>
                        <?php
                        $stats = $pdo->query("
                            SELECT COUNT(*) as total
                            FROM officers
                            WHERE is_archived = FALSE
                        ")->fetch();
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <div class="stats-card">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-users-cog text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Officers</p>
                                        <p class="text-2xl font-bold text-blue-600 leading-none mt-1"><?php echo $stats['total']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <a href="?action=add" class="military-btn flex items-center gap-2">
                                <i class="fas fa-user-plus"></i>
                                <span>Add New Officer</span>
                            </a>
                        </div>
                    <?php elseif ($action === 'view'): ?>
                        <a href="officers.php" class="military-btn flex items-center gap-2 bg-gray-700 hover:bg-gray-800">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Directory</span>
                        </a>
                    <?php else: ?>
                        <a href="officers.php" class="military-btn flex items-center gap-2 bg-gray-700 hover:bg-gray-800">
                            <i class="fas fa-arrow-left"></i>
                            <span>Cancel</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <main class="px-8 pb-8">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
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
                    <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
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
            
            <!-- View Officer Profile -->
            <?php if ($action === 'view' && $view_officer): 
                $formatted_view_name = formatOfficerName($view_officer['name'], $view_officer['rank'], $view_officer['class'] ?? '');
            ?>
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">Officer Profile</h3>
                        <p class="text-blue-100 text-sm mt-1">Detailed information and contact details</p>
                    </div>
                    
                    <div class="p-8">
                        <div class="flex flex-col md:flex-row gap-8">
                            <!-- Profile Picture -->
                            <div class="md:w-1/3">
                                <div class="profile-image-container view-profile mx-auto">
                                    <?php if ($view_officer['profile_picture'] && file_exists("../uploads/officers/" . $view_officer['profile_picture'])): ?>
                                        <img src="../uploads/officers/<?php echo htmlspecialchars($view_officer['profile_picture']); ?>" 
                                             alt="Profile" class="profile-image">
                                    <?php else: ?>
                                        <div class="w-full h-full default-bg bg-pattern">
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <span class="text-4xl font-bold text-white opacity-50">
                                                    <?php echo strtoupper(substr($view_officer['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($formatted_view_name); ?></h2>
                                    <p class="text-md text-gray-600 mt-1"><?php echo htmlspecialchars($view_officer['position']); ?></p>
                                </div>
                                
                                <div class="mt-6 flex justify-center gap-2">
                                    <a href="?action=edit&id=<?php echo $view_officer['id']; ?>" 
                                       class="action-btn edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="confirmArchive(<?php echo $view_officer['id']; ?>, '<?php echo htmlspecialchars(addslashes($view_officer['name'])); ?>')"
                                            class="action-btn archive">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Officer Details -->
                            <div class="md:w-2/3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Contact Information</h3>
                                        <div class="space-y-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-phone text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500">Contact Number</p>
                                                    <p class="font-medium"><?php echo htmlspecialchars($view_officer['contact_number']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                                    <i class="fab fa-facebook text-indigo-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500">Facebook Name</p>
                                                    <p class="font-medium"><?php echo htmlspecialchars($view_officer['facebook_name']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <?php if ($view_officer['facebook_link']): ?>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                                                    <i class="fas fa-link text-purple-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500">Facebook Link</p>
                                                    <a href="<?php echo htmlspecialchars($view_officer['facebook_link']); ?>" 
                                                       target="_blank" class="font-medium text-blue-600 hover:underline">
                                                        <?php echo htmlspecialchars($view_officer['facebook_link']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($view_officer['email']): ?>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                                    <i class="fas fa-envelope text-red-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500">Email</p>
                                                    <p class="font-medium"><?php echo htmlspecialchars($view_officer['email']); ?></p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Military Information</h3>
                                        <div class="space-y-3">
                                            <?php if ($view_officer['date_commissioned']): ?>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                                    <i class="fas fa-calendar-check text-amber-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500">Date Commissioned</p>
                                                    <p class="font-medium"><?php echo date('F j, Y', strtotime($view_officer['date_commissioned'])); ?></p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($view_officer['specialization']): ?>
                                <div class="mt-8">
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Specialization</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($view_officer['specialization'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($view_officer['bio']): ?>
                                <div class="mt-6">
                                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Biography</h3>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($view_officer['bio'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            
            <!-- Add/Edit Form -->
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <div class="glass-card rounded-xl overflow-hidden">
                    <div class="section-header">
                        <h3 class="text-lg font-semibold">
                            <?php echo $action === 'add' ? 'Add New Officer' : 'Edit Officer Information'; ?>
                        </h3>
                        <p class="text-blue-100 text-sm mt-1">
                            <?php echo $action === 'add' ? 'Fill in the officer details below' : 'Update officer information'; ?>
                        </p>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-8" id="officerForm">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $officer['id']; ?>">
                                <input type="hidden" name="edit_officer" value="1">
                            <?php else: ?>
                                <input type="hidden" name="add_officer" value="1">
                            <?php endif; ?>
                            
                            <!-- Profile Picture Upload -->
                            <div class="form-section bg-white p-6 rounded-xl border border-gray-200">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                    <i class="fas fa-camera text-blue-600"></i>
                                    Profile Picture
                                </h4>
                                
                                <div class="flex flex-col md:flex-row items-center gap-8">
                                    <div class="profile-image-container w-32 h-32">
                                        <?php if ($action === 'edit' && $officer['profile_picture'] && file_exists("../uploads/officers/" . $officer['profile_picture'])): ?>
                                            <img src="../uploads/officers/<?php echo htmlspecialchars($officer['profile_picture']); ?>" 
                                                 alt="Profile" class="profile-image" id="profilePreview">
                                        <?php else: ?>
                                            <div class="w-full h-full default-bg bg-pattern flex items-center justify-center" id="profilePreviewContainer">
                                                <i class="fas fa-user text-4xl text-white opacity-50"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Upload New Picture
                                        </label>
                                        <input type="file" name="profile_picture" id="profilePicture" 
                                               accept="image/jpeg,image/png,image/gif,image/webp"
                                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="text-xs text-gray-500 mt-2">Maximum file size: 20MB. Allowed formats: JPG, PNG, GIF, WEBP</p>
                                        <p class="text-xs text-green-600 mt-1">✓ Auto compression and optimization applied</p>
                                        <p class="text-xs text-blue-600">✓ Auto background removal simulation</p>
                                        <div id="fileSizeWarning" class="text-xs text-amber-600 mt-1 hidden">
                                            <i class="fas fa-exclamation-triangle"></i> Large file detected. Will be compressed.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="text" name="name" id="name" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['name']) : ''; ?>"
                                               class="input-field w-full pl-10"
                                               placeholder="e.g., Juan S Dela Cruz"
                                               onkeyup="validateName(this)"
                                               onblur="validateName(this)">
                                        <div class="field-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    <div id="nameError" class="error-message">Name should contain only letters and spaces</div>
                                    <p class="validation-hint">Format: Firstname Middlename Lastname (Middle initial will be extracted automatically)</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Rank <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <select name="rank" required class="select-field w-full pl-10">
                                            <option value="">Select Rank</option>
                                            <?php foreach ($rank_options as $rank_option): ?>
                                                <option value="<?php echo htmlspecialchars($rank_option); ?>"
                                                    <?php echo ($action === 'edit' && $officer['rank'] == $rank_option) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rank_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-icon">
                                            <i class="fas fa-chess-queen"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Position <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <select name="position" required class="select-field w-full pl-10">
                                            <option value="">Select Position</option>
                                            <?php foreach ($position_options as $position_option): ?>
                                                <option value="<?php echo htmlspecialchars($position_option); ?>"
                                                    <?php echo ($action === 'edit' && $officer['position'] == $position_option) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($position_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-icon">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Class <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <select name="class" required class="select-field w-full pl-10">
                                            <option value="">Select Class</option>
                                            <?php foreach ($class_options as $class_option): ?>
                                                <option value="<?php echo htmlspecialchars($class_option); ?>"
                                                    <?php echo ($action === 'edit' && ($officer['class'] ?? '') === $class_option) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="field-icon">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Number <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="tel" name="contact_number" id="contact_number" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['contact_number']) : ''; ?>"
                                               class="input-field w-full pl-10"
                                               placeholder="e.g., 09123456789"
                                               maxlength="11"
                                               onkeyup="validateContactNumber(this)"
                                               onblur="validateContactNumber(this)">
                                        <div class="field-icon">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                    </div>
                                    <div id="contactError" class="error-message">Contact number must be exactly 11 digits</div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Facebook Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="text" name="facebook_name" required
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['facebook_name']) : ''; ?>"
                                               class="input-field w-full pl-10"
                                               placeholder="e.g., Juan Dela Cruz">
                                        <div class="field-icon">
                                            <i class="fab fa-facebook"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Facebook Profile Link
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="url" name="facebook_link"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['facebook_link']) : ''; ?>"
                                               class="input-field w-full pl-10"
                                               placeholder="https://facebook.com/username">
                                        <div class="field-icon">
                                            <i class="fas fa-link"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Email Address
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="email" name="email"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['email']) : ''; ?>"
                                               class="input-field w-full pl-10"
                                               placeholder="officer@rotc.edu">
                                        <div class="field-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Date Commissioned
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="date" name="date_commissioned"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['date_commissioned']) : ''; ?>"
                                               class="input-field w-full pl-10">
                                        <div class="field-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Display Order
                                    </label>
                                    <div class="field-with-icon">
                                        <input type="number" name="order_number" min="0"
                                               value="<?php echo $action === 'edit' ? htmlspecialchars($officer['order_number']) : '0'; ?>"
                                               class="input-field w-full pl-10">
                                        <div class="field-icon">
                                            <i class="fas fa-sort-numeric-down"></i>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                                </div>
                                
                            </div>
                            
                            <!-- Additional Information -->
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Specialization / Areas of Expertise
                                    </label>
                                    <div class="field-with-icon textarea-icon">
                                        <textarea name="specialization" rows="3"
                                                  class="input-field w-full pl-10"
                                                  placeholder="e.g., Tactical Operations, Leadership Training, Physical Fitness..."><?php echo $action === 'edit' ? htmlspecialchars($officer['specialization']) : ''; ?></textarea>
                                        <div class="field-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Biography / Background
                                    </label>
                                    <div class="field-with-icon textarea-icon">
                                        <textarea name="bio" rows="4"
                                                  class="input-field w-full pl-10"
                                                  placeholder="Brief background, achievements, and qualifications..."><?php echo $action === 'edit' ? htmlspecialchars($officer['bio']) : ''; ?></textarea>
                                        <div class="field-icon">
                                            <i class="fas fa-align-left"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                                <a href="officers.php" class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                    Cancel
                                </a>
                                <button type="submit" onclick="return validateForm()" class="military-btn px-6 py-3 flex items-center gap-2">
                                    <i class="fas fa-<?php echo $action === 'add' ? 'save' : 'sync-alt'; ?>"></i>
                                    <?php echo $action === 'add' ? 'Add Officer' : 'Update Officer'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- Officers Grid View -->
            <?php else: ?>
                <!-- Search and Filter -->
                <div class="glass-card rounded-xl p-6 mb-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <input type="text" id="searchInput" placeholder="Search officers by name, rank, position, or class..." 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                        </div>
                        <div>
                            <button onclick="resetFilters()" class="px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Officers Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="officersGrid">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM officers WHERE is_archived = FALSE ORDER BY order_number ASC, name ASC");
                    $officers = $stmt->fetchAll();
                    
                    if (empty($officers)): ?>
                        <div class="col-span-full text-center py-12">
                            <div class="mx-auto w-24 h-24 mb-4 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                <i class="fas fa-users text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">No Officers Found</h3>
                            <p class="text-gray-500 max-w-md mx-auto mb-6">
                                Start by adding officers to the directory.
                            </p>
                            <a href="?action=add" class="inline-flex items-center gap-2 military-btn">
                                <i class="fas fa-user-plus"></i>
                                Add First Officer
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($officers as $officer): 
                            $formatted_name = formatOfficerName($officer['name'], $officer['rank'], $officer['class'] ?? '');
                        ?>
                            <div class="officer-card animate-fade-in" data-name="<?php echo strtolower($officer['name']); ?>" data-rank="<?php echo strtolower($officer['rank']); ?>" data-position="<?php echo strtolower($officer['position']); ?>" data-class="<?php echo strtolower($officer['class'] ?? ''); ?>">
                                <!-- Header with gradient background -->
                                <div class="card-header-bg"></div>
                                
                                <!-- Profile Image - Circular -->
                                <div class="profile-image-container <?php echo (!$officer['profile_picture'] || !file_exists("../uploads/officers/" . $officer['profile_picture'])) ? 'default-bg' : ''; ?>">
                                    <?php if ($officer['profile_picture'] && file_exists("../uploads/officers/" . $officer['profile_picture'])): ?>
                                        <img src="../uploads/officers/<?php echo htmlspecialchars($officer['profile_picture']); ?>" 
                                             alt="Profile" class="profile-image">
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Content with Formatted Name (with rank abbreviation) -->
                                <div class="card-content">
                                    <div class="name-title">
                                        <h3 class="formatted-name"><?php echo htmlspecialchars($formatted_name); ?></h3>
                                    </div>
                                    
                                    <!-- Position -->
                                    <div class="text-center mb-3">
                                        <span class="text-xs font-medium text-gray-500">POSITION</span>
                                        <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($officer['position']); ?></p>
                                    </div>
                                    
                                    <!-- Information Grid with Labels -->
                                    <div class="info-grid">
                                        <!-- Contact Number -->
                                        <div class="info-item">
                                            <i class="fas fa-phone-alt info-icon"></i>
                                            <div class="info-content">
                                                <div class="info-label">CONTACT NUMBER</div>
                                                <div class="info-value"><?php echo htmlspecialchars($officer['contact_number']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Facebook Name -->
                                        <div class="info-item">
                                            <i class="fab fa-facebook info-icon text-blue-600"></i>
                                            <div class="info-content">
                                                <div class="info-label">FACEBOOK NAME</div>
                                                <div class="info-value"><?php echo htmlspecialchars($officer['facebook_name']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <!-- Facebook Link (if exists) -->
                                        <?php if (!empty($officer['facebook_link'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-link info-icon text-purple-600"></i>
                                            <div class="info-content">
                                                <div class="info-label">FACEBOOK LINK</div>
                                                <div class="info-value truncate">
                                                    <a href="<?php echo htmlspecialchars($officer['facebook_link']); ?>" 
                                                       target="_blank" class="text-blue-600 hover:underline">
                                                        View Profile
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Email (if exists) -->
                                        <?php if (!empty($officer['email'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-envelope info-icon text-red-600"></i>
                                            <div class="info-content">
                                                <div class="info-label">EMAIL</div>
                                                <div class="info-value truncate"><?php echo htmlspecialchars($officer['email']); ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
            
                                    </div>
                                </div>
                                
                                <!-- Footer -->
                                <div class="card-footer">
                                    <div class="flex gap-2">
                                        <a href="?action=view&id=<?php echo $officer['id']; ?>" class="action-btn view" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?action=edit&id=<?php echo $officer['id']; ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="confirmArchive(<?php echo $officer['id']; ?>, '<?php echo htmlspecialchars(addslashes($officer['name'])); ?>')" 
                                                class="action-btn archive" title="Archive">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Drag to Reorder Info -->
                <div class="mt-6 text-center text-sm text-gray-500">
                    <i class="fas fa-arrows-alt mr-1"></i>
                    Drag cards to reorder (Admin only)
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Archive Confirmation Modal -->
    <div id="archiveModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 w-96 modal-content">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-archive text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Archive Officer</h3>
                        <p class="text-sm text-gray-600 mb-6" id="archiveMessage"></p>
                    </div>
                    
                    <form id="archiveForm" method="POST" onsubmit="return validateArchiveForm()" class="space-y-4">
                        <input type="hidden" name="id" id="archiveId">
                        <input type="hidden" name="archive_officer" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Archiving (Optional)
                            </label>
                            <textarea name="archive_reason" id="archiveReason" rows="3"
                                      class="input-field w-full text-sm"
                                      placeholder="Provide a reason for archiving this officer..."
                                      oninput="validateArchiveReason(this)"></textarea>
                            <div id="archiveReasonError" class="error-message">Reason must be at least 10 characters if provided</div>
                        </div>
                        
                        <div class="flex gap-3 pt-4">
                            <button type="button" onclick="closeArchiveModal()" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-colors font-medium">
                                Archive Officer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toast notification functions
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            const toastHTML = `
                <div id="${toastId}" class="toast toast-${type}">
                    <div class="toast-icon text-xl mr-3">
                        <i class="fas ${icons[type]}"></i>
                    </div>
                    <div class="toast-content text-sm font-medium flex-1">
                        ${message}
                    </div>
                    <button class="toast-close ml-3" onclick="closeToast('${toastId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            const toast = document.getElementById(toastId);
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }
        
        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }
        
        // Upload progress indicator
        function showUploadProgress() {
            document.getElementById('uploadProgress').classList.add('show');
            let progress = 0;
            const interval = setInterval(() => {
                progress += 5;
                document.getElementById('progressFill').style.width = progress + '%';
                
                if (progress <= 30) {
                    document.getElementById('progressText').innerHTML = 'Compressing image...';
                } else if (progress <= 60) {
                    document.getElementById('progressText').innerHTML = 'Optimizing quality...';
                } else if (progress <= 90) {
                    document.getElementById('progressText').innerHTML = 'Applying background removal...';
                }
                
                if (progress >= 100) {
                    clearInterval(interval);
                }
            }, 100);
            return interval;
        }
        
        function hideUploadProgress() {
            setTimeout(() => {
                document.getElementById('uploadProgress').classList.remove('show');
                document.getElementById('progressFill').style.width = '0%';
            }, 500);
        }
        
        // Name validation
        function validateName(input) {
            const nameRegex = /^[A-Za-z\s]+$/;
            const errorElement = document.getElementById('nameError');
            
            if (!nameRegex.test(input.value) && input.value.trim() !== '') {
                input.classList.add('error');
                errorElement.classList.add('show');
                return false;
            } else {
                input.classList.remove('error');
                errorElement.classList.remove('show');
                return true;
            }
        }
        
        // Contact number validation
        function validateContactNumber(input) {
            const contactRegex = /^\d{11}$/;
            const errorElement = document.getElementById('contactError');
            
            if (!contactRegex.test(input.value) && input.value.trim() !== '') {
                input.classList.add('error');
                errorElement.classList.add('show');
                return false;
            } else if (input.value.trim() !== '' && input.value.length !== 11) {
                input.classList.add('error');
                errorElement.classList.add('show');
                return false;
            } else {
                input.classList.remove('error');
                errorElement.classList.remove('show');
                return true;
            }
        }
        
        // Archive reason validation
        function validateArchiveReason(textarea) {
            const reason = textarea.value.trim();
            const errorElement = document.getElementById('archiveReasonError');
            
            if (reason !== '' && reason.length < 10) {
                textarea.classList.add('error');
                errorElement.classList.add('show');
                return false;
            } else {
                textarea.classList.remove('error');
                errorElement.classList.remove('show');
                return true;
            }
        }
        
        // Form validation
        function validateForm() {
            const nameInput = document.getElementById('name');
            const contactInput = document.getElementById('contact_number');
            
            const isNameValid = validateName(nameInput);
            const isContactValid = validateContactNumber(contactInput);
            
            // Check if name is empty
            if (nameInput.value.trim() === '') {
                showToast('Name is required', 'error');
                nameInput.classList.add('error');
                return false;
            }
            
            // Check if contact number is empty
            if (contactInput.value.trim() === '') {
                showToast('Contact number is required', 'error');
                contactInput.classList.add('error');
                return false;
            }
            
            // Check if contact number is exactly 11 digits
            if (!/^\d{11}$/.test(contactInput.value)) {
                showToast('Contact number must be exactly 11 digits', 'error');
                contactInput.classList.add('error');
                document.getElementById('contactError').classList.add('show');
                return false;
            }
            
            // Check if name contains only letters and spaces
            if (!/^[A-Za-z\s]+$/.test(nameInput.value)) {
                showToast('Name should contain only letters and spaces', 'error');
                nameInput.classList.add('error');
                document.getElementById('nameError').classList.add('show');
                return false;
            }
            
            return isNameValid && isContactValid;
        }
        
        // Archive form validation
        function validateArchiveForm() {
            const reasonTextarea = document.getElementById('archiveReason');
            const reason = reasonTextarea.value.trim();
            
            if (reason !== '' && reason.length < 10) {
                showToast('Archive reason must be at least 10 characters if provided', 'warning');
                reasonTextarea.classList.add('error');
                document.getElementById('archiveReasonError').classList.add('show');
                return false;
            }
            
            return true;
        }
        
        // Archive modal functions
        function confirmArchive(id, name) {
            document.getElementById('archiveId').value = id;
            document.getElementById('archiveMessage').innerHTML = 
                `Are you sure you want to archive <strong>${name}</strong>? This will remove them from the active directory.`;
            document.getElementById('archiveModal').classList.remove('hidden');
            
            // Reset form
            document.getElementById('archiveForm').reset();
            document.getElementById('archiveReason').classList.remove('error');
            document.getElementById('archiveReasonError').classList.remove('show');
        }
        
        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.add('hidden');
        }
        
        // Profile picture preview with compression and background removal
        document.getElementById('profilePicture')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size
                const fileSizeMB = file.size / (1024 * 1024);
                const fileSizeWarning = document.getElementById('fileSizeWarning');
                
                if (fileSizeMB > 5) {
                    fileSizeWarning.classList.remove('hidden');
                    showToast(`Large file detected (${fileSizeMB.toFixed(1)}MB). Will be compressed automatically.`, 'info');
                } else {
                    fileSizeWarning.classList.add('hidden');
                }
                
                // Show upload progress
                const progressInterval = showUploadProgress();
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Simulate processing time
                    setTimeout(() => {
                        const previewContainer = document.querySelector('.profile-image-container');
                        const existingPreview = document.getElementById('profilePreview');
                        
                        if (existingPreview) {
                            existingPreview.src = e.target.result;
                            existingPreview.classList.add('profile-image');
                        } else {
                            // Create new preview
                            const container = document.querySelector('.profile-image-container');
                            container.innerHTML = '';
                            container.classList.remove('default-bg');
                            
                            const preview = document.createElement('img');
                            preview.id = 'profilePreview';
                            preview.className = 'profile-image';
                            preview.src = e.target.result;
                            
                            container.appendChild(preview);
                        }
                        
                        // Clear progress interval and hide progress
                        clearInterval(progressInterval);
                        document.getElementById('progressFill').style.width = '100%';
                        document.getElementById('progressText').innerHTML = 'Complete!';
                        
                        setTimeout(() => {
                            hideUploadProgress();
                            showToast('Image uploaded and optimized successfully!', 'success');
                        }, 500);
                    }, 2000);
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        
        function filterOfficers() {
            const searchTerm = (searchInput?.value || '').toLowerCase();
            
            const cards = document.querySelectorAll('.officer-card');
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const rank = card.dataset.rank;
                const position = card.dataset.position;
                const officerClass = card.dataset.class || '';
                
                const matchesSearch = name.includes(searchTerm) || 
                                     rank.includes(searchTerm) || 
                                     position.includes(searchTerm) ||
                                     officerClass.includes(searchTerm);
                
                if (matchesSearch) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function resetFilters() {
            if (searchInput) {
                searchInput.value = '';
            }
            filterOfficers();
        }
        
        searchInput?.addEventListener('input', filterOfficers);
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const archiveModal = document.getElementById('archiveModal');
            if (event.target == archiveModal) {
                closeArchiveModal();
            }
        }
        
        // Initialize animations and validations
        document.addEventListener('DOMContentLoaded', function() {
            // Show success/error messages as toasts
            const successMessage = document.querySelector('.bg-green-50');
            const errorMessage = document.querySelector('.bg-red-50');
            
            if (successMessage) {
                const messageText = successMessage.querySelector('p').textContent;
                showToast(messageText, 'success');
                setTimeout(() => {
                    successMessage.remove();
                }, 3000);
            }
            
            if (errorMessage) {
                const messageText = errorMessage.querySelector('p').textContent;
                showToast(messageText, 'error');
                setTimeout(() => {
                    errorMessage.remove();
                }, 3000);
            }
            
            // Initial validation for edit form
            const nameInput = document.getElementById('name');
            const contactInput = document.getElementById('contact_number');
            
            if (nameInput && nameInput.value) {
                validateName(nameInput);
            }
            
            if (contactInput && contactInput.value) {
                validateContactNumber(contactInput);
            }
            
            // Add drag and drop reordering
            const grid = document.getElementById('officersGrid');
            if (grid && grid.children.length > 0) {
                let draggedItem = null;
                
                grid.querySelectorAll('.officer-card').forEach(card => {
                    card.setAttribute('draggable', 'true');
                    card.classList.add('cursor-move');
                    
                    card.addEventListener('dragstart', function(e) {
                        draggedItem = this;
                        this.classList.add('opacity-50');
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    
                    card.addEventListener('dragend', function(e) {
                        this.classList.remove('opacity-50');
                    });
                    
                    card.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                    });
                    
                    card.addEventListener('drop', function(e) {
                        e.preventDefault();
                        
                        if (draggedItem !== this) {
                            const items = [...grid.children];
                            const draggedIndex = items.indexOf(draggedItem);
                            const targetIndex = items.indexOf(this);
                            
                            if (draggedIndex < targetIndex) {
                                grid.insertBefore(draggedItem, this.nextSibling);
                            } else {
                                grid.insertBefore(draggedItem, this);
                            }
                            
                            // Save new order
                            saveOrder();
                        }
                    });
                });
            }
        });
        
        // Save order function
        function saveOrder() {
            const grid = document.getElementById('officersGrid');
            const items = grid.children;
            const orders = [];
            
            for (let i = 0; i < items.length; i++) {
                const viewBtn = items[i].querySelector('a[href*="view"]');
                if (viewBtn) {
                    const id = viewBtn.href.split('=').pop();
                    orders.push({
                        id: id,
                        order: i
                    });
                }
            }
            
            fetch('officers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'update_order=1&orders=' + encodeURIComponent(JSON.stringify(orders))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Order updated successfully', 'success');
                }
            });
        }
        
        // Restrict contact number input to numbers only
        document.getElementById('contact_number')?.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.charCode);
            if (!/^\d+$/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Restrict name input to letters and spaces only
        document.getElementById('name')?.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.charCode);
            if (!/^[A-Za-z\s]+$/.test(char) && char !== ' ') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>