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

/* 1. CHECK NOTES */
$stmt = $conn->prepare("
    SELECT note_id AS subject_id,
           title AS subject_name,
           content,
           subject_image,
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
}

/* 2. CHECK SUBJECTS */
if (!$subject) {
    $stmt = $conn->prepare("
        SELECT subject_id,
               subject_name,
               description,
               content,
               subject_image,
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
    }
}

/* 3. FAILSAFE */
if (!$subject) {
    exit("Subject not found.");
}

/* 4. BUILD DISPLAY CARDS (SAFE LOGIC) */
$cards = [];

/* CASE 1: JSON CONTENT EXISTS (notes or advanced subjects) */
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

/* CASE 2: FALLBACK TO DESCRIPTION (YOUR CURRENT SYSTEM) */
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

    <!-- Top Header Card - same as viewnote.php -->
    <div class="subject-top-card">
      <div class="top-left">
        <h2><?= htmlspecialchars($subject['subject_name']) ?></h2>
        <p class="uploaded">
          <strong>Uploaded by:</strong><br>
          <?= htmlspecialchars($_SESSION['name'] ?? 'Unknown') ?>
        </p>
      </div>
      <div class="top-right">
        <img src="<?= htmlspecialchars($subject['subject_image'] ?? 'file.png') ?>" class="subject-icon" onerror="this.src='file.png'">
      </div>
    </div>

    <!-- User Created Content Cards - same structure as viewnote.php -->
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

  <!-- Bottom bar - same as viewnote.php but with Add to List button -->
  <div class="bottom-file-section">
    <div class="item">
      <button onclick="addToMyList(
<?= (int)$subject['subject_id'] ?>,
'<?= $source_type ?>'
)" class="study-btn" id="addBtn">
        + Add to My List
      </button>
      <p id="addedMsg" style="display:none; color: green;">Added!</p>
    </div>
    <div class="item">
      <button onclick="goBack()" style="background:none;border:none;">
        <img src="back.png">
      </button>
      <p>Back</p>
    </div>
  </div>

</div>

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