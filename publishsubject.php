<?php
session_start();
include 'database.php';

$note_id = $_POST['note_id'];

$sql = "SELECT * FROM notes WHERE note_id = $note_id";
$result = $conn->query($sql);
$note = $result->fetch_assoc();

$data = json_decode($note['content'], true);

$title = $data['title'];
$body = $data['body'];
$student_id = $note['student_id'];

/* INSERT INTO SUBJECTS */
$stmt = $conn->prepare("INSERT INTO subjects (subject_name, description, student_id, is_preset) VALUES (?, ?, ?, 0)");
$stmt->bind_param("ssi", $title, $body, $student_id);
$stmt->execute();

/* DELETE DRAFT */
$conn->query("DELETE FROM notes WHERE note_id = $note_id");

echo "success";