<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

$ids = json_decode($_POST['ids'], true);

if (!$ids || !is_array($ids)) {
  exit("No IDs");
}

$id_list = implode(',', array_map('intval', $ids));

$sql = "DELETE FROM notes 
        WHERE note_id IN ($id_list) 
        AND student_id = $student_id";

if ($conn->query($sql)) {
  echo "Deleted";
} else {
  echo "Error: " . $conn->error;
}