<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// 1. Get user's added subjects from SUBJECTS table
$sql_added = "
    SELECT s.*, st.name as uploader_name, 0 as is_added
    FROM subjects s
    JOIN student_subjects ss ON s.subject_id = ss.subject_id
    LEFT JOIN student st ON s.student_id = st.student_id
    WHERE ss.student_id = ?
    AND ss.source_type = 'subjects'
    ORDER BY s.subject_id DESC
";

$stmt = $conn->prepare($sql_added);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$added_subjects = $stmt->get_result();

// 2. Get user's added subjects from NOTES table (THIS WAS MISSING!)
$sql_added_notes = "
    SELECT 
        n.note_id AS subject_id,
        n.title AS subject_name,
        n.subject_image,
        st.name AS uploader_name
    FROM notes n
    JOIN student_subjects ss ON n.note_id = ss.subject_id
    LEFT JOIN student st ON n.student_id = st.student_id
    WHERE ss.student_id = ?
    AND ss.source_type = 'notes'
    AND n.type = 'subject'
    ORDER BY n.note_id DESC
";

$stmt_notes = $conn->prepare($sql_added_notes);
$stmt_notes->bind_param("i", $student_id);
$stmt_notes->execute();
$added_notes = $stmt_notes->get_result();

// 3. Get preset subjects not yet added
$sql_presets = "
    SELECT s.*, 'The Ins' as uploader_name, 1 as is_preset
    FROM subjects s
    WHERE s.is_preset = 1
    AND s.subject_id NOT IN (
        SELECT subject_id FROM student_subjects WHERE student_id = ?
    )
    ORDER BY s.subject_id DESC
";

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
   <!-- Profile Image Upload -->
    <form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
      <label for="imageInput" style="cursor: pointer; position: relative;">
        <img id="preview" src="<?= !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'acc.png' ?>" 
             style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;">
      </label>
      <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
    </form>

    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>

    <a href="#">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <!-- SUBJECT LIST -->
<?php
$has_content =
    ($added_subjects && $added_subjects->num_rows > 0) ||
    ($added_notes && $added_notes->num_rows > 0) ||
    ($presets && $presets->num_rows > 0);

if ($has_content) {
 echo "<div class='subjects-container'>";

/* 1. USER ADDED SUBJECTS (from subjects table) */
if ($added_subjects && $added_subjects->num_rows > 0) {
  while ($row = $added_subjects->fetch_assoc()) {
    // Get real progress for this subject
    $prog_stmt = $conn->prepare("
        SELECT overall_percent FROM subject_progress 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $type = 'subjects';
    $prog_stmt->bind_param("iis", $student_id, $row['subject_id'], $type);
    $prog_stmt->execute();
    $prog = $prog_stmt->get_result()->fetch_assoc();
    $progress_value = $prog['overall_percent'] ?? 0;
    
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
        <div class='progress'>" . $progress_value . "%</div>
      </div>
    </div>
    ";
  }
}

/* 2. USER ADDED NOTES (from notes table) - THIS WAS MISSING! */
if ($added_notes && $added_notes->num_rows > 0) {
  while ($row = $added_notes->fetch_assoc()) {
    // Get real progress for this note/subject
    $prog_stmt = $conn->prepare("
        SELECT overall_percent FROM subject_progress 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $type = 'notes';
    $prog_stmt->bind_param("iis", $student_id, $row['subject_id'], $type);
    $prog_stmt->execute();
    $prog = $prog_stmt->get_result()->fetch_assoc();
    $progress_value = $prog['overall_percent'] ?? 0;
    
    echo "
    <div class='subject-card' onclick='goToCustomSubject(" . (int)$row['subject_id'] . ", \"notes\")'>
      <div class='download-icon'>
        <img src='offlinemode.png'>
      </div>
      <div class='card-left'>
        <h2>" . htmlspecialchars($row['subject_name']) . "</h2>
        <p class='uploaded'>Uploaded by:<br>" . htmlspecialchars($row['uploader_name'] ?? 'You') . "</p>
        <button onclick='event.stopPropagation(); goToCustomSubject(" . (int)$row['subject_id'] . ", \"notes\")' class='study-btn'>
          Study Course
        </button>
      </div>
      <div class='card-right'>
        <img src='" . htmlspecialchars(!empty($row['subject_image']) ? $row['subject_image'] : 'file.png') . "' class='subject-icon' onerror=\"this.src='file.png'\">
        <div class='progress'>" . $progress_value . "%</div>
      </div>
    </div>
    ";
  }
}

/* 3. PRESETS */
if ($presets && $presets->num_rows > 0) {
  while ($row = $presets->fetch_assoc()) {
    // Get real progress for this preset
    $prog_stmt = $conn->prepare("
        SELECT overall_percent FROM subject_progress 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $type = 'subjects';
    $prog_stmt->bind_param("iis", $student_id, $row['subject_id'], $type);
    $prog_stmt->execute();
    $prog = $prog_stmt->get_result()->fetch_assoc();
    $progress_value = $prog['overall_percent'] ?? 0;
    
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
        <div class='progress'>" . $progress_value . "%</div>
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
    <div class="subject-list" id="modalSubjectList"></div>
    <div class="bottom-arrow" onclick="closeModal()">⌄</div>
  </div>
</div>

<script src="script.js"></script>

</body>
</html>