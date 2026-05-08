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
        <h2><?= htmlspecialchars($row['subject_name']) ?></h2>
        <p class="uploaded">
          Uploaded by:<br><?= htmlspecialchars($uploader) ?>
        </p>
      </div>

      <div class="top-right">
        <img src="<?= !empty($row['subject_image']) ? $row['subject_image'] : 'file.png' ?>"
             class="subject-icon"
             onerror="this.src='file.png'">
      </div>
    </div>

    <!-- MAIN CONTENT -->
<div class="subject-main-card">
  <div class="text-side">
    <h3>Lesson</h3>
    <?php
    $raw_desc = $row['description'] ?? '';
    
    // Try to decode JSON
    $lessons = json_decode($raw_desc, true);
    $json_valid = (json_last_error() === JSON_ERROR_NONE && is_array($lessons));

    if ($json_valid) {
        foreach ($lessons as $lesson) {
            $title = htmlspecialchars($lesson['title'] ?? 'Untitled');
            $desc  = htmlspecialchars($lesson['desc'] ?? '');
            $img_raw = $lesson['image'] ?? '';
            
            // Handle different image formats
            $img_src = '';
            if (!empty($img_raw)) {
                // Check if it's a base64 string (no http/ path prefix)
                if (strpos($img_raw, 'http') === 0 || strpos($img_raw, '/') === 0) {
                    // Regular URL or path
                    $img_src = $img_raw;
                } elseif (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $img_raw)) {
                    // Likely base64 — try with common image prefixes
                    $img_src = 'data:image/png;base64,' . $img_raw;
                } else {
                    // Unknown format, try as-is
                    $img_src = $img_raw;
                }
            }
    ?>
        <div class="lesson-item">
            <h4><?= $title ?></h4>
            <?php if ($desc): ?>
                <p><?= $desc ?></p>
            <?php endif; ?>
            <?php if ($img_src): ?>
                <img src="<?= htmlspecialchars($img_src) ?>" 
                     alt="<?= $title ?>" 
                     class="lesson-img" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <p class="img-error" style="display:none; color:#888; font-size:0.9em;">Image failed to load</p>
            <?php endif; ?>
        </div>
    <?php
        }
    } else {
        // Fallback: plain text
        echo '<p>' . nl2br(htmlspecialchars($raw_desc)) . '</p>';
    }
    ?>
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