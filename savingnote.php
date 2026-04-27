<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

$title   = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
$note_id = $_POST['note_id'] ?? null;

/* DEBUG */
if (!$student_id) {
  die("No session / not logged in");
}

if ($title === '' && $content === '') {
  die("Empty note not saved");
}

/* ================= SAVE ================= */
if ($note_id) {
  // UPDATE
  $stmt = $conn->prepare("UPDATE notes SET title=?, content=? WHERE note_id=? AND student_id=?");
  $stmt->bind_param("ssii", $title, $content, $note_id, $student_id);
} else {
  // INSERT
  $stmt = $conn->prepare("INSERT INTO notes (student_id, title, content) VALUES (?, ?, ?)");
  $stmt->bind_param("iss", $student_id, $title, $content);
}

if (!$stmt->execute()) {
  die("SQL ERROR: " . $stmt->error);
}

/* ================= REDIRECT ================= */
header("Location: notes.php");
exit;