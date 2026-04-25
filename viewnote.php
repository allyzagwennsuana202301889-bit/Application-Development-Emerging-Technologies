<?php
session_start();
include 'database.php';

$note_id = $_GET['note_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM notes WHERE note_id=?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();
?>

<h2><?= htmlspecialchars($note['title']) ?></h2>
<p><?= nl2br(htmlspecialchars($note['content'])) ?></p>