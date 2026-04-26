<?php
session_start();
include 'database.php';

$id = $_GET['id'];

$sql = "SELECT subjects.*, student.name 
        FROM subjects 
        LEFT JOIN student ON subjects.student_id = student.student_id
        WHERE subjects.subject_id='$id'";

$result = $conn->query($sql);
$row = $result->fetch_assoc();

//  determine uploader
if ($row['is_preset'] == 1) {
  $uploader = "The Ins";
} else {
  $uploader = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Study</title>

  <!-- GOOGLE FONT -->
  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <img src="back.png" class="back-btn" onclick="goBack()">
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>

    <label for="imageInput">
      <img id="preview" src="acc.png">
    </label>

    <input type="file" id="imageInput" hidden>

    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? ''; ?></p>

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

  <!-- CONTENT -->
  <div class="subject-content">

    <!-- TOP CARD -->
    <div class="subject-top-card">
      <div class="top-left">
        <h2><?php echo $row['subject_name']; ?></h2>
        <p class="uploaded">
          Uploaded by:<br><?php echo $uploader; ?>
        </p>
      </div>

      <div class="top-right">
        <img src="chemistry.png" class="subject-icon">
        <p class="progress">67%</p>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="subject-main-card">
      <div class="text-side">
        <h3>Lesson</h3>
        <p><?php echo $row['description']; ?></p>
      </div>
    </div>

  </div>

  <!-- BOTTOM BAR -->
  <div class="bottom-file-section">
    <div class="item">
      <img src="notes.png">
      <p>Quick Note</p>
    </div>

    <div class="item">
      <button onclick="upload()"><img src="uploaded.png"></button>
      <p>Uploads</p>
    </div>

    <div class="item">
      <button onclick="quiz()"><img src="flashcards.png"></button>
      <p>Flash Cards</p>
    </div>
  </div>

</div>

<script src="script.js"></script>

</body>
</html>