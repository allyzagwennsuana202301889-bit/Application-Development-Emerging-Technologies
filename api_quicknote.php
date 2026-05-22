<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? 0;

if (!$student_id) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$title = trim($data['title'] ?? 'Untitled');
$content = trim($data['content'] ?? '');

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Empty content']);
    exit;
}

$text_alignment = 'center';

$stmt = $conn->prepare("INSERT INTO notes (student_id, title, content, text_alignment) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $student_id, $title, $content, $text_alignment);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'note_id' => $conn->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
?>