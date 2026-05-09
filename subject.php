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
        foreach ($lessons as $lesson) {
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
        <div class="subject-main-card">
            <?php if ($img_src): ?>
                <img src="<?= htmlspecialchars($img_src) ?>" alt="<?= $title ?>" class="card-image-preview">
            <?php endif; ?>
            <input type="text" class="card-title" value="<?= $title ?>" readonly>
            <div class="fake-desc"><?= nl2br($desc) ?></div>
        </div>
    <?php
        }
    } else {
        echo '<div class="subject-main-card"><div class="fake-desc">' . nl2br(htmlspecialchars($raw_desc)) . '</div></div>';
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
        <button onclick="quiz()"><img src="flashcards.png"></button>
        <p>Flash Cards</p>
      </div>
    </div>

  </div>

</div>

<script src="script.js"></script>
</body>
</html>