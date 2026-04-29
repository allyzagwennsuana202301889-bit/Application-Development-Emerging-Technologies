<?php
session_start();
include 'database.php';

$note = null;
$cards = [];

if (isset($_GET['note_id'])) {
  $note_id = (int)$_GET['note_id'];
  $stmt = $conn->prepare("SELECT * FROM notes WHERE note_id = ?");
  $stmt->bind_param("i", $note_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $note = $result->fetch_assoc();

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
    .delete-card-btn:hover { background: #cc0000; }

  </style>
</head>

<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="bell.png" class="bells">
    <button class="back-btn">
      <img src="back.png">
    </button> 
  </nav>

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

  <div class="overlay"></div>

  <div class="subject-top">
    <div class="left">
      <input type="text" id="subjectName" placeholder="(Subject)" class="subject-input"
        value="<?= htmlspecialchars($note['title'] ?? '') ?>">
      <p>Uploaded by:<br><?php echo $_SESSION['name'] ?? 'Guest'; ?></p>
    </div>
    <div class="right">
      
      <label class="subject-image-label">
        
        <img id="subjectImagePreview"
             src="<?= !empty($note['subject_image']) ? htmlspecialchars($note['subject_image']) : 'file.png' ?>"
             class="subject-image">
        <input type="file" id="subjectImageInput" accept="image/*" hidden>
      </label>
    </div>
  </div>

<div id="cardContainer">

<?php if (!empty($cards)): ?>
  <?php foreach ($cards as $card): 
    $title = $card['title'] ?? '';
    $desc  = $card['desc'] ?? '';
    $img   = (!empty($card['img']) && $card['img'] !== 'file.png') ? $card['img'] : 'file.png';
  ?>
    <div class="subject-main-card">
      <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
      <label class="card-image-label">
        <img src="<?= htmlspecialchars($img) ?>" class="card-image-preview" data-img="<?= htmlspecialchars($img) ?>">
        <input type="file" class="card-image-input" accept="image/*" hidden>
      </label>
      <input class="card-title" value="<?= htmlspecialchars($title) ?>">
      <div class="fake-desc" contenteditable="true"><?= htmlspecialchars($desc) ?></div>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <div class="subject-main-card">
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <label class="card-image-label">
      <img src="file.png" class="card-image-preview" data-img="file.png">
      <input type="file" class="card-image-input" accept="image/*" style="display:none;">
    </label>
    <input class="card-title" placeholder="(Insert title here)">
    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  </div>
<?php endif; ?>

</div>

</div>

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

function hasRealContent() {
  const title = document.getElementById("subjectName").value.trim();
  const cards = getAllContent();
  const hasRealCards = cards.some(card => {
    const hasTitle = card.title && card.title.trim() !== "" && card.title !== "(Insert title here)";
    const hasDesc  = card.desc && card.desc.trim() !== "" && card.desc !== "(Insert desc here)";
    const hasImage = card.img && card.img !== "file.png" && !card.img.includes("file.png");
    return hasTitle || hasDesc || hasImage;
  });
  return title !== "" || hasRealCards;
}

function addCard() {
  const container = document.getElementById("cardContainer");
  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";
  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <label class="card-image-label">
      <img src="file.png" class="card-image-preview" data-img="file.png">
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

/* ========== GET CONTENT - READS data-img INSTEAD OF .src ========== */
function getAllContent() {
  const cards = document.querySelectorAll(".subject-main-card");
  let data = [];

  cards.forEach(card => {
    const preview = card.querySelector(".card-image-preview");
    // Read from data-img attribute, NOT .src (which gives absolute URL)
    let img = preview?.getAttribute("data-img") || "file.png";
    
    // Normalize if it's somehow still an absolute URL to file.png
    if (img.includes('file.png') && !img.startsWith('data:')) {
      img = 'file.png';
    }

    data.push({
      title: card.querySelector(".card-title")?.value || "",
      desc: card.querySelector(".fake-desc")?.innerText || "",
      img: img
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
      const img = c.img || 'file.png';
      card.innerHTML = `
        <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
        <label class="card-image-label">
          <img src="${escapeHtml(img)}" class="card-image-preview" data-img="${escapeHtml(img)}">
          <input type="file" class="card-image-input" accept="image/*" hidden>
        </label>
        <input type="text" class="card-title" value="${escapeHtml(c.title || '')}" placeholder="(Insert title here)">
        <div class="fake-desc" contenteditable="true">${escapeHtml(c.desc || '')}</div>
      `;
      container.appendChild(card);
      attachImageHandler(card);
    });
  } else if (container.children.length === 0) {
    addCard();
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

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

document.getElementById("cardContainer").addEventListener("input", function(e) {
  if (e.target.classList.contains("fake-desc")) {
    persistToSession();
  }
}, true);

document.getElementById("subjectName").addEventListener("input", persistToSession);

document.querySelector(".back-btn").addEventListener("click", async function(e) {
  e.preventDefault();
  await saveToDatabase();
  history.back();
});

function attachImageHandler(card) {
  const input = card.querySelector(".card-image-input");
  const preview = card.querySelector(".card-image-preview");
  if (!input || !preview) return;

  input.addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.setAttribute("data-img", e.target.result);
    };
    reader.readAsDataURL(file);
  });
}

let subjectImageData = null;

document.getElementById("subjectImageInput").addEventListener("change", function() {
  const file = this.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    subjectImageData = e.target.result;
    document.getElementById("subjectImagePreview").src = subjectImageData;
    persistToSession();
  };
  reader.readAsDataURL(file);
});

window.addEventListener("load", () => {
  restoreFromSession();
  document.querySelectorAll(".subject-main-card").forEach(card => {
    attachImageHandler(card);
  });
});
</script>
<script src="script.js"></script>
</body>
</html>