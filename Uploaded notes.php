<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

/*  GET ALL SUBJECT DRAFTS  */
$sql = "SELECT * FROM notes 
        WHERE student_id = ? 
        AND type IN ('subject_draft', 'subject')
        ORDER BY note_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Subject</title>
  <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
   <img src="bell.png" class="bell">
  </nav>

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

 <div class="folders">
  <?php
  $student_id = $_SESSION['student_id'];

  $folder_sql = "SELECT * FROM folders WHERE student_id=?";
  $stmt = $conn->prepare($folder_sql);
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $folder_result = $stmt->get_result();

  while ($row = $folder_result->fetch_assoc()) {
    echo "
      <div class='folder' onclick='openFolder(".$row['folder_id'].")'>
        <img src='folder.png'>
        <p>{$row['folder_name']}</p>
      </div>
    ";
  }
  ?>
  </div>

  <!--  NOTES SCROLL AREA -->
  <div class="drafts-container">

<?php
if ($result->num_rows === 0) {
  echo "<p style='padding:15px;'>No drafts found.</p>";
} else {
  while ($draft = $result->fetch_assoc()) {

    $title = $draft['title'] ?? 'Untitled';
    $desc  = $draft['content'] ?? '';
    $author = $_SESSION['name'] ?? 'User';

    echo "
<div class='draft-card'>

  <!-- TOP RIGHT DOWNLOAD -->
  <img src='offlinemode.png' class='draft-download'>

  <div class='draft-content'>

    <!-- LEFT SIDE -->
    <div class='draft-left'>
      <h3>" . htmlspecialchars($title) . "</h3>

      <p class='draft-author'>
        Uploaded by:<br>" . htmlspecialchars($author) . "
      </p>

      <div class='draft-left-btns'>
  <button onclick='readNote(" . $draft['note_id'] . ")' class='btn-read'>Read</button>
  <button onclick='deleteNote(" . $draft['note_id'] . ")' class='btn-delete'>Delete</button>
</div>
    </div>

    <!-- RIGHT SIDE -->
    <div class='draft-right'>
      <img src='subject-icon.png' class='draft-icon'>

     <button onclick='togglePublish(" . $draft['note_id'] . ", \"" . $draft['type'] . "\")' class='btn-unpublish'>
  " . ($draft['type'] === 'subject' ? 'Unpublish' : 'Publish') . "
</button>
    </div>

  </div>

</div>
";
  }
}
?>

</div>

    <!-- BOTTOM -->
      <div class="bottom-add-section">

     <div class="item">
    <button onclick="addnote()"><img src="addnote.png"></button>
    <p>Add Subject</p>
  </div>

    <div class="item">
      <button onclick="goBack()""><img src="back.png"></button>
      <p>Back</p>
    </div>
</div>

  </div>

<script>
const descBox = document.getElementById("descBox");
const descInput = document.getElementById("descInput");

descBox.addEventListener("input", () => {
  descInput.value = descBox.innerText;
});
</script>
<script src="script.js"></script>
</body>
</html>