<?php
session_start();
include 'database.php';
$student_id = $_SESSION['student_id'] ?? 0;
$note_id = intval($_POST['note_id'] ?? 0);
$folder_id = $_POST['folder_id'] === 'null' ? null : intval($_POST['folder_id']);
if ($student_id && $note_id) {
    $stmt = $conn->prepare("UPDATE notes SET subject_folder_id=? WHERE note_id=? AND student_id=?");
    $stmt->bind_param("iii", $folder_id, $note_id, $student_id);
    $stmt->execute();
}
echo 'ok';
