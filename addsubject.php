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
    <button class="back-btn" onclick="history.back()">
      <img src="back.png">
    </button> 
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
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

 
  
  <!-- TOP -->
  <div class="subject-top">
    <div class="left">
      <input 
        type="text" 
        id="subjectName"
        placeholder="(Subject)" 
        class="subject-input"
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

  <!-- BODY -->
  <div class="subject-body">
    <div class="subject-main-card">

      <div 
        class="fake-desc" 
        contenteditable="true" 
        id="descBox"
      >
        (Insert desc here)
      </div>

    </div>
  </div>

  <!-- BOTTOM -->
  <div class="bottom-add-section">

    <div class="item">
      <button type="button" onclick="upload()">
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

</div>
<script>
const subjectInput = document.getElementById("subjectName");
const descBox = document.getElementById("descBox");

/* CHECK CONTENT */
function hasContent() {
  const title = subjectInput.value.trim();
  const body = descBox.innerText.trim();

  return title !== "" || (body !== "" && body !== "(Insert desc here)");
}

/* SAVE */
function saveDraft() {
  if (!hasContent()) return Promise.resolve();

  const params = new URLSearchParams();
  params.append("title", subjectInput.value.trim());
  params.append("content", descBox.innerText.trim());
  params.append("type", "subject_draft");

  return fetch('save_note.php', {
    method: 'POST',
    body: params
  });
}

/* BACK BUTTON */
document.querySelector(".back-btn").addEventListener("click", async function (e) {
  e.preventDefault();
  await saveDraft();
  history.back();
});

/* SAVE ON EXIT */
window.addEventListener("beforeunload", function () {
  if (!hasContent()) return;

  const params = new URLSearchParams();
  params.append("title", subjectInput.value.trim());
  params.append("content", descBox.innerText.trim());
  params.append("type", "subject_draft");

  navigator.sendBeacon("savenote.php", params);
});
</script>

<script src="script.js"></script>

</body>
</html>