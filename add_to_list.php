<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

if (!$student_id || !$note_id) {
    echo "missing_data";
    exit;
}

// Check if already exists
$check = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
$check->bind_param("ii", $student_id, $note_id);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo "already";
    exit;
}

// Insert into student's list
$stmt = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
$stmt->bind_param("ii", $student_id, $note_id);

if ($stmt->execute()) {
    echo "added";
} else {
    echo "error: " . $conn->error;
}
?>