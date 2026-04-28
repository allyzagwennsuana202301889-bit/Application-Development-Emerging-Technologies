<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? 0;

$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$type = $_POST['type'] ?? 'subject_draft';
$note_id = $_POST['note_id'] ?? null;
$subject_image = $_POST['subject_image'] ?? '';

if ($note_id) {

    if ($subject_image !== '') {
        $stmt = $conn->prepare("
            UPDATE notes 
            SET title=?, content=?, type=?, subject_image=? 
            WHERE note_id=? AND student_id=?
        ");
        $stmt->bind_param("ssssii", $title, $content, $type, $subject_image, $note_id, $student_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE notes 
            SET title=?, content=?, type=? 
            WHERE note_id=? AND student_id=?
        ");
        $stmt->bind_param("sssii", $title, $content, $type, $note_id, $student_id);
    }

    $stmt->execute();

    echo json_encode(["status" => "updated"]);

} else {

    $stmt = $conn->prepare("
        INSERT INTO notes (title, content, type, student_id, subject_image) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssis", $title, $content, $type, $student_id, $subject_image);
    $stmt->execute();

    echo json_encode([
        "status" => "created",
        "note_id" => $stmt->insert_id
    ]);
}
?>