<?php
session_start();
include 'database.php';

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;

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
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10;
      line-height: 1;
      padding-bottom: 2px;
    }
    .edit-mode .delete-card-btn { display: flex; }
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
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10;
      line-height: 1;
      padding: 0;
      box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .edit-mode .remove-subject-img-btn,
    .edit-mode .remove-card-img-btn {
      display: flex;
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

       /* ============================================
       TOOLBAR FIXES - override style.css
       ============================================ */

    /* FIX: Toolbar was getting clipped by .container { overflow: hidden }
       because transform: translateX(-50%) creates a new containing block.
       Solution: Use left: 0 with margin: 0 auto for centering instead of transform. */
    .formatting-toolbar {
      position: fixed;
      bottom: 115px;
      left: 0;
      right: 0;
      margin: 0 auto;
      width: 100%;
      max-width: 412px;
      z-index: 200;
      border-top: 2px solid #3B8BFF;
      box-sizing: border-box;
    }

    /* Keep the animation but without transform conflict */
    .formatting-toolbar.toolbar-visible {
      animation: toolbarSlideUpFix 0.22s cubic-bezier(0.34,1.4,0.64,1) both;
    }

    @keyframes toolbarSlideUpFix {
      from { transform: translateY(100%); opacity: 0; }
      to   { transform: translateY(0);   opacity: 1; }
    }

    /* Push content up when toolbar is open */
    .subject-content.toolbar-open {
      bottom: 185px;
    }

    /* Make sure bottom nav stays below toolbar */
    .bottom-file-section {
      z-index: 150;
    }

    /* ============================================
       BOTTOM BAR FIX - White text on blue bg
       ============================================ */

    /* Force white text for ALL bottom bar items */
    .bottom-file-section .item p,
    .bottom-file-section p,
    .bottom-file-section .item {
      color: #ffffff !important;
    }

    /* Also target any text inside the bottom section */
    .bottom-file-section * {
      color: #ffffff;
    }

    /* Subject image label - only clickable in edit mode */

    /* Toolbar button active/highlight state */
    .toolbar-btn.active {
      background: #3B8BFF;
      color: white;
      border-color: #3B8BFF;
    }
    .toolbar-btn.active svg {
      fill: white;
      stroke: white;
    }
    .toolbar-btn.active b,
    .toolbar-btn.active i,
    .toolbar-btn.active u,
    .toolbar-btn.active s {
      color: white;
    }

    /* Move remove image buttons to the LEFT */
    .remove-subject-img-btn,
    .remove-card-img-btn {
      right: auto;
      left: -6px;
    }
    .remove-subject-img-btn {
      right: auto;
      left: -8px;
    }

    .subject-image-label {
      pointer-events: none;
    }
    .edit-mode .subject-image-label {
      pointer-events: auto;
    }
    </style>
<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <button class="back-btn">
      <img src="back.png" class="back-btn-img">
    </button>
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back">
    </div>
   <?php
$student_id = $_SESSION['student_id'] ?? 0;
$pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
$pfp_stmt->bind_param("i", $student_id);
$pfp_stmt->execute();
$pfp_result = $pfp_stmt->get_result()->fetch_assoc();
$profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';
$image_src = $profile_image;
if (strpos($image_src, 'data:') !== 0) {
    $image_src .= '?t=' . time();
}
?>
<form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
  <label for="imageInput" style="cursor: pointer; position: relative;">
    <img id="preview" src="<?= htmlspecialchars($image_src) ?>" 
         style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;"
         onerror="this.src='acc.png'">
  </label>
  <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
</form>
    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

<div class="subject-content" id="mainContent">

<div class="subject-top-card">
  <div class="top-left">
    <input type="text" id="subjectName" class="subject-title-input"
      value="<?= htmlspecialchars($note['title']) ?>" placeholder="(Subject)" readonly>
    <p class="uploaded"><strong>Uploaded by:</strong><br><?= htmlspecialchars($note['uploader_name']) ?></p>
  </div>
  <div class="top-right" style="position:relative;">
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
      <input type="file" class="card-image-input" hidden disabled>
    </label>
  </div>

  <input type="text" class="card-title" value="<?= htmlspecialchars($title) ?>" readonly>
  <div class="fake-desc" contenteditable="false"><?= $desc ?></div>
</div>

<?php endforeach; ?>
</div>


  <!-- FORMATTING TOOLBAR -->
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

  <!-- BOTTOM NAV -->
  <div class="bottom-file-section">
    <div class="item" id="editItem">
      <button id="editBtn" style="background:none;border:none;">
        <img src="Edit.png" style="width:80px;height:70px;object-fit:contain;">
      </button>
      <p>Edit</p>
    </div>
    <div class="item" id="textNavItem" style="display:none;">
      <button id="textBtn" style="background:none;border:none;">
        <img src="text.png" style="width:80px;height:70px;object-fit:contain;">
      </button>
      <p>Text</p>
    </div>
    <div class="item" id="saveItem" style="display:none;">
      <button id="saveBtn" style="background:none;border:none;">
        <img src="Edit.png" style="width:80px;height:70px;object-fit:contain;">
      </button>
      <p>Save</p>
    </div>
    <div class="item">
      <button onclick="quizes()" style="background:none;border:none;"><img src="flashcards.png"></button>
      <p>Flash Cards</p>
    </div>
  </div>

<script>
let NOTE_ID = <?= isset($note['note_id']) ? $note['note_id'] : 'null' ?>;
let isEditing = false;
let subjectImageData = "<?= htmlspecialchars($note['subject_image'] ?? '') ?>";
let lastFocusedDesc = null;
let selectedImage = null;

// ============================================
// IMAGE COMPRESSION UTILITY
// ============================================
function compressImage(file, maxWidth = 800, quality = 0.8) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = new Image();
      img.onload = function() {
        let width = img.width;
        let height = img.height;
        if (width > maxWidth) {
          height = Math.round(height * (maxWidth / width));
          width = maxWidth;
        }
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        // Check if image has transparency
        const hasTransparency = imageHasTransparency(ctx, width, height);

        // Use PNG for transparent images, JPEG for opaque (smaller)
        const output = hasTransparency
          ? canvas.toDataURL('image/png')
          : canvas.toDataURL('image/jpeg', quality);

        resolve(output);
      };
      img.onerror = reject;
      img.src = e.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function imageHasTransparency(ctx, width, height) {
  try {
    const imageData = ctx.getImageData(0, 0, width, height);
    const data = imageData.data;
    // Check alpha channel of every 4th byte (R,G,B,A)
    for (let i = 3; i < data.length; i += 4) {
      if (data[i] < 255) {
        return true; // Found a transparent/semi-transparent pixel
      }
    }
  } catch (e) {
    // Fallback: assume no transparency if we can't read
    return false;
  }
  return false;
}

function toggleEditMode() {
  isEditing = !isEditing;
  const mainContent = document.getElementById('mainContent');
  const titleInput = document.getElementById('subjectName');
  const descs = document.querySelectorAll('.fake-desc');
  const titles = document.querySelectorAll('.card-title');
  const imageInputs = document.querySelectorAll('.card-image-input');
  const subjectImageInput = document.getElementById("subjectImageInput");
  const editItem = document.getElementById('editItem');
  const textNavItem = document.getElementById('textNavItem');
  const saveItem = document.getElementById('saveItem');

  if (isEditing) {
    mainContent.classList.add('edit-mode');
    titleInput.readOnly = false;
    descs.forEach(el => el.contentEditable = "true");
    titles.forEach(t => t.removeAttribute("readonly"));
    imageInputs.forEach(i => i.disabled = false);
    if (subjectImageInput) subjectImageInput.disabled = false;
    editItem.style.display = 'none';
    textNavItem.style.display = 'flex';
    saveItem.style.display = 'flex';
    setupImageClickHandlers();
  } else {
    saveToDatabase().then(() => {
      mainContent.classList.remove('edit-mode');
      titleInput.readOnly = true;
      descs.forEach(el => el.contentEditable = "false");
      titles.forEach(t => t.setAttribute("readonly", true));
      imageInputs.forEach(i => i.disabled = true);
      if (subjectImageInput) subjectImageInput.disabled = true;
      editItem.style.display = 'flex';
      textNavItem.style.display = 'none';
      saveItem.style.display = 'none';
      const toolbar = document.getElementById('formattingToolbar');
      toolbar.style.display = 'none';
      toolbar.classList.remove('toolbar-visible');
      textNavItem.classList.remove('bottom-item-active');
      mainContent.classList.remove('toolbar-open');
      selectedImage = null;
    });
  }
}

function saveToDatabase() {
  const content = getAllContent();
  console.log("SAVING CONTENT:", JSON.stringify(content, null, 2));

  const payload = {
    title: document.getElementById("subjectName").value,
    content: JSON.stringify(content),
    type: "subject_draft"
  };

  if (NOTE_ID) payload.note_id = NOTE_ID;
  if (subjectImageData === null) {
    payload.subject_image = "__REMOVE__";
  } else if (subjectImageData) {
    payload.subject_image = subjectImageData;
  }

  return fetch('savenote.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(async res => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch(e) {
      console.error("Invalid JSON response:", text);
      throw new Error("Server error: " + text.substring(0, 200));
    }
  })
  .then(data => {
    console.log("SAVE RESPONSE:", data);
    if (data.note_id) NOTE_ID = data.note_id;
    return data;
  })
  .catch(err => {
    console.error(err);
    alert("Save failed: " + err.message);
    throw err;
  });
}

