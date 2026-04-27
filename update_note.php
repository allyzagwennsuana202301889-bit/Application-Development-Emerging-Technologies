<?php
include 'database.php';

if(!isset($_POST['note_id']) || !isset($_POST['content'])){
    echo "error";
    exit;
}

$id = intval($_POST['note_id']);
$content = $conn->real_escape_string($_POST['content']);

$sql = "UPDATE notes SET content='$content' WHERE note_id=$id";

if($conn->query($sql)){
    echo "success";
} else {
    echo "error: " . $conn->error;
}
?>