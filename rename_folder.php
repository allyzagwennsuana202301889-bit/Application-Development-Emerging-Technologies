<?php
include 'database.php';

if(!isset($_POST['folder_id']) || !isset($_POST['folder_name'])){
    echo "error";
    exit;
}

$id = intval($_POST['folder_id']);
$name = $conn->real_escape_string($_POST['folder_name']);

$sql = "UPDATE folders SET folder_name='$name' WHERE folder_id=$id";

if($conn->query($sql)){
    echo "success";
} else {
    echo "error: " . $conn->error;
}
?>