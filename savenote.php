<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? 0;

$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$type = $_POST['type'] ?? 'general';
$note_id = $_POST['note_id'] ?? '';

if ($note_id) {
    $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, type=? WHERE note_id=?");
    $stmt->bind_param("sssi", $title, $content, $type, $note_id);
    $stmt->execute();

    echo json_encode(["note_id" => $note_id]);
} else {
    $stmt = $conn->prepare("INSERT INTO notes (title, content, type, student_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $content, $type, $student_id);
    $stmt->execute();

    echo json_encode(["note_id" => $stmt->insert_id]);
}
?>