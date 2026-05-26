<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

$raw = $_POST['ids'] ?? '';
$ids = json_decode($raw, true);

if (!$ids || !is_array($ids) || count($ids) === 0) {
    echo "no ids";
    exit;
}

// Sanitize: keep only integers
$ids = array_map('intval', $ids);
$ids = array_filter($ids, fn($id) => $id > 0);

if (empty($ids)) {
    echo "invalid ids";
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids) + 1); // +1 for student_id

// Only delete folders belonging to this student
$sql = "DELETE FROM folders WHERE folder_id IN ($placeholders) AND student_id = ?";
$stmt = $conn->prepare($sql);

// Bind: all folder ids + student_id at the end
$params = array_merge($ids, [$student_id]);
$stmt->bind_param($types, ...$params);
$stmt->execute();

echo "deleted " . $stmt->affected_rows . " folders";
?>