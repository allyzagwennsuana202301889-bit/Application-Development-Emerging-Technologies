<?php
session_start();
include 'database.php';
$student_id = $_SESSION['student_id'] ?? 0;
$folder_id = intval($_POST['folder_id'] ?? 0);
if ($student_id && $folder_id) {
    // Unlink notes from folder first
    $stmt = $conn->prepare("UPDATE notes SET subject_folder_id=NULL WHERE subject_folder_id=? AND student_id=?");
    $stmt->bind_param("ii", $folder_id, $student_id);
    $stmt->execute();
    // Delete folder
    $stmt2 = $conn->prepare("DELETE FROM subject_folders WHERE folder_id=? AND student_id=?");
    $stmt2->bind_param("ii", $folder_id, $student_id);
    $stmt2->execute();
}
echo 'ok';
