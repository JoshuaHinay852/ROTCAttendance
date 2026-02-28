<?php
// test_qr.php - Place in your project root
require_once 'vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

echo "<h2>Testing QR Code Generation</h2>";

// Test 1: Check if vendor folder exists
if (file_exists('vendor/autoload.php')) {
    echo "✓ Composer autoload found<br>";
} else {
    echo "✗ Composer autoload NOT found. Run: composer install<br>";
}

// Test 2: Check if qr_codes folder is writable
$qr_dir = 'assets/qr_codes/';
if (file_exists($qr_dir)) {
    echo "✓ QR directory exists<br>";
    
    // Check if writable
    if (is_writable($qr_dir)) {
        echo "✓ QR directory is writable<br>";
    } else {
        echo "✗ QR directory is NOT writable. Check permissions.<br>";
    }
} else {
    echo "✗ QR directory doesn't exist. Create: assets/qr_codes/<br>";
}

// Test 3: Generate a test QR code
echo "<h3>Test QR Generation:</h3>";
try {
    $options = new QROptions([
        'version' => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel' => QRCode::ECC_L,
    ]);
    
    $qrcode = new QRCode($options);
    
    // Generate test QR
    $test_file = $qr_dir . 'test_qr.png';
    $qrcode->render('Test QR Code - ROTC System', $test_file);
    
    if (file_exists($test_file)) {
        echo "✓ QR Code generated successfully!<br>";
        echo "<img src='$test_file' alt='Test QR'><br>";
        echo "File: $test_file";
    } else {
        echo "✗ QR Code generation failed<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}
?>