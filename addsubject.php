<?php
session_start();
include 'database.php';

$note = null;

if (isset($_GET['note_id'])) {
  $note_id = (int)$_GET['note_id'];

  $stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ?");
  $stmt->bind_param("i", $note_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $note = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Subject</title>

  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Delete button on card */
    .delete-card-btn {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 28px;
      height: 28px;
      background: #ff4444;
      color: white;
      border: none;
      border-radius: 50%;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      line-height: 1;
      padding-bottom: 2px;
    }
    .delete-card-btn:hover {
      background: #cc0000;
    }
    .subject-main-card {
      position: relative;
    }
  </style>
</head>

<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="bell.png" class="bells">
    <button class="back-btn">
      <img src="back.png">
    </button> 
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>

    <label for="imageInput">
      <img id="preview" src="acc.png" alt="Upload Image">
    </label>

    <input type="file" id="imageInput" accept="image/*" hidden>

    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>



  <!-- TOP -->
  <div class="subject-top">
    <div class="left">
      <input 
        type="text" 
        id="subjectName"
        placeholder="(Subject)" 
        class="subject-input"
        value="<?= htmlspecialchars($note['title'] ?? '') ?>"
      >

      <p>
        Uploaded by:<br>
        <?php echo $_SESSION['name'] ?? 'Guest'; ?>
      </p>
    </div>

    <div class="right">
      <img src="addfile.png">
    </div>
  </div>

  <!-- BODY -->
<?php
$cards = [];

if ($note && !empty($note['content'])) {
  $decoded = json_decode($note['content'], true);
  $cards = is_array($decoded) ? $decoded : [$note['content']];
}
?>

<div class="subject-body" id="cardContainer">

<?php if (!empty($cards)): ?>
  <?php foreach ($cards as $card): ?>
    <div class="subject-main-card">
      <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
      <div class="fake-desc" contenteditable="true">
        <?= htmlspecialchars($card) ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="subject-main-card">
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <div class="fake-desc" contenteditable="true">
      (Insert desc here)
    </div>
  </div>
<?php endif; ?>

</div>
  <!-- BOTTOM -->
  <div class="bottom-add-section">

    <div class="item">
      <button type="button" onclick="upload()">
        <img src="upload.png">
      </button>
      <p>Upload</p>
    </div>

  <div class="item">
  <button onclick="addCard()">
    <img src="add.png">
  </button>
  <p>Add</p>
</div>

    <div class="item">
      <img src="flashcards.png">
      <p>Flash Cards</p>
    </div>

  </div>

</div>
<script>

let NOTE_ID = <?= isset($note['note_id']) ? $note['note_id'] : 'null' ?>;
const STORAGE_KEY = 'subjectDraft_' + (NOTE_ID || 'new');

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

/* ========== CONTENT CHECK ========== */

function hasRealContent() {
  const title = document.getElementById("subjectName").value.trim();
  const cards = getAllContent();
  const hasRealCards = cards.some(text => text && text !== "(Insert desc here)");
  return title !== "" || hasRealCards;
}

/* ========== CARD MANAGEMENT ========== */

function addCard() {
  const container = document.getElementById("cardContainer");

  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";
  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <div class="fake-desc" contenteditable="true">
      (Insert desc here)
    </div>
  `;

  container.appendChild(newCard);
  persistToSession();
}

function deleteCard(btn) {
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
      card.innerHTML = `<button class="delete-card-btn" onclick="deleteCard(this)">-</button><div class="fake-desc" contenteditable="true">${escapeHtml(text)}</div>`;
      container.appendChild(card);
    });
  } else {
    container.innerHTML = `
      <div class="subject-main-card">
        <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
        <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
      </div>
    `;
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/* ========== DATABASE SAVE (only on back button) ========== */

function saveToDatabase() {
  if (!hasRealContent()) {
    clearLocalState();
    return Promise.resolve();
  }

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
    if (!NOTE_ID && data.note_id) {
      NOTE_ID = data.note_id;
    }
    clearLocalState();
  })
  .catch(err => console.error('Save failed:', err));
}

/* ========== EVENT LISTENERS ========== */

window.addEventListener("load", restoreFromSession);

document.getElementById("cardContainer").addEventListener("input", function(e) {
  if (e.target.classList.contains("fake-desc")) {
    persistToSession();
  }
}, true);

document.getElementById("subjectName").addEventListener("input", persistToSession);

document.querySelector(".back-btn").addEventListener("click", async function (e) {
  e.preventDefault();
  await saveToDatabase();
  history.back();
});

</script>

<script src="script.js"></script>

</body>
</html>