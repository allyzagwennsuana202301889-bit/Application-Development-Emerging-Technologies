<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'];
$note_id = $_POST['note_id'];

// GET NOTE
$note = $conn->query("SELECT * FROM notes WHERE id = $note_id")->fetch_assoc();

$title = $note['title'];
$content = $note['content'];

// INSERT INTO SUBJECTS
$conn->query("INSERT INTO subjects (subject_name, description, student_id)
              VALUES ('$title', '$content', '$student_id')");

$subject_id = $conn->insert_id;

// LINK
$conn->query("INSERT INTO student_subjects (student_id, subject_id)
              VALUES ($student_id, $subject_id)");

// MARK NOTE AS UPLOADED
$conn->query("UPDATE notes SET is_uploaded = 1 WHERE id = $note_id");

header("Location: uploads.php");
?>