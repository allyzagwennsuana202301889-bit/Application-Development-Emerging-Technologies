<?php
session_start();
include 'database.php';

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;

// JOIN notes + student
$stmt = $conn->prepare("
    SELECT notes.*, student.name AS uploader_name
    FROM notes
    JOIN student ON notes.student_id = student.student_id
    WHERE notes.note_id = ?
");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Note not found.";
    exit;
}

// Parse content: could be JSON array or plain text
$cards = [];
if (!empty($note['content'])) {
    $decoded = json_decode($note['content'], true);
    if (is_array($decoded)) {
        $cards = $decoded;
    } else {
        $cards = [$note['content']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Note</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <button class="back-btn" style="background:none;border:none;padding:0;">
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

<div class="subject-content" id="mainContent">

<div class="subject-top-card">
  <div class="top-left">
    <input 
      type="text" 
      id="subjectName"
      class="subject-title-input"
      value="<?= htmlspecialchars($note['title']) ?>"
      placeholder="(Subject)"
      readonly
    >
    <p class="uploaded"><strong>Uploaded by:</strong><br><?= htmlspecialchars($note['uploader_name']) ?></p>
  </div>

  <div class="top-right">
    <img src="chemistry.png" class="subject-icon">
  </div>
</div>

<!-- CONTENT -->
<div id="cardContainer">
<?php foreach ($cards as $card): ?>
    <div class="subject-main-card">
      <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
      <div class="text-side fake-desc" contenteditable="false">
        <?= nl2br(htmlspecialchars($card)) ?>
      </div>
    </div>
<?php endforeach; ?>
</div>

</div>

  <div class="bottom-file-section">
    <div class="item">
      <button id="editSaveBtn" style="background:none;border:none;">
        <img src="Edit.png" id="editSaveImg">
      </button>
      <p id="editSaveText">Edit</p>
    </div>

    <div class="item add-btn-wrapper" id="addBtnWrapper">
      <button onclick="addCard()" style="background:none;border:none;">
        <img src="add.png">
      </button>
      <p>Add</p>
    </div>

    <div class="item">
      <button onclick="quiz()" style="background:none;border:none;"><img src="flashcards.png"></button>
      <p>Flash Cards</p>
    </div>
  </div>

<script>

let NOTE_ID = <?= isset($note['note_id']) ? $note['note_id'] : 'null' ?>;
const STORAGE_KEY = 'viewnoteDraft_' + (NOTE_ID || 'new');
let isEditing = false;

/* ========== LOCAL STATE ========== */

function getLocalState() {
  const raw = sessionStorage.getItem(STORAGE_KEY);
  return raw ? JSON.parse(raw) : null;
}

function setLocalState(state) {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function clearLocalState() {
  sessionStorage.removeItem(STORAGE_KEY);
}

/* ========== EDIT / SAVE TOGGLE ========== */

function toggleEditMode() {
  isEditing = !isEditing;
  const mainContent = document.getElementById('mainContent');
  const titleInput = document.getElementById('subjectName');
  const descs = document.querySelectorAll('.fake-desc');
  const editSaveImg = document.getElementById('editSaveImg');
  const editSaveText = document.getElementById('editSaveText');
  const addBtnWrapper = document.getElementById('addBtnWrapper');

  if (isEditing) {
    // Switch to EDIT mode
    mainContent.classList.add('edit-mode');
    titleInput.readOnly = false;
    titleInput.focus();
    descs.forEach(el => el.contentEditable = "true");
    editSaveImg.src = "Edit.png";
    editSaveText.textContent = "Save";
    addBtnWrapper.style.display = "flex";
  } else {
    // Switch to SAVE mode
    saveToDatabase().then(() => {
      mainContent.classList.remove('edit-mode');
      titleInput.readOnly = true;
      descs.forEach(el => el.contentEditable = "false");
      editSaveImg.src = "Edit.png";
      editSaveText.textContent = "Edit";
      addBtnWrapper.style.display = "none";
    });
  }
}

/* ========== CARD MANAGEMENT ========== */

function addCard() {
  if (!isEditing) return;

  const container = document.getElementById("cardContainer");

  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";
  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <div class="text-side fake-desc" contenteditable="true">
      (Insert desc here)
    </div>
  `;

  container.appendChild(newCard);
  persistToSession();
}

function deleteCard(btn) {
  if (!isEditing) return;
  const card = btn.closest('.subject-main-card');
  if (card) {
    card.remove();
    persistToSession();
  }
}

function getAllContent() {
  const boxes = document.querySelectorAll(".fake-desc");
  let content = [];
  boxes.forEach(box => content.push(box.innerText.trim()));
  return content;
}

function persistToSession() {
  setLocalState({
    title: document.getElementById("subjectName").value,
    cards: getAllContent()
  });
}

/* ========== RESTORE ON LOAD ========== */

function restoreFromSession() {
  const local = getLocalState();
  if (!local) return;

  if (local.title) {
    document.getElementById("subjectName").value = local.title;
  }

  const container = document.getElementById("cardContainer");
  container.innerHTML = "";

  if (local.cards && local.cards.length > 0) {
    local.cards.forEach(text => {
      const card = document.createElement("div");
      card.className = "subject-main-card";
      card.innerHTML = `<button class="delete-card-btn" onclick="deleteCard(this)">-</button><div class="text-side fake-desc" contenteditable="false">${escapeHtml(text)}</div>`;
      container.appendChild(card);
    });
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/* ========== DATABASE SAVE ========== */

function saveToDatabase() {
  const cards = getAllContent();
  const title = document.getElementById("subjectName").value.trim();

  const params = new URLSearchParams();
  params.append("title", title);
  params.append("content", JSON.stringify(cards));
  params.append("type", "subject_draft");

  if (NOTE_ID) {
    params.append("note_id", NOTE_ID);
  }

  return fetch('savenote.php', {
    method: 'POST',
    body: params
  })
  .then(res => res.json())
  .then(data => {
    clearLocalState();
  })
  .catch(err => {
    console.error('Save failed:', err);
    alert('Save failed!');
  });
}

/* ========== EVENT LISTENERS ========== */

window.addEventListener("load", restoreFromSession);

document.getElementById("editSaveBtn").addEventListener("click", toggleEditMode);

document.getElementById("cardContainer").addEventListener("input", function(e) {
  if (e.target.classList.contains("fake-desc") && isEditing) {
    persistToSession();
  }
}, true);

document.getElementById("subjectName").addEventListener("input", function() {
  if (isEditing) persistToSession();
});

document.querySelector(".back-btn").addEventListener("click", async function (e) {
  e.preventDefault();
  if (isEditing) {
    await toggleEditMode(); // save first
  }
  history.back();
});

function quiz() {
  // your quiz logic
}

</script>

<script src="script.js"></script>
</body>
</html>