/* GET CONTENT - uses innerHTML to preserve images and formatting */
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
      clone.querySelectorAll('.img-selected').forEach(el => el.classList.remove('img-selected'));
      clone.querySelectorAll('img').forEach(imgEl => {
        imgEl.removeAttribute('onclick');
        imgEl.removeAttribute('onmousedown');
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

function addCard() {
  if (!isEditing) return;
  const container = document.getElementById("cardContainer");
  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";
  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <div class="card-image-wrap" style="position:relative;">
      <button class="remove-card-img-btn" onclick="removeCardImage(this)" title="Remove image">×</button>
      <label class="card-image-label">
        <img src="file.png" class="card-image-preview" data-img="file.png">
        <input type="file" class="card-image-input">
      </label>
    </div>
    <input type="text" class="card-title" placeholder="Title">
    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  `;
  container.appendChild(newCard);
  attachImageHandler(newCard);
  setupImageClickHandlers();
}

function deleteCard(btn) {
  if (!isEditing) return;
  btn.closest('.subject-main-card')?.remove();
}

function attachImageHandler(card) {
  const input = card.querySelector(".card-image-input");
  const preview = card.querySelector(".card-image-preview");
  if (!input || !preview) return;

  input.addEventListener("change", async function() {
    const file = this.files[0];
    if (!file) return;

    try {
      const compressed = await compressImage(file, 800, 0.8);
      preview.src = compressed;
      preview.setAttribute("data-img", compressed);
    } catch (err) {
      console.error("Image compression failed:", err);
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.setAttribute("data-img", e.target.result);
      };
      reader.readAsDataURL(file);
    }
  });
}

window.addEventListener("load", () => {
  document.querySelectorAll(".subject-main-card").forEach(card => {
    attachImageHandler(card);
  });
  document.querySelectorAll('.fake-desc').forEach((desc, i) => {
    console.log("LOADED DESC " + i + ":", desc.innerHTML.substring(0, 200));
    desc.querySelectorAll('img').forEach(img => {
      if (!img.classList.contains('desc-img')) {
        img.classList.add('desc-img');
        img.setAttribute('contenteditable', 'false');
        img.setAttribute('draggable', 'false');
        img.setAttribute('unselectable', 'on');
      }
      console.log("  IMAGE STYLE:", img.style.cssText);
    });
  });
  setupImageClickHandlers();
});

const subjectInput = document.getElementById("subjectImageInput");
if (subjectInput) {
  subjectInput.addEventListener("change", async function() {
    const file = this.files[0];
    if (!file) return;

    try {
      const compressed = await compressImage(file, 800, 0.8);
      subjectImageData = compressed;
      document.getElementById("subjectImagePreview").src = subjectImageData;
    } catch (err) {
      console.error("Image compression failed:", err);
      const reader = new FileReader();
      reader.onload = function(e) {
        subjectImageData = e.target.result;
        document.getElementById("subjectImagePreview").src = subjectImageData;
      };
      reader.readAsDataURL(file);
    }
  });
}

// Edit button enters edit mode
document.getElementById("editBtn").addEventListener("click", toggleEditMode);
// Save button exits edit mode
document.getElementById("saveBtn").addEventListener("click", toggleEditMode);

// Text button toggles toolbar (only when editing)
document.getElementById("textBtn").addEventListener("click", function() {
  if (!isEditing) return;
  toggleToolbar();
});

document.querySelector(".back-btn").addEventListener("click", async function(e) {
  e.preventDefault();
  if (isEditing) await toggleEditMode();
  window.location.href = "Uploaded notes.php";
});

function quizes() {
  window.location.href = "readflashcards.php?note_id=" + NOTE_ID;
}

/* ========== TOOLBAR FUNCTIONS ========== */

document.addEventListener('focusin', function(e) {
  if (e.target && e.target.classList.contains('fake-desc')) {
    lastFocusedDesc = e.target;
  }
});

function fmt(cmd) {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand(cmd, false, null);
  document.execCommand('styleWithCSS', false, false);
  updateToolbarState();
}

function fmtSup() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('superscript', false, null);
  document.execCommand('styleWithCSS', false, false);
  updateToolbarState();
}

function fmtSub() {
  document.execCommand('styleWithCSS', false, true);
  document.execCommand('subscript', false, null);
  document.execCommand('styleWithCSS', false, false);
  updateToolbarState();
}

function updateToolbarState() {
  const toolbar = document.getElementById('formattingToolbar');
  if (!toolbar || toolbar.style.display === 'none') return;

  toolbar.querySelectorAll('.toolbar-btn').forEach(btn => btn.classList.remove('active'));

  const isBold = document.queryCommandState('bold');
  const isItalic = document.queryCommandState('italic');
  const isUnderline = document.queryCommandState('underline');
  const isStrike = document.queryCommandState('strikeThrough');
  const isSub = document.queryCommandState('subscript');
  const isSup = document.queryCommandState('superscript');
  const isBullet = document.queryCommandState('insertUnorderedList');
  const isNumber = document.queryCommandState('insertOrderedList');

  const buttons = toolbar.querySelectorAll('.toolbar-btn');
  buttons.forEach((btn, idx) => {
    const title = btn.getAttribute('title') || '';
    if (title === 'Bold' && isBold) btn.classList.add('active');
    if (title === 'Italic' && isItalic) btn.classList.add('active');
    if (title === 'Underline' && isUnderline) btn.classList.add('active');
    if (title === 'Strikethrough' && isStrike) btn.classList.add('active');
    if (title === 'Subscript' && isSub) btn.classList.add('active');
    if (title === 'Superscript' && isSup) btn.classList.add('active');
    if (title === 'Bullet List' && isBullet) btn.classList.add('active');
    if (title === 'Numbered List' && isNumber) btn.classList.add('active');
  });

  const alignLeft = document.queryCommandState('justifyLeft');
  const alignCenter = document.queryCommandState('justifyCenter');
  const alignRight = document.queryCommandState('justifyRight');
  const alignJustify = document.queryCommandState('justifyFull');

  buttons.forEach(btn => {
    const title = btn.getAttribute('title') || '';
    if (title === 'Align Left' && alignLeft) btn.classList.add('active');
    if (title === 'Center' && alignCenter) btn.classList.add('active');
    if (title === 'Align Right' && alignRight) btn.classList.add('active');
    if (title === 'Justify' && alignJustify) btn.classList.add('active');
  });
}

// Update toolbar state when focusing on a desc
document.addEventListener('focusin', function(e) {
  if (e.target && e.target.classList.contains('fake-desc')) {
    lastFocusedDesc = e.target;
    setTimeout(updateToolbarState, 10);
  }
});

// Update toolbar state on selection change
document.addEventListener('selectionchange', function() {
  if (isEditing) {
    setTimeout(updateToolbarState, 10);
  }
});

function smartAlign(align) {
  let img = selectedImage;

  if (!img) {
    img = document.querySelector('.desc-img.img-selected');
  }

  if (!img) {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
      const node = sel.anchorNode;
      if (node) {
        if (node.nodeType === 1 && node.tagName === 'IMG') img = node;
        else if (node.parentElement && node.parentElement.tagName === 'IMG') img = node.parentElement;
      }
      if (!img && sel.focusNode) {
        const fNode = sel.focusNode;
        if (fNode.nodeType === 1 && fNode.tagName === 'IMG') img = fNode;
        else if (fNode.parentElement && fNode.parentElement.tagName === 'IMG') img = fNode.parentElement;
      }
    }
  }

  if (img && img.closest('.fake-desc')) {
    console.log("Aligning image:", align, img.src.substring(0, 50));
    img.style.display = 'block';
    if (align === 'left') {
      img.style.float = 'left';
      img.style.marginRight = '12px';
      img.style.marginLeft = '0';
      img.style.clear = 'none';
    } else if (align === 'right') {
      img.style.float = 'right';
      img.style.marginLeft = '12px';
      img.style.marginRight = '0';
      img.style.clear = 'none';
    } else {
      img.style.float = 'none';
      img.style.marginLeft = 'auto';
      img.style.marginRight = 'auto';
    }
    img.style.opacity = '0.5';
    setTimeout(() => img.style.opacity = '1', 200);
    return;
  }

  console.log("Aligning text:", align);
  const target = lastFocusedDesc || document.querySelector('.fake-desc');
  if (!target) return;
  target.focus();
  const cmd = align === 'left' ? 'justifyLeft' : align === 'center' ? 'justifyCenter' : align === 'right' ? 'justifyRight' : 'justifyFull';
  fmt(cmd);
  updateToolbarState();
}

function toggleToolbar() {
  const toolbar = document.getElementById('formattingToolbar');
  const textItem = document.getElementById('textNavItem');
  const scroll = document.getElementById('mainContent');
  const isVisible = toolbar.style.display === 'flex';

  if (isVisible) {
    toolbar.style.display = 'none';
    toolbar.classList.remove('toolbar-visible');
    textItem.classList.remove('bottom-item-active');
    if (scroll) scroll.classList.remove('toolbar-open');
  } else {
    toolbar.style.display = 'flex';
    toolbar.classList.remove('toolbar-visible');
    void toolbar.offsetWidth;
    toolbar.classList.add('toolbar-visible');
    textItem.classList.add('bottom-item-active');
    if (scroll) scroll.classList.add('toolbar-open');
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

  input.addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) { input.remove(); return; }

    try {
      const compressed = await compressImage(file, 800, 0.8);
      const target = lastFocusedDesc || document.querySelector('.fake-desc');
      if (!target) { input.remove(); return; }

      target.focus();
      const img = makeDescImage(compressed);

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

      selectImage(img);
      input.remove();
    } catch (err) {
      console.error("Image compression failed:", err);
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
        selectImage(img);
        input.remove();
      };
      reader.readAsDataURL(file);
    }
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
  const desc = img.closest('.fake-desc');
  if (desc) {
    desc.focus();
    lastFocusedDesc = desc;
  }

  selectedImage = img;
  console.log("Image selected for alignment:", img.src.substring(0, 50));

  document.querySelectorAll('.desc-img').forEach(i => i.classList.remove('img-selected'));
  img.classList.add('img-selected');
}

function setupImageClickHandlers() {
  const container = document.getElementById('cardContainer');
  if (!container) return;

  container.addEventListener('click', function(e) {
    const img = e.target.closest('img');
    const desc = e.target.closest('.fake-desc');

    if (!img || !desc) {
      document.querySelectorAll('.desc-img').forEach(i => i.classList.remove('img-selected'));
      selectedImage = null;
      return;
    }

    if (!img.classList.contains('desc-img')) {
      img.classList.add('desc-img');
      img.setAttribute('contenteditable', 'false');
      img.setAttribute('draggable', 'false');
      img.setAttribute('unselectable', 'on');
    }

    e.preventDefault();
    e.stopPropagation();
    selectImage(img);
  });
}

function removeSubjectImage() {
  subjectImageData = null;
  document.getElementById("subjectImagePreview").src = "file.png";
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
}
</script>
<script src="script.js"></script>
</body>
</html>