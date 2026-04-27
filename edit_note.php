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

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" href="style2.css">
</head>
<body>
<div class ="container">

<form method="POST" action="savingnote.php">

<input type="hidden" name="note_id" value="<?= $note_id ?>">

<div class="top-bar">
  <button type="button" onclick="history.back()">←</button>
  <input type="text" name="title" value="<?= htmlspecialchars($note['title'] ?? '') ?>" placeholder="Insert title here">
</div>

<div class="editor">
  <textarea name="content"><?= htmlspecialchars($note['content']) ?></textarea>
</div>

<div class="bottom">
  <button type="submit">Save</button>
</div>

</form>
</div>

</body>
</html>