<?php
session_start();
include 'database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Subject</title>

  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
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
  <!-- FORM START -->

  
  <form action="insert_subject.php" method="POST">

    <!-- TOP CARD (TITLE INPUT) -->
    <div class="subject-top">

      <div class="left">
        <input 
          type="text" 
          name="subject_name" 
          placeholder="(Subject)" 
          class="subject-input"
          required
        >

        <p>
          Uploaded by:<br>
          <?php echo $_SESSION['name'] ?? 'Guest'; ?>
        </p>
      </div>

      <div class="right">
        <img src="addfile.png">
      </div>

    </div>

    <!-- MAIN CARD (DESCRIPTION INPUT) -->
    <div class="subject-body">

      <div class="subject-main-card">

  <!-- hidden file input -->
  <input type="file" id="fileInput" name="file" hidden>

  <!-- upload icon -->
  <label for="fileInput" class="upload-center">
    <img src="addfile.png">
    <p>File</p>
  </label>

  <!-- fake placeholder (NOT textarea) -->
<div 
  class="fake-desc" 
  contenteditable="true" 
  id="descBox"
>
  (Insert desc here)
</div>

<!-- hidden textarea for backend -->
<textarea name="description" id="descInput" hidden></textarea>
</div>

    </div>

    <!-- BOTTOM BAR (SUBMIT BUTTON HERE) -->
    <div class="bottom-add-section">

      <div class="item">
        <button type="submit">
          <img src="upload.png">
        </button>
        <p>Upload</p>
      </div>

      <div class="item">
        <img src="add.png">
        <p>Add</p>
      </div>

      <div class="item">
        <img src="flashcards.png">
        <p>Flash Cards</p>
      </div>

    </div>

  </form>
  <!-- FORM END -->

</div>
<script src="script.js"></script>

</body>
</html>