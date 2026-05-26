<?php
session_start();
include 'database.php';

$student_id  = $_SESSION['student_id'] ?? 0;
$subject_id  = isset($_POST['subject_id'])  ? (int)$_POST['subject_id']        : 0;
$source_type = isset($_POST['source_type']) ? $_POST['source_type'] : '';

if (!$student_id || !$subject_id || !in_array($source_type, ['subjects','notes'])) {
    echo "error"; exit;
}

$stmt = $conn->prepare("DELETE FROM student_subjects WHERE student_id = ? AND subject_id = ? AND source_type = ?");
$stmt->bind_param("iis", $student_id, $subject_id, $source_type);
$stmt->execute();

echo "removed";
?>