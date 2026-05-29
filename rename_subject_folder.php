<?php
session_start();
include 'database.php';
$student_id = $_SESSION['student_id'] ?? 0;
$folder_id = intval($_POST['folder_id'] ?? 0);
$name = trim($_POST['folder_name'] ?? '');
if ($student_id && $folder_id && $name) {
    $stmt = $conn->prepare("UPDATE subject_folders SET folder_name=? WHERE folder_id=? AND student_id=?");
    $stmt->bind_param("sii", $name, $folder_id, $student_id);
    $stmt->execute();
}
echo 'ok';
