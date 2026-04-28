<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$note_id = $_POST['note_id'] ?? 0;

/*  get note info */
$stmt = $conn->prepare("SELECT title, content FROM notes WHERE note_id=? AND student_id=?");
$stmt->bind_param("ii", $note_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

  $title = $row['title'];
  $desc = $row['content'];

  /*insert into subjects */
  $stmt2 = $conn->prepare("INSERT INTO subjects (subject_name, description, student_id) VALUES (?, ?, ?)");
  $stmt2->bind_param("ssi", $title, $desc, $student_id);
  $stmt2->execute();

  echo "added";
} else {
  echo "error";
}
?>