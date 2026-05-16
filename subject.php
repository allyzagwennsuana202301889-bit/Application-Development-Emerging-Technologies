<?php
session_start();
include 'database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? 'subjects'; 

if (!$id) {
    exit("Subject not found.");
}

$row = null;
$uploader = "Unknown";

/* =========================
   1. NOTES SOURCE
========================= */
if ($type === 'notes') {

    $stmt = $conn->prepare("
        SELECT n.note_id AS subject_id,
               n.title AS subject_name,
               n.content AS description,
               n.subject_image,
               s.name AS uploader_name
        FROM notes n
        LEFT JOIN student s ON n.student_id = s.student_id
        WHERE n.note_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $uploader = $row['uploader_name'] ?? 'Unknown';
    }
}
/* =========================
   2. SUBJECTS SOURCE
========================= */
else {

    $stmt = $conn->prepare("
        SELECT s.subject_id,
               s.subject_name,
               s.description,
               s.subject_image,
               st.name AS uploader_name,
               s.is_preset
        FROM subjects s
        LEFT JOIN student st ON s.student_id = st.student_id
        WHERE s.subject_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $uploader = ($row['is_preset'] == 1)
            ? "The Ins"
            : ($row['uploader_name'] ?? 'Unknown');
    }
}

if (!$row) {
    exit("Subject not found.");
}

// Resolve subject image: logo (subject_image) takes priority over lesson content
$subject_img_src = '';
if (!empty($row['subject_image'])) {
    $subject_img_src = $row['subject_image'];
}
if (empty($subject_img_src) && !empty($row['description'])) {
    $lessons = json_decode($row['description'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($lessons) && !empty($lessons)) {
        $first_img = $lessons[0]['img'] ?? '';
        if (!empty($first_img) && $first_img !== 'file.png') {
            $subject_img_src = $first_img;
        }
    }
}

// Get current reading progress for progress bar display
$student_id = $_SESSION['student_id'] ?? 0;
$read_stmt = $conn->prepare("
    SELECT COUNT(*) as read_count FROM reading_progress 
    WHERE student_id = ? AND subject_id = ? AND source_type = ? AND completed = 1
");
$read_stmt->bind_param("iis", $student_id, $id, $type);
$read_stmt->execute();
$read_count = $read_stmt->get_result()->fetch_assoc()['read_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Study</title>
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
      <!-- Profile Image Upload -->
    <form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
      <label for="imageInput" style="cursor: pointer; position: relative;">
        <img id="preview" src="<?= !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'acc.png' ?>" 
             style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;">
      </label>
      <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
    </form>
    
    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? ''; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
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
        <h2><?= htmlspecialchars($row['subject_name']) ?></h2>
        <p class="uploaded">
          Uploaded by:<br><?= htmlspecialchars($uploader) ?>
        </p>
      </div>
      <div class="top-right">
        <img id="subjectImagePreview"
             src="<?= !empty($subject_img_src) ? htmlspecialchars($subject_img_src) : 'file.png' ?>"
             class="subject-image"
             onerror="this.src='file.png'">
      </div>
    </div>

    <!-- MAIN CONTENT - Cards directly, no "Lesson" heading -->
    <?php
    $raw_desc = $row['description'] ?? '';
    $lessons = json_decode($raw_desc, true);
    $json_valid = (json_last_error() === JSON_ERROR_NONE && is_array($lessons));

    if ($json_valid) {
        foreach ($lessons as $index => $lesson) {
            $title = htmlspecialchars($lesson['title'] ?? 'Untitled');
            $desc  = htmlspecialchars($lesson['desc'] ?? '');
            
            $img_raw = $lesson['img'] ?? '';
            $img_src = '';
            
            if (!empty($img_raw) && $img_raw !== 'file.png') {
                if (strpos($img_raw, 'data:image/') === 0) {
                    $img_src = $img_raw;
                } elseif (preg_match('/^https?:\/\//i', $img_raw) || strpos($img_raw, '/') === 0) {
                    $img_src = $img_raw;
                } elseif (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $img_raw) && strlen($img_raw) > 50) {
                    $prefix = substr($img_raw, 0, 20);
                    if (strpos($prefix, 'iVBOR') === 0) {
                        $img_src = 'data:image/png;base64,' . $img_raw;
                    } elseif (strpos($prefix, '/9j/') === 0) {
                        $img_src = 'data:image/jpeg;base64,' . $img_raw;
                    } elseif (strpos($prefix, 'R0lGOD') === 0) {
                        $img_src = 'data:image/gif;base64,' . $img_raw;
                    } else {
                        $img_src = 'data:image/png;base64,' . $img_raw;
                    }
                } else {
                    $img_src = $img_raw;
                }
            }
    ?>
        <div class="subject-main-card" data-lesson-index="<?= $index ?>">
            <?php if ($img_src): ?>
                <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= $title ?>" class="card-image-preview">
            <?php endif; ?>
            <input type="text" class="card-title" value="<?= $title ?>" readonly>
            <div class="fake-desc"><?= nl2br($desc) ?></div>
        </div>
    <?php
        }
    } else {
        echo '<div class="subject-main-card" data-lesson-index="0"><div class="fake-desc">' . nl2br(htmlspecialchars($raw_desc)) . '</div></div>';
    }
    ?>

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
        <a href="quiz.php?id=<?= $id ?>&type=<?= htmlspecialchars($type) ?>" class="flashcard-link">
          <img src="flashcards.png">
        </a>
        <p>Flash Cards</p>
      </div>
    </div>

  </div>

</div>

<script src="script.js"></script>
<script>
// Track which lesson cards are visible
const lessonCards = document.querySelectorAll('.subject-main-card');
const totalLessons = lessonCards.length;
let observedCards = new Set();

// Intersection Observer - marks card as "read" when scrolled into view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const cardIndex = parseInt(entry.target.dataset.lessonIndex);
            if (!observedCards.has(cardIndex)) {
                observedCards.add(cardIndex);
                markAsRead(cardIndex);
            }
        }
    });
}, { threshold: 0.5 });

lessonCards.forEach(card => observer.observe(card));

function markAsRead(lessonIndex) {
    fetch('api_progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_read&subject_id=<?= $id ?>&type=<?= $type ?>&lesson_index=${lessonIndex}`
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              console.log('Lesson ' + lessonIndex + ' marked as read');
          }
      })
      .catch(err => console.error('Error marking read:', err));
}

function goBack() {
    window.history.back();
}
</script>
</body>
</html>