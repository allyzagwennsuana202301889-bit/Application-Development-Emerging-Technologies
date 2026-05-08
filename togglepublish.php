<?php
session_start();
include 'database.php';

if (!isset($_SESSION['student_id'])) {
  die("Not logged in");
}

$student_id = $_SESSION['student_id'];
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$type = $_POST['type'] ?? '';

if (!$note_id || !$type) {
  die("Invalid request");
}

/* ===============================
   PUBLISH (ONLY MARK NOTE)
================================= */
if ($type === "subject") {

  // Get note data (optional validation)
  $get = $conn->prepare("
    SELECT title, content 
    FROM notes 
    WHERE note_id=? AND student_id=?
  ");
  $get->bind_param("ii", $note_id, $student_id);
  $get->execute();
  $res = $get->get_result();
  $note = $res->fetch_assoc();

  if (!$note) {
    die("Note not found");
  }

  // Only update note type (NO INSERT INTO subjects anymore)
  $stmt = $conn->prepare("
    UPDATE notes 
    SET type='subject'
    WHERE note_id=? AND student_id=?
  ");
  $stmt->bind_param("ii", $note_id, $student_id);
  $stmt->execute();

}

/* ===============================
   UNPUBLISH
================================= */
else {

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