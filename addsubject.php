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

    .desc-img {
      max-width: 100%;
      border-radius: 6px;
      margin: 6px 0;
      display: block;
      cursor: pointer;
      -webkit-user-select: none;
      user-select: none;
      -webkit-user-drag: none;
    }
    .desc-img.img-selected {
      outline: 2px solid #3B8BFF;
      outline-offset: 2px;
    }

    /* Remove image buttons */
    .remove-subject-img-btn,
    .remove-card-img-btn {
      position: absolute;
      top: -6px;
      right: -6px;
      width: 22px;
      height: 22px;
      background: #ff4444;
      color: white;
      border: none;
      border-radius: 50%;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      line-height: 1;
      padding: 0;
      box-shadow: 0 1px 4px rgba(0,0,0,0.3);
      opacity: 40%;
    }
    .remove-subject-img-btn:hover,
    .remove-card-img-btn:hover {
      background: #cc0000;
    }
    .remove-subject-img-btn {
      top: -8px;
      right: -8px;
      width: 24px;
      height: 24px;
    }

    /* FIX: Ensure all child elements of fake-desc inherit the Itim font */
    .fake-desc *,
    .fake-desc *::before,
    .fake-desc *::after {
      font-family: 'Itim', cursive !important;
    }

    /* But keep Inria Sans for bold/italic buttons if needed */
    .fake-desc b,
    .fake-desc strong {
      font-family: 'Itim', cursive !important;
      font-weight: bold;
    }

    .fake-desc i,
    .fake-desc em {
      font-family: 'Itim', cursive !important;
      font-style: italic;
    }

    /* Ensure divs created by Enter key inherit font */
    .fake-desc div,
    .fake-desc p,
    .fake-desc span,
    .fake-desc br {
      font-family: 'Itim', cursive !important;
    }

    /* Pull bullet/number markers inside content box so text-align moves them too */
    .fake-desc ul,
    .fake-desc ol {
      list-style-position: inside;
      padding-left: 0;
      margin-left: 0;
    }
    .fake-desc li {
      list-style-position: inside;
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
    <img src="bell.png" onclick="notif()" class="bells">
    <button class="back-btn" id="backBtn">
      <img src="back.png">
    </button> 
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>
     <!-- Profile Image Fetch -->
    <?php
// Fetch current user's profile image fresh from DB
$student_id = $_SESSION['student_id'] ?? 0;
$pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
$pfp_stmt->bind_param("i", $student_id);
$pfp_stmt->execute();
$pfp_result = $pfp_stmt->get_result()->fetch_assoc();
$profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';

// Cache bust: append timestamp so browser always fetches fresh
$image_src = $profile_image;
if (strpos($image_src, 'data:') === 0) {
    // base64 — no cache bust needed, but force reload with unique session
    $image_src = $profile_image;
} else {
    $image_src .= '?t=' . time();
}
?>

<!-- Profile Image Upload -->
<form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
  <label for="imageInput" style="cursor: pointer; position: relative;">
    <img id="preview" src="<?= htmlspecialchars($image_src) ?>" 
         style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;"
         onerror="this.src='acc.png'">
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
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <div class="addsubject-scroll">
  <div class="subject-top">
    <div class="left">
      <input type="text" id="subjectName" placeholder="(Subject)" class="subject-input"
        value="<?= htmlspecialchars($note['title'] ?? '') ?>">
      <p>Uploaded by:<br><?php echo $_SESSION['name'] ?? 'Guest'; ?></p>
    </div>
    <div class="right" style="position:relative;">
      <button class="remove-subject-img-btn" onclick="removeSubjectImage()" title="Remove image">×</button>
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
      <div class="card-image-wrap" style="position:relative;">
        <button class="remove-card-img-btn" onclick="removeCardImage(this)" title="Remove image">×</button>
        <label class="card-image-label">
          <img src="<?= htmlspecialchars($img) ?>" class="card-image-preview" data-img="<?= htmlspecialchars($img) ?>">
          <input type="file" class="card-image-input" accept="image/*" hidden>
        </label>
      </div>
      <input class="card-title" value="<?= htmlspecialchars($title) ?>">
      <div class="fake-desc" contenteditable="true"><?= $desc ?></div>
    </div>
  <?php endforeach; ?>

<?php else: ?>
  <div class="subject-main-card">
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <div class="card-image-wrap" style="position:relative;">
      <button class="remove-card-img-btn" onclick="removeCardImage(this)" title="Remove image">×</button>
      <label class="card-image-label">
        <img src="file.png" class="card-image-preview" data-img="file.png">
        <input type="file" class="card-image-input" accept="image/*" style="display:none;">
      </label>
    </div>
    <input class="card-title" placeholder="(Insert title here)">
    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  </div>
<?php endif; ?>

</div>
</div>

</div>

  <!-- FORMATTING TOOLBAR (hidden by default, toggled by Text button) -->
  <div class="formatting-toolbar" id="formattingToolbar" style="display:none;" onmousedown="event.preventDefault()">
    <div class="toolbar-group">
      <button class="toolbar-btn" onclick="smartAlign('left')" title="Align Left">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="2"/><rect x="3" y="10" width="12" height="2"/><rect x="3" y="15" width="18" height="2"/><rect x="3" y="20" width="12" height="2"/></svg>
      </button>
      <button class="toolbar-btn" onclick="smartAlign('center')" title="Center">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="2"/><rect x="6" y="10" width="12" height="2"/><rect x="3" y="15" width="18" height="2"/><rect x="6" y="20" width="12" height="2"/></svg>
      </button>
      <button class="toolbar-btn" onclick="smartAlign('right')" title="Align Right">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="2"/><rect x="9" y="10" width="12" height="2"/><rect x="3" y="15" width="18" height="2"/><rect x="9" y="20" width="12" height="2"/></svg>
      </button>
      <button class="toolbar-btn" onclick="smartAlign('justify')" title="Justify">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="2"/><rect x="3" y="10" width="18" height="2"/><rect x="3" y="15" width="18" height="2"/><rect x="3" y="20" width="18" height="2"/></svg>
      </button>
    </div>
    <div class="toolbar-group">
      <button class="toolbar-btn" onclick="fmt('insertUnorderedList')" title="Bullet List">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><circle cx="4" cy="7" r="1.5"/><rect x="8" y="6" width="13" height="2"/><circle cx="4" cy="12" r="1.5"/><rect x="8" y="11" width="13" height="2"/><circle cx="4" cy="17" r="1.5"/><rect x="8" y="16" width="13" height="2"/></svg>
      </button>
      <button class="toolbar-btn" onclick="fmt('insertOrderedList')" title="Numbered List">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><text x="2" y="9" font-size="7" font-family="sans-serif">1.</text><rect x="10" y="6" width="11" height="2"/><text x="2" y="14" font-size="7" font-family="sans-serif">2.</text><rect x="10" y="11" width="11" height="2"/><text x="2" y="19" font-size="7" font-family="sans-serif">3.</text><rect x="10" y="16" width="11" height="2"/></svg>
      </button>
    </div>
    <div class="toolbar-group">
      <button class="toolbar-btn" onclick="fmt('bold')" title="Bold"><b>B</b></button>
      <button class="toolbar-btn" onclick="fmt('italic')" title="Italic"><i>I</i></button>
      <button class="toolbar-btn" onclick="fmt('underline')" title="Underline"><u>U</u></button>
      <button class="toolbar-btn" onclick="fmt('strikeThrough')" title="Strikethrough"><s>abc</s></button>
      <button class="toolbar-btn" onclick="fmtSub()" title="Subscript">x<sub>2</sub></button>
      <button class="toolbar-btn" onclick="fmtSup()" title="Superscript">x<sup>2</sup></button>
    </div>
    <div class="toolbar-group">
      <button class="toolbar-btn toolbar-btn-addcard" onclick="addCard()" title="Add Card">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="5" width="18" height="14" rx="2"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg>
      </button>
      <button class="toolbar-btn toolbar-btn-addimg" onclick="addImageToDesc()" title="Add Image">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      </button>
    </div>

  </div>

  <div class="bottom-add-section">
    <div class="item" id="textNavItem">
      <button type="button" onclick="toggleToolbar()">
        <img src="text.png" style="width:80px;height:70px;object-fit:contain;">
      </button>
      <p>Text</p>
    </div>
    <div class="item">
      <button type="button" onclick="upload()">
        <img src="upload.png">
      </button>
      <p>Uploads</p>
    </div>
    <div class="item">
      <button onclick="goToFlashcards()">
        <img src="flashcards.png">
      </button>
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
    <div class="card-image-wrap" style="position:relative;">
      <button class="remove-card-img-btn" onclick="removeCardImage(this)" title="Remove image">×</button>
      <label class="card-image-label">
        <img src="file.png" class="card-image-preview" data-img="file.png">
        <input type="file" class="card-image-input" accept="image/*" hidden>
      </label>
    </div>
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

    const descEl = card.querySelector(".fake-desc");
    let desc = "";
    if (descEl) {
      const clone = descEl.cloneNode(true);
      // Remove selection highlight before saving
      clone.querySelectorAll('.img-selected').forEach(el => el.classList.remove('img-selected'));
      // Clean up temporary event attributes (but KEEP style for alignment!)
      clone.querySelectorAll('img').forEach(imgEl => {
        imgEl.removeAttribute('onclick');
        imgEl.removeAttribute('onmousedown');
        // Ensure class is set for next load
        if (!imgEl.classList.contains('desc-img')) {
          imgEl.classList.add('desc-img');
        }
      });
      desc = clone.innerHTML;
    }

    data.push({
      title: card.querySelector(".card-title")?.value || "",
      desc: desc,
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
        <div class="card-image-wrap" style="position:relative;">
          <button class="remove-card-img-btn" onclick="removeCardImage(this)" title="Remove image">×</button>
          <label class="card-image-label">
            <img src="${escapeHtml(img)}" class="card-image-preview" data-img="${escapeHtml(img)}">
            <input type="file" class="card-image-input" accept="image/*" hidden>
          </label>
        </div>
        <input type="text" class="card-title" value="${escapeHtml(c.title || '')}" placeholder="(Insert title here)">
        <div class="fake-desc" contenteditable="true">${c.desc || ''}</div>
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

// FIX: Handle Enter key to ensure new lines keep the Itim font
document.getElementById("cardContainer").addEventListener("keydown", function(e) {
  if (e.key === 'Enter' && e.target.classList.contains("fake-desc")) {
    // Let browser handle the line break, but we'll clean up fonts after
    setTimeout(function() {
      fixDescFonts(e.target);
    }, 0);
  }
}, true);

function fixDescFonts(desc) {
  // Ensure all text nodes and elements use Itim font
  const walker = document.createTreeWalker(desc, NodeFilter.SHOW_ELEMENT, null, false);
  let node;
  while (node = walker.nextNode()) {
    if (node.style && node.style.fontFamily && !node.style.fontFamily.includes('Itim')) {
      node.style.fontFamily = "'Itim', cursive";
    }
  }
}

document.getElementById("subjectName").addEventListener("input", persistToSession);

document.getElementById("backBtn").addEventListener("click", async function(e) {
  e.preventDefault();
  showToast('Saving...');

  try {
    await saveToDatabase();
    await saveFlashcardsFromSession();
    window.location.href = "Uploaded notes.php";
  } catch (err) {
    console.error('Save error:', err);
    showToast('Save failed!');
    window.location.href = "Uploaded notes.php";
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
  // Ensure all images in descriptions have proper classes
  document.querySelectorAll('.fake-desc img').forEach(img => {
    if (!img.classList.contains('desc-img')) {
      img.classList.add('desc-img');
      img.setAttribute('contenteditable', 'false');
      img.setAttribute('draggable', 'false');
      img.setAttribute('unselectable', 'on');
    }
  });
  setupImageClickHandlers();
  checkForFlashcardsData();
});

function setupImageClickHandlers() {
  const container = document.getElementById('cardContainer');
  if (!container) return;

  // Simple click handler - no capture phase, just direct handling
  container.addEventListener('click', function(e) {
    const img = e.target.closest('.desc-img');
    if (!img) {
      // Clicked outside an image - remove selection
      document.querySelectorAll('.desc-img').forEach(i => i.classList.remove('img-selected'));
      return;
    }
    // Clicked on an image - select it
    e.preventDefault();
    e.stopPropagation();
    selectImage(img);
  });
}

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

/* ============================================================
   FORMATTING TOOLBAR
   ============================================================ */

let lastFocusedDesc = null;

document.addEventListener('focusin', function(e) {
  if (e.target && e.target.classList.contains('fake-desc')) {
    lastFocusedDesc = e.target;
  }
});

function fmt(cmd) {
  // Use CSS-based styling so bold works reliably even when font-weight is inherited
  document.execCommand('styleWithCSS', false, true);
  document.execCommand(cmd, false, null);
  document.execCommand('styleWithCSS', false, false);
  persistToSession();
}

function fmtSup() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('superscript', false, null);
  document.execCommand('styleWithCSS', false, false);
  persistToSession();
}

function fmtSub() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('subscript', false, null);
  document.execCommand('styleWithCSS', false, false);
  persistToSession();
}

function smartAlign(align) {
  const target = lastFocusedDesc || document.querySelector('.fake-desc');
  if (!target) return;
  target.focus();

  // First check if any image has the .img-selected class
  let selectedImg = document.querySelector('.desc-img.img-selected');

  // If not, try to detect from selection
  if (!selectedImg) {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      const node = sel.anchorNode;
      if (node) {
        if (node.nodeType === 1 && node.tagName === 'IMG') {
          selectedImg = node;
        } else if (node.parentElement && node.parentElement.tagName === 'IMG') {
          selectedImg = node.parentElement;
        }
      }
      if (!selectedImg && sel.focusNode) {
        const fNode = sel.focusNode;
        if (fNode.nodeType === 1 && fNode.tagName === 'IMG') {
          selectedImg = fNode;
        } else if (fNode.parentElement && fNode.parentElement.tagName === 'IMG') {
          selectedImg = fNode.parentElement;
        }
      }
    }
  }

  // If an image is selected, align the image
  if (selectedImg) {
    selectedImg.style.display = 'block';
    if (align === 'left') {
      selectedImg.style.float = 'left';
      selectedImg.style.marginRight = '12px';
      selectedImg.style.marginLeft = '0';
      selectedImg.style.clear = 'none';
    } else if (align === 'right') {
      selectedImg.style.float = 'right';
      selectedImg.style.marginLeft = '12px';
      selectedImg.style.marginRight = '0';
      selectedImg.style.clear = 'none';
    } else {
      selectedImg.style.float = 'none';
      selectedImg.style.marginLeft = 'auto';
      selectedImg.style.marginRight = 'auto';
    }
    persistToSession();
    return;
  }

  // Otherwise, do normal text alignment
  const cmd = align === 'left' ? 'justifyLeft' : align === 'center' ? 'justifyCenter' : align === 'right' ? 'justifyRight' : 'justifyFull';
  fmt(cmd);
}

function toggleToolbar() {
  const toolbar = document.getElementById('formattingToolbar');
  const textItem = document.getElementById('textNavItem');
  const scroll = document.querySelector('.addsubject-scroll');
  const isVisible = toolbar.style.display === 'flex';

  if (isVisible) {
    toolbar.style.display = 'none';
    toolbar.classList.remove('toolbar-visible');
    textItem.classList.remove('bottom-item-active');
    if (scroll) scroll.classList.remove('toolbar-open');
  } else {
    toolbar.style.display = 'flex';
    // Re-trigger animation by removing then adding the class
    toolbar.classList.remove('toolbar-visible');
    void toolbar.offsetWidth; // force reflow
    toolbar.classList.add('toolbar-visible');
    textItem.classList.add('bottom-item-active');
    if (scroll) scroll.classList.add('toolbar-open');
    // Focus last active desc so formatting works immediately
    const target = lastFocusedDesc || document.querySelector('.fake-desc');
    if (target) target.focus();
  }
}

function addImageToDesc() {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.style.display = 'none';
  document.body.appendChild(input);

  input.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) { input.remove(); return; }

    const reader = new FileReader();
    reader.onload = function(e) {
      const target = lastFocusedDesc || document.querySelector('.fake-desc');
      if (!target) { input.remove(); return; }

      target.focus();
      const img = makeDescImage(e.target.result);

      const sel = window.getSelection();
      if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        range.insertNode(img);
        range.setStartAfter(img);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      } else {
        target.appendChild(img);
      }

      // Select the image immediately after insertion
      selectImage(img);
      persistToSession();
      input.remove();
    };
    reader.readAsDataURL(file);
  });

  input.click();
}

