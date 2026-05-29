<?php
session_start();
include 'database.php';
$student_id = $_SESSION['student_id'] ?? 0;
$name = trim($_POST['folder_name'] ?? '');
if ($student_id && $name) {
    $stmt = $conn->prepare("INSERT INTO subject_folders (student_id, folder_name) VALUES (?, ?)");
    $stmt->bind_param("is", $student_id, $name);
    $stmt->execute();
    echo json_encode(['folder_id' => $conn->insert_id, 'folder_name' => $name]);
} else {
    echo json_encode(['error' => 'Invalid']);
}
