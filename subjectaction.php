<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'];
$subject_id = $_POST['subject_id'];

$sql = "INSERT INTO student_subjects (student_id, subject_id)
        VALUES ($student_id, $subject_id)";

$conn->query($sql);
?>