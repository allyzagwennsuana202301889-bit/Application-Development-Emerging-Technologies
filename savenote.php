<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$student_id = $_SESSION['student_id'] ?? 0;
$title = $data['title'] ?? '';
$content = $data['content'] ?? '';
$type = $data['type'] ?? 'subject_draft';
$note_id = $data['note_id'] ?? null;
$subject_image = $data['subject_image'] ?? '';

if ($subject_image === '__REMOVE__') {
    $subject_image = '';
}

$subject_image_sent = isset($data['subject_image']);

try {
    if ($note_id) {
        if ($subject_image_sent) {
            $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, type=?, subject_image=? WHERE note_id=? AND student_id=?");
            $stmt->bind_param("ssssii", $title, $content, $type, $subject_image, $note_id, $student_id);
        } else {
            $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, type=? WHERE note_id=? AND student_id=?");
            $stmt->bind_param("sssii", $title, $content, $type, $note_id, $student_id);
        }
        $stmt->execute();
        echo json_encode(["status" => "updated"]);
    } else {
        $stmt = $conn->prepare("INSERT INTO notes (title, content, type, student_id, subject_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $title, $content, $type, $student_id, $subject_image);
        $stmt->execute();
        echo json_encode(["status" => "created", "note_id" => $stmt->insert_id]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>