<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? 0;
$folder_name = $_POST['folder_name'] ?? '';

if (!$student_id || empty($folder_name)) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// OPTIONAL: prevent duplicate folder names
$check = $conn->prepare("SELECT * FROM folders WHERE student_id=? AND folder_name=?");
$check->bind_param("is", $student_id, $folder_name);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Folder already exists"]);
    exit;
}

// INSERT folder
$stmt = $conn->prepare("INSERT INTO folders (folder_name, student_id) VALUES (?, ?)");
$stmt->bind_param("si", $folder_name, $student_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "folder_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>