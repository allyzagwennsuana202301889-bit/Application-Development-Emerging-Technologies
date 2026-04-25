<?php
session_start();
include 'database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lectures</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search subject">
    <img src="bell.png" class="bell">
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
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>

    <a href="#">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <!-- SUBJECT LIST -->
<?php
$sql = "SELECT subjects.*, student.name 
        FROM subjects 
        LEFT JOIN student 
        ON subjects.student_id = student.student_id";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  echo "<div class='subjects-container'>";

  while ($row = $result->fetch_assoc()) {
    $uploadedBy = ($row['is_preset'] == 1) ? "The Ins" : $row['name'];

    echo "
    <div class='subject-card'>
      <div class='download-icon'>
        <img src='offlinemode.png'>
      </div>

      <div class='card-left'>
        <h2>{$row['subject_name']}</h2>
        <p class='uploaded'>Uploaded by:<br>{$uploadedBy}</p>

        <button onclick='goToSubject({$row['subject_id']})' class='study-btn'>
          Study Course
        </button>
      </div>

      <div class='card-right'>
        <img src='globe.png' class='subject-icon'>
        <div class='progress'>78%</div>
      </div>
    </div>
    ";
  }

  echo "</div>";
}
?>

  <!-- BOTTOM BAR -->
  <div class="bottom">
    <div class="file-section">
      <button onclick="upload()">
        <img src="uploaded.png">
      </button>
      <p>Uploads</p>
    </div>

    <button onclick="openModal()">Add Subject(s)</button>
  </div>

</div> 



<!-- MODAL -->
<div id="modal" class="modal">

  <div class="modal-content">

    <div class="search-box">
      <input type="text" id="search" placeholder="Search...">
    </div>

 <div class="subject-list">
<?php
$student_id = $_SESSION['student_id'];

$sql = "
  SELECT 
    subject_id AS id,
    subject_name AS title,
    description,
    'subject' AS source
  FROM subjects

  UNION

  SELECT 
    note_id AS id,
    title,
    content AS description,
    'note' AS source
  FROM notes
  WHERE student_id = $student_id
  AND type = 'subject'
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {

    $id = $row['id'];
    $title = htmlspecialchars($row['title']);
    $desc = htmlspecialchars($row['description']);
    $source = $row['source'];

    if ($source === 'subject') {
      $click = "addSubject($id)";
    } else {
      $click = "addNoteAsSubject($id)";
    }

echo "
  <div class='subject-item' onclick='$click'>
    <h3>$title</h3>
    <p>$desc</p>
  </div>
";
  }
} else {
  echo "<p>No subjects found.</p>";
}
?>
</div>

    <div class="bottom-arrow" onclick="closeModal()">⌄</div>

  </div>

</div>


<script src="script.js"></script>
</body>
</html>