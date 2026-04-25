<?php
session_start();
include 'database.php';

if (!isset($_SESSION['student_id'])) {
  die("Not logged in");
}

$student_id = $_SESSION['student_id'];
$note_id = $_POST['note_id'] ?? 0;

$stmt = $conn->prepare("DELETE FROM notes WHERE note_id=? AND student_id=?");
$stmt->bind_param("ii", $note_id, $student_id);
$stmt->execute();

/* DEBUG RESPONSE */
if ($stmt->affected_rows > 0) {
  echo "deleted";
} else {
  echo "no match";
}
?>