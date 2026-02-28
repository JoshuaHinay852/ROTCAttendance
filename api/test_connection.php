<?php
// Ultra simple test - no includes, no database
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Simple test working!',
    'time' => date('Y-m-d H:i:s')
]);
?>