header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

<?php
session_start();
include 'database.php';

$note = null;
$cards = []; // FIX 1: always define

if (isset($_GET['note_id'])) {
  $note_id = (int)$_GET['note_id'];

  $stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ?");
  $stmt->bind_param("i", $note_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $note = $result->fetch_assoc();

  //  FIX 2: safely decode cards
  if ($note && !empty($note['content'])) {
    $decoded = json_decode($note['content'], true);
    if (is_array($decoded)) {
      $cards = $decoded;
    }
  }
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

  .subject-image {
  width: 70px;
  height: 70px;
  object-fit: contain;
  cursor: pointer;
  opacity: 0.7;
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
      <label class="subject-image-label">
    <img 
      id="subjectImagePreview"
      src="<?= !empty($note['subject_image']) ? htmlspecialchars($note['subject_image']) : 'addfile.png' ?>"
      class="subject-image"
    >
    <input type="file" id="subjectImageInput" accept="image/*" hidden>
  </label>
    </div>
  </div>

  <!-- BODY -->
<div id="cardContainer">

<?php if (!empty($cards)): ?>
  <?php foreach ($cards as $card): 
    $title = $card['title'] ?? '';
    $desc  = $card['desc'] ?? '';
    $img   = $card['img'] ?? 'addfile.png';
  ?>
    <div class="subject-main-card">
      <button class="delete-card-btn" onclick="deleteCard(this)">-</button>

      <label>
        <img src="<?= htmlspecialchars($img) ?>" class="card-image-preview">
        <input type="file" class="card-image-input" hidden>
      </label>

      <input class="card-title" value="<?= htmlspecialchars($title) ?>">

      <div class="fake-desc" contenteditable="true">
        <?= htmlspecialchars($desc) ?>
      </div>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <!-- DEFAULT CARD -->
  <div class="subject-main-card">
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>

    <label>
      <img src="addfile.png" class="card-image-preview">
      <input type="file" class="card-image-input" hidden>
    </label>

    <input class="card-title" placeholder="(Insert title here)">

    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  </div>
<?php endif; ?>

</div>

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

  const hasRealCards = cards.some(card => {
    const hasTitle = card.title && card.title.trim() !== "" && card.title !== "(Insert title here)";
    const hasDesc  = card.desc && card.desc.trim() !== "" && card.desc !== "(Insert desc here)";
    const hasImage = card.img && !card.img.includes("addfile.png");

    return hasTitle || hasDesc || hasImage;
  });

  return title !== "" || hasRealCards;
}

/* ========== CARD MANAGEMENT ========== */

function addCard() {
  const container = document.getElementById("cardContainer");

  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";

  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>

    <label class="card-image-label">
      <img src="addfile.png" class="card-image-preview">
      <input type="file" class="card-image-input" accept="image/*" hidden>
    </label>

    <input type="text" class="card-title" placeholder="(Insert title here)">

    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  `;

  container.appendChild(newCard);
  attachImageHandler(newCard);
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
  const cards = document.querySelectorAll(".subject-main-card");

  let data = [];

  cards.forEach(card => {
    const title = card.querySelector(".card-title")?.value || "";
    const desc = card.querySelector(".fake-desc")?.innerText || "";
    const img = card.querySelector(".card-image-preview")?.src || "";

    data.push({
      title,
      desc,
      img
    });
  });

  return data;
}

function persistToSession() {
  setLocalState({
    title: document.getElementById("subjectName").value,
    subjectImage: subjectImageData,
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

  if (local.subjectImage) {
  subjectImageData = local.subjectImage;
  document.getElementById("subjectImagePreview").src = local.subjectImage;
}

  const container = document.getElementById("cardContainer");
  container.innerHTML = "";

  if (local.cards && local.cards.length > 0) {
    local.cards.forEach(c => {
  const card = document.createElement("div");
  card.className = "subject-main-card";

  card.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>

    <label class="card-image-label">
      <img src="${c.img || 'addfile.png'}" class="card-image-preview">
      <input type="file" class="card-image-input" accept="image/*" hidden>
    </label>

    <input type="text" class="card-title" value="${escapeHtml(c.title || '')}" placeholder="(Insert title here)">

    <div class="fake-desc" contenteditable="true">${escapeHtml(c.desc || '')}</div>
  `;

  container.appendChild(card);
  attachImageHandler(card);
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

  if (subjectImageData) {
  params.append("subject_image", subjectImageData);
}

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


function attachImageHandler(card){
  const input = card.querySelector(".card-image-input");
  const preview = card.querySelector(".card-image-preview");

  input.addEventListener("change", function(){
    const file = this.files[0];
    if(!file) return;

    const reader = new FileReader();
    reader.onload = function(e){
      preview.src = e.target.result;
    };
    reader.readAsDataURL(file);

    persistToSession();
  });
}

let subjectImageData = null;

document.getElementById("subjectImageInput").addEventListener("change", function(){
  const file = this.files[0];
  if(!file) return;

  const reader = new FileReader();
  reader.onload = function(e){
    subjectImageData = e.target.result;
    document.getElementById("subjectImagePreview").src = subjectImageData;
    persistToSession();
  };
  reader.readAsDataURL(file);
});

window.addEventListener("load", () => {
  restoreFromSession();

  //  attach image handler to ALL existing cards
  document.querySelectorAll(".subject-main-card").forEach(card => {
    attachImageHandler(card);
  });
});
</script>

<script src="script.js"></script>

</body>
</html>