<?php
session_start();
include 'database.php';

$note_id    = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$student_id = $_SESSION['student_id'] ?? 0;

if (!$note_id || !$student_id) {
    echo "error: missing data";
    exit;
}

// Safety check — only delete notes owned by this student
$check = $conn->prepare("SELECT note_id FROM notes WHERE note_id = ? AND student_id = ?");
$check->bind_param("ii", $note_id, $student_id);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo "error: not authorized";
    exit;
}

// 1. Delete quiz questions first
$q1 = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
$q1->bind_param("i", $note_id);
$q1->execute();

// 2. Delete the note
$q2 = $conn->prepare("DELETE FROM notes WHERE note_id = ? AND student_id = ?");
$q2->bind_param("ii", $note_id, $student_id);
$q2->execute();

echo "deleted";
?>