<?php
session_start();
include 'database.php';

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;
$student_id = $_SESSION['student_id'] ?? 0;

// Get the note/subject
$stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ? AND type = 'subject'");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Subject not found.";
    exit;
}

// Check if already in student's list
$check = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
$check->bind_param("ii", $student_id, $note_id);
$check->execute();
$alreadyAdded = $check->get_result()->num_rows > 0;

// Parse content
$cards = [];
if (!empty($note['content'])) {
    $decoded = json_decode($note['content'], true);
    $cards = is_array($decoded) ? $decoded : [$note['content']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($note['title']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
      .add-to-list-btn {
        background: #F4A261;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 16px;
        cursor: pointer;
        margin-top: 10px;
      }
      .add-to-list-btn:disabled {
        background: #888;
        cursor: not-allowed;
      }
      .added-msg {
        color: #2ecc71;
        font-size: 14px;
        margin-top: 5px;
      }
    </style>
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <button class="back-btn" onclick="goBack()" style="background:none;border:none;padding:0;">
      <img src="back.png" class="back-btn-img">
    </button>
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

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

<div class="subject-content">

<div class="subject-top-card">
  <div class="top-left">
    <h2><?= htmlspecialchars($note['title']) ?></h2>
    <p class="uploaded"><strong>Uploaded by:</strong><br><?= htmlspecialchars($note['uploader_name'] ?? 'Unknown') ?></p>

    <?php if (!$alreadyAdded): ?>
      <button onclick="addToMyList(<?= $note_id ?>)" class="add-to-list-btn" id="addBtn">
        + Add to My List
      </button>
      <p class="added-msg" id="addedMsg" style="display:none;">Added to your list!</p>
    <?php else: ?>
      <button disabled class="add-to-list-btn">Already in List</button>
    <?php endif; ?>
  </div>

  <div class="top-right">
    <img src="chemistry.png" class="subject-icon">
  </div>
</div>

<!-- CONTENT -->
<?php foreach ($cards as $card): ?>
    <div class="subject-main-card">
      <div class="text-side">
        <p><?= nl2br(htmlspecialchars($card)) ?></p>
      </div>
    </div>
<?php endforeach; ?>

</div>

  <div class="bottom-file-section">
    <div class="item">
      <button onclick="goBack()" style="background:none;border:none;">
        <img src="back.png">
      </button>
      <p>Back</p>
    </div>
  </div>

<script>
function addToMyList(noteId) {
  fetch("add_to_list.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "note_id=" + noteId
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

<script src="script.js"></script>
</body>
</html>