<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    // Validate
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        $response['error'] = 'Only JPG, PNG, GIF, WEBP allowed';
        echo json_encode($response);
        exit;
    }
    
    if ($file['size'] > 2 * 1024 * 1024) {
        $response['error'] = 'File too large (max 2MB)';
        echo json_encode($response);
        exit;
    }
    
    // Read file as base64
    $image_data = file_get_contents($file['tmp_name']);
    $base64 = 'data:' . $file['type'] . ';base64,' . base64_encode($image_data);
    
    // DEBUG: Check size
    // $response['debug_size'] = strlen($base64);
    
    // Save to database
    $stmt = $conn->prepare("UPDATE student SET profile_image = ? WHERE student_id = ?");
    $stmt->bind_param("si", $base64, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['profile_image'] = $base64;
        $response['success'] = true;
        $response['image'] = $base64;
    } else {
        $response['error'] = 'Database error: ' . $stmt->error;
    }
}

echo json_encode($response);
?>