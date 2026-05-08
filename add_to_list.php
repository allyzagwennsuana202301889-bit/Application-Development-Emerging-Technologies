<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

$subject_id = isset($_POST['subject_id'])
    ? (int)$_POST['subject_id']
    : 0;

$source_type = $_POST['source_type'] ?? 'subjects';

if (!$student_id || !$subject_id) {
    echo "missing_data";
    exit;
}

/* CHECK IF ALREADY EXISTS */
$check = $conn->prepare("
    SELECT 1
    FROM student_subjects
    WHERE student_id = ?
      AND subject_id = ?
      AND source_type = ?
");

$check->bind_param(
    "iis",
    $student_id,
    $subject_id,
    $source_type
);

$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo "already";
    exit;
}

/* INSERT */
$stmt = $conn->prepare("
    INSERT INTO student_subjects
    (student_id, subject_id, source_type)
    VALUES (?, ?, ?)
");

$stmt->bind_param(
    "iis",
    $student_id,
    $subject_id,
    $source_type
);

if ($stmt->execute()) {
    echo "added";
} else {
    echo "error: " . $stmt->error;
}
?>