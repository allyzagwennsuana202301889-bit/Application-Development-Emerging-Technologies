<?php
session_start();
include 'database.php';

if (!isset($_SESSION['student_id'])) {
  die("Not logged in");
}

$student_id = $_SESSION['student_id'];
$note_id = $_POST['note_id'] ?? 0;
$type = $_POST['type'] ?? '';

/* 🔥 If publishing → make sure only ONE exists */
if ($type === "subject") {

  // get title first
  $get = $conn->prepare("SELECT title FROM notes WHERE note_id=? AND student_id=?");
  $get->bind_param("ii", $note_id, $student_id);
  $get->execute();
  $res = $get->get_result();
  $row = $res->fetch_assoc();

  if ($row) {
    $title = $row['title'];

    // remove duplicates with same title
    $del = $conn->prepare("DELETE FROM notes 
                           WHERE student_id=? 
                           AND title=? 
                           AND type='subject'");
    $del->bind_param("is", $student_id, $title);
    $del->execute();
  }
}

/* 🔥 Now update normally */
$stmt = $conn->prepare("UPDATE notes SET type=? WHERE note_id=? AND student_id=?");
$stmt->bind_param("sii", $type, $note_id, $student_id);
$stmt->execute();

echo "updated";
?>