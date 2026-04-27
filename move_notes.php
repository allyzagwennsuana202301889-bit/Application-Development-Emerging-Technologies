<?php
include 'database.php';

if(!isset($_POST['ids']) || !isset($_POST['folder_id'])){
    echo "error: missing data";
    exit;
}

$ids = json_decode($_POST['ids'], true);
$folder_id_raw = $_POST['folder_id'];

if(!$ids){
    echo "error: invalid ids";
    exit;
}

foreach($ids as $id){
    $id = intval($id);

    // 🔥 handle "remove from folder"
    if($folder_id_raw === "NULL"){
        $sql = "UPDATE notes SET folder_id = NULL WHERE note_id = $id";
    } else {
        $folder_id = intval($folder_id_raw);
        $sql = "UPDATE notes SET folder_id = $folder_id WHERE note_id = $id";
    }

    if(!$conn->query($sql)){
        echo "error: " . $conn->error;
        exit;
    }
}

echo "success";
?>