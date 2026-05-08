<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// Get user's added subjects (from student_subjects)
$sql_added = "
    SELECT s.*, st.name as uploader_name, 0 as is_added
    FROM subjects s
    JOIN student_subjects ss ON s.subject_id = ss.subject_id
    LEFT JOIN student st ON s.student_id = st.student_id
    WHERE ss.student_id = ?
    ORDER BY s.subject_id DESC
";

$stmt = $conn->prepare($sql_added);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$added_subjects = $stmt->get_result();


/* USER-CREATED SUBJECTS FROM NOTES */
$sql_note_subjects = "
    SELECT 
        n.note_id AS subject_id,
        n.title AS subject_name,
        n.subject_image,
        st.name AS uploader_name
    FROM notes n
    JOIN student_subjects ss 
        ON n.note_id = ss.subject_id
    LEFT JOIN student st 
        ON n.student_id = st.student_id
    WHERE ss.student_id = ?
    AND ss.source_type = 'notes'
    AND n.type = 'subject'
    ORDER BY n.note_id DESC
";
// Get preset subjects (is_preset = 1) that are NOT already added
$sql_presets = "
    SELECT s.*, 'The Ins' as uploader_name, 1 as is_preset
    FROM subjects s
    WHERE s.is_preset = 1
    AND s.subject_id NOT IN (
        SELECT subject_id FROM student_subjects WHERE student_id = ?
    )
    ORDER BY s.subject_id DESC
";

$stmt3 = $conn->prepare($sql_note_subjects);
$stmt3->bind_param("i", $student_id);
$stmt3->execute();
$note_subjects = $stmt3->get_result();

$stmt2 = $conn->prepare($sql_presets);
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$presets = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home</title>
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
$has_content =
    ($added_subjects && $added_subjects->num_rows > 0) ||
    ($note_subjects && $note_subjects->num_rows > 0) ||
    ($presets && $presets->num_rows > 0);

if ($has_content) {
 echo "<div class='subjects-container'>";

/* 1. USER ADDED SUBJECTS */
if ($added_subjects && $added_subjects->num_rows > 0) {
  while ($row = $added_subjects->fetch_assoc()) {

    echo "
    <div class='subject-card' onclick='goToSubject(" . (int)$row['subject_id'] . ")'>
      <div class='download-icon'>
        <img src='offlinemode.png'>
      </div>

      <div class='card-left'>
        <h2>" . htmlspecialchars($row['subject_name']) . "</h2>
        <p class='uploaded'>Uploaded by:<br>" . htmlspecialchars($row['uploader_name'] ?? 'You') . "</p>

        <button onclick='event.stopPropagation(); goToSubject(" . (int)$row['subject_id'] . ")' class='study-btn'>
          Study Course
        </button>
      </div>

      <div class='card-right'>
        <img src='" . htmlspecialchars(!empty($row['subject_image']) ? $row['subject_image'] : 'file.png') . "' class='subject-icon' onerror=\"this.src='file.png'\">
        <div class='progress'>78%</div>
      </div>
    </div>
    ";
  }
}

/* 2. USER CREATED FROM NOTES */
if ($note_subjects && $note_subjects->num_rows > 0) {
  while ($row = $note_subjects->fetch_assoc()) {

    echo "
    <div class='subject-card' onclick='goToCustomSubject(" . (int)$row['subject_id'] . ", \"notes\")'>
      <div class='download-icon'>
        <img src='offlinemode.png'>
      </div>

      <div class='card-left'>
        <h2>" . htmlspecialchars($row['subject_name']) . "</h2>
        <p class='uploaded'>Uploaded by:<br>" . htmlspecialchars($row['uploader_name'] ?? 'You') . "</p>

         <button onclick='event.stopPropagation(); goToCustomSubject(" . (int)$row['subject_id'] . ", \"notes\")' class='study-btn' >
          Study Course
        </button>
      </div>

      <div class='card-right'>
        <img src='" . htmlspecialchars(!empty($row['subject_image']) ? $row['subject_image'] : 'file.png') . "' class='subject-icon' onerror=\"this.src='file.png'\">
        <div class='progress'>78%</div>
      </div>
    </div>
    ";
  }
}

/* 3. PRESETS */
if ($presets && $presets->num_rows > 0) {
  while ($row = $presets->fetch_assoc()) {

    echo "
    <div class='subject-card' onclick='goToSubject(" . (int)$row['subject_id'] . ")'>
      <div class='download-icon'>
        <img src='offlinemode.png'>
      </div>

      <div class='card-left'>
        <h2>" . htmlspecialchars($row['subject_name']) . "</h2>
        <p class='uploaded'>Uploaded by:<br>The Ins</p>

        <button onclick='event.stopPropagation(); goToSubject(" . (int)$row['subject_id'] . ")' class='study-btn'>
          Study Course
        </button>
      </div>

      <div class='card-right'>
        <img src='" . htmlspecialchars(!empty($row['subject_image']) ? $row['subject_image'] : 'file.png') . "' class='subject-icon' onerror=\"this.src='file.png'\">
        <div class='progress'>78%</div>
      </div>
    </div>
    ";
  }
}

echo "</div>";

} else {
  echo "
  <div style='text-align:center; color:white; padding:50px; font-family: Itim, cursive;'>
    <h2>No subjects yet</h2>
    <p>Add subjects from Lectures to see them here!</p>
    <button onclick=\"window.location.href='lectures.php'\" class='study-btn' style='margin-top:20px;'>
      Go to Lectures
    </button>
  </div>";
}
?>

  <!-- BOTTOM BAR -->
  <div class="bottom">
    <div class="file-section">
  <button onclick="upload()"><img src="uploaded.png"></button>
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
    <div class="subject-list" id="modalSubjectList">
      <!-- Loaded via fetch -->
    </div>
    <div class="bottom-arrow" onclick="closeModal()">⌄</div>
  </div>
</div>

<script src="script.js"></script>

</body>
</html>