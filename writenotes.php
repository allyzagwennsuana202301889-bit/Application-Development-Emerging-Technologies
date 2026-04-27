<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Note</title>
<link rel="stylesheet" href="style2.css">
</head>

<body>

<div class="container">

<form method="POST" action="savingnote.php">

<input type="hidden" name="note_id" value="">

<!-- TOP -->
<div class="top-bar">
  <div class="back-btn" onclick="history.back()">←</div>
  <input type="text" name="title" class="title-input" placeholder="(Insert title here)">
</div>

<!-- CONTENT -->
<div class="content">
  <textarea name="content" class="text-area" placeholder="(insert text here)"></textarea>
</div>

<!-- BOTTOM -->
<div class="bottom-bar">
  <button onclick="noting()">✔</button>
  <p>Save Note</p>
</div>

</form>

</div>

</body>
</html>