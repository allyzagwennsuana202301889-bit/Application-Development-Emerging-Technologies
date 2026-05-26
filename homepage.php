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
$added_subjects_raw = $stmt->get_result();
$added_subjects = [];
while ($row = $added_subjects_raw->fetch_assoc()) {
    $added_subjects[] = $row;
}

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
$added_notes_raw = $stmt_notes->get_result();
$added_notes = [];
while ($row = $added_notes_raw->fetch_assoc()) {
    $added_notes[] = $row;
}

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
$presets_raw = $stmt2->get_result();
$presets = [];
while ($row = $presets_raw->fetch_assoc()) {
    $presets[] = $row;
}

/* ════════════════════════════════════════════
   RECALCULATE PROGRESS ON PAGE LOAD
   Ensures progress updates when quiz questions are added
════════════════════════════════════════════ */
function recalcSubjectProgress($conn, $student_id, $subject_id, $source_type) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as read_count FROM reading_progress
        WHERE student_id = ? AND subject_id = ? AND source_type = ? AND completed = 1
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $read_count = $stmt->get_result()->fetch_assoc()['read_count'] ?? 0;

    if ($source_type === 'notes') {
        $stmt = $conn->prepare("SELECT content FROM notes WHERE note_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT description FROM subjects WHERE subject_id = ?");
    }
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $desc = $stmt->get_result()->fetch_assoc();
    $raw = '';
    if ($desc !== null) {
        $raw = $desc['content'] ?? $desc['description'] ?? '';
    }
    $lessons = json_decode($raw ?: '[]', true);
    $total_lessons = (is_array($lessons) && count($lessons) > 0) ? count($lessons) : 1;
    $reading_percent = $total_lessons > 0 ? round(($read_count / $total_lessons) * 100) : 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as q_count FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $current_quiz_total = (int)($stmt->get_result()->fetch_assoc()['q_count'] ?? 0);
    $has_quiz = $current_quiz_total > 0;

    if (!$has_quiz) {
        $overall = $reading_percent;
        $quiz_percent = 0;
    } else {
        $stmt = $conn->prepare("
            SELECT score_percent, total_questions, correct_answers
            FROM quiz_results
            WHERE student_id = ? AND subject_id = ? AND source_type = ?
            ORDER BY date_taken DESC, result_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
        $stmt->execute();
        $last_result = $stmt->get_result()->fetch_assoc();
        $last_total = (int)($last_result['total_questions'] ?? 0);
        $last_correct = (int)($last_result['correct_answers'] ?? 0);
        $last_percent = (int)($last_result['score_percent'] ?? 0);

        if ($current_quiz_total > $last_total && $last_total > 0) {
            $quiz_percent = round(($last_correct / $current_quiz_total) * 100);
        } else {
            $quiz_percent = $last_percent;
        }
        $overall = round(($reading_percent * 0.4) + ($quiz_percent * 0.6));
    }

    $stmt = $conn->prepare("
        INSERT INTO subject_progress
            (student_id, subject_id, source_type, reading_percent, quiz_percent, overall_percent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            reading_percent = VALUES(reading_percent),
            quiz_percent    = VALUES(quiz_percent),
            overall_percent = VALUES(overall_percent)
    ");
    $stmt->bind_param("iisiii", $student_id, $subject_id, $source_type,
                                $reading_percent, $quiz_percent, $overall);
    $stmt->execute();
}

// Recalculate for all subjects
foreach ($added_subjects as $row) {
    recalcSubjectProgress($conn, $student_id, $row['subject_id'], 'subjects');
}
foreach ($added_notes as $row) {
    recalcSubjectProgress($conn, $student_id, $row['subject_id'], 'notes');
}
foreach ($presets as $row) {
    recalcSubjectProgress($conn, $student_id, $row['subject_id'], 'subjects');
}
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
    <img src="bell.png" onclick="notif()" class="bell" >
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back">
    </div>
    
   <!-- Profile Image Fetch -->
   <?php
// Fetch current user's profile image fresh from DB
$pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
$pfp_stmt->bind_param("i", $student_id);
$pfp_stmt->execute();
$pfp_result = $pfp_stmt->get_result()->fetch_assoc();
$profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';

// Cache bust: append timestamp so browser always fetches fresh
$image_src = $profile_image;
if (strpos($image_src, 'data:') === 0) {
    // base64 — no cache bust needed, but force reload with unique session
    $image_src = $profile_image;
} else {
    $image_src .= '?t=' . time();
}
?>

<!-- Profile Image Upload -->
<form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
  <label for="imageInput" style="cursor: pointer; position: relative;">
    <img id="preview" src="<?= htmlspecialchars($image_src) ?>" 
         style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;"
         onerror="this.src='acc.png'">
  </label>
  <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
</form>

    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>

    <a href="#">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <!-- SUBJECT LIST -->
<?php
$has_content =
    count($added_subjects) > 0 ||
    count($added_notes) > 0 ||
    count($presets) > 0;

if ($has_content) {
 echo "<div class='subjects-container'>";

/* 1. USER ADDED SUBJECTS (from subjects table) */
if (count($added_subjects) > 0) {
  foreach ($added_subjects as $row) {
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
      <button class='remove-subject-btn' onclick='event.stopPropagation(); removeSubject(" . (int)$row['subject_id'] . ", \"subjects\")'>✕</button>
      <div class='download-icon' onclick='event.stopPropagation(); toggleDownload(this)'>
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
if (count($added_notes) > 0) {
  foreach ($added_notes as $row) {
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
      <button class='remove-subject-btn' onclick='event.stopPropagation(); removeSubject(" . (int)$row['subject_id'] . ", \"notes\")'>✕</button>
      <div class='download-icon' onclick='event.stopPropagation(); toggleDownload(this)'>
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
if (count($presets) > 0) {
  foreach ($presets as $row) {
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
      <div class='download-icon' onclick='event.stopPropagation(); toggleDownload(this)'>
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


<script>
/* ================= OFFLINE DOWNLOAD SIMULATION ================= */
function toggleDownload(icon) {
  const img = icon.querySelector('img');
  if (!img) return;

  const isDownloaded = img.getAttribute('data-downloaded') === 'true';

  if (!isDownloaded) {
    img.src = 'bluecheck.png';
    img.setAttribute('data-downloaded', 'true');
    img.title = 'Downloaded for offline';
    showToast('Downloaded for offline reading');
  } else {
    img.src = 'offlinemode.png';
    img.setAttribute('data-downloaded', 'false');
    img.title = 'Download for offline';
    showToast('Removed from offline');
  }
}

function showToast(msg) {
  let toast = document.getElementById('toastMsg');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toastMsg';
    toast.style.cssText = `
      position: fixed;
      top: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      background: #333;
      color: white;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 14px;
      z-index: 300;
      opacity: 0;
      transition: 0.3s;
      pointer-events: none;
      font-family: 'Inria Sans', sans-serif;
    `;
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.style.opacity = '1';
  toast.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-50%) translateY(-20px)';
  }, 2500);
}
</script>

</body>
</html>