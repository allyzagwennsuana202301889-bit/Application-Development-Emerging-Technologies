<?php
include 'database.php';

$id = $_POST['folder_id'] ?? 0;

$conn->query("DELETE FROM folders WHERE folder_id=$id");
$conn->query("DELETE FROM notes WHERE folder_id=$id"); // optional cascade

echo "deleted";