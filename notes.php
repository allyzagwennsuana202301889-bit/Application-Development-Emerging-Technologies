<?php
session_start();
include 'database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>notes</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="bell.png" class="bells">
    <button class="back-btn" button onclick="goBack()"><img src="back.png" ></button> 
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>

    <label for="imageInput">
      <img id="preview" src="acc.png" alt="Upload Image">
    </label>

   <input type="file" id="imageInput" accept="image/*" hidden>

    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>

    <a href="homepage.php">Home</a>
    <a href="#">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

  <div class="folders">
<?php
$student_id = $_SESSION['student_id'];

$sql = "SELECT * FROM folders WHERE student_id='$student_id'";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
  echo "
    <div class='folder' onclick='openFolder(".$row['folder_id'].")'>
      <img src='folder.png'>
      <p>{$row['folder_name']}</p>
    </div>
  ";
}
?>
</div>

<?php
$folder_id = $_GET['folder_id'] ?? null;

if ($folder_id) {
  $sql = "SELECT * FROM notes 
          WHERE student_id='$student_id' 
          AND folder_id='$folder_id'";
} else {
  $sql = "SELECT * FROM notes 
          WHERE student_id='$student_id'";
}

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
  echo "
    <div class='note-card'>
      {$row['content']}
    </div>
  ";
}
?>

  <!-- BOTTOM BAR -->
  <div class="bottom-notes">
  <div class="item">
    <button onclick="addnote()"><img src="addnote.png"></button>
    <p>Add Subject</p>
  </div>

  <div class="item">
     <button onclick="upload()"><img src="uploaded.png"></button>
    <p>Uploads</p>
  </div>

  <div class="item">
   <img src="notes.png">
    <p>Add notes</p>
  </div>

</div>
  </div>

</div>

<script src="script.js"></script>
</body>
</html>