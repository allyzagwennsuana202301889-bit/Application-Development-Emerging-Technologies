<?php
session_start();
include 'database.php';

if (!isset($_SESSION['student_id'])) {
  die("Not logged in");
}

$student_id = $_SESSION['student_id'];
$note_id = $_POST['note_id'] ?? 0;
$type = $_POST['type'] ?? '';

if (!$note_id || !$type) {
  die("Invalid request");
}

/* ===============================
   IF PUBLISHING
================================= */
if ($type === "subject") {

  // Get current note title
  $get = $conn->prepare("SELECT title FROM notes WHERE note_id=? AND student_id=?");
  $get->bind_param("ii", $note_id, $student_id);
  $get->execute();
  $res = $get->get_result();
  $row = $res->fetch_assoc();

  if ($row) {
    $title = $row['title'];

    // Instead of DELETE → downgrade other published ones
    $reset = $conn->prepare("
      UPDATE notes 
      SET type='subject_draft' 
      WHERE student_id=? 
      AND title=? 
      AND note_id != ?
    ");
    $reset->bind_param("isi", $student_id, $title, $note_id);
    $reset->execute();
  }

  //  Now publish THIS note
  $stmt = $conn->prepare("
    UPDATE notes 
    SET type='subject' 
    WHERE note_id=? AND student_id=?
  ");
  $stmt->bind_param("ii", $note_id, $student_id);
  $stmt->execute();

/* ===============================
   IF UNPUBLISHING
================================= */
} else {

  $stmt = $conn->prepare("
    UPDATE notes 
    SET type='subject_draft' 
    WHERE note_id=? AND student_id=?
  ");
  $stmt->bind_param("ii", $note_id, $student_id);
  $stmt->execute();
}

echo "updated";
?>