function makeDescImage(src) {
  const img = document.createElement('img');
  img.src = src;
  img.className = 'desc-img';
  img.setAttribute('contenteditable', 'false');
  img.setAttribute('draggable', 'false');
  img.setAttribute('unselectable', 'on');
  // Direct onclick handler on the image itself
  img.onclick = function(e) {
    e.preventDefault();
    e.stopPropagation();
    selectImage(this);
    return false;
  };
  img.onmousedown = function(e) {
    e.preventDefault();
    e.stopPropagation();
    selectImage(this);
    return false;
  };
  return img;
}

function selectImage(img) {
  // Focus the parent desc first
  const desc = img.closest('.fake-desc');
  if (desc) {
    desc.focus();
    lastFocusedDesc = desc;
  }

  // Select the entire img element (not contents, since img has no children)
  try {
    const range = document.createRange();
    range.selectNode(img);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  } catch (err) {
    // Fallback
    const range = document.createRange();
    range.setStartBefore(img);
    range.setEndAfter(img);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  }

  // Visual feedback
  document.querySelectorAll('.desc-img').forEach(i => i.classList.remove('img-selected'));
  img.classList.add('img-selected');
}

function removeSubjectImage() {
  subjectImageData = null;
  document.getElementById("subjectImagePreview").src = "file.png";
  persistToSession();
}

function removeCardImage(btn) {
  const wrap = btn.closest('.card-image-wrap');
  if (!wrap) return;
  const preview = wrap.querySelector('.card-image-preview');
  const input = wrap.querySelector('.card-image-input');
  if (preview) {
    preview.src = "file.png";
    preview.setAttribute("data-img", "file.png");
  }
  if (input) input.value = "";
  persistToSession();
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