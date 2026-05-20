<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if (!$id) {
    exit("Subject not found.");
}

$subject = null;
$source_type = null;
$uploader_name = 'Unknown';
$uploader_id = 0;
$is_preset = false;

/* 1. CHECK NOTES */
$stmt = $conn->prepare("
    SELECT note_id AS subject_id,
           title AS subject_name,
           content,
           subject_image,
           student_id as uploader_id,
           'notes' AS source_type
    FROM notes
    WHERE note_id = ?
      AND type = 'subject'
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

if ($subject) {
    $source_type = 'notes';
    $uploader_id = (int)$subject['uploader_id'];
}

/* 2. CHECK SUBJECTS */
if (!$subject) {
    $stmt = $conn->prepare("
        SELECT subject_id,
               subject_name,
               description,
               content,
               subject_image,
               student_id as uploader_id,
               is_preset,
               'subjects' AS source_type
        FROM subjects
        WHERE subject_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $subject = $stmt->get_result()->fetch_assoc();

    if ($subject) {
        $source_type = 'subjects';
        $uploader_id = (int)$subject['uploader_id'];
        $is_preset = (int)($subject['is_preset'] ?? 0);
    }
}

/* 3. FAILSAFE */
if (!$subject) {
    exit("Subject not found.");
}

// Get uploader name
if ($uploader_id > 0) {
    $uploader_stmt = $conn->prepare("SELECT name FROM student WHERE student_id = ?");
    $uploader_stmt->bind_param("i", $uploader_id);
    $uploader_stmt->execute();
    $uploader_result = $uploader_stmt->get_result()->fetch_assoc();
    $uploader_name = $uploader_result['name'] ?? 'Unknown';
} elseif ($is_preset) {
    $uploader_name = 'The Ins';
}

/* 4. ACCESS CONTROL */
$has_access = false;
$is_owner = ($uploader_id === $student_id);

if ($is_preset) {
    $has_access = true;
} elseif ($source_type === 'notes') {
    $has_access = true; // Published notes (type='subject') are viewable by all
} else {
    $has_access = $is_owner || $is_preset;
}

if (!$has_access) {
    header("Location: homepage.php?error=no_access");
    exit;
}

/* 5. CHECK IF ALREADY IN USER'S LIST */
$already_added = false;
if ($student_id > 0) {
    $check = $conn->prepare("
        SELECT 1 FROM student_subjects 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $check->bind_param("iis", $student_id, $id, $source_type);
    $check->execute();
    $already_added = $check->get_result()->num_rows > 0;
}

/* 6. BUILD DISPLAY CARDS */
$cards = [];

if (!empty($subject['content'])) {
    $decoded = json_decode($subject['content'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $c) {
            $cards[] = [
                'title' => $c['title'] ?? '',
                'desc'  => $c['desc'] ?? '',
                'img'   => $c['img'] ?? 'file.png'
            ];
        }
    }
}

if (empty($cards) && !empty($subject['description'])) {
    $decoded = json_decode($subject['description'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $c) {
            $cards[] = [
                'title' => $c['title'] ?? '',
                'desc'  => $c['desc'] ?? '',
                'img'   => $c['img'] ?? 'file.png'
            ];
        }
    }
}

if (empty($cards)) {
    $cards[] = [
        'title' => $subject['subject_name'],
        'desc'  => $subject['description'] ?? 'No description available.',
        'img'   => $subject['subject_image'] ?? 'file.png'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($subject['subject_name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <img src="bell.png" class="bell">
  </nav>

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
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <div class="subject-content">

    <!-- Top Header Card -->
    <div class="subject-top-card">
      <div class="top-left">
        <h2><?= htmlspecialchars($subject['subject_name']) ?></h2>
        <p class="uploaded">
          <strong>Uploaded by:</strong><br>
          <?= htmlspecialchars($uploader_name) ?>
        </p>
      </div>
      <div class="top-right">
        <img src="<?= htmlspecialchars($subject['subject_image'] ?? 'file.png') ?>" class="subject-icon" onerror="this.src='file.png'">
      </div>
    </div>

    <!-- Content Cards -->
    <div id="cardContainer">
    <?php if (!empty($cards)): ?>
      <?php foreach ($cards as $card): 
          $cardTitle = $card['title'] ?? '';
          $cardDesc = $card['desc'] ?? '';
          $cardImg = (!empty($card['img']) && $card['img'] !== 'file.png') ? $card['img'] : 'file.png';
      ?>
      <div class="subject-main-card">
          <label class="card-image-label">
            <img src="<?= htmlspecialchars($cardImg) ?>" class="card-image-preview" onerror="this.src='file.png'">
          </label>
          <input type="text" class="card-title" value="<?= htmlspecialchars($cardTitle) ?>" readonly>
          <div class="fake-desc" contenteditable="false">
            <?= nl2br(htmlspecialchars($cardDesc)) ?>
          </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="subject-main-card">
          <div class="fake-desc" contenteditable="false">
            <p>No content added yet.</p>
          </div>
      </div>
    <?php endif; ?>
    </div>

  </div>

  <!-- Bottom bar with Add to List button -->
  <div class="bottom-file-section">
    <div class="item">
      <button onclick="addToMyList(<?= (int)$subject['subject_id'] ?>, '<?= $source_type ?>')" 
              class="study-btn" 
              id="addBtn"
              <?= $already_added ? 'disabled' : '' ?>>
        <?= $already_added ? 'Already in List' : '+ Add to My List' ?>
      </button>
      <p id="addedMsg" style="display:none; color: green; font-size: 12px;">Added!</p>
    </div>
    <div class="item">
      <button onclick="goBack()" style="background:none;border:none;">
        <img src="back.png">
      </button>
      <p>Back</p>
    </div>
  </div>

</div>

<script src="script.js"></script>
<script>
function goBack() {
  window.history.back();
}

function addToMyList(subjectId, sourceType) {
  fetch("add_to_list.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body:
      "subject_id=" + subjectId +
      "&source_type=" + sourceType
  })
  .then(res => res.text())
  .then(data => {
    if (data === "added" || data === "already") {
      document.getElementById('addBtn').disabled = true;
      document.getElementById('addBtn').textContent = 'Already in List';
      document.getElementById('addedMsg').style.display = 'block';
    } else {
      alert('Error: ' + data);
    }
  })
  .catch(err => {
    console.error('Add failed:', err);
    alert('Failed to add. Try again.');
  });
}
</script>

</body>
</html>