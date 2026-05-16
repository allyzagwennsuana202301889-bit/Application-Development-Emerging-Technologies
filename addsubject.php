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

    .toast {
      position: fixed;
      top: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      background: #333;
      color: white;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 14px;
      z-index: 300;
      opacity: 0;
      transition: 0.3s;
      pointer-events: none;
    }
    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    .flashcards-badge {
      position: fixed;
      top: 70px;
      right: 16px;
      background: #87CEEB;
      color: #1a1a2e;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-family: 'Inria Sans', sans-serif;
      z-index: 50;
      display: none;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .flashcards-badge.show { display: flex; }
    .flashcards-badge img {
      width: 16px; height: 16px;
    }
  </style>
</head>

<body>

<div class="toast" id="toast"></div>
<div class="flashcards-badge" id="flashcardsBadge">
  <img src="flashcards.png"> <span id="flashcardsCount">0 flashcards</span>
</div>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="bell.png" class="bells">
    <button class="back-btn" id="backBtn">
      <img src="back.png">
    </button> 
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
    
    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
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
         <button onclick="goToFlashcards()"><img src="flashcards.png"></button>
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
  sessionStorage.removeItem('subjectDraft_new');
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2500);
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

function getAllContent() {
  const cards = document.querySelectorAll(".subject-main-card");
  let data = [];

  cards.forEach(card => {
    const preview = card.querySelector(".card-image-preview");
    let img = preview?.getAttribute("data-img") || "file.png";
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
  let local = getLocalState();

  if (!local && NOTE_ID) {
    const legacyRaw = sessionStorage.getItem('subjectDraft_new');
    if (legacyRaw) {
      local = JSON.parse(legacyRaw);
      setLocalState(local);
      sessionStorage.removeItem('subjectDraft_new');
    }
  }

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
      const oldKey = 'subjectDraft_new';
      const newKey = 'subjectDraft_' + NOTE_ID;
      const oldData = sessionStorage.getItem(oldKey);
      if (oldData) {
        sessionStorage.setItem(newKey, oldData);
        sessionStorage.removeItem(oldKey);
      }
    }
    clearLocalState();
    return data;
  })
  .catch(err => {
    console.error('Save failed:', err);
    throw err;
  });
}

// ============================================
// FIX: Replaced savequiz.php with autosavequiz.php
// ============================================
async function saveFlashcardsFromSession() {
  const flashKey = 'flashcardsData_' + (NOTE_ID || 'new');
  const flashDataRaw = sessionStorage.getItem(flashKey);

  if (!flashDataRaw) return;

  try {
    const flashData = JSON.parse(flashDataRaw);
    if (!flashData.cards || flashData.cards.length === 0) return;

    let targetNoteId = NOTE_ID;
    if (!targetNoteId) {
      const subjectData = await saveToDatabase();
      if (subjectData && subjectData.note_id) {
        targetNoteId = subjectData.note_id;
        NOTE_ID = targetNoteId;
      } else {
        console.error('Could not save subject to get note_id');
        return;
      }
    }

    // FIX: Build JSON payload for autosavequiz.php
    const payload = [];
    for (let i = 0; i < flashData.cards.length; i++) {
      const c = flashData.cards[i];
      if (!c.question || !c.question.trim()) continue;
      if (!c.question_type) continue;

      payload.push({
        note_id: targetNoteId,
        subject: document.getElementById("subjectName").value.trim() || flashData.subject || 'Untitled',
        question: c.question,
        type: c.question_type,
        question_order: i,
        choices: c.question_type === 'choice' ? JSON.stringify(c.choices || []) : null,
        answer: c.question_type === 'identification' ? (c.correct_answer || '') : null,
        question_image: c.question_image || null
      });
    }

    if (payload.length === 0) return;

    // FIX: Use autosavequiz.php with JSON Content-Type
    const res = await fetch('autosavequiz.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ questions: payload })
    });
    const data = await res.json();
    
    if (data.success) {
      showToast(`${data.saved} flashcards saved!`);
    }

    sessionStorage.removeItem(flashKey);
    sessionStorage.removeItem('flashcardsData_new');

  } catch (err) {
    console.error('Error processing flashcards:', err);
  }
}

document.getElementById("cardContainer").addEventListener("input", function(e) {
  if (e.target.classList.contains("fake-desc")) {
    persistToSession();
  }
}, true);

document.getElementById("subjectName").addEventListener("input", persistToSession);

document.getElementById("backBtn").addEventListener("click", async function(e) {
  e.preventDefault();
  showToast('Saving...');

  try {
    await saveToDatabase();
    await saveFlashcardsFromSession();
    window.location.href = "notes.php";
  } catch (err) {
    console.error('Save error:', err);
    showToast('Save failed!');
    window.location.href = "notes.php";
  }
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
  checkForFlashcardsData();
});

function checkForFlashcardsData() {
  const flashKey = 'flashcardsData_' + (NOTE_ID || 'new');
  const flashDataRaw = sessionStorage.getItem(flashKey);

  if (flashDataRaw) {
    try {
      const flashData = JSON.parse(flashDataRaw);
      if (flashData.cards && flashData.cards.length > 0) {
        const badge = document.getElementById('flashcardsBadge');
        const count = document.getElementById('flashcardsCount');
        count.textContent = `${flashData.cards.length} flashcard${flashData.cards.length !== 1 ? 's' : ''} ready`;
        badge.classList.add('show');
      }
    } catch (e) {
      console.error('Error parsing flashcards data:', e);
    }
  }
}

function goToFlashcards() {
  const title = document.getElementById("subjectName").value.trim();

  if (!title) {
    alert("Please enter a subject name first!");
    return;
  }

  persistToSession();

  if (!NOTE_ID) {
    saveToDatabase().then(data => {
      if (data && data.note_id) {
        NOTE_ID = data.note_id;
        const params = new URLSearchParams();
        params.append("subject", title);
        params.append("note_id", NOTE_ID);
        window.location.href = "flashcards.php?" + params.toString();
      }
    }).catch(err => {
      console.error('Failed to save subject:', err);
      const params = new URLSearchParams();
      params.append("subject", title);
      window.location.href = "flashcards.php?" + params.toString();
    });
  } else {
    const params = new URLSearchParams();
    params.append("subject", title);
    params.append("note_id", NOTE_ID);
    window.location.href = "flashcards.php?" + params.toString();
  }
}

function goToQuizzes() {
  const title = document.getElementById("subjectName").value.trim();
  const params = new URLSearchParams();
  if (title) params.append("subject", title);
  if (NOTE_ID) params.append("note_id", NOTE_ID);
  window.location.href = "quiz-review.php?" + params.toString();
}
</script>
<script src="script.js"></script>
</body>
</html>