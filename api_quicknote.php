<?php
session_start();
header('Content-Type: application/json');
include 'database.php';

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$content = trim($input['content'] ?? '');

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Empty note']);
    exit;
}

$student_id = (int)$_SESSION['student_id'];

// Auto-title from first line
$lines = explode("\n", $content);
$title = trim($lines[0]);
if (strlen($title) > 30) $title = substr($title, 0, 30) . '...';
if (empty($title)) $title = 'Quick Note';

// Simple insert — only the columns that matter
$stmt = $conn->prepare("
    INSERT INTO notes (student_id, title, content, type, date_uploaded)
    VALUES (?, ?, ?, 'general', NOW())
");

$stmt->bind_param("iss", $student_id, $title, $content);
$stmt->execute();

echo json_encode(['success' => true, 'note_id' => $stmt->insert_id]);
$stmt->close();
